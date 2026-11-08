<?php
declare(strict_types=1);

namespace ImagineAI\MockUsage;

use ImagineAI\Domain\{Attempt, MockError, TenantContext, Usage};

/** Single-process, volatile, fail-closed technical-usage lab (not transactional quota). */
final class MockUsageLedger
{
    /** @var array<string, array{fingerprint:string,usage:Usage}> */
    private array $events = [];
    /** @var array<string, array<string,mixed>> */
    private array $samples = [];
    private array $trace = [];
    private array $alerts = [];
    private array $alertCodes = [];

    public function __construct(private readonly ?MockUsagePolicy $policy) {}

    private function policy(TenantContext $context, string $operation, int $now): MockUsagePolicy
    {
        if ($this->policy === null) MockError::fail('USAGE_POLICY_REQUIRED');
        $this->policy->assertActive($context, $operation, $now);
        return $this->policy;
    }

    /** A budget observation is NEVER a grant, approval or credit reservation. */
    public function budget(TenantContext $context, string $operation, int $units, int $now): array
    {
        $policy = $this->policy($context, $operation, $now);
        self::units($units);
        if ($this->hasUnknown()) return ['state' => 'INDETERMINADO', 'reason' => 'MEASUREMENT_UNCERTAIN'];
        if ($units > $policy->limit || $this->used() > $policy->limit - $units) {
            return ['state' => 'NEGADO', 'reason' => 'SYNTHETIC_QUOTA_EXCEEDED'];
        }
        return ['state' => 'SYNTHETIC_WITHIN_QUOTA', 'reason' => 'TECHNICAL_ONLY_NOT_AUTHORIZATION'];
    }

    /** Record one immutable material event. Late measurements are still accounted for. */
    public function record(string $eventId, Attempt $attempt, string $state, int $units, string $material, int $now): Usage
    {
        $this->policy($attempt->context, $attempt->intent->operation, $now);
        self::units($units);
        if (!str_starts_with($eventId, 'SYNTHETIC_') || strlen($eventId) > 128
            || !str_starts_with($material, 'SYNTHETIC_') || strlen($material) > 256
            || !in_array($state, ['ESTIMADO', 'OBSERVADO', 'AJUSTADO'], true)) {
            MockError::fail('USAGE_EVENT_INVALID');
        }
        $fingerprint = hash('sha256', json_encode([
            $attempt->id, $attempt->intent->signature(), $attempt->context->key(),
            $state, $units, $material,
        ], JSON_THROW_ON_ERROR));
        if (isset($this->events[$eventId])) {
            if ($this->events[$eventId]['fingerprint'] !== $fingerprint) MockError::fail('USAGE_EVENT_CONFLICT');
            return $this->events[$eventId]['usage'];
        }
        $sample = $this->sample($attempt);
        if ($sample['proof'] !== null) MockError::fail('USAGE_FINALIZED');
        if (($state === 'ESTIMADO' && ($sample['observed'] !== null || $sample['estimated'] !== null))
            || ($state === 'OBSERVADO' && $sample['observed'] !== null)
            || ($state === 'AJUSTADO' && ($sample['observed'] === null || $sample['adjusted'] !== null))) {
            MockError::fail('USAGE_STATE_CONFLICT');
        }
        $usage = new Usage($eventId, $attempt, $units, $state);
        $property = match ($state) {
            'ESTIMADO' => 'estimated', 'OBSERVADO' => 'observed', 'AJUSTADO' => 'adjusted',
        };
        $sample[$property] = $units;
        $this->samples[$attempt->id] = $sample;
        $this->events[$eventId] = ['fingerprint' => $fingerprint, 'usage' => $usage];
        $this->log('RECORDED_' . $state, $eventId, $attempt);
        $this->updateAlerts();
        return $usage;
    }

    /** A transport timeout has no implication that provider usage is zero. */
    public function markUnknown(Attempt $attempt, int $now): void
    {
        $this->policy($attempt->context, $attempt->intent->operation, $now);
        if ($attempt->state !== 'TIMEOUT' && $attempt->state !== 'ERRO') MockError::fail('USAGE_STATE_CONFLICT');
        $sample = $this->sample($attempt);
        if (!$sample['unknown']) {
            $sample['unknown'] = true;
            $this->samples[$attempt->id] = $sample;
            $this->log('UNCERTAIN', $attempt->id, $attempt);
        }
    }

