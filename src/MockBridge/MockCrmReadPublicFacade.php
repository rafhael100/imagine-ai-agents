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
     *   context:string,request_hash:string,revision:int,observed_at:int}
     */
    private ?array $lastReadReceipt = null;
    // A second call to handle() on the same facade while a reader is active
    // is not a separate authorized operation, even if a nested adapter
    // swallows its generic denial and the outer reader returns normally.
    private bool $handlingRead = false;
    private bool $nestedReadObserved = false;

    public function __construct(
        MockTrustGateway $gateway,
        CrmReader $reader,
        MockCrmOwnerPolicy $ownerPolicy,
        MockCrmReadAuditTrail $audit,
    ) {
        $this->readBoundary = new MockCrmReadBoundary(
            $gateway, $reader, $ownerPolicy,
            function (): void {
                if ($this->nestedReadObserved) {
                    throw new \LogicException('REENTRANT_SYNTHETIC_READ_DENIED');
                }
            }
        );
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
                || $receipt['context'] !== $trustedContext->key()) {
                return false;
            }
            // Reject an unbounded/malformed body with the closed parser
            // BEFORE hashing or otherwise traversing its raw contents.
            $request = new MockCrmReadRequest($wire);
            if ($receipt['request_hash'] !== self::wireFingerprint($wire)) {
                return false;
            }
            // The supplied clock is NOT trusted real time. Within this one
            // mock receipt we can still fail closed on backward timestamps.
            // A forward observation persists even if the proof has expired:
            // rolling time back must never revive an earlier read.
            if ($now < $receipt['observed_at']) {
                $this->lastReadReceipt = null;
                return false;
            }
            $this->lastReadReceipt['observed_at'] = $now;
            $this->readBoundary->recheck(
                $request, $envelope, $owner, $trustedContext, $now);
            // A temporarily revoked/resumed policy cannot reactivate a prior
            // read, even if the current owner grant looks permitted again.
            $this->readBoundary->assertPolicyRevision($receipt['revision']);
            return true;
        } catch (\Throwable) {
            // Fail closed after any exception in the authenticated recheck,
            // including proof expiry, owner outage, invalid time or revocation.
            // Never let a formerly denied receipt become live again.
            $this->lastReadReceipt = null;
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
        if ($this->handlingRead) {
            // No recursive parser/reader/audit/nonce consumption. Taint the
            // enclosing read so its pre-success audited guard also denies.
            $this->nestedReadObserved = true;
            $this->lastReadReceipt = null;
            return self::response('DENIED_OR_NOT_FOUND', null);
        }
        $this->handlingRead = true;
        $this->nestedReadObserved = false;
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
                'observed_at' => $now,
            ];
            return self::response('READ_SYNTHETIC_ONLY', $projection);
        } catch (\Throwable) {
            // A real API would translate errors here and log operational detail
            // separately, using a reviewed privacy-conscious transport policy.
            $this->lastReadReceipt = null;
            return self::response('DENIED_OR_NOT_FOUND', null);
        } finally {
            $this->handlingRead = false;
            $this->nestedReadObserved = false;
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
