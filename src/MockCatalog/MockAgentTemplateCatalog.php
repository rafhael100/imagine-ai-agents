<?php
declare(strict_types=1);

namespace ImagineAI\MockCatalog;

use ImagineAI\Domain\{MockError, TenantContext};

/** Closed in-memory catalog; neither identity, publication nor permission service. */
final class MockAgentTemplateCatalog
{
    // A volatile mock catalog must have a deterministic memory ceiling.
    // Never evict old snapshots: existing reviews and version conflicts
    // depend on retaining their original identities and digests.
    private const MAX_TEMPLATES = 64;
    private const MAX_SNAPSHOTS = 256;
    private array $templates = [];
    private array $snapshots = [];
    private array $versions = [];
    private array $disabledSnapshots = [];

    /** @param list<string> $eligibleTools */
    public function addTemplate(string $ref, TenantContext $context, array $eligibleTools): void
    {
        MockAgentVersionSnapshot::syntheticRef($ref);
        if (isset($this->templates[$ref])) MockError::fail('TEMPLATE_CONFLICT');
        if (!array_is_list($eligibleTools) || count($eligibleTools) > 8) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        foreach ($eligibleTools as $tool) {
            if (!is_string($tool)) MockError::fail('CATALOG_SCHEMA_DENIED');
            MockAgentVersionSnapshot::syntheticRef($tool);
        }
        if (count(array_unique($eligibleTools)) !== count($eligibleTools)) MockError::fail('CATALOG_SCHEMA_DENIED');
        if (count($this->templates) >= self::MAX_TEMPLATES) {
            MockError::fail('CATALOG_CAPACITY_DENIED');
        }
        $this->templates[$ref] = ['context' => $context->key(), 'tools' => $eligibleTools];
    }

    public function addSnapshot(MockAgentVersionSnapshot $snapshot): void
    {
        $template = $this->templates[$snapshot->templateRef] ?? null;
        if ($template === null || $template['context'] !== $snapshot->context->key()) {
            MockError::fail('CATALOG_DENIED');
        }
        foreach ($snapshot->manifest['tools'] as $tool) {
            if (!in_array($tool, $template['tools'], true)) MockError::fail('CATALOG_DENIED');
        }
        if (isset($this->snapshots[$snapshot->ref])) {
            if ($this->snapshots[$snapshot->ref]->digest !== $snapshot->digest) {
                MockError::fail('SNAPSHOT_CONFLICT');
            }
            return;
        }
        $versionKey = json_encode([$snapshot->templateRef, $snapshot->version], JSON_THROW_ON_ERROR);
        if (isset($this->versions[$versionKey])) MockError::fail('SNAPSHOT_CONFLICT');
        // Check both indexes BEFORE altering either of them. A failed new
        // version must not create a partial reference or lose its history.
        if (count($this->snapshots) >= self::MAX_SNAPSHOTS) {
            MockError::fail('CATALOG_CAPACITY_DENIED');
        }
        $this->snapshots[$snapshot->ref] = $snapshot;
        $this->versions[$versionKey] = $snapshot->ref;
    }

    public function get(string $ref, TenantContext $context): MockAgentVersionSnapshot
    {
        $snapshot = $this->snapshots[$ref] ?? null;
        if ($snapshot === null || isset($this->disabledSnapshots[$ref])
            || $snapshot->context->key() !== $context->key()) {
            MockError::fail('SNAPSHOT_DENIED');
        }
        return $snapshot;
    }

    public function disable(string $ref, TenantContext $context): void
    {
        $this->get($ref, $context);
        $this->disabledSnapshots[$ref] = true;
    }
}
