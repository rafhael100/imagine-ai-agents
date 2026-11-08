<?php
declare(strict_types=1);

namespace ImagineAI\MockCatalog;

use ImagineAI\Domain\{MockError, TenantContext};

/** Local selection state only. Never changes deployed versions or CRM grants. */
final class MockPromotionGate
{
    private ?string $active = null;
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
            || !array_is_list($allowedTools) || count($allowedTools) > 8
            || count(array_unique($allowedTools)) !== count($allowedTools)) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        foreach ($allowedTools as $tool) {
            if (!is_string($tool)) MockError::fail('CATALOG_SCHEMA_DENIED');
            MockAgentVersionSnapshot::syntheticRef($tool);
        }
        $this->allowedTools = $allowedTools;
    }

    /** Simulate loss of current tool eligibility without changing a real grant. */
    public function revokeTool(string $ref): void
    {
        MockAgentVersionSnapshot::syntheticRef($ref);
        $this->allowedTools = array_values(array_filter(
            $this->allowedTools, static fn (string $tool): bool => $tool !== $ref
        ));
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
            $this->decisions[$decisionRef] = [$verb, $ref, $digest];
            return true;
        }
        if ($this->decisions[$decisionRef] !== [$verb, $ref, $digest]) MockError::fail('PROMOTION_CONFLICT');
        return false;
    }

    private function trace(string $eventCode, MockAgentVersionSnapshot $snapshot): void
    {
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
            if ($record[0] !== 'SUSPEND') MockError::fail('PROMOTION_CONFLICT');
            return 'SUSPENSO';
        }
        if ($this->active === null) MockError::fail('RELEASE_DENIED');
        $snapshot = $this->catalog->get($this->active, $context);
        $this->record($decisionRef, 'SUSPEND', $snapshot->ref, $snapshot->digest);
        $this->active = null;
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
        $this->trace('ROLLED_BACK', $snapshot);
        return 'ATIVO_SIMULADO';
    }

    public function current(): ?string { return $this->active; }

    /** Closed codes + hashes only; no raw manifest, text, secrets, or identities. */
    public function events(): array { return $this->events; }
}
