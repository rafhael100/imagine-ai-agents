<?php
declare(strict_types=1);

namespace ImagineAI\MockPilot;

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmReadRequestBuilder;
use ImagineAI\MockOrchestration\MockReadOnlyRunQueue;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

/**
 * Offline composition: a bounded read-only queue -> supervised OP-01 pilot.
 * No worker, network, persistence, external CRM, actual service identity,
 * API credentials, billable model or write-side gateway is created.
 */
final class MockQueuedReadOnlyPilot
{
    public function __construct(
        private readonly TenantContext $boundContext,
        private readonly MockReadOnlyRunQueue $queue,
        private readonly MockReadOnlyPilot $pilot,
    ) {}

    /**
     * Preflight for callers that may schedule more than one queued job.
     * Must happen before any queue claim or allocation of audit/nonce.
     */
    public function assertQueueAdmissionReady(): void
    {
        $this->pilot->assertQueueAdmissionReady();
    }

    /**
     * The job supplies only a synthetic request reference and Run reference.
     * The request wire is constructed from the bound context, never from
     * tenant/resource identifiers supplied by a caller or model.
     *
     * @param list<string> $fields
     */
    public function runNext(
        TenantContext $context,
        ?MockTrustEnvelope $envelope,
        ?MockOwnerScopeEvidence $owner,
        array $fields,
        int $now,
        int $leaseTicks,
    ): array {
        try {
            $this->boundContext->assertSame($context);
            // A nested pilot call from an injected suggestion provider must
            // not reserve another FIFO job or consume a second trust proof.
            // This call marks the coordinator callback as contaminated.
            $this->assertQueueAdmissionReady();
            // Validate fields BEFORE claiming a queued job, so invalid user
            // input does not burn a legitimate pending job or CRM nonce.
            MockCrmReadRequestBuilder::fromContext(
                $context, 'SYNTHETIC_QUEUE_PREFLIGHT', $fields);
        } catch (\Throwable) {
            return self::denial();
        }

        $claim = null;
        try {
            $claim = $this->queue->claim($context, $now, $leaseTicks);
            if ($claim === null) return self::empty();
            $wire = MockCrmReadRequestBuilder::fromContext(
                $context, $claim['request_ref'], $fields);
            $result = $this->pilot->suggest(
                $wire, $envelope, $owner, $context, $now, $claim['run_ref']);

            // The pilot is the only component that can decide the fictional
            // minimal read was permitted and the summary is a fixed draft.
            $ok = $result === [
                'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
                'state' => 'SUGGESTION_SYNTHETIC_ONLY',
                'summary' => 'SYNTHETIC summary proposal only',
                'real_authorization' => false,
                'external_calls' => 0,
                'write_calls' => 0,
            ];
            $this->queue->finish($context, $claim['run_ref'],
                $claim['claim_ref'], $ok ? 'CONCLUIDA' : 'FALHOU', $now);
            if (!$ok) return self::denial();
            return [
                'contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
                'state' => 'SUGGESTION_SYNTHETIC_ONLY',
                'summary' => 'SYNTHETIC summary proposal only',
                'real_authorization' => false,
                'external_calls' => 0,
                'write_calls' => 0,
            ];
        } catch (\Throwable) {
            // A claim might be indeterminate/expired; NEVER requeue or
            // dispatch a second read as recovery. Try to close locally only
            // if the queue still considers that exact lease valid.
            if ($claim !== null) {
                try {
                    $this->queue->finish($context, $claim['run_ref'],
                        $claim['claim_ref'], 'FALHOU', $now);
                } catch (\Throwable) {
                    // Leave uncertain state unchanged. No raw error is public.
                }
            }
            return self::denial();
        }
    }

    private static function denial(): array
    {
        return ['contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
            'state' => 'DENIED_OR_NOT_FOUND', 'summary' => null,
            'real_authorization' => false, 'external_calls' => 0,
            'write_calls' => 0];
    }

    private static function empty(): array
    {
        return ['contract' => 'SYNTHETIC_QUEUED_PILOT_V1',
            'state' => 'EMPTY', 'summary' => null,
            'real_authorization' => false, 'external_calls' => 0,
            'write_calls' => 0];
    }
}
