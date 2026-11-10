<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\{MockCrmReadAuditTrail, MockCrmReadAuditedBoundary};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\Contracts\CrmReader;

/**
 * Offline public-facing VALUE ENVELOPE only. Not HTTP, API, IAM or a CRM endpoint.
 *
 * A caller cannot omit the fictional CRM-owner policy or internal audit trail:
 * all failed evaluations expose the same uninformative response shape.
 */
final class MockCrmReadPublicFacade
{
    private readonly MockCrmReadAuditedBoundary $boundary;
    private readonly MockCrmReadBoundary $readBoundary;

    public function __construct(
        MockTrustGateway $gateway,
        CrmReader $reader,
        MockCrmOwnerPolicy $ownerPolicy,
        MockCrmReadAuditTrail $audit,
    ) {
        $this->readBoundary = new MockCrmReadBoundary($gateway, $reader, $ownerPolicy);
        $this->boundary = new MockCrmReadAuditedBoundary($this->readBoundary, $audit);
    }

    /**
     * Same in-memory owner/trust authority used by the audited read. Returns
     * a single denial bit, not policy detail. No new read, audit or nonce.
     * Not an operational CRM permission-check endpoint.
     * @param array<string,mixed> $wire
     */
    public function recheck(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): bool {
        try {
            $request = new MockCrmReadRequest($wire);
            $this->readBoundary->recheck(
                $request, $envelope, $owner, $trustedContext, $now);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }


    public function handle(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        try {
            $request = new MockCrmReadRequest($wire);
            $result = $this->boundary->evaluate($request, $envelope, $owner, $trustedContext, $now);
            $projection = $result['projection'] ?? null;

            if (($result['mode'] ?? null) !== 'SYNTHETIC_ONLY'
                || ($result['contract'] ?? null) !== 'SYNTHETIC_CRM_AIA_READ_V1'
                || ($result['state'] ?? null) !== 'READ_SYNTHETIC_ONLY'
                || ($result['real_authorization'] ?? null) !== false
                || ($result['external_calls'] ?? null) !== 0
                || !is_array($projection)
                || !MockReadProjectionInvariant::isCanonical(
                    $projection, $request->fields, $trustedContext)) {
                throw new \LogicException('UNSAFE_SYNTHETIC_PROJECTION');
            }

            // Shared with the pre-audit boundary and edge response checker.
            // Never let a future adapter bypass resource binding or 90-byte
            // limits by changing only a downstream response validator.
            return self::response('READ_SYNTHETIC_ONLY', $projection);
        } catch (\Throwable) {
            // A real API would translate errors here and log operational detail
            // separately, using a reviewed privacy-conscious transport policy.
            return self::response('DENIED_OR_NOT_FOUND', null);
        }
    }

    private static function response(string $state, ?array $projection): array
    {
        return [
            'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            'state' => $state,
            'projection' => $projection,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }
}
