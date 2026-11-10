<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

final class Step
{
    private const MAX_REF_BYTES = 96;
    public readonly TenantContext $context;
    public function __construct(public readonly string $id, public readonly Run $run) {
        $run->assertActive();
        // Direct Run::step / new Step must not bypass coordinator limits.
        if (!str_starts_with($id, 'SYNTHETIC_')
            || strlen($id) <= strlen('SYNTHETIC_')
            || strlen($id) > self::MAX_REF_BYTES) {
            MockError::fail('INVALID_SYNTHETIC_REF');
        }
        $this->context = $run->context;
    }
}
