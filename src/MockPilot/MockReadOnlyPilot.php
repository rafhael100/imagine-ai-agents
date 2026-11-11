<?php
declare(strict_types=1);

namespace ImagineAI\MockPilot;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadRequest, MockCrmPublicResponseContract};
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/**
 * Fully offline OP-01 + suggestion composition for a proposed read-only pilot.
 * No real CRM, IAM, HTTP, LLM, queue, database, or mutable operation.
 * The trusted context is a synthetic test value, NOT authenticated identity.
 */
final class MockReadOnlyPilot
{
    public function __construct(
        private readonly TenantContext $boundContext,
        private readonly MockCrmReadEdgeBoundary $edge,
        private readonly SupervisedCoordinator $coordinator,
    ) {}

    /**
     * The only successful output is a fixed synthetic draft.
     * A denied read never starts an agent run or invokes the suggestion provider.
     * @param array<string, mixed> $wire A fictional request, never HTTP input.
     * @return array<string, mixed>
     */
    public function suggest(
        array $wire,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        TenantContext $trustedContext,
        int $now,
        string $runId,
    ): array {
        // Track only a run started by this very invocation, so a provider
        // failure cannot leave a fictional execution indefinitely active.
        $run = null;
        try {
            // The coordinator and read edge must operate for the same bound
            // mock context. Never trust caller-supplied tenant substitutions.
            $this->boundContext->assertSame($trustedContext);
            // Before a mock CRM read consumes its nonce and audit slot,
            // validate run ownership and room for the entire four-event
            // synthetic lifecycle. A failed preflight must be side-effect free.
            $this->coordinator->assertPilotRunReady($runId, $trustedContext);

            $read = $this->edge->handle(
                $wire, $envelope, $owner, $trustedContext, $now
            );
            // Reuse the one closed public-response contract; the requested
            // fields, trusted tenant/resource and denial envelope are verified
            // together before any provider or Run can start.
            $request = new MockCrmReadRequest($wire);
            if (!MockCrmPublicResponseContract::isAcceptedRead(
                $read, $request, $trustedContext)) {
                return self::denial();
            }

            // Only the explicitly approved fake fields from the CRM-owned edge
            // are forwarded. The coordinator never executes a mutation here.
            $run = $this->coordinator->start($runId);
            $this->coordinator->step($run, $runId . '_STEP');
            $suggestion = $this->coordinator->suggest($run, $read['projection']);
            if (array_keys($suggestion) !== ['kind', 'version', 'run', 'source', 'text']
                || $suggestion['kind'] !== 'SYNTHETIC_SUGGESTION'
                || $suggestion['version'] !== 'mock-v1'
                || $suggestion['run'] !== $runId
                || $suggestion['source'] !== $trustedContext->resource
                || $suggestion['text'] !== 'SYNTHETIC summary proposal only') {
                throw new \LogicException('UNSAFE_SYNTHETIC_SUGGESTION');
            }
            // Permission can be withdrawn while the suggestion provider runs.
            // Recheck the SAME used proof without a new read, counter, audit
            // slot or nonce. On revocation the Run closes as FALHOU.
            if (!$this->edge->recheck(
                $wire, $envelope, $owner, $trustedContext, $now)) {
                throw new \LogicException('SYNTHETIC_AUTHORIZATION_CHANGED');
            }
            $this->coordinator->transition($run, 'CONCLUIDA');
            return [
                'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
                'state' => 'SUGGESTION_SYNTHETIC_ONLY',
                'summary' => $suggestion['text'],
                'real_authorization' => false,
                'external_calls' => 0,
                'write_calls' => 0,
            ];
        } catch (\Throwable) {
            // If a mock provider failed after starting a run, record the
            // terminal FAILED state using the room reserved by preflight.
            // Never overwrite a terminal state or issue a CRM write.
            if ($run !== null && $run->state() === 'EXECUTANDO') {
                try {
                    $this->coordinator->transition($run, 'FALHOU');
                } catch (\Throwable) {
                    // The public contract remains uniformly denied even if
                    // this in-memory diagnostic closure itself cannot run.
                }
            }
            // No run or resource ID, policy reason, or untrusted text leaves
            // this synthetic boundary in either the output or error handling.
            return self::denial();
        }
    }

    private static function denial(): array
    {
        return [
            'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
            'state' => 'DENIED_OR_NOT_FOUND',
            'summary' => null,
            'real_authorization' => false,
            'external_calls' => 0,
            'write_calls' => 0,
        ];
    }
}
