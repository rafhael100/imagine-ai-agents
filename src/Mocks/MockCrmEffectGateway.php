<?php
declare(strict_types=1);

namespace ImagineAI\Mocks;

use ImagineAI\Contracts\CrmEffectGateway;
use ImagineAI\Domain\{TenantContext, Intent, Approval, Effect, MockError};
/** Volatile, single-process fake. No concurrency, transactions, or real authorization. */
final class MockCrmEffectGateway implements CrmEffectGateway
{
    private array $effects = [];
    public function __construct(private readonly string $outcome = 'CONFIRMADO') {
        if (!in_array($outcome, ['CONFIRMADO', 'NEGADO', 'PENDENTE', 'INDETERMINADO'], true)) MockError::fail('STATE_CONFLICT');
    }
    public function submit(Intent $intent, ?Approval $approval, int $now): Effect {
        $intent->step->run->assertActive();
        if ($approval === null) MockError::fail('APPROVAL_REQUIRED');
        $approval->assertValid($intent, $now);
        // Local fixture eligibility rechecked on every submission, never inferred from prompt.
        (new MockCrmReader())->lead($intent->context, ['ref']);
        $prior = $this->status($intent->context, $intent);
        if (isset($this->effects[$intent->key()])) return $prior;
        $effect = new Effect($intent, $this->outcome,
            $this->outcome === 'CONFIRMADO' ? 'SYNTHETIC_EFFECT_' . hash('sha256', $intent->key()) : null,
            $this->outcome === 'CONFIRMADO' ? 'SYNTHETIC_MOCK_CRM' : null);
        $this->effects[$intent->key()] = $effect;
        return $effect;
    }
    public function status(TenantContext $context, Intent $intent): Effect {
        $context->assertSame($intent->context);
        (new MockCrmReader())->lead($context, ['ref']);
        $effect = $this->effects[$intent->key()] ?? null;
        if ($effect !== null && $effect->intent->signature() !== $intent->signature()) MockError::fail('IDEMPOTENCY_CONFLICT');
        return $effect ?? new Effect($intent, 'NAO_SOLICITADO');
    }
    public function count(): int { return count($this->effects); }
}
