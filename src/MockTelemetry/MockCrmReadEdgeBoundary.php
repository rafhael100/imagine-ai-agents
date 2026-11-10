<?php
declare(strict_types=1);

namespace ImagineAI\MockTelemetry;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadPublicFacade, MockCrmReadRequest, MockReadProjectionInvariant};
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
            return self::denial();
        }

        try {
            $request = new MockCrmReadRequest($wire);
        } catch (\Throwable) {
            // Parsing failed BEFORE the F1-13 audit decorator; count category only.
            try { $this->counters->finish($ticket, 'SCHEMA_DENIED'); } catch (\Throwable) {}
            return self::denial();
        }

        try {
            $result = $this->facade->handle($wire, $envelope, $owner, $trustedContext, $now);
            if (!self::isSafeResponse($result, $request, $trustedContext)) {
                throw new \LogicException('UNSAFE_SYNTHETIC_EDGE_RESULT');
            }
            $outcome = $result['state'] === 'READ_SYNTHETIC_ONLY'
                ? 'READ_SYNTHETIC_ONLY' : 'DENIED_OR_NOT_FOUND';
            $this->counters->finish($ticket, $outcome);
            return $result;
        } catch (\Throwable) {
            // Never return potentially unsafe data if telemetry cannot be completed.
            try { $this->counters->finish($ticket, 'INTERNAL_DENIED'); } catch (\Throwable) {}
            return self::denial();
        }
    }

    private static function isSafeResponse(array $result, MockCrmReadRequest $request,
        TenantContext $trustedContext): bool
    {
        if (array_keys($result) !== ['contract', 'state', 'projection',
                'real_authorization', 'external_calls']
            || ($result['contract'] ?? null) !== 'SYNTHETIC_CRM_AIA_PUBLIC_V1'
            || ($result['real_authorization'] ?? null) !== false
            || ($result['external_calls'] ?? null) !== 0) {
            return false;
        }
        if (($result['state'] ?? null) === 'DENIED_OR_NOT_FOUND') {
            return $result['projection'] === null;
        }
        if (($result['state'] ?? null) !== 'READ_SYNTHETIC_ONLY'
            || !is_array($result['projection'])
            || !MockReadProjectionInvariant::isCanonical(
                $result['projection'], $request->fields, $trustedContext)) {
            return false;
        }
        // Even if the public facade changes, the edge requires the same
        // resource-bound, ordered, bounded, synthetic-only projection.
        return true;
    }

    private static function denial(): array
    {
        return [
            'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            'state' => 'DENIED_OR_NOT_FOUND',
            'projection' => null,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }
}
