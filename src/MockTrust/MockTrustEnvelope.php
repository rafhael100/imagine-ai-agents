<?php
declare(strict_types=1);

namespace ImagineAI\MockTrust;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** In-memory context mock; a digest is NOT a token or authenticated signature. */
final class MockTrustEnvelope
{
    private bool $revoked = false;
    public readonly array $scopes;
    public readonly string $digest;

    /** @param list<string> $scopes */
    public function __construct(
        public readonly string $ref,
        public readonly string $issuerRef,
        public readonly string $audienceRef,
        public readonly TenantContext $context,
        public readonly string $nonceRef,
        array $scopes,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
        foreach ([$ref, $issuerRef, $audienceRef, $nonceRef] as $item) {
            MockAgentVersionSnapshot::syntheticRef($item);
        }
        if (!array_is_list($scopes) || count($scopes) === 0 || count($scopes) > 2
            || $issuedAt < 0 || $expiresAt <= $issuedAt || $expiresAt - $issuedAt > 120) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, [
                'SYNTHETIC_LEAD_READ', 'SYNTHETIC_SUMMARY_DRAFT',
            ], true)) {
                MockError::fail('TRUST_SCHEMA_DENIED');
            }
        }
        if (count(array_unique($scopes)) !== count($scopes)) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        $this->scopes = $scopes;
        $this->digest = hash('sha256', json_encode([
            'ref' => $ref, 'issuer' => $issuerRef, 'audience' => $audienceRef,
            'context' => $context->key(), 'nonce' => $nonceRef,
            'scopes' => $scopes, 'issued_at' => $issuedAt, 'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertValid(TenantContext $context, int $now): void
    {
        if ($this->revoked || $now < $this->issuedAt || $now >= $this->expiresAt
            || $this->context->key() !== $context->key()) {
            MockError::fail('TRUST_DENIED');
        }
    }
}
