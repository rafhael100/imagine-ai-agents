<?php
declare(strict_types=1);

namespace ImagineAI\MockTrust;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Fictional assertion of owner permission; never a replacement for the resource owner's ACL. */
final class MockOwnerScopeEvidence
{
    private bool $revoked = false;

    public function __construct(
        public readonly string $ref,
        public readonly string $envelopeDigest,
        public readonly TenantContext $context,
        public readonly string $scopeRef,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly bool $permitted,
    ) {
        MockAgentVersionSnapshot::syntheticRef($ref);
        MockAgentVersionSnapshot::syntheticRef($scopeRef);
        if (!preg_match('/^[0-9a-f]{64}$/D', $envelopeDigest)
            || $issuedAt < 0 || $expiresAt <= $issuedAt) {
            MockError::fail('OWNER_SCHEMA_DENIED');
        }
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertValid(MockTrustEnvelope $envelope, TenantContext $context,
        string $scope, int $now): void
    {
        if ($this->revoked || !$this->permitted || $now < $this->issuedAt
            || $now >= $this->expiresAt || $this->context->key() !== $context->key()
            || $this->scopeRef !== $scope || !hash_equals($this->envelopeDigest, $envelope->digest)) {
            MockError::fail('OWNER_DENIED');
        }
    }
}
