<?php
declare(strict_types=1);

namespace ImagineAI\MockUsage;

use ImagineAI\Domain\{MockError, TenantContext};

/** In-memory synthetic technical-unit policy, never a CRM billing or access grant. */
final class MockUsagePolicy
{
    private bool $revoked = false;

    public function __construct(
        public readonly string $id,
        public readonly TenantContext $context,
        public readonly string $operation,
        public readonly int $limit,
        public readonly int $warnAt,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
        if (!str_starts_with($id, 'SYNTHETIC_') || strlen($id) > 128
            || !str_starts_with($operation, 'mock.') || strlen($operation) > 128
            || $limit < 0 || $limit > 1_000_000 || $warnAt < 0 || $warnAt > $limit
            || $issuedAt < 0 || $expiresAt <= $issuedAt) {
            MockError::fail('USAGE_POLICY_INVALID');
        }
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertActive(TenantContext $context, string $operation, int $now): void
    {
        if ($this->revoked || $context->key() !== $this->context->key()
            || $operation !== $this->operation || $now < $this->issuedAt
            || $now >= $this->expiresAt) MockError::fail('USAGE_POLICY_DENIED');
    }
}
