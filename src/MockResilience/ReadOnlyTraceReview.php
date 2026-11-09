<?php
declare(strict_types=1);

namespace ImagineAI\MockResilience;

use ImagineAI\Domain\{Effect, Intent, TenantContext};

/** Pure synthetic trace inspection. Neither a replay executor nor durable recovery. */
final class ReadOnlyTraceReview
{
    private const FIELDS = ['sequence', 'product', 'tenant', 'run', 'step', 'intent', 'code', 'run_state'];
    private const CODES = [
        'STARTED', 'STEP_CREATED', 'SUGGESTED', 'APPROVAL_DENIED', 'SUBMITTED',
        'STATUS_REQUESTED', 'TIMEOUT', 'INDETERMINADO', 'PENDENTE',
        'CONFIRMADO', 'NEGADO', 'RUN_CHANGED',
    ];
    private const STATES = ['RECEBIDA', 'EXECUTANDO', 'AGUARDANDO', 'CONCLUIDA', 'FALHOU', 'CANCELADA'];

    private static function unknown(string $reason): array
    {
        return ['state' => 'INDETERMINADO', 'reason' => $reason];
    }

    private static function ref(string $value): string
    {
        return 'SYNTHETIC_' . hash('sha256', $value);
    }

    /**
     * Only interprets a supplied synthetic snapshot. Trace alone is NOT proof of an effect.
     * Optional clock and intent_signature fields let fixtures model corruption/reordering.
     * @return array{state: string, reason: string}
     */
    public static function inspect(array $events, TenantContext $context, ?Intent $intent = null, ?Effect $proof = null): array
    {
        if ($events === [] || !array_is_list($events) || count($events) > 1024) return self::unknown('TRACE_INVALID');
        if ($intent !== null && $context->key() !== $intent->context->key()) return self::unknown('CONTEXT_DENIED');
        if ($proof !== null && ($intent === null || $proof->context->key() !== $context->key()
            || $proof->intent->key() !== $intent->key() || $proof->intent->signature() !== $intent->signature())) {
            return self::unknown('EFFECT_CONFLICT');
        }

        $runRef = $intent === null ? null : self::ref($intent->step->run->id);
        $stepRef = $intent === null ? null : self::ref($intent->step->id);
        $intentRef = $intent === null ? null : self::ref($intent->id);
        $signatureRef = $intent === null ? null : self::ref($intent->signature());
        $previousClock = null;
        $sawEffect = false;
        $effectStates = [];
        $terminal = null;
        $seenSubmit = false;
        $seenExpectedStep = false;
        $lastEffect = null;
        foreach ($events as $index => $event) {
            if (!is_array($event)) return self::unknown('TRACE_INVALID');
            $keys = array_keys($event);
            $expected = self::FIELDS;
            // Event-owned signature is written before an optional test clock.
            if (array_key_exists('intent_signature', $event)) $expected[] = 'intent_signature';
            if (array_key_exists('clock', $event)) $expected[] = 'clock';
            if ($keys !== $expected || $event['sequence'] !== $index + 1) return self::unknown('TRACE_INVALID');
            if ($event['product'] !== self::ref($context->product) || $event['tenant'] !== self::ref($context->tenant)) {
                return self::unknown('CONTEXT_DENIED');
            }
            foreach (['run', 'step', 'intent'] as $key) {
                if (!is_string($event[$key]) && $event[$key] !== null) return self::unknown('TRACE_INVALID');
                if ($event[$key] !== null && !preg_match('/^SYNTHETIC_[a-f0-9]{64}$/D', $event[$key])) return self::unknown('TRACE_INVALID');
            }
            if ($runRef !== null && $event['run'] !== $runRef) return self::unknown('CONTEXT_DENIED');
            if ($intentRef !== null && $event['intent'] !== null && ($event['intent'] !== $intentRef || $event['step'] !== $stepRef)) {
                return self::unknown('INTENT_CONFLICT');
            }
            if (array_key_exists('clock', $event)) {
                if (!is_int($event['clock']) || $event['clock'] < 0
                    || ($previousClock !== null && $event['clock'] < $previousClock)) return self::unknown('CLOCK_CONFLICT');
                $previousClock = $event['clock'];
            }
            if (array_key_exists('intent_signature', $event)) {
                if (!is_string($event['intent_signature']) || $signatureRef === null
                    || $event['intent_signature'] !== $signatureRef || $event['intent'] !== $intentRef) {
                    return self::unknown('INTENT_CONFLICT');
                }
            }
            if (!is_string($event['code']) || !str_starts_with($event['code'], 'SYNTHETIC_')
                || !in_array(substr($event['code'], 10), self::CODES, true)) return self::unknown('TRACE_INVALID');
            if (!is_string($event['run_state']) || !str_starts_with($event['run_state'], 'SYNTHETIC_')) return self::unknown('TRACE_INVALID');
            $runState = substr($event['run_state'], 10);
            if (!in_array($runState, self::STATES, true)) return self::unknown('TRACE_INVALID');
            if ($terminal !== null && $runState !== $terminal) return self::unknown('STATE_CONFLICT');
            if (in_array($runState, ['CONCLUIDA', 'FALHOU', 'CANCELADA'], true)) $terminal = $runState;
            $code = substr($event['code'], 10);
            if ($index === 0 && ($code !== 'STARTED' || $runState !== 'EXECUTANDO')) return self::unknown('TRACE_INVALID');
            if ($index > 0 && $code === 'STARTED') return self::unknown('TRACE_INVALID');
            if ($code === 'STEP_CREATED' && $stepRef !== null
                && $event['step'] === $stepRef && $event['intent'] === null) {
                $seenExpectedStep = true;
            }
            // A proof cannot repair missing or detached trace bindings:
            // require the hashed run/step/idempotency/material signature, too.
            if (in_array($code, [
                'APPROVAL_DENIED', 'SUBMITTED', 'STATUS_REQUESTED', 'TIMEOUT',
                'INDETERMINADO', 'PENDENTE', 'CONFIRMADO', 'NEGADO',
            ], true) && ($event['intent'] === null || $event['step'] === null
                || !array_key_exists('intent_signature', $event))) {
                return self::unknown('INTENT_CONFLICT');
            }
            if ($code === 'SUBMITTED') {
                if ($terminal !== null || $runState !== 'EXECUTANDO' || $seenSubmit) return self::unknown('STATE_CONFLICT');
                // The matching synthetic step must precede the submission.
                // This is a consistency check, NOT authentication of events.
                if ($intent !== null && !$seenExpectedStep) return self::unknown('INTENT_CONFLICT');
                $seenSubmit = true;
            }
            if (in_array($code, ['CONFIRMADO', 'NEGADO', 'PENDENTE', 'INDETERMINADO'], true)) {
                if (!$seenSubmit || $event['intent'] === null) return self::unknown('TRACE_INVALID');
                if ($lastEffect !== null && in_array($lastEffect, ['CONFIRMADO', 'NEGADO'], true)
                    && $code !== $lastEffect) return self::unknown('EFFECT_CONFLICT');
                $lastEffect = $code;
                $sawEffect = true;
                $effectStates[$code] = true;
            }
        }
        if (isset($effectStates['CONFIRMADO'], $effectStates['NEGADO'])) return self::unknown('EFFECT_CONFLICT');
        if (!$sawEffect || $proof === null) return self::unknown('PROOF_UNAVAILABLE');
        // Confirmations/denials are never derived from a log, only from compatible fake evidence.
        if ($lastEffect !== $proof->state) return self::unknown('PROOF_CONFLICT');
        if ($proof->state === 'CONFIRMADO' && (!$proof->isConfirmed() || $proof->source !== 'SYNTHETIC_MOCK_CRM')) {
            return self::unknown('PROOF_UNAVAILABLE');
        }
        if (!in_array($proof->state, ['CONFIRMADO', 'NEGADO', 'PENDENTE', 'INDETERMINADO'], true)) {
            return self::unknown('PROOF_UNAVAILABLE');
        }
        return ['state' => $proof->state, 'reason' => 'SYNTHETIC_PROOF_ONLY'];
    }

    /** Cross-process/after-crash recovery cannot be asserted from volatile traces. */
    public static function afterSimulatedRestart(array $events, TenantContext $context, Intent $intent): array
    {
        self::inspect($events, $context, $intent); // Defensive local validation, no dispatch.
        return self::unknown('NO_DURABLE_STATE');
    }
}
