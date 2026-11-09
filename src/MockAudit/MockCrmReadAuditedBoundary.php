<?php
declare(strict_types=1);

namespace ImagineAI\MockAudit;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockBridge\{MockCrmReadBoundary, MockCrmReadRequest};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/** Decorator over F1-12, not a new CRM authorization or logging backend. */
final class MockCrmReadAuditedBoundary
{
    public function __construct(
        private readonly MockCrmReadBoundary $boundary,
        private readonly MockCrmReadAuditTrail $trail,
    ) {}

    public function evaluate(
        MockCrmReadRequest $request,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        // Exhausted audit budget must block BEFORE the simulated read occurs.
        $this->trail->assertRoom();
        try {
            $result = $this->boundary->evaluate($request, $envelope, $owner, $trustedContext, $now);
        } catch (\Throwable $error) {
            $this->trail->append($trustedContext, $request->ref, 'DENIED', self::reason($error));
            throw $error;
        }
        $this->trail->append($trustedContext, $request->ref, 'READ_SYNTHETIC_ONLY', 'NONE');
        return $result;
    }

    private static function reason(\Throwable $error): string
    {
        if (!$error instanceof MockError) return 'OTHER';
        return match ($error->getMessage()) {
            'BRIDGE_CONTEXT_DENIED', 'CONTEXT_DENIED' => 'CONTEXT',
            'TRUST_DENIED', 'BRIDGE_TRUST_DENIED', 'TRUST_STATE_UNAVAILABLE' => 'TRUST',
            'OWNER_DENIED' => 'OWNER',
            'TRUST_REPLAY_DENIED' => 'REPLAY',
            'RESOURCE_DENIED_OR_MISSING' => 'RESOURCE',
            'FIELD_DENIED', 'BRIDGE_PROJECTION_DENIED' => 'PROJECTION',
            default => 'OTHER',
        };
    }
}
