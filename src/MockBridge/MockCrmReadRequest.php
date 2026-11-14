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

    /**
     * F219: byte-exact canonical JSON for a synthetic-only eight-key request.
     * Not an HTTP decoder, interoperable JSON format, authentication or CRM API.
     * Comparing raw bytes to a canonical re-encoding rejects duplicate names
     * and alternative escapes before they can acquire a second interpretation.
     */
    public static function decodeCanonicalJson(string $body): array
    {
        if ($body === '' || strlen($body) > 1024) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        try {
            $wire = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($wire) || array_keys($wire) !== [
                'contract', 'request_ref', 'operation', 'tenant_ref',
                'principal_ref', 'resource_ref', 'purpose_ref', 'fields',
            ]) {
                MockError::fail('BRIDGE_SCHEMA_DENIED');
            }
            new self($wire);
            $canonical = json_encode(
                $wire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );
            if (!hash_equals($canonical, $body)) {
                MockError::fail('BRIDGE_SCHEMA_DENIED');
            }
            return $wire;
        } catch (\Throwable) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
    }

    /**
     * Issue matching canonical body and HMAC proof in ONE operation.
     *
     * PUBLIC test keys only. This deliberately is not IAM, identity, a CRM
     * grant, or a real API credential; the mock cannot reach a real service.
     *
     * @param array<string,mixed> $wire
     * @return array{body:string,proof:array<string,mixed>}
     */
    public static function issueSyntheticSignedFixture(
        array $wire,
        string $nonce,
        int $issuedAt,
        int $expiresAt,
        string $fixtureKey,
    ): array {
        $body = self::encodeCanonicalJson($wire);
        if (strlen($fixtureKey) < 48 || strlen($fixtureKey) > 128
            || !str_starts_with($fixtureKey, 'SYNTHETIC_PUBLIC_TEST_ONLY_')
            || strlen($nonce) > 90
            || preg_match('/\ASYNTHETIC_[A-Z0-9_]{4,80}\z/D', $nonce) !== 1
            || $issuedAt < 0 || $expiresAt <= $issuedAt
            || $expiresAt > 4102444800
            || $expiresAt - $issuedAt > 30) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }

        $proof = [
            'issuer' => 'SYNTHETIC_AGENT_SERVICE',
            'audience' => 'SYNTHETIC_CRM_API',
            'tenant_ref' => $wire['tenant_ref'],
            'nonce' => $nonce,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'body_sha256' => hash('sha256', $body),
        ];
        $message = implode("\n", [
            'SYNTHETIC_AIA_HMAC_PROOF_V1', ...array_values($proof),
        ]);
        $proof['signature'] = hash_hmac('sha256', $message, $fixtureKey);
        return ['body' => $body, 'proof' => $proof];
    }

    /** Only known ordered mock values; never a general serialization service. */
    public static function encodeCanonicalJson(array $wire): string
    {
        new self($wire);
        if (array_keys($wire) !== [
            'contract', 'request_ref', 'operation', 'tenant_ref',
            'principal_ref', 'resource_ref', 'purpose_ref', 'fields',
        ]) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        try {
            $encoded = json_encode(
                $wire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );
            if (strlen($encoded) > 1024) {
                MockError::fail('BRIDGE_SCHEMA_DENIED');
            }
            return $encoded;
        } catch (\Throwable) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
    }

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
