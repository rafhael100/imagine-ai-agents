<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Technical units only: no commercial balance, credits, or billing API. */
final class Usage
{
    private const MAX_REF_BYTES = 128;
    private const MAX_TECHNICAL_UNITS = 1_000_000;
    public readonly TenantContext $context;
    public function __construct(public readonly string $id, public readonly Attempt $attempt,
        public readonly int $units, public readonly string $state = 'OBSERVADO') {
        if (!str_starts_with($id, 'SYNTHETIC_')
            || strlen($id) <= strlen('SYNTHETIC_')
            || strlen($id) > self::MAX_REF_BYTES
            || $units < 0 || $units > self::MAX_TECHNICAL_UNITS) {
            MockError::fail('VALIDATION_FAILED');
        }
        if (!in_array($state, ['ESTIMADO', 'OBSERVADO', 'AJUSTADO', 'CONCILIADO'], true)) MockError::fail('STATE_CONFLICT');
        $this->context = $attempt->context;
    }
}
