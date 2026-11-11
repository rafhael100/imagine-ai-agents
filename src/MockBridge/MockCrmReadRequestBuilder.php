<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\TenantContext;

/**
 * OP-01 mock consumer-side request composition from an injected, trusted
 * synthetic context. Never accepts tenant/principal/resource/purpose from a
 * model or caller-controlled wire and does NOT authenticate its context.
 */
final class MockCrmReadRequestBuilder
{
    /**
     * A transparent, closed, deterministic request value. The requestRef is
     * caller-provided and not a nonce; the CRM-owner trust gateway alone
     * checks a separate proof, owner policy and replay in this laboratory.
     *
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    public static function fromContext(
        TenantContext $trustedContext,
        string $requestRef,
        array $fields,
    ): array {
        // Prevent copying or sorting large caller-owned arrays before
        // rejecting them. All references are validated by the same parser.
        $wire = [
            'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
            'request_ref' => $requestRef,
            'operation' => 'crm.lead.read_minimal',
            'tenant_ref' => $trustedContext->tenant,
            'principal_ref' => $trustedContext->principal,
            'resource_ref' => $trustedContext->resource,
            'purpose_ref' => $trustedContext->purpose,
            'fields' => $fields,
        ];
        $request = new MockCrmReadRequest($wire);
        $request->assertMatches($trustedContext);
        return $wire;
    }
}
