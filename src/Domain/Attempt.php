<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Transport simulation is separate from effect proof. */
final class Attempt
{
    private const MAX_REF_BYTES = 128;
    public readonly TenantContext $context;
    public function __construct(public readonly string $id, public readonly Intent $intent, public readonly string $state) {
        if (!str_starts_with($id, 'SYNTHETIC_')
            || strlen($id) <= strlen('SYNTHETIC_')
            || strlen($id) > self::MAX_REF_BYTES) MockError::fail('INVALID_SYNTHETIC_REF');
        if (!in_array($state, ['PREPARADA', 'ENVIADA', 'RESPOSTA', 'ERRO', 'TIMEOUT'], true)) MockError::fail('STATE_CONFLICT');
        $this->context = $intent->context;
    }
}
