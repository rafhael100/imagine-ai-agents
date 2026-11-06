<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

final class Step
{
    public readonly TenantContext $context;
    public function __construct(public readonly string $id, public readonly Run $run) {
        $run->assertActive();
        if (!str_starts_with($id, 'SYNTHETIC_')) MockError::fail('INVALID_SYNTHETIC_REF');
        $this->context = $run->context;
    }
}
