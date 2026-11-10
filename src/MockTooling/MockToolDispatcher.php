<?php
declare(strict_types=1);

namespace ImagineAI\MockTooling;

use ImagineAI\Domain\{Approval, Intent, MockError, Run, TenantContext};
use ImagineAI\MockOrchestration\SupervisedCoordinator;

/** Local, supervised dispatch over pre-existing fake services only. */
final class MockToolDispatcher
{
    private array $events = [];

    public function __construct(
        private readonly MockToolCatalog $catalog,
        private readonly SupervisedCoordinator $coordinator,
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
