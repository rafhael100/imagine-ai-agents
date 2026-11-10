<?php
declare(strict_types=1);

namespace ImagineAI\MockCatalog;

use ImagineAI\Domain\{MockError, TenantContext};

/** Local selection state only. Never changes deployed versions or CRM grants. */
final class MockPromotionGate
{
    // Leave one decision and trace slot for an emergency explicit suspension.
    // No historic decision is evicted: replay/conflict evidence must survive.
    private const MAX_DECISIONS = 128;
    private const MAX_TRACE_EVENTS = 256;
    private ?string $active = null;
    /** Last selected snapshot is kept so suspension remains possible after catalog disable. */
    private ?MockAgentVersionSnapshot $activeSnapshot = null;
    private array $decisions = [];
    private array $history = [];
    private array $events = [];
    private array $allowedTools;

    /** @param list<string> $allowedTools */
    public function __construct(
        private readonly MockAgentTemplateCatalog $catalog,
        private readonly TenantContext $context,
        array $allowedTools,
        private readonly string $datasetVersion,
        private readonly string $ruleVersion,
        private readonly string $maxRisk = 'MEDIUM',
    ) {
        MockAgentVersionSnapshot::syntheticRef($datasetVersion);
        MockAgentVersionSnapshot::syntheticRef($ruleVersion);
        if (!in_array($maxRisk, ['LOW', 'MEDIUM', 'HIGH'], true)
            || !array_is_list($allowedTools) || count($allowedTools) > 8) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        foreach ($allowedTools as $tool) {
            if (!is_string($tool)) MockError::fail('CATALOG_SCHEMA_DENIED');
            MockAgentVersionSnapshot::syntheticRef($tool);
        }
        if (count(array_unique($allowedTools)) !== count($allowedTools)) MockError::fail('CATALOG_SCHEMA_DENIED');
        $this->allowedTools = $allowedTools;
    }

    /** Simulate loss of current tool eligibility without changing a real grant. */
    public function revokeTool(string $ref): void
    {
        MockAgentVersionSnapshot::syntheticRef($ref);
        $this->allowedTools = array_values(array_filter(
            $this->allowedTools, static fn (string $tool): bool => $tool !== $ref
        ));
        // A revoked capability immediately invalidates the local selection, without any external action.
        if ($this->activeSnapshot !== null && in_array($ref, $this->activeSnapshot->manifest['tools'], true)) {
            $snapshot = $this->activeSnapshot;
            $this->active = null;
            $this->activeSnapshot = null;
            $this->trace('SUSPENDED_TOOL_REVOKED', $snapshot);
        }
    }

    private function eligible(MockAgentVersionSnapshot $snapshot, MockVersionReview $review,
        TenantContext $context, int $now): void
    {
        if ($this->context->key() !== $context->key() || $snapshot->context->key() !== $context->key()) {
            MockError::fail('CATALOG_DENIED');
        }
        if ($this->catalog->get($snapshot->ref, $context)->digest !== $snapshot->digest) {
            MockError::fail('SNAPSHOT_CONFLICT');
        }
        $review->assertValid($snapshot, $context, $this->datasetVersion, $this->ruleVersion, $now);
        $scores = ['LOW' => 0, 'MEDIUM' => 1, 'HIGH' => 2];
        if ($scores[$snapshot->manifest['risk']] > $scores[$this->maxRisk]) {
            MockError::fail('RELEASE_DENIED');
        }
        foreach ($snapshot->manifest['tools'] as $tool) {
            if (!in_array($tool, $this->allowedTools, true)) MockError::fail('RELEASE_DENIED');
        }
    }

    private function record(string $decisionRef, string $verb, string $ref, string $digest): bool
    {
        MockAgentVersionSnapshot::syntheticRef($decisionRef);
        if (!isset($this->decisions[$decisionRef])) {
            // PROMOTE/ROLLBACK reserve one additional slot for SUSPEND;
            // SUSPEND may consume the final slot to safely deactivate a
            // selected snapshot. Reserve before recording a decision.
            $reserve = $verb === 'SUSPEND' ? 1 : 2;
            if (count($this->decisions) > self::MAX_DECISIONS - $reserve
                || count($this->events) > self::MAX_TRACE_EVENTS - $reserve) {
                MockError::fail('PROMOTION_CAPACITY_DENIED');
            }
            $this->decisions[$decisionRef] = [$verb, $ref, $digest];
            return true;
        }
        if ($this->decisions[$decisionRef] !== [$verb, $ref, $digest]) MockError::fail('PROMOTION_CONFLICT');
        return false;
    }

