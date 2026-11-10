<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\{MockError, TenantContext};

/** Synthetic wire-like contract, NEVER a credential or real CRM API request. */
final class MockCrmReadRequest
{
    public readonly string $ref;
    public readonly string $operation;
    public readonly string $tenantRef;
    public readonly string $principalRef;
    public readonly string $resourceRef;
    public readonly string $purposeRef;
    public readonly array $fields;

    /** Strict, closed mock structure; no permissive coercion or unknown keys. */
    public function __construct(array $wire)
    {
        $keys = ['contract', 'request_ref', 'operation', 'tenant_ref', 'principal_ref',
            'resource_ref', 'purpose_ref', 'fields'];
        // A hostile PHP array may contain arbitrarily many extra keys.
        // Do not allocate/sort an unbounded key list just to deny the body.
        if (count($wire) !== count($keys)) MockError::fail('BRIDGE_SCHEMA_DENIED');
        $actualKeys = array_keys($wire);
        sort($actualKeys, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actualKeys !== $keys
            || ($wire['contract'] ?? null) !== 'SYNTHETIC_CRM_AIA_READ_V1'
            || ($wire['operation'] ?? null) !== 'crm.lead.read_minimal') {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        foreach (['request_ref', 'tenant_ref', 'principal_ref', 'resource_ref', 'purpose_ref'] as $key) {
            if (!is_string($wire[$key]) || strlen($wire[$key]) > 90
                || !preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $wire[$key])) {
                MockError::fail('BRIDGE_SCHEMA_DENIED');
            }
        }
        if (!is_array($wire['fields']) || !array_is_list($wire['fields'])
            || count($wire['fields']) < 1 || count($wire['fields']) > 2) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        foreach ($wire['fields'] as $field) {
            if (!is_string($field) || !in_array($field, ['ref', 'stage'], true)) {
                MockError::fail('BRIDGE_SCHEMA_DENIED');
            }
        }
        if (count(array_unique($wire['fields'])) !== count($wire['fields'])) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        $this->ref = $wire['request_ref'];
        $this->operation = $wire['operation'];
        $this->tenantRef = $wire['tenant_ref'];
        $this->principalRef = $wire['principal_ref'];
        $this->resourceRef = $wire['resource_ref'];
        $this->purposeRef = $wire['purpose_ref'];
        $this->fields = $wire['fields'];
    }

    public function assertMatches(TenantContext $context): void
    {
        if ($context->product !== 'SYNTHETIC_CRM' || $context->tenant !== $this->tenantRef
            || $context->principal !== $this->principalRef
            || $context->resource !== $this->resourceRef
            || $context->purpose !== $this->purposeRef) {
            MockError::fail('BRIDGE_CONTEXT_DENIED');
        }
    }
}
