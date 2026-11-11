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
    // A coordinator callback can inspect other canonical Runs. Deny direct
    // lifecycle and Step mutations of ALL Runs registered in that coordinator,
    // not only the Run passed to the provider. Token never goes to provider.
    private ?object $coordinatorScopeToken = null;
    private bool $coordinatorMutationAttempt = false;

    /** Enter a coordinator-managed read-only scope (process-local only). */
    public function beginCoordinatorSuggestionScope(object $token): void
    {
        if ($this->coordinatorScopeToken !== null) {
            $this->coordinatorMutationAttempt = true;
            MockError::fail('PROVIDER_CALLBACK_DENIED');
        }
        $this->coordinatorScopeToken = $token;
        $this->coordinatorMutationAttempt = false;
    }

    /** Restore local lifecycle and report swallowed mutation attempts. */
    public function endCoordinatorSuggestionScope(object $token): bool
    {
        if ($this->coordinatorScopeToken !== $token) {
            if ($this->coordinatorScopeToken !== null) {
                $this->coordinatorMutationAttempt = true;
            }
            MockError::fail('PROVIDER_CALLBACK_DENIED');
        }
        $attempted = $this->coordinatorMutationAttempt;
        $this->coordinatorScopeToken = null;
        $this->coordinatorMutationAttempt = false;
        return $attempted;
    }
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
        if ($this->suggestionReadOnly || $this->coordinatorScopeToken !== null) {
            if ($this->suggestionReadOnly) $this->suggestionMutationAttempt = true;
            if ($this->coordinatorScopeToken !== null) $this->coordinatorMutationAttempt = true;
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

    /**
     * Validate even detached Step constructors that receive this same Run.
     * A provider must not bypass Run::step() by invoking new Step directly.
     * Calls during the suggestion callback leave a sticky denial mark.
     */
    public function assertStepCreationReady(): void
    {
        $this->assertSuggestionWritable();
        $this->assertActive();
    }

    public function step(string $id): Step
    {
        $this->assertStepCreationReady();
        return new Step($id, $this);
    }
}
