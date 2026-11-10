<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Immutable material string: byte equality is deliberate; no production canonicalization. */
final class Intent
{
    private const MAX_REF_BYTES = 128;
    private const MAX_MATERIAL_BYTES = 4096;
    public readonly TenantContext $context;
    public function __construct(
        public readonly string $id,
        public readonly Step $step,
        public readonly string $idempotencyKey,
        public readonly string $operation,
        public readonly string $material,
    ) {
        $step->run->assertActive();
        foreach ([$id, $idempotencyKey] as $ref) {
            if (!str_starts_with($ref, 'SYNTHETIC_')
                || strlen($ref) <= strlen('SYNTHETIC_')
                || strlen($ref) > self::MAX_REF_BYTES) {
                MockError::fail('INVALID_SYNTHETIC_REF');
            }
        }
        // An intent's material participates in its signature and may be
        // copied into idempotency evidence. Bound it before computing either.
        if (!str_starts_with($material, 'SYNTHETIC_')
            || strlen($material) <= strlen('SYNTHETIC_')
            || strlen($material) > self::MAX_MATERIAL_BYTES) {
            MockError::fail('INVALID_SYNTHETIC_REF');
        }
        if (!in_array($operation, ['mock.summary.persist', 'mock.task.create'], true)) MockError::fail('OPERATION_DENIED');
        $this->context = $step->context;
    }
    public function signature(): string {
        // Bind the approval and idempotency fingerprint to the originating run and step.
        // These are synthetic identifiers, not authenticated actors or CRM authority.
        return json_encode([
            $this->id, $this->idempotencyKey, $this->step->run->id, $this->step->id,
            $this->context->key(), $this->operation, $this->material,
        ], JSON_THROW_ON_ERROR);
    }
    public function key(): string {
        return json_encode([$this->context->product, $this->context->tenant, $this->idempotencyKey], JSON_THROW_ON_ERROR);
    }
}
