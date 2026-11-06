<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Immutable material string: byte equality is deliberate; no production canonicalization. */
final class Intent
{
    public readonly TenantContext $context;
    public function __construct(
        public readonly string $id,
        public readonly Step $step,
        public readonly string $idempotencyKey,
        public readonly string $operation,
        public readonly string $material,
    ) {
        $step->run->assertActive();
        foreach ([$id, $idempotencyKey, $material] as $ref) {
            if (!str_starts_with($ref, 'SYNTHETIC_')) MockError::fail('INVALID_SYNTHETIC_REF');
        }
        if (!in_array($operation, ['mock.summary.persist', 'mock.task.create'], true)) MockError::fail('OPERATION_DENIED');
        $this->context = $step->context;
    }
    public function signature(): string {
        return json_encode([$this->id, $this->context->key(), $this->operation, $this->material], JSON_THROW_ON_ERROR);
    }
    public function key(): string {
        return json_encode([$this->context->product, $this->context->tenant, $this->idempotencyKey], JSON_THROW_ON_ERROR);
    }
}
