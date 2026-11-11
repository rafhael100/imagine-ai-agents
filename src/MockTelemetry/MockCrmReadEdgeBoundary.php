<?php
declare(strict_types=1);

namespace ImagineAI\MockTelemetry;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadPublicFacade, MockCrmReadRequest, MockCrmPublicResponseContract};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/**
 * Synthetic pre-parser edge wrapper for F1-17.
 * This object consumes PHP arrays, NEVER an HTTP request or a real CRM response.
 */
final class MockCrmReadEdgeBoundary
{
    public function __construct(
        private readonly MockCrmReadPublicFacade $facade,
        private readonly MockCrmReadEdgeCounters $counters,
    ) {}

    /**
     * Recheck current synthetic permission without consuming a second nonce
     * or a new edge/audit slot; never re-read or reveal policy detail.
     * Only valid for the proof that the caller already used in a read.
     * @param array<string,mixed> $wire
     */
    public function recheck(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): bool {
        return $this->facade->recheck(
            $wire, $envelope, $owner, $trustedContext, $now);
    }

    /** @param array<string,mixed> $wire Fake local values only. */
    public function handle(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        try {
            $ticket = $this->counters->begin();
        } catch (\Throwable) {
            return MockCrmPublicResponseContract::denial();
        }

        try {
            $request = new MockCrmReadRequest($wire);
        } catch (\Throwable) {
            // Parsing failed BEFORE the F1-13 audit decorator; count category only.
            try { $this->counters->finish($ticket, 'SCHEMA_DENIED'); } catch (\Throwable) {}
            return MockCrmPublicResponseContract::denial();
        }

        try {
            $result = $this->facade->handle($wire, $envelope, $owner, $trustedContext, $now);
            if (!MockCrmPublicResponseContract::isSafe($result, $request, $trustedContext)) {
                throw new \LogicException('UNSAFE_SYNTHETIC_EDGE_RESULT');
            }
            $outcome = $result['state'] === 'READ_SYNTHETIC_ONLY'
                ? 'READ_SYNTHETIC_ONLY' : 'DENIED_OR_NOT_FOUND';
            $this->counters->finish($ticket, $outcome);
            return $result;
        } catch (\Throwable) {
            // Never return potentially unsafe data if telemetry cannot be completed.
            try { $this->counters->finish($ticket, 'INTERNAL_DENIED'); } catch (\Throwable) {}
            return MockCrmPublicResponseContract::denial();
        }
    }

}
