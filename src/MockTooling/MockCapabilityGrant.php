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
        // Fail before storing large/untrusted grant references, even when
        // no dispatcher has been invoked. These are fake grants, not IAM.
        foreach ([$id, $toolId] as $ref) {
            if (strlen($ref) > 90
                || preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $ref) !== 1) {
                MockError::fail('VALIDATION_FAILED');
            }
        }
        if ($issuedAt < 0 || $expiresAt <= $issuedAt) MockError::fail('VALIDATION_FAILED');
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
