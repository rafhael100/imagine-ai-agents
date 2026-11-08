<?php
declare(strict_types=1);

namespace ImagineAI\MockTooling;

use ImagineAI\Domain\{MockError, TenantContext};

/** Injected separately from untrusted model text. NOT authentication or real ACL. */
final class MockCapabilityGrant
{
    private bool $revoked = false;

    public function __construct(
        public readonly string $id,
        public readonly string $toolId,
        public readonly TenantContext $context,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
        if (!str_starts_with($id, 'SYNTHETIC_') || !str_starts_with($toolId, 'SYNTHETIC_')
            || $issuedAt < 0 || $expiresAt <= $issuedAt) MockError::fail('VALIDATION_FAILED');
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertPermits(string $toolId, TenantContext $context, int $now): void
    {
        if ($this->revoked || $now < $this->issuedAt || $now >= $this->expiresAt
            || $toolId !== $this->toolId || $context->key() !== $this->context->key()) {
            MockError::fail('CAPABILITY_DENIED');
        }
    }
}
