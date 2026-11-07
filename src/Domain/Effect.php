<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Confirmation here is ALWAYS synthetic; never evidence of a CRM operation. */
final class Effect
{
    public readonly TenantContext $context;
    public function __construct(public readonly Intent $intent, public readonly string $state,
        public readonly ?string $id = null, public readonly ?string $source = null) {
        if (!in_array($state, ['NAO_SOLICITADO', 'SOLICITADO', 'PENDENTE', 'NEGADO', 'INDETERMINADO', 'CONFIRMADO'], true)) MockError::fail('STATE_CONFLICT');
        if ($state === 'CONFIRMADO') {
            if ($id === null || !str_starts_with($id, 'SYNTHETIC_') || $source !== 'SYNTHETIC_MOCK_CRM') MockError::fail('PROOF_REQUIRED');
        } elseif ($id !== null || $source !== null) MockError::fail('PROOF_CONFLICT');
        $this->context = $intent->context;
    }
    public function isConfirmed(): bool { return $this->state === 'CONFIRMADO'; }
}
