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
    private array $trace = [];
    private ?int $observedAt = null;
    private bool $admissionPaused = false;
    private const MAX_CONTROL_DECISIONS = 32;
    /** @var array<string,string> */
    private array $controlDecisions = [];
    // Optional while this queue is used as a standalone laboratory fixture.
    // A queue consumed by a supervised pilot must bind exactly once to its
    // coordinator; a provider callback can otherwise bypass the pilot guard
    // by directly calling enqueue/claim/finish/cancel on the same object.
    private ?SupervisedCoordinator $admissionCoordinator = null;

    public function __construct(private readonly TenantContext $boundContext) {}

    /**
     * Bind this queue to the same coordinator as the pilot, once. A second
     * different coordinator can never replace the first one.
     * This is per-object/process protection, never distributed IAM.
     */
    public function bindAdmissionCoordinator(SupervisedCoordinator $coordinator): void
    {
        if ($this->admissionCoordinator !== null) {
            // A swallowed rebinding attempt during a provider callback
            // still taints the already-active coordinator.
            $this->admissionCoordinator->assertProviderCallbackIdle();
            if ($this->admissionCoordinator !== $coordinator) {
                MockError::fail('QUEUE_COORDINATOR_CONFLICT');
            }
            return;
        }
        $coordinator->assertProviderCallbackIdle();
        $this->admissionCoordinator = $coordinator;
    }

    private function assertAdmissionIdle(): void
    {
        $this->admissionCoordinator?->assertProviderCallbackIdle();
    }

    /** Shared gate for read-only review notebooks associated with this queue. */
    public function assertReviewAdmissionReady(): void
    {
        $this->assertAdmissionIdle();
    }

    /**
     * Local emergency brake for new synthetic queue admissions.
     * No operational identity or CRM permission is provided by this API.
     */
    public function pauseAdmission(TenantContext $context, string $decisionRef): array
    {
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        self::reference($decisionRef, 80);
        if (isset($this->controlDecisions[$decisionRef])) {
            if ($this->controlDecisions[$decisionRef] !== 'PAUSE'
                || !$this->admissionPaused) {
                MockError::fail('QUEUE_CONTROL_CONFLICT');
            }
            return $this->admissionView();
        }
        if ($this->admissionPaused) MockError::fail('QUEUE_CONTROL_STATE_DENIED');
        if (count($this->controlDecisions) >= self::MAX_CONTROL_DECISIONS) {
            MockError::fail('QUEUE_CONTROL_CAPACITY_DENIED');
        }
        // A saturated audit must not leave admissions open in an emergency.
        // Enter fail-closed pause even if it cannot append an event, and
        // explicitly report that the trace capacity was exhausted.
        if (count($this->trace) >= self::MAX_TRACE) {
            $this->admissionPaused = true;
            MockError::fail('QUEUE_AUDIT_CAPACITY_DENIED');
        }
        $this->admissionPaused = true;
        $this->controlDecisions[$decisionRef] = 'PAUSE';
        $this->record('ADMISSION_PAUSED', 'SYNTHETIC_QUEUE_CONTROL');
        return $this->admissionView();
    }

    /** Resume requires a new, bounded synthetic decision and audit slot. */
    public function resumeAdmission(TenantContext $context, string $decisionRef): array
    {
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        self::reference($decisionRef, 80);
        if (isset($this->controlDecisions[$decisionRef])) {
            if ($this->controlDecisions[$decisionRef] !== 'RESUME'
                || $this->admissionPaused) {
                MockError::fail('QUEUE_CONTROL_CONFLICT');
            }
            return $this->admissionView();
        }
        if (!$this->admissionPaused) MockError::fail('QUEUE_CONTROL_STATE_DENIED');
        // Reserve one final control decision for an emergency PAUSE.
        // Never return OPEN if no future bounded PAUSE could be recorded.
        if (count($this->controlDecisions) >= self::MAX_CONTROL_DECISIONS - 1) {
            MockError::fail('QUEUE_CONTROL_CAPACITY_DENIED');
        }
        // Never reopen a queue that cannot record the resume event.
        $this->assertTraceRoom(1);
        $this->admissionPaused = false;
        $this->controlDecisions[$decisionRef] = 'RESUME';
        $this->record('ADMISSION_RESUMED', 'SYNTHETIC_QUEUE_CONTROL');
        return $this->admissionView();
    }

    /** Redacted aggregate, never an authorization or a worker command. */
    public function admissionState(TenantContext $context): array
    {
        $this->boundContext->assertSame($context);
        return $this->admissionView();
    }

    /**
     * Bounded, redacted capacity telemetry. These numbers are NOT a worker
     * readiness check, authorization, or permission to resume an admission.
     * Terminal/uncertain jobs still consume one of 64 in-memory job slots.
     */
    public function capacityOverview(TenantContext $context): array
    {
        $this->boundContext->assertSame($context);
        $jobs = count($this->jobs);
        $trace = count($this->trace);
        $decisions = count($this->controlDecisions);
        $jobsLeft = self::MAX_JOBS - $jobs;
        $traceLeft = self::MAX_TRACE - $trace;
        $decisionsLeft = self::MAX_CONTROL_DECISIONS - $decisions;
        $alert = 'NORMAL';
        if ($jobsLeft === 0 || $traceLeft === 0 || $decisionsLeft === 0) {
            $alert = 'EXHAUSTED';
        } elseif ($jobsLeft <= 4 || $traceLeft <= 16 || $decisionsLeft <= 2) {
            $alert = 'NEAR_LIMIT';
        }
        return [
            'contract' => 'SYNTHETIC_QUEUE_CAPACITY_V1',
            'alert' => $alert,
            'jobs' => ['used' => $jobs, 'limit' => self::MAX_JOBS,
                'remaining' => $jobsLeft],
            'audit' => ['used' => $trace, 'limit' => self::MAX_TRACE,
                'remaining' => $traceLeft],
            'decisions' => ['used' => $decisions,
                'limit' => self::MAX_CONTROL_DECISIONS,
                'remaining' => $decisionsLeft],
            // Budget observation only. Real IAM or an operational control
            // decision is never granted by a Boolean in this report.
            'resume_budget_available' => $this->admissionPaused
                && $decisions < self::MAX_CONTROL_DECISIONS - 1
                && $trace < self::MAX_TRACE,
            'admission' => ['state' => $this->admissionPaused ? 'PAUSED' : 'OPEN'],
            'operational_decision' => 'NO_GO',
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }

    private function admissionView(): array
    {
        return [
            'contract' => 'SYNTHETIC_QUEUE_CONTROL_V1',
            'state' => $this->admissionPaused ? 'PAUSED' : 'OPEN',
            'operational_decision' => 'NO_GO',
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }

    /** Enqueue one synthetic OP-01 read-only Run reference, idempotently. */
    public function enqueue(TenantContext $context, string $runRef,
        string $requestRef, int $now): array
    {
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        if ($this->admissionPaused) MockError::fail('QUEUE_ADMISSION_PAUSED');
        self::reference($runRef, 80);
        self::reference($requestRef, 90);
        $this->assertClock($now);
        $old = $this->jobs[$runRef] ?? null;
        if ($old !== null) {
            if ($old['request'] !== $requestRef) MockError::fail('QUEUE_IDEMPOTENCY_CONFLICT');
            return self::view($runRef, $old);
        }
        // A single request ref must never designate two different Runs.
        // Terminal and uncertain jobs remain indexed until the process ends:
        // reuse after cancellation/timeout would destroy correlation and
        // could silently authorize a second synthetic read with fresh proof.
        // At most 64 entries are scanned; no unbounded index or eviction.
        foreach ($this->jobs as $known) {
            if ($known['request'] === $requestRef) {
                MockError::fail('QUEUE_REQUEST_CONFLICT');
            }
        }
        if (count($this->jobs) >= self::MAX_JOBS) MockError::fail('QUEUE_CAPACITY_DENIED');
        $this->assertTraceRoom(1);
        // Commit observed time only after the operation can change state.
        // Rejected/conflicting/idempotent calls must not poison the queue.
        $this->commitClock($now);
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
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        if ($leaseTicks < 1 || $leaseTicks > self::MAX_LEASE_TICKS
            || $now > PHP_INT_MAX - $leaseTicks) {
            MockError::fail('QUEUE_LEASE_DENIED');
        }
        $this->assertClock($now);
        // Compute the whole audit demand BEFORE modifying any job. Both
        // expiry and a new reservation must fit or neither takes effect.
        $expiring = [];
        $pending = null;
        foreach ($this->jobs as $runRef => $job) {
            if ($job['state'] === 'RESERVADA' && $now >= $job['until']) {
                $expiring[] = $runRef;
            } elseif (!$this->admissionPaused && $pending === null
                && $job['state'] === 'PENDENTE') {
                $pending = $runRef;
            }
        }
        $needed = count($expiring) + ($pending === null ? 0 : 1);
        if ($needed > 0) {
            $this->assertTraceRoom($needed);
            $this->commitClock($now);
        }
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
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        // Claim references are machine-generated SHA-256 hex, unlike the
        // uppercase domain IDs received on the enqueue boundary.
        if (strlen($claimRef) !== 74
            || preg_match('/^SYNTHETIC_[a-f0-9]{64}$/D', $claimRef) !== 1) {
            MockError::fail('QUEUE_CLAIM_DENIED');
        }
        $this->assertClock($now);
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
        // Expiration is a real state transition (INDETERMINADO), even when
        // finish throws to its caller; advance clock in both outcome paths.
        $this->commitClock($now);
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
        $this->assertAdmissionIdle();
        $this->boundContext->assertSame($context);
        self::reference($runRef, 80);
        $this->assertClock($now);
        $job = $this->jobs[$runRef] ?? null;
        if ($job === null) MockError::fail('QUEUE_NOT_FOUND');
        if ($job['state'] === 'CANCELADA') return self::view($runRef, $job);
        if ($job['state'] !== 'PENDENTE') MockError::fail('QUEUE_STATE_DENIED');
        $this->assertTraceRoom(1);
        $this->commitClock($now);
        $this->jobs[$runRef]['state'] = 'CANCELADA';
        $this->record('CANCELLED', $runRef);
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

    /**
     * An expired local claim has unknown outcome. Reporting an empty queue
     * while such a job remains would misleadingly imply no work needs review.
     * This is only a redacted bit; never release run/claim/nonce material.
     */
    public function needsReview(TenantContext $context): bool
    {
        $this->boundContext->assertSame($context);
        foreach ($this->jobs as $job) {
            if ($job['state'] === 'INDETERMINADO') return true;
        }
        return false;
    }

    /** Another local claim is still active; this is not an empty queue. */
    public function hasActiveReservation(TenantContext $context): bool
    {
        $this->boundContext->assertSame($context);
        foreach ($this->jobs as $job) {
            if ($job['state'] === 'RESERVADA') return true;
        }
        return false;
    }

    public function events(): array { return $this->trace; }

    public function counts(): array
    {
        $states = ['PENDENTE' => 0, 'RESERVADA' => 0, 'CONCLUIDA' => 0,
            'FALHOU' => 0, 'CANCELADA' => 0, 'INDETERMINADO' => 0];
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

    /** Validate supplied synthetic time without modifying queue state. */
    private function assertClock(int $now): void
    {
        if ($now < 0 || ($this->observedAt !== null && $now < $this->observedAt)) {
            MockError::fail('QUEUE_TIME_DENIED');
        }
    }

    /** Only a committed queue mutation advances the local monotonic clock. */
    private function commitClock(int $now): void
    {
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
