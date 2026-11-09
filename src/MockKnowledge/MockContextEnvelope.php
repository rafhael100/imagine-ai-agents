<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\{MockError, TenantContext};

/** Pending fake context; rechecks all grants and sources at actual delivery. */
final class MockContextEnvelope
{
    private bool $closed = false;

    /** @param array<string,string> $selected Exact ref => digest at selection time. */
    public function __construct(
        private readonly MockKnowledgeCatalog $catalog,
        private readonly MockRetrievalGrant $grant,
        private readonly MockKnowledgeTrace $trace,
        private readonly TenantContext $context,
        private readonly array $selected,
    ) {
        if (count($selected) === 0 || count($selected) > 3) MockError::fail('CONTEXT_LIMIT_DENIED');
    }

    public function deliver(TenantContext $context, int $now): array
    {
        if ($this->closed) MockError::fail('CONTEXT_CLOSED');
        $this->grant->assertValid($context, $now);
        if ($context->key() !== $this->context->key()) MockError::fail('KNOWLEDGE_DENIED');

        $passages = [];
        $facts = [];
        $totalLength = 0;
        foreach ($this->selected as $ref => $digest) {
            if (!is_string($ref) || !is_string($digest)) MockError::fail('KNOWLEDGE_DENIED');
            $source = $this->catalog->get($ref, $this->grant->sourceVersion, $context, $now);
            $this->grant->assertValid($context, $now, $source);
            if (!hash_equals($digest, $source->digest)) MockError::fail('KNOWLEDGE_DENIED');
            if (isset($facts[$source->factKey]) && $facts[$source->factKey] !== $source->factValue) {
                $this->trace->record('CONFLICT', array_keys($this->selected));
                return ['status' => 'CONFLICT', 'trust' => 'UNTRUSTED_DATA', 'passages' => []];
            }
            $facts[$source->factKey] = $source->factValue;
            $totalLength += strlen($source->text);
            if ($totalLength > 600) MockError::fail('CONTEXT_LIMIT_DENIED');
            $passages[] = [
                'source_ref' => $source->ref,
                'source_version' => $source->version,
                'section_ref' => $source->sectionRef,
                'owner_ref' => $source->ownerRef,
                'digest' => $source->digest,
                'trust' => 'UNTRUSTED_DATA',
                'text' => $source->text,
            ];
        }
        $this->trace->record('DELIVERED', array_keys($this->selected));
        return ['status' => 'READY', 'trust' => 'UNTRUSTED_DATA', 'passages' => $passages];
    }

    public function close(): void
    {
        if ($this->closed) return;
        $this->closed = true;
        $this->trace->record('CLOSED', array_keys($this->selected));
    }
}
