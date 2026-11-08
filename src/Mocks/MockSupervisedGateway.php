<?php
declare(strict_types=1);

namespace ImagineAI\Mocks;

use ImagineAI\Contracts\CrmEffectGateway;
use ImagineAI\Domain\{Approval, Effect, Intent, MockError, TenantContext};

/** Explicit fake evidence injection, never a real confirmation or network timeout. */
final class MockSupervisedGateway implements CrmEffectGateway
{
    private MockCrmEffectGateway $inner;
    private array $evidence = [];
    private int $submits = 0;
    private int $queries = 0;

    public function __construct(string $initial = 'INDETERMINADO', private readonly bool $loseResponse = false)
    {
        $this->inner = new MockCrmEffectGateway($initial);
    }

    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        ++$this->submits;
        $effect = $this->inner->submit($intent, $approval, $now);
        if ($this->loseResponse) MockError::fail('SYNTHETIC_TIMEOUT');
        return $effect;
    }

    public function status(TenantContext $context, Intent $intent): Effect
    {
        ++$this->queries;
        $original = $this->inner->status($context, $intent);
        return $this->evidence[$intent->key()] ?? $original;
    }

    public function recordSyntheticEvidence(Intent $intent, string $state): void
    {
        if (!in_array($state, ['CONFIRMADO', 'NEGADO', 'PENDENTE', 'INDETERMINADO'], true)) MockError::fail('STATE_CONFLICT');
        $prior = $this->status($intent->context, $intent);
        if ($prior->state === 'NAO_SOLICITADO') MockError::fail('INTENT_NOT_SUBMITTED');
        if (in_array($prior->state, ['CONFIRMADO', 'NEGADO'], true)) MockError::fail('STATE_CONFLICT');
        $this->evidence[$intent->key()] = new Effect($intent, $state,
            $state === 'CONFIRMADO' ? 'SYNTHETIC_EFFECT_' . hash('sha256', $intent->key()) : null,
            $state === 'CONFIRMADO' ? 'SYNTHETIC_MOCK_CRM' : null);
    }

    public function submitCount(): int { return $this->submits; }
    public function statusCount(): int { return $this->queries; }
}
