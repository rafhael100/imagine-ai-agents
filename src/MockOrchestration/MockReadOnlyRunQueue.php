<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Single-process, one-tenant, READ-ONLY scheduler experiment.
 * It never calls a CRM, provider, gateway, callback or worker.
 *
 * Expired claims are INDETERMINADO and are NEVER automatically requeued:
 * an unseen response is not proof that no read or suggestion happened.
 */
final class MockReadOnlyRunQueue
{
    private const MAX_JOBS = 64;
    private const MAX_TRACE = 256;
    private const MAX_LEASE_TICKS = 300;

    /** @var array<string,array{request:string,state:string,claim:?string,until:?int,attempts:int}> */
    private array $jobs = [];
    // One review reference can acknowledge one uncertain job only.
    // Synthetic IDs are not proof of real reviewers or IAM.
    private array $reviewRefs = [];
    private array $trace = [];
    private ?int $observedAt = null;

    public function __construct(private readonly TenantContext $boundContext) {}

    /** Enqueue one synthetic OP-01 read-only Run reference, idempotently. */
    public function enqueue(TenantContext $context, string $runRef,
        string $requestRef, int $now): array
    {
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        self::reference($requestRef, 90);
        $this->clock($now);
        $old = $this->jobs[$runRef] ?? null;
        if ($old !== null) {
            if ($old['request'] !== $requestRef) MockError::fail('QUEUE_IDEMPOTENCY_CONFLICT');
            return self::view($runRef, $old);
        }
        if (count($this->jobs) >= self::MAX_JOBS) MockError::fail('QUEUE_CAPACITY_DENIED');
        $this->assertTraceRoom(1);
        $this->jobs[$runRef] = [
            'request' => $requestRef, 'state' => 'PENDENTE',
            'claim' => null, 'until' => null, 'attempts' => 0,
        ];
        $this->record('ENQUEUED', $runRef);
        return self::view($runRef, $this->jobs[$runRef]);
    }

    /**
     * Claim oldest pending synthetic request for a read-only worker.
     * Return null when nothing can be claimed; never imply a real worker ran.
     */
    public function claim(TenantContext $context, int $now, int $leaseTicks): ?array
    {
        $this->boundContext->assertSame($context);
        if ($leaseTicks < 1 || $leaseTicks > self::MAX_LEASE_TICKS
            || $now > PHP_INT_MAX - $leaseTicks) {
            MockError::fail('QUEUE_LEASE_DENIED');
        }
        $this->clock($now);
        // Compute the whole audit demand BEFORE modifying any job. Both
        // expiry and a new reservation must fit or neither takes effect.
        $expiring = [];
        $pending = null;
        foreach ($this->jobs as $runRef => $job) {
            if ($job['state'] === 'RESERVADA' && $now >= $job['until']) {
                $expiring[] = $runRef;
            } elseif ($pending === null && $job['state'] === 'PENDENTE') {
                $pending = $runRef;
            }
        }
        $needed = count($expiring) + ($pending === null ? 0 : 1);
        if ($needed > 0) $this->assertTraceRoom($needed);
        foreach ($expiring as $runRef) {
            $this->jobs[$runRef]['state'] = 'INDETERMINADO';
            $this->record('LEASE_EXPIRED', $runRef);
        }
        if ($pending === null) return null;
        $job = $this->jobs[$pending];
        $attempt = $job['attempts'] + 1;
        // This is only a local comparison marker, NEVER an auth credential.
        $claim = 'SYNTHETIC_' . hash('sha256', $this->boundContext->key()
            . ':' . $pending . ':' . $job['request'] . ':' . $attempt);
        $this->jobs[$pending]['state'] = 'RESERVADA';
        $this->jobs[$pending]['claim'] = $claim;
        $this->jobs[$pending]['until'] = $now + $leaseTicks;
        $this->jobs[$pending]['attempts'] = $attempt;
        $this->record('CLAIMED', $pending);
        return [
            'run_ref' => $pending,
            'request_ref' => $job['request'],
            'claim_ref' => $claim,
            'lease_until' => $now + $leaseTicks,
            'state' => 'RESERVADA',
        ];
    }

    /** Complete a matching current local claim once, with no side effects. */
    public function finish(TenantContext $context, string $runRef,
        string $claimRef, string $result, int $now): array
    {
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        // Claim references are machine-generated SHA-256 hex, unlike the
        // uppercase domain IDs received on the enqueue boundary.
        if (strlen($claimRef) !== 74
            || preg_match('/^SYNTHETIC_[a-f0-9]{64}$/D', $claimRef) !== 1) {
            MockError::fail('QUEUE_CLAIM_DENIED');
        }
        $this->clock($now);
        if (!in_array($result, ['CONCLUIDA', 'FALHOU'], true)) {
            MockError::fail('QUEUE_STATE_DENIED');
        }
        $job = $this->jobs[$runRef] ?? null;
        if ($job === null || $job['claim'] === null
            || !hash_equals($job['claim'], $claimRef)) {
            MockError::fail('QUEUE_CLAIM_DENIED');
        }
        if ($job['state'] === $result) return self::view($runRef, $job);
        if ($job['state'] !== 'RESERVADA') MockError::fail('QUEUE_STATE_DENIED');
        $this->assertTraceRoom(1);
        if ($now >= $job['until']) {
            $this->jobs[$runRef]['state'] = 'INDETERMINADO';
            $this->record('LEASE_EXPIRED', $runRef);
            MockError::fail('QUEUE_CLAIM_EXPIRED');
        }
        $this->jobs[$runRef]['state'] = $result;
        $this->record($result, $runRef);
        return self::view($runRef, $this->jobs[$runRef]);
    }

