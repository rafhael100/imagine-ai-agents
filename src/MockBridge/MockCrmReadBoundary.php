<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\Contracts\CrmReader;

/** Offline mock; CRM resource-owner ACL and service authentication are NOT implemented. */
final class MockCrmReadBoundary
{
    public function __construct(
        private readonly MockTrustGateway $gateway,
        private readonly CrmReader $reader,
        private readonly MockCrmOwnerPolicy $ownerPolicy,
    ) {}

    public function evaluate(
        MockCrmReadRequest $request,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
    ): array {
        // Even a syntactically valid body cannot select another identity, tenant or resource.
        $request->assertMatches($trustedContext);
        // Deny ungranted/suspended access before consuming a single-use proof.
        // The CRM-owned policy remains mandatory and is never inferred from
        // caller-supplied claims or a previously valid trust envelope.
        $this->ownerPolicy->assertAllowed($trustedContext, $request->fields);
        $trusted = $this->gateway->authorize(
            $envelope, $owner, $trustedContext, 'SYNTHETIC_LEAD_READ', $now
        );
        if ($trusted['real_authorization'] !== false || $trusted['external_calls'] !== 0) {
            MockError::fail('BRIDGE_TRUST_DENIED');
        }
        // The reader is replaceable behind the CrmReader contract. Inspect its
        // entire (at most two-field) result BEFORE a success audit is written.
        // The common invariant also reorders an otherwise valid reader output
        // according to the request, without accepting extra or foreign data.
        $projection = $this->reader->lead($trustedContext, $request->fields);
        // A replaceable reader can re-enter a volatile owner/trust fixture
        // while a read is in progress. Check revocation/suspension again
        // before calling the projection safe or recording a success.
        // The already-consumed nonce is NOT consumed a second time.
        $this->ownerPolicy->assertAllowed($trustedContext, $request->fields);
        if ($envelope === null || $owner === null) MockError::fail('TRUST_DENIED');
        $envelope->assertValid($trustedContext, $now);
        $owner->assertValid($envelope, $trustedContext, 'SYNTHETIC_LEAD_READ', $now);
        $orderedProjection = MockReadProjectionInvariant::canonicalize(
            $projection, $request->fields, $trustedContext);
        if ($orderedProjection === null) MockError::fail('BRIDGE_PROJECTION_DENIED');
        return [
            'mode' => 'SYNTHETIC_ONLY',
            'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
            'operation' => $request->operation,
            'request_ref' => $request->ref,
            'state' => 'READ_SYNTHETIC_ONLY',
            'projection' => $orderedProjection,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }
}