    private function trace(string $eventCode, MockAgentVersionSnapshot $snapshot): void
    {
        // Defense in depth for capability revocation (which is not recorded
        // as a decision). If audit is exhausted, prior revocation already
        // cleared the active selection and must fail visibly.
        if (count($this->events) >= self::MAX_TRACE_EVENTS) {
            MockError::fail('PROMOTION_CAPACITY_DENIED');
        }
        $this->events[] = ['sequence' => count($this->events) + 1,
            'template_hash' => hash('sha256', $snapshot->templateRef),
            'snapshot_hash' => hash('sha256', $snapshot->ref),
            'code' => 'SYNTHETIC_' . $eventCode];
    }

    public function promote(string $decisionRef, MockAgentVersionSnapshot $snapshot,
        MockVersionReview $review, TenantContext $context, int $now): string
    {
        $this->eligible($snapshot, $review, $context, $now);
        if (!$this->record($decisionRef, 'PROMOTE', $snapshot->ref, $snapshot->digest)) {
            return 'ATIVO_SIMULADO'; // Original result, no replayed effect or state change.
        }
        $this->active = $snapshot->ref;
        $this->activeSnapshot = $snapshot;
        $this->history[$snapshot->ref] = $snapshot->digest;
        $this->trace('PROMOTED', $snapshot);
        return 'ATIVO_SIMULADO';
    }

    public function suspend(string $decisionRef, TenantContext $context): string
    {
        if ($context->key() !== $this->context->key()) MockError::fail('CATALOG_DENIED');
        MockAgentVersionSnapshot::syntheticRef($decisionRef);
        if (isset($this->decisions[$decisionRef])) {
            $record = $this->decisions[$decisionRef];
            // A past suspension may only be replayed when no later selection is active.
            if ($record[0] !== 'SUSPEND' || $this->active !== null) MockError::fail('PROMOTION_CONFLICT');
            return 'SUSPENSO';
        }
        if ($this->active === null || $this->activeSnapshot === null) MockError::fail('RELEASE_DENIED');
        // Suspension is still allowed after the catalog has disabled this snapshot.
        // Re-reading via catalog->get() would incorrectly deny the safe action.
        $snapshot = $this->activeSnapshot;
        $this->record($decisionRef, 'SUSPEND', $snapshot->ref, $snapshot->digest);
        $this->active = null;
        $this->activeSnapshot = null;
        $this->trace('SUSPENDED', $snapshot);
        return 'SUSPENSO';
    }

    public function rollback(string $decisionRef, MockAgentVersionSnapshot $snapshot,
        MockVersionReview $review, TenantContext $context, int $now): string
    {
        $this->eligible($snapshot, $review, $context, $now);
        if (!isset($this->history[$snapshot->ref]) || $this->history[$snapshot->ref] !== $snapshot->digest) {
            MockError::fail('ROLLBACK_DENIED');
        }
        if (!$this->record($decisionRef, 'ROLLBACK', $snapshot->ref, $snapshot->digest)) {
            return 'ATIVO_SIMULADO';
        }
        $this->active = $snapshot->ref;
        $this->activeSnapshot = $snapshot;
        $this->trace('ROLLED_BACK', $snapshot);
        return 'ATIVO_SIMULADO';
    }

    /** The visible selection fails closed when its catalog entry or tool eligibility is revoked. */
    public function current(): ?string
    {
        if ($this->active === null || $this->activeSnapshot === null) return null;
        try {
            $known = $this->catalog->get($this->active, $this->context);
        } catch (MockError $error) {
            if ($error->getMessage() === 'SNAPSHOT_DENIED') return null;
            throw $error;
        }
        if ($known->digest !== $this->activeSnapshot->digest) return null;
        foreach ($this->activeSnapshot->manifest['tools'] as $tool) {
            if (!in_array($tool, $this->allowedTools, true)) return null;
        }
        return $this->active;
    }

    /** Closed codes + hashes only; no raw manifest, text, secrets, or identities. */
    public function events(): array { return $this->events; }
}
