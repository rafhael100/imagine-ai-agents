<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Explicit fake retrieval scope. No real IAM identity or capability is granted. */
final class MockRetrievalGrant
{
    private bool $revoked = false;
    public readonly array $sourceRefs;

    /** @param list<string> $sourceRefs */
    public function __construct(
        public readonly string $ref,
        public readonly TenantContext $context,
        public readonly string $baseRef,
        public readonly string $sourceVersion,
        array $sourceRefs,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
    ) {
        foreach ([$ref, $baseRef, $sourceVersion] as $item) {
            MockAgentVersionSnapshot::syntheticRef($item);
        }
        if (!array_is_list($sourceRefs) || count($sourceRefs) === 0 || count($sourceRefs) > 3
            || $issuedAt < 0 || $expiresAt <= $issuedAt) {
            MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
        }
        foreach ($sourceRefs as $sourceRef) {
            if (!is_string($sourceRef)) MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
            MockAgentVersionSnapshot::syntheticRef($sourceRef);
        }
        if (count(array_unique($sourceRefs)) !== count($sourceRefs)) {
            MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
        }
        $this->sourceRefs = $sourceRefs;
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertValid(TenantContext $context, int $now, ?MockKnowledgeSource $source = null): void
    {
        if ($this->revoked || $context->key() !== $this->context->key()
            || $now < $this->issuedAt || $now >= $this->expiresAt
            || ($source !== null && ($source->context->key() !== $context->key()
                || $source->baseRef !== $this->baseRef
                || $source->version !== $this->sourceVersion
                || !in_array($source->ref, $this->sourceRefs, true)))) {
            MockError::fail('KNOWLEDGE_DENIED');
        }
    }
}
