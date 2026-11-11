<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

use ImagineAI\Contracts\AiSuggestionProvider;

/** Minimal mock lifecycle. Completing a run never confirms an effect. */
final class Run
{
    private const MAX_REF_BYTES = 80;
    private string $state = 'RECEBIDA';
    // Per-instance callback guard. An injected provider receives this Run;
    // a coordinator-only guard is insufficient to protect direct transitions.
    private bool $suggestionReadOnly = false;
    private bool $suggestionMutationAttempt = false;
    public function __construct(public readonly string $id, public readonly TenantContext $context) {
        // Enforce the same bound even for standalone legacy domain instances.
        // Coordinator-only checks would leave this construction path unbounded.
        if (!str_starts_with($id, 'SYNTHETIC_')
            || strlen($id) <= strlen('SYNTHETIC_')
            || strlen($id) > self::MAX_REF_BYTES) {
            MockError::fail('INVALID_SYNTHETIC_REF');
        }
    }
    public function state(): string { return $this->state; }

    /**
     * Read-only lifetime of a synthetic suggestion callback. The provider
     * may inspect this Run but not mutate its lifecycle or create a Step.
     * A swallowed mutation attempt permanently denies the outer callback.
     * Process-local only; not a permission/authentication boundary.
     *
     * @return array
     */
    public function withReadOnlySuggestion(AiSuggestionProvider $provider, array $fields): array
    {
        if ($this->suggestionReadOnly) {
            $this->suggestionMutationAttempt = true;
            MockError::fail('PROVIDER_CALLBACK_DENIED');
        }
        $this->suggestionReadOnly = true;
        $this->suggestionMutationAttempt = false;
        try {
            $result = $provider->suggest($this, $fields);
            if ($this->suggestionMutationAttempt) {
                MockError::fail('PROVIDER_CALLBACK_DENIED');
            }
            return $result;
        } finally {
            // Always restore the ability of the coordinator to terminalize
            // the Run (FALHOU) after a failed or rejected provider callback.
            $this->suggestionReadOnly = false;
            $this->suggestionMutationAttempt = false;
        }
    }

    private function assertSuggestionWritable(): void
    {
        if ($this->suggestionReadOnly) {
            $this->suggestionMutationAttempt = true;
            MockError::fail('PROVIDER_CALLBACK_DENIED');
        }
    }
    public function transition(string $next): void
    {
        $this->assertSuggestionWritable();
        $edges = [
            'RECEBIDA' => ['EXECUTANDO', 'CANCELADA'],
            'EXECUTANDO' => ['AGUARDANDO', 'CONCLUIDA', 'FALHOU', 'CANCELADA'],
            'AGUARDANDO' => ['EXECUTANDO', 'FALHOU', 'CANCELADA'],
        ];
        if (!in_array($next, $edges[$this->state] ?? [], true)) MockError::fail('STATE_CONFLICT');
        $this->state = $next;
    }
    public function assertActive(): void
    {
        if ($this->state !== 'EXECUTANDO') MockError::fail('RUN_NOT_ACTIVE');
    }
    public function step(string $id): Step { $this->assertSuggestionWritable(); $this->assertActive(); return new Step($id, $this); }
}
