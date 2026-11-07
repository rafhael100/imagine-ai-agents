<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Technical units only: no commercial balance, credits, or billing API. */
final class Usage
{
    public readonly TenantContext $context;
    public function __construct(public readonly string $id, public readonly Attempt $attempt,
        public readonly int $units, public readonly string $state = 'OBSERVADO') {
        if (!str_starts_with($id, 'SYNTHETIC_') || $units < 0) MockError::fail('VALIDATION_FAILED');
        if (!in_array($state, ['ESTIMADO', 'OBSERVADO', 'AJUSTADO', 'CONCILIADO'], true)) MockError::fail('STATE_CONFLICT');
        $this->context = $attempt->context;
    }
}
