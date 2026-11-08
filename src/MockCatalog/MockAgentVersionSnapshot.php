<?php
declare(strict_types=1);

namespace ImagineAI\MockCatalog;

use ImagineAI\Domain\{MockError, TenantContext};

/** Immutable manifest of fake references; never a runnable agent or a signed release. */
final class MockAgentVersionSnapshot
{
    public readonly array $manifest;
    public readonly string $digest;

    /** @param array<string,mixed> $manifest */
    public function __construct(
        public readonly string $ref,
        public readonly string $templateRef,
        public readonly string $version,
        public readonly TenantContext $context,
        array $manifest,
    ) {
        foreach ([$ref, $templateRef, $version] as $value) self::syntheticRef($value);
        $keys = array_keys($manifest);
        sort($keys);
        if ($keys !== ['instruction_ref', 'policy_ref', 'risk', 'schema_version', 'tools']) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        if (!is_string($manifest['instruction_ref']) || !is_string($manifest['policy_ref'])
            || !is_string($manifest['schema_version']) || !is_string($manifest['risk'])) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        self::syntheticRef($manifest['instruction_ref']);
        self::syntheticRef($manifest['policy_ref']);
        if ($manifest['schema_version'] !== 'synthetic-catalog-v1'
            || !in_array($manifest['risk'], ['LOW', 'MEDIUM', 'HIGH'], true)) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        if (!is_array($manifest['tools']) || !array_is_list($manifest['tools'])
            || count($manifest['tools']) > 8) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
        foreach ($manifest['tools'] as $tool) {
            if (!is_string($tool)) MockError::fail('CATALOG_SCHEMA_DENIED');
            self::syntheticRef($tool);
        }
        if (count(array_unique($manifest['tools'])) !== count($manifest['tools'])) MockError::fail('CATALOG_SCHEMA_DENIED');
        $manifest['tools'] = array_values($manifest['tools']);
        sort($manifest['tools'], SORT_STRING);
        ksort($manifest, SORT_STRING);
        $this->manifest = $manifest;
        $this->digest = hash('sha256', json_encode([
            'ref' => $ref,
            'template' => $templateRef,
            'version' => $version,
            'context' => $context->key(),
            'manifest' => $manifest,
        ], JSON_THROW_ON_ERROR));
    }

    public static function syntheticRef(string $value): void
    {
        if (!preg_match('/^SYNTHETIC_[A-Z0-9_]{1,100}$/D', $value)) {
            MockError::fail('CATALOG_SCHEMA_DENIED');
        }
    }

    /** Do not expose instruction refs, capability details or material in this view. */
    public function safeView(): array
    {
        return ['snapshot_ref' => $this->ref, 'template_ref' => $this->templateRef,
            'version' => $this->version, 'digest' => $this->digest];
    }
}
