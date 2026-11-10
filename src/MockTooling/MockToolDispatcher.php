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
                $value = $this->coordinator->suggest($run, ['text' => $parameters['text']]);
                $this->trace($toolId, $context, 'SUGGESTED');
                return ['kind' => 'SUGGEST', 'state' => 'SYNTHETIC_OK', 'data' => $value];
            }
            if ($intent === null) MockError::fail('INTENT_REQUIRED');
            $context->assertSame($intent->context);
            if ($intent->operation !== 'mock.task.create' || $intent->material !== $parameters['material']) {
                MockError::fail('INTENT_CONFLICT');
            }
            // The existing coordinator owns approval, reserve-before-submit,
            // idempotency, status-only reconciliation and conservative effects.
            $effect = $this->coordinator->execute($intent, $approval, $now);
            $this->trace($toolId, $context, $effect->state === 'CONFIRMADO' ? 'CONFIRMED' : 'UNKNOWN');
            return ['kind' => 'MUTATE', 'state' => $effect->state];
        } catch (MockError $error) {
            // Never serialize exception text, prompt, resource or parameter values.
            $this->trace($toolId, $context, 'DENIED');
            throw $error;
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
            return [
                'kind' => 'READ', 'state' => 'DENIED_OR_NOT_FOUND',
                'real_authorization' => false, 'external_calls' => 0,
            ];
        }
    }

    /** Minimal synthetic event codes: never text/material, secrets or raw IDs. */
    private function trace(string $toolId, TenantContext $context, string $code): void
    {
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
