<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Closed in-memory source registry; uniform denial for missing or forbidden entries. */
final class MockKnowledgeCatalog
{
    private array $sources = [];
    private array $disabled = [];

    public function add(MockKnowledgeSource $source): void
    {
        $key = json_encode([$source->ref, $source->version], JSON_THROW_ON_ERROR);
        if (isset($this->sources[$key])) {
            if ($this->sources[$key]->digest !== $source->digest) MockError::fail('KNOWLEDGE_CONFLICT');
            return;
        }
        $this->sources[$key] = $source;
    }

    public function get(string $ref, string $version, TenantContext $context, int $now): MockKnowledgeSource
    {
        MockAgentVersionSnapshot::syntheticRef($ref);
        MockAgentVersionSnapshot::syntheticRef($version);
        $key = json_encode([$ref, $version], JSON_THROW_ON_ERROR);
        $source = $this->sources[$key] ?? null;
        if ($source === null || isset($this->disabled[$key])
            || $source->context->key() !== $context->key()
            || $now < $source->issuedAt || $now >= $source->expiresAt) {
            MockError::fail('KNOWLEDGE_DENIED');
        }
        return $source;
    }

    public function disable(string $ref, string $version, TenantContext $context): void
    {
        MockAgentVersionSnapshot::syntheticRef($ref);
        MockAgentVersionSnapshot::syntheticRef($version);
        $key = json_encode([$ref, $version], JSON_THROW_ON_ERROR);
        $source = $this->sources[$key] ?? null;
        if ($source === null || $source->context->key() !== $context->key()) {
            MockError::fail('KNOWLEDGE_DENIED');
        }
        $this->disabled[$key] = true;
    }
}
