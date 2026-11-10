<?php
declare(strict_types=1);

namespace ImagineAI\MockTooling;

use ImagineAI\Domain\{Approval, Intent, MockError, Run, TenantContext};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockBridge\MockCrmReadRequest;
use ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/** Local, supervised dispatch over pre-existing fake services only. */
final class MockToolDispatcher
{
    private const MAX_TRACE_EVENTS = 1024;
    // Only fixed, reviewed API-level errors may cross the mock dispatcher.
    // A gateway can throw MockError with arbitrary text: never forward it.
    private const PUBLIC_DENIAL_CODES = [
        'TOOL_DENIED', 'CAPABILITY_DENIED', 'PARAMETER_DENIED',
        'READ_BOUNDARY_REQUIRED', 'RUN_REQUIRED', 'CONTEXT_DENIED',
        'RUN_NOT_OWNED', 'RUN_NOT_ACTIVE', 'TRACE_CAPACITY_EXCEEDED',
        'TOOL_TRACE_CAPACITY_DENIED', 'INTENT_REQUIRED', 'INTENT_CONFLICT',
        'APPROVAL_REQUIRED', 'IDEMPOTENCY_CONFLICT', 'STEP_NOT_OWNED',
        'TOOL_SUGGESTION_DENIED', 'SYNTHETIC_STATUS_UNAVAILABLE',
    ];
    private array $events = [];

    public function __construct(
        private readonly MockToolCatalog $catalog,
        private readonly SupervisedCoordinator $coordinator,
        private readonly ?MockCrmReadEdgeBoundary $readEdge = null,
    ) {}

    /**
     * @return array{kind:string,state:string, data?:array<string,mixed>}
     * No gateway, model, network, token or persistent operation is created here.
     */
    public function dispatch(
        string $toolId,
        string $version,
        TenantContext $context,
        ?MockCapabilityGrant $grant,
        array $parameters,
        int $now,
        ?Run $run = null,
        ?Intent $intent = null,
        ?Approval $approval = null,
    ): array {
        // Reserve one local audit event before any tool, suggestion or
        // synthetic effect executes. No event capacity means no operation.
        $this->assertTraceRoom();
        try {
            $kind = $this->catalog->kind($toolId, $version);
            if ($grant === null) MockError::fail('CAPABILITY_DENIED');
            $grant->assertPermits($toolId, $context, $now);
            $this->catalog->validate($kind, $parameters);

            if ($kind === 'READ') {
                // This legacy tool has no owner policy, trust envelope, audit,
                // or anti-replay inputs. Never call the reader directly.
                // Use the authorized OP-01 public edge/read-only pilot instead.
                MockError::fail('READ_BOUNDARY_REQUIRED');
            }
            if ($kind === 'SUGGEST') {
                if ($run === null) MockError::fail('RUN_REQUIRED');
                $context->assertSame($run->context);
                // An accepted response uses one trace event. A rejected
                // response may need one additional event to close this Run
                // as FALHOU; reject before invocation if neither fits.
                $this->coordinator->assertToolSuggestionReady($run);
                try {
                    $value = $this->coordinator->suggest($run, ['text' => $parameters['text']]);
                    // A coordinator SUGGESTED event only means a provider
                    // answered. It is not proof of trusted output content.
                    if (array_keys($value) !== ['kind', 'version', 'run', 'source', 'text']
                        || $value['kind'] !== 'SYNTHETIC_SUGGESTION'
                        || $value['version'] !== 'mock-v1'
                        || $value['run'] !== $run->id
                        || $value['source'] !== $context->resource
                        || $value['text'] !== 'SYNTHETIC summary proposal only') {
                        MockError::fail('TOOL_SUGGESTION_DENIED');
                    }
                } catch (\Throwable) {
                    // A rejected/failed provider MUST NOT leave this
                    // synthetic Run active for a subsequent approved action.
                    // Preflight reserved space for the terminal event.
                    try {
                        if ($run->state() === 'EXECUTANDO') {
                            $this->coordinator->transition($run, 'FALHOU');
                        }
                    } catch (\Throwable) {
                        // Never leak secondary diagnostic failures.
                    }
                    MockError::fail('TOOL_SUGGESTION_DENIED');
                }
                $this->trace($toolId, $context, 'SUGGESTED');
                return ['kind' => 'SUGGEST', 'state' => 'SYNTHETIC_OK', 'data' => $value];
            }
            if ($intent === null) MockError::fail('INTENT_REQUIRED');
            $context->assertSame($intent->context);
            if ($intent->operation !== 'mock.task.create' || $intent->material !== $parameters['material']) {
                MockError::fail('INTENT_CONFLICT');
            }
            // The generic coordinator intentionally retains support for
            // historical standalone fixtures. The tool boundary must NOT:
            // require a Run and Step that this coordinator actually created.
            $this->coordinator->assertToolIntentReady($intent);
            // The existing coordinator owns approval, reserve-before-submit,
            // idempotency, status-only reconciliation and conservative effects.
            $effect = $this->coordinator->execute($intent, $approval, $now);
            $this->trace($toolId, $context, $effect->state === 'CONFIRMADO' ? 'CONFIRMED' : 'UNKNOWN');
            return ['kind' => 'MUTATE', 'state' => $effect->state];
        } catch (MockError $error) {
            // The original exception may originate from an untrusted
            // status gateway. Audit a fixed code and disclose only an
            // explicitly reviewed error, never arbitrary exception text.
            $this->trace($toolId, $context, 'DENIED');
            if (!in_array($error->getMessage(), self::PUBLIC_DENIAL_CODES, true)) {
                MockError::fail('TOOL_EXECUTION_DENIED');
            }
            throw $error;
        } catch (\Throwable) {
            // Unexpected adapter faults are recorded as a generic denial,
            // not leaked as raw provider/transport errors.
            $this->trace($toolId, $context, 'DENIED');
            MockError::fail('TOOL_EXECUTION_DENIED');
        }
    }

