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
    /**
     * Only the most recent successful synthetic read may be rechecked.
     * Replacing the lease (rather than keeping a map) bounds memory to one
     * local slot and fails closed for overlapping mock runs.
     * @var ?array{envelope:MockTrustEnvelope,owner:MockOwnerScopeEvidence,
     *   context:string,request_hash:string,revision:int}
     */
    private ?array $lastReadReceipt = null;

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
            $receipt = $this->lastReadReceipt;
            if ($receipt === null || $envelope === null || $owner === null
                || $receipt['envelope'] !== $envelope
                || $receipt['owner'] !== $owner
                || $receipt['context'] !== $trustedContext->key()
                || $receipt['request_hash'] !== self::wireFingerprint($wire)) {
                return false;
            }
            $request = new MockCrmReadRequest($wire);
            $this->readBoundary->recheck(
                $request, $envelope, $owner, $trustedContext, $now);
            // A temporarily revoked/resumed policy cannot reactivate a prior
            // read, even if the current owner grant looks permitted again.
            $this->readBoundary->assertPolicyRevision($receipt['revision']);
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
        // Do not preserve a previous positive receipt across any new attempt,
        // including a malformed, denied, audited or replayed read.
        $this->lastReadReceipt = null;
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

            // Keep only an in-memory witness of the successful read; no
            // externally reusable token, request text or new authorization.
            if ($envelope === null || $owner === null) {
                throw new \LogicException('MISSING_SYNTHETIC_PROOF');
            }
            $this->lastReadReceipt = [
                'envelope' => $envelope,
                'owner' => $owner,
                'context' => $trustedContext->key(),
                'request_hash' => self::wireFingerprint($wire),
                'revision' => $this->readBoundary->policyRevision(),
            ];
            return self::response('READ_SYNTHETIC_ONLY', $projection);
        } catch (\Throwable) {
            // A real API would translate errors here and log operational detail
            // separately, using a reviewed privacy-conscious transport policy.
            return self::response('DENIED_OR_NOT_FOUND', null);
        }
    }

    /**
     * Opaque in-process matching only; never exposed as a proof of identity.
     * A malformed payload fails closed rather than being serialized into logs.
     * @param array<string,mixed> $wire
     */
    private static function wireFingerprint(array $wire): string
    {
        return hash('sha256', json_encode($wire, JSON_THROW_ON_ERROR));
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
