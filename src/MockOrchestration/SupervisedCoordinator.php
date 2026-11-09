<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

use ImagineAI\Contracts\{AiSuggestionProvider, CrmEffectGateway};
use ImagineAI\Domain\{Approval, Attempt, Effect, Intent, MockError, Run, Step, TenantContext};

/** Single-process laboratory coordinator. Context is synthetic, not authenticated. */
final class SupervisedCoordinator
{
    // Must match the maximum accepted by ReadOnlyTraceReview::inspect().
    // This is a process-local safety limit, not production retention.
    private const MAX_TRACE_EVENTS = 1024;
    private array $submitted = [];
    private array $effects = [];
    private array $events = [];
    /** @var array<string, Run> Canonical run instances started by this coordinator. */
    private array $startedRuns = [];
    /** @var array<string, array<string, Step>> Canonical steps for started runs. */
    private array $startedSteps = [];

    public function __construct(
        private readonly TenantContext $context,
        private readonly CrmEffectGateway $gateway,
        private readonly AiSuggestionProvider $ai,
    ) {}

    public function start(string $id): Run
    {
        if (isset($this->startedRuns[$id])) MockError::fail('RUN_ID_CONFLICT');
        $this->assertTraceCapacity(1);
        $run = new Run($id, $this->context);
        $run->transition('EXECUTANDO');
        $this->trace($run, 'STARTED');
        // Commit the canonical identity only AFTER the trace is recorded.
        $this->startedRuns[$id] = $run;
        return $run;
    }

    public function step(Run $run, string $id): Step
    {
        $this->assertRunCompatible($run);
        if (isset($this->startedSteps[$run->id][$id])) MockError::fail('STEP_ID_CONFLICT');
        $this->assertTraceCapacity(1);
        $step = $run->step($id);
        $this->trace($run, 'STEP_CREATED', null, $step);
        if (isset($this->startedRuns[$run->id])) {
            $this->startedSteps[$run->id][$id] = $step;
        }
        return $step;
    }

    public function suggest(Run $run, array $fields): array
    {
        $this->assertRunCompatible($run);
        $run->assertActive();
        $this->assertTraceCapacity(1); // Do not call a provider without space to record the event.
        $suggestion = $this->ai->suggest($run, $fields);
        $this->trace($run, 'SUGGESTED');
        return $suggestion; // Never dispatch a mutation based on model output.
    }

    public function execute(Intent $intent, ?Approval $approval, int $now): Effect
    {
        $this->bind($intent);
        $intent->step->run->assertActive();
        if ($approval === null) {
            $this->trace($intent->step->run, 'APPROVAL_DENIED', $intent);
            MockError::fail('APPROVAL_REQUIRED');
        }
        try {
            $approval->assertValid($intent, $now);
        } catch (MockError $error) {
            $this->trace($intent->step->run, 'APPROVAL_DENIED', $intent);
            MockError::fail('APPROVAL_REQUIRED');
        }
        if (isset($this->submitted[$intent->key()])) return $this->reconcile($intent);
        // Reserve room for SUBMITTED plus either the result or INDETERMINADO.
        // Check BEFORE recording the reservation or invoking the gateway.
        $this->assertTraceCapacity(2);
        // Reserve BEFORE dispatch. Even a lost response must never cause resubmission.
        $this->submitted[$intent->key()] = $intent->signature();
        $this->trace($intent->step->run, 'SUBMITTED', $intent);
        try {
            $effect = $this->gateway->submit($intent, $approval, $now);
        } catch (\Throwable $error) {
            $this->effects[$intent->key()] = new Effect($intent, 'INDETERMINADO');
            $this->trace($intent->step->run, 'INDETERMINADO', $intent);
            // No raw exception or stack enters trace; no automatic retry or status query.
            return $this->effects[$intent->key()];
        }
        try {
            return $this->remember($intent, $effect);
        } catch (MockError $error) {
            // A gateway may respond successfully with an effect bound to the
            // wrong intention. It is not proof of this dispatch. Fail closed,
            // record a fixed uncertainty, and never submit again.
            $unknown = new Effect($intent, 'INDETERMINADO');
            $this->effects[$intent->key()] = $unknown;
            $this->trace($intent->step->run, 'INDETERMINADO', $intent);
            return $unknown;
        }
    }

    public function afterTimeout(Intent $intent, Attempt $attempt): Effect
    {
        $this->bind($intent);
        if ($attempt->state !== 'TIMEOUT' || $attempt->intent->signature() !== $intent->signature()
            || $attempt->intent->key() !== $intent->key()) MockError::fail('ATTEMPT_CONFLICT');
        // A timeout without a prior dispatch is not a transport observation.
        // Reject BEFORE tracing, otherwise the audit records a fictitious send.
        if (!isset($this->submitted[$intent->key()])) MockError::fail('INTENT_NOT_SUBMITTED');
        // TIMEOUT + STATUS_REQUESTED + at most one status result.
        $this->assertTraceCapacity(3);
        $this->trace($intent->step->run, 'TIMEOUT', $intent);
        return $this->reconcile($intent); // Only status, never submit.
    }

    public function reconcile(Intent $intent): Effect
    {
        $this->bind($intent);
        if (!isset($this->submitted[$intent->key()])) MockError::fail('INTENT_NOT_SUBMITTED');
        // STATUS_REQUESTED + at most one observed status result.
        $this->assertTraceCapacity(2);
        $this->trace($intent->step->run, 'STATUS_REQUESTED', $intent);
        try {
            $effect = $this->gateway->status($this->context, $intent);
        } catch (\Throwable $error) {
            // Preserve the exception and prior effect, but close the audit
            // attempt with a fixed, privacy-minimized event.
            $this->trace($intent->step->run, 'STATUS_UNAVAILABLE', $intent);
            throw $error;
        }
        try {
            return $this->remember($intent, $effect);
        } catch (MockError $error) {
            // The gateway answered, but the returned synthetic evidence was
            // inconsistent. Close the status attempt without certifying it.
            // Never leak the raw error, mutate a prior effect or re-submit.
            $this->trace($intent->step->run, 'STATUS_REJECTED', $intent);
            throw $error;
        }
    }

