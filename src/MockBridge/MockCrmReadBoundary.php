<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\Mocks\MockCrmReader;

/** Offline mock; CRM resource-owner ACL and service authentication are NOT implemented. */
final class MockCrmReadBoundary
{
    public function __construct(
        private readonly MockTrustGateway $gateway,
        private readonly MockCrmReader $reader,
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
        $trusted = $this->gateway->authorize(
            $envelope, $owner, $trustedContext, 'SYNTHETIC_LEAD_READ', $now
        );
        if ($trusted['real_authorization'] !== false || $trusted['external_calls'] !== 0) {
            MockError::fail('BRIDGE_TRUST_DENIED');
        }
        // Mandatory CRM-owner policy: there is no longer a legacy bypass
        // for reads that happen to carry a plausible trust envelope.
        $this->ownerPolicy->assertAllowed($trustedContext, $request->fields);
        // The reader has a hard-coded fictional dataset and denies unknown fields/resources.
        $projection = $this->reader->lead($trustedContext, $request->fields);
        if (count($projection) !== count($request->fields)) {
            MockError::fail('BRIDGE_PROJECTION_DENIED');
        }
        $orderedProjection = [];
        foreach ($request->fields as $field) {
            if (!array_key_exists($field, $projection)) {
                MockError::fail('BRIDGE_PROJECTION_DENIED');
            }
            $orderedProjection[$field] = $projection[$field];
        }
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