    /**
     * Explicit opt-in read operation: unlike legacy dispatch(READ), this must
     * traverse the CRM-owner OP-01 edge. It cannot create effects, mutate a
     * lead or authenticate real service credentials.
     *
     * @param array<string,mixed> $wire A fully specified synthetic OP-01 request.
     * @return array<string,mixed> Uniform denial or minimal fictional projection.
     */
    public function readThroughBoundary(
        string $toolId,
        string $version,
        TenantContext $context,
        ?MockCapabilityGrant $grant,
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        int $now,
    ): array {
        // If no trace can be written, refuse before consuming a nonce.
        // This endpoint returns a generic denial by contract.
        if (count($this->events) >= self::MAX_TRACE_EVENTS) {
            return self::deniedRead();
        }
        try {
            if ($this->catalog->kind($toolId, $version) !== 'READ') {
                MockError::fail('TOOL_DENIED');
            }
            if ($grant === null) MockError::fail('CAPABILITY_DENIED');
            $grant->assertPermits($toolId, $context, $now);
            if ($this->readEdge === null) MockError::fail('READ_BOUNDARY_REQUIRED');

            // Cross-check operation, context, fields and closed request schema
            // before invoking the owner-controlled edge.
            $request = new MockCrmReadRequest($wire);
            $request->assertMatches($context);
            $this->catalog->validate('READ', ['fields' => $request->fields]);

            $out = $this->readEdge->handle($wire, $envelope, $owner, $context, $now);
            if (array_keys($out) !== [
                    'contract', 'state', 'projection', 'real_authorization', 'external_calls'
                ]
                || $out['contract'] !== 'SYNTHETIC_CRM_AIA_PUBLIC_V1'
                || $out['state'] !== 'READ_SYNTHETIC_ONLY'
                || $out['real_authorization'] !== false
                || $out['external_calls'] !== 0
                || !is_array($out['projection'])
                || array_keys($out['projection']) !== $request->fields) {
                MockError::fail('READ_DENIED');
            }
            foreach ($out['projection'] as $field => $value) {
                if (!in_array($field, ['ref', 'stage'], true)
                    || !is_string($value)
                    || preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $value) !== 1) {
                    MockError::fail('READ_DENIED');
                }
            }
            $this->trace($toolId, $context, 'READ');
            return [
                'kind' => 'READ', 'state' => 'SYNTHETIC_OK',
                'data' => $out['projection'],
                'real_authorization' => false, 'external_calls' => 0,
            ];
        } catch (\Throwable) {
            // Never expose the existence of another tenant's lead, policy
            // reason, token, untrusted field or internal exception text.
            $this->trace($toolId, $context, 'DENIED');
            return self::deniedRead();
        }
    }

    private static function deniedRead(): array
    {
        return [
            'kind' => 'READ', 'state' => 'DENIED_OR_NOT_FOUND',
            'real_authorization' => false, 'external_calls' => 0,
        ];
    }

    private function assertTraceRoom(): void
    {
        if (count($this->events) >= self::MAX_TRACE_EVENTS) {
            MockError::fail('TOOL_TRACE_CAPACITY_DENIED');
        }
    }

    /** Minimal synthetic event codes: never text/material, secrets or raw IDs. */
    private function trace(string $toolId, TenantContext $context, string $code): void
    {
        $this->assertTraceRoom();
        $this->events[] = [
            'sequence' => count($this->events) + 1,
            'product' => self::traceReference($context->product),
            'tenant' => self::traceReference($context->tenant),
            'tool' => self::traceReference($toolId),
            'code' => 'SYNTHETIC_' . $code,
        ];
    }

    public function events(): array { return $this->events; }

    private static function traceReference(string $value): string
    {
        // Pure deterministic minimization: never disclose caller-supplied identifiers.
        return 'SYNTHETIC_' . hash('sha256', $value);
    }
}
