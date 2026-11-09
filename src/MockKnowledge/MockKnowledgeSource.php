<?php
declare(strict_types=1);

namespace ImagineAI\MockKnowledge;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Immutable synthetic reference only; no live document, index or privilege. */
final class MockKnowledgeSource
{
    public readonly string $digest;

    public function __construct(
        public readonly string $ref,
        public readonly string $version,
        public readonly TenantContext $context,
        public readonly string $baseRef,
        public readonly string $sectionRef,
        public readonly string $ownerRef,
        public readonly string $factKey,
        public readonly string $factValue,
        public readonly string $text,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly string $sourceType = 'TENANT_PRIVATE',
        public readonly string $classification = 'TENANT_MOCK',
    ) {
        foreach ([$ref, $version, $baseRef, $sectionRef, $ownerRef, $factKey, $factValue] as $value) {
            MockAgentVersionSnapshot::syntheticRef($value);
        }
        if (!in_array($sourceType, ['OFFICIAL_PRODUCT', 'TENANT_PRIVATE', 'IMAGINE_INTERNAL'], true)
            || !in_array($classification, ['PUBLIC_MOCK', 'TENANT_MOCK'], true)
            || $issuedAt < 0 || $expiresAt <= $issuedAt
            || strlen($text) < 12 || strlen($text) > 240
            || !preg_match('/^SYNTHETIC_[\x20-\x7E]+$/D', $text)) {
            MockError::fail('KNOWLEDGE_SCHEMA_DENIED');
        }
        $this->digest = hash('sha256', json_encode([
            $ref, $version, $context->key(), $baseRef, $sectionRef, $ownerRef,
            $factKey, $factValue, $text, $issuedAt, $expiresAt, $sourceType, $classification,
        ], JSON_THROW_ON_ERROR));
    }
}
