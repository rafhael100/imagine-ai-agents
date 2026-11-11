<?php
declare(strict_types=1);

namespace ImagineAI\MockPilot;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmReadRequestBuilder;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/**
 * Bounded, explicit offline batch over the existing one-job read-only pilot.
 * No worker, scheduler, network, credentials, CRM, effects or automatic retry.
 */
final class MockQueuedBatchRunner
{
    private const MAX_BATCH = 8;

    public function __construct(
        private readonly TenantContext $boundContext,
        private readonly MockQueuedReadOnlyPilot $pilot,
    ) {}

    /**
     * Each explicit input pair supplies the next synthetic service/owner proof.
     * Null values represent a missing proof and must fail closed in the pilot;
     * proofs do NOT authenticate a caller or authorize a real CRM operation.
     *
     * @param list<array{envelope:?MockTrustEnvelope,owner:?MockOwnerScopeEvidence}> $pairs
     * @param list<string> $fields
     * @return array<string,mixed>
     */
    public function drain(
        TenantContext $context,
        array $pairs,
        array $fields,
        int $now,
        int $leaseTicks,
    ): array {
        try {
            $this->boundContext->assertSame($context);
            if (!array_is_list($pairs) || count($pairs) < 1
                || count($pairs) > self::MAX_BATCH
                || $now < 0 || $leaseTicks < 1 || $leaseTicks > 300
                || $now > PHP_INT_MAX - $leaseTicks) {
                return self::denial();
            }
            // Validate every entry AND the field schema before the first
            // claim. A bad eighth pair cannot consume the first seven jobs.
            foreach ($pairs as $pair) {
                if (!is_array($pair)
                    || array_keys($pair) !== ['envelope', 'owner']
                    || ($pair['envelope'] !== null
                        && !($pair['envelope'] instanceof MockTrustEnvelope))
                    || ($pair['owner'] !== null
                        && !($pair['owner'] instanceof MockOwnerScopeEvidence))) {
                    return self::denial();
                }
            }
            MockCrmReadRequestBuilder::fromContext(
                $context, 'SYNTHETIC_BATCH_PREFLIGHT', $fields);
        } catch (\Throwable) {
            return self::denial();
        }

        $processed = 0;
        $succeeded = 0;
        $denied = 0;
        foreach ($pairs as $pair) {
            // Calls are explicit and limited by MAX_BATCH; a denied or
            // indeterminate job cannot be automatically retried here.
            $out = $this->pilot->runNext($context, $pair['envelope'],
                $pair['owner'], $fields, $now, $leaseTicks);
            if (($out['state'] ?? null) === 'EMPTY') break;
            ++$processed;
            if (($out['state'] ?? null) === 'SUGGESTION_SYNTHETIC_ONLY') {
                ++$succeeded;
            } else {
                ++$denied;
            }
        }
        return [
            'contract' => 'SYNTHETIC_QUEUED_BATCH_V1',
            'state' => $processed === 0 ? 'EMPTY' : 'BATCH_SYNTHETIC_ONLY',
            'processed' => $processed,
            'succeeded' => $succeeded,
            'denied' => $denied,
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }

    private static function denial(): array
    {
        return [
            'contract' => 'SYNTHETIC_QUEUED_BATCH_V1',
            'state' => 'DENIED_OR_NOT_FOUND',
            'processed' => 0,
            'succeeded' => 0,
            'denied' => 0,
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }
}
