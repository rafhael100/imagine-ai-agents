<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Strict synthetic context references; not an authenticated identity or tenant ACL. */
final class TenantContext
{
    public function __construct(
        public readonly string $product,
        public readonly string $tenant,
        public readonly string $principal,
        public readonly string $purpose,
        public readonly string $resource,
        public readonly string $version = 'mock-v1',
    ) {
        foreach ([$product, $tenant, $principal, $purpose, $resource] as $ref) {
            if (preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $ref) !== 1) {
                MockError::fail('INVALID_SYNTHETIC_REF');
            }
        }
        if ($version !== 'mock-v1') MockError::fail('VERSION_UNSUPPORTED');
    }

    public function key(): string
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
    }

    public function assertSame(self $other): void
    {
        if ($this->key() !== $other->key()) MockError::fail('CONTEXT_DENIED');
    }
}