    /** Cancel only a not-yet-claimed read. Never cancel an in-flight effect. */
    public function cancel(TenantContext $context, string $runRef, int $now): array
    {
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        $this->clock($now);
        $job = $this->jobs[$runRef] ?? null;
        if ($job === null) MockError::fail('QUEUE_NOT_FOUND');
        if ($job['state'] === 'CANCELADA') return self::view($runRef, $job);
        if ($job['state'] !== 'PENDENTE') MockError::fail('QUEUE_STATE_DENIED');
        $this->assertTraceRoom(1);
        $this->jobs[$runRef]['state'] = 'CANCELADA';
        $this->record('CANCELLED', $runRef);
        return self::view($runRef, $this->jobs[$runRef]);
    }

    /**
     * Explicit two-reviewer acknowledgment of local uncertainty; this is
     * NOT a business outcome, effect confirmation, retry or CRM approval.
     * An indeterminate lease can never be resurrected into PENDENTE.
     */
    public function acknowledgeIndeterminate(
        TenantContext $context,
        string $runRef,
        string $reviewRef,
        string $reviewerA,
        string $reviewerB,
        int $now,
    ): array {
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        self::reference($reviewRef, 90);
        self::reference($reviewerA, 90);
        self::reference($reviewerB, 90);
        if ($reviewerA === $reviewerB) MockError::fail('QUEUE_REVIEW_DENIED');
        $this->clock($now);
        $job = $this->jobs[$runRef] ?? null;
        if ($job === null) MockError::fail('QUEUE_NOT_FOUND');
        $digest = hash('sha256', $reviewerA . ':' . $reviewerB);
        $prior = $job['review'] ?? null;
        if ($prior !== null) {
            if ($job['state'] === 'REVISADA_SEM_PROVA'
                && $prior['ref'] === $reviewRef
                && hash_equals($prior['digest'], $digest)) {
                return self::view($runRef, $job);
            }
            MockError::fail('QUEUE_REVIEW_CONFLICT');
        }
        // A review is never a substitute for an observed CRM status. It only
        // closes local queue triage for a lease already marked indeterminate.
        if ($job['state'] !== 'INDETERMINADO') MockError::fail('QUEUE_REVIEW_DENIED');
        if (isset($this->reviewRefs[$reviewRef])) MockError::fail('QUEUE_REVIEW_CONFLICT');
        $this->assertTraceRoom(1);
        $this->jobs[$runRef]['review'] = ['ref' => $reviewRef, 'digest' => $digest];
        $this->jobs[$runRef]['state'] = 'REVISADA_SEM_PROVA';
        $this->reviewRefs[$reviewRef] = $runRef;
        $this->record('UNCERTAINTY_ACKNOWLEDGED', $runRef);
        return self::view($runRef, $this->jobs[$runRef]);
    }

    public function status(TenantContext $context, string $runRef): array
    {
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        $job = $this->jobs[$runRef] ?? null;
        return $job === null ? ['state' => 'DENIED_OR_NOT_FOUND']
            : self::view($runRef, $job);
    }

    public function events(): array { return $this->trace; }

    public function counts(): array
    {
        $states = ['PENDENTE' => 0, 'RESERVADA' => 0, 'CONCLUIDA' => 0,
            'FALHOU' => 0, 'CANCELADA' => 0, 'INDETERMINADO' => 0,
            'REVISADA_SEM_PROVA' => 0];
        foreach ($this->jobs as $job) ++$states[$job['state']];
        return $states;
    }

    private static function view(string $runRef, array $job): array
    {
        return ['run_ref' => $runRef, 'state' => $job['state'],
            'attempts' => $job['attempts']];
    }

    private static function reference(string $ref, int $max): void
    {
        if (strlen($ref) > $max
            || preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $ref) !== 1) {
            MockError::fail('QUEUE_REF_DENIED');
        }
    }

    private function clock(int $now): void
    {
        if ($now < 0 || ($this->observedAt !== null && $now < $this->observedAt)) {
            MockError::fail('QUEUE_TIME_DENIED');
        }
        $this->observedAt = $now;
    }

    private function assertTraceRoom(int $needed): void
    {
        if ($needed < 1 || $needed > self::MAX_TRACE
            || count($this->trace) > self::MAX_TRACE - $needed) {
            MockError::fail('QUEUE_AUDIT_CAPACITY_DENIED');
        }
    }

    private function record(string $code, string $runRef): void
    {
        $this->trace[] = [
            'sequence' => count($this->trace) + 1,
            'code' => 'SYNTHETIC_' . $code,
            'tenant' => 'SYNTHETIC_' . hash('sha256', $this->boundContext->tenant),
            'run' => 'SYNTHETIC_' . hash('sha256', $runRef),
        ];
    }
}
