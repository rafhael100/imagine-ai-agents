<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Explicit selection of fake references only. Never searches an outside system. */
final class MockKnowledgeRetriever
{
    public function __construct(
        private readonly MockKnowledgeCatalog $catalog,
        private readonly MockKnowledgeTrace $trace,
    ) {}

    /** @param list<string> $sourceRefs */
    public function select(?MockRetrievalGrant $grant, TenantContext $context,
        array $sourceRefs, int $now, string $kind = 'STATIC'): MockContextEnvelope
    {
        if ($grant === null) MockError::fail('KNOWLEDGE_DENIED');
        $grant->assertValid($context, $now);
        if ($kind === 'DYNAMIC') MockError::fail('DYNAMIC_SOURCE_REQUIRED');
        if ($kind !== 'STATIC' || !array_is_list($sourceRefs)
            || count($sourceRefs) === 0 || count($sourceRefs) > 3) {
            MockError::fail('KNOWLEDGE_DENIED');
        }
        foreach ($sourceRefs as $ref) {
            if (!is_string($ref)) MockError::fail('KNOWLEDGE_DENIED');
            MockAgentVersionSnapshot::syntheticRef($ref);
        }
        if (count(array_unique($sourceRefs)) !== count($sourceRefs)) {
            MockError::fail('KNOWLEDGE_DENIED');
        }
        $selected = [];
        foreach ($sourceRefs as $ref) {
            $source = $this->catalog->get($ref, $grant->sourceVersion, $context, $now);
            $grant->assertValid($context, $now, $source);
            $selected[$ref] = $source->digest;
        }
        $this->trace->record('SELECTED', $sourceRefs);
        return new MockContextEnvelope($this->catalog, $grant, $this->trace, $context, $selected);
    }
}