    /** Confirmation exists ONLY with compatible synthetic measured proof. */
    public function reconcile(Attempt $attempt, ?MockUsageProof $proof, int $now): array
    {
        $this->policy($attempt->context, $attempt->intent->operation, $now);
        $sample = $this->sample($attempt);
        if ($sample['conflict']) return ['state' => 'INDETERMINADO', 'reason' => 'PROOF_CONFLICT'];
        if ($proof === null) return ['state' => 'INDETERMINADO', 'reason' => 'PROOF_UNAVAILABLE'];
        $matching = $proof->attempt->id === $attempt->id
            && $proof->attempt->intent->signature() === $attempt->intent->signature()
            && $proof->attempt->context->key() === $attempt->context->key()
            && $sample['observed'] !== null
            && $proof->units === ($sample['adjusted'] ?? $sample['observed']);
        if (!$matching || ($sample['proof'] !== null && $sample['proof'] !== $proof->id)) {
            $sample['unknown'] = true;
            $sample['conflict'] = true; // A contradictory proof cannot be silently cleared by a later call.
            $this->samples[$attempt->id] = $sample;
            $this->log('PROOF_CONFLICT', $proof->id, $attempt);
            return ['state' => 'INDETERMINADO', 'reason' => 'PROOF_CONFLICT'];
        }
        if ($sample['unknown'] && $sample['proof'] !== null && $sample['proof'] !== $proof->id) {
            return ['state' => 'INDETERMINADO', 'reason' => 'PROOF_CONFLICT'];
        }
        if ($sample['proof'] === null) $this->log('RECONCILED_FAKE', $proof->id, $attempt);
        $sample['proof'] = $proof->id;
        $sample['unknown'] = false;
        $this->samples[$attempt->id] = $sample;
        return ['state' => 'CONCILIADO', 'reason' => 'SYNTHETIC_PROOF_ONLY'];
    }

    /** Count each attempt at its highest recorded exposure, never sum estimate+observed. */
    public function used(): int
    {
        $sum = 0;
        foreach ($this->samples as $sample) {
            $value = max($sample['estimated'] ?? 0, $sample['observed'] ?? 0, $sample['adjusted'] ?? 0);
            if ($sum > PHP_INT_MAX - $value) MockError::fail('USAGE_OVERFLOW');
            $sum += $value;
        }
        return $sum;
    }

    /** No message, email, webhook or price is ever emitted. */
    public function alerts(): array { return $this->alerts; }
    public function events(): array { return $this->trace; }

    private static function units(int $units): void
    {
        if ($units < 0 || $units > 1_000_000) MockError::fail('USAGE_UNITS_INVALID');
    }

    private function sample(Attempt $attempt): array
    {
        $key = $attempt->id;
        if (isset($this->samples[$key])) {
            $known = $this->samples[$key];
            if ($known['intent'] !== $attempt->intent->signature()
                || $known['context'] !== $attempt->context->key()) MockError::fail('USAGE_ATTEMPT_CONFLICT');
            return $known;
        }
        return [
            'intent' => $attempt->intent->signature(), 'context' => $attempt->context->key(),
            'estimated' => null, 'observed' => null, 'adjusted' => null,
            'proof' => null, 'unknown' => false, 'conflict' => false,
        ];
    }

    private function hasUnknown(): bool
    {
        foreach ($this->samples as $sample) if ($sample['unknown']) return true;
        return false;
    }

    private function updateAlerts(): void
    {
        if ($this->policy === null) return;
        $used = $this->used();
        if ($used >= $this->policy->warnAt && $used < $this->policy->limit) $this->alert('NEAR_LIMIT');
        if ($used >= $this->policy->limit) $this->alert('EXHAUSTED');
    }

    private function alert(string $code): void
    {
        if (isset($this->alertCodes[$code])) return;
        $this->alertCodes[$code] = true;
        $this->alerts[] = [
            'code' => 'SYNTHETIC_' . $code,
            'policy' => self::ref($this->policy->id),
        ];
    }

    private function log(string $code, string $ref, Attempt $attempt): void
    {
        $this->trace[] = [
            'sequence' => count($this->trace) + 1,
            'code' => 'SYNTHETIC_' . $code,
            'event' => self::ref($ref),
            'attempt' => self::ref($attempt->id),
            'tenant' => self::ref($attempt->context->tenant),
        ];
    }

    private static function ref(string $text): string
    {
        return 'SYNTHETIC_' . hash('sha256', $text);
    }
}