    public function transition(Run $run, string $state): void
    {
        $this->assertRunCompatible($run);
        $this->assertTraceCapacity(1); // Do not mutate lifecycle without space for its trace.
        $run->transition($state);
        $this->trace($run, 'RUN_CHANGED');
    }

    public function events(): array { return $this->events; }

    /**
     * Derived per-run view for offline inspection. Renumbering is VIEW-LOCAL;
     * the original process-level trace and its sequences are never changed.
     * This is neither a signed audit log nor an access-control boundary.
     */
    public function eventsForRun(Run $run): array
    {
        $this->context->assertSame($run->context);
        // An unknown run has no view; a different object using a known ID must
        // not inherit that run's synthetic evidence.
        $known = $this->startedRuns[$run->id] ?? null;
        if ($known === null) return [];
        if ($known !== $run) MockError::fail('RUN_ID_CONFLICT');
        $runRef = self::traceReference($run->id);
        $projection = [];
        foreach ($this->events as $event) {
            if ($event['run'] !== $runRef) continue;
            $copy = $event;
            $copy['sequence'] = count($projection) + 1;
            $projection[] = $copy;
        }
        return $projection;
    }

    private function bind(Intent $intent): void
    {
        $this->assertRunCompatible($intent->step->run);
        // For a coordinator-started run, never accept a detached Step instance
        // which happens to share the same textual identifier.
        if (isset($this->startedRuns[$intent->step->run->id])
            && ($this->startedSteps[$intent->step->run->id][$intent->step->id] ?? null) !== $intent->step) {
            MockError::fail('STEP_NOT_OWNED');
        }
        $signature = $this->submitted[$intent->key()] ?? null;
        if ($signature !== null && $signature !== $intent->signature()) MockError::fail('IDEMPOTENCY_CONFLICT');
    }

    /**
     * These checks prevent accidental identity collisions in this mock.
     * Legacy standalone fixtures remain accepted only while no run has been
     * started through this coordinator; this is NOT caller authentication.
     */
    private function assertRunCompatible(Run $run): void
    {
        $this->context->assertSame($run->context);
        if ($this->startedRuns !== []
            && ($this->startedRuns[$run->id] ?? null) !== $run) {
            MockError::fail('RUN_NOT_OWNED');
        }
    }

    private function remember(Intent $intent, Effect $effect): Effect
    {
        if ($effect->intent->signature() !== $intent->signature() || $effect->intent->key() !== $intent->key()) {
            MockError::fail('EFFECT_CONFLICT');
        }
        $prior = $this->effects[$intent->key()] ?? null;
        if ($prior !== null && in_array($prior->state, ['CONFIRMADO', 'NEGADO'], true)) {
            if ($effect->state !== $prior->state || $effect->id !== $prior->id || $effect->source !== $prior->source) {
                MockError::fail('STATE_CONFLICT');
            }
            // A successful status recheck must close STATUS_REQUESTED in the
            // trace even when it confirms the same immutable prior proof.
            // It does not resubmit, mutate or replace the original Effect.
            $this->trace($intent->step->run, $prior->state, $intent);
            return $prior;
        }
        // A missing record after dispatch cannot prove that nothing happened.
        // Neither submission acknowledgement nor absence is evidence of an effect.
        // The trace reviewer only accepts explicit outcome/uncertainty states.
        if (in_array($effect->state, ['NAO_SOLICITADO', 'SOLICITADO'], true)) {
            $effect = new Effect($intent, 'INDETERMINADO');
        }
        $this->effects[$intent->key()] = $effect;
        $this->trace($intent->step->run, $effect->state, $intent);
        return $effect;
    }

    private function trace(Run $run, string $code, ?Intent $intent = null, ?Step $step = null): void
    {
        $this->assertTraceCapacity(1); // Last line of defense for internal calls.
        // Hash caller-supplied references as well: SYNTHETIC prefix is not sanitization.
        $event = [
            'sequence' => count($this->events) + 1,
            'product' => self::traceReference($this->context->product),
            'tenant' => self::traceReference($this->context->tenant),
            'run' => self::traceReference($run->id),
            'step' => ($intent?->step ?? $step) === null ? null : self::traceReference(($intent?->step ?? $step)->id),
            'intent' => $intent === null ? null : self::traceReference($intent->id),
            'code' => 'SYNTHETIC_' . $code,
            'run_state' => 'SYNTHETIC_' . $run->state(),
        ];
        if ($intent !== null) {
            // Hash the complete run/step/key-bound signature, never its raw material.
            $event['intent_signature'] = self::traceReference($intent->signature());
        }
        $this->events[] = $event;
    }

    private function assertTraceCapacity(int $needed): void
    {
        // Subtract rather than add to avoid integer overflow if this mock evolves.
        if ($needed < 1 || $needed > self::MAX_TRACE_EVENTS
            || count($this->events) > self::MAX_TRACE_EVENTS - $needed) {
            MockError::fail('TRACE_CAPACITY_EXCEEDED');
        }
    }

    private static function traceReference(string $value): string
    {
        // Pure deterministic minimization: never disclose caller-supplied identifiers.
        return 'SYNTHETIC_' . hash('sha256', $value);
    }
}
