<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Synthetic references only. Not an authenticated identity. */
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
            if (!str_starts_with($ref, 'SYNTHETIC_')) MockError::fail('INVALID_SYNTHETIC_REF');
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
