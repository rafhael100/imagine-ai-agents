<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\MockError;
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Closed event codes and hashed fake references; never logs passage or prompt text. */
final class MockKnowledgeTrace
{
    private array $events = [];

    /** @param list<string> $sourceRefs */
    public function record(string $code, array $sourceRefs): void
    {
        if (!in_array($code, ['SELECTED', 'DELIVERED', 'CONFLICT', 'CLOSED'], true)
            || !array_is_list($sourceRefs) || count($sourceRefs) > 3) {
            MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
        }
        foreach ($sourceRefs as $sourceRef) {
            if (!is_string($sourceRef)) MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
            MockAgentVersionSnapshot::syntheticRef($sourceRef);
        }
        $this->events[] = [
            'sequence' => count($this->events) + 1,
            'code' => 'SYNTHETIC_' . $code,
            'refs_hash' => hash('sha256', json_encode($sourceRefs, JSON_THROW_ON_ERROR)),
        ];
    }

    public function events(): array { return $this->events; }
}
