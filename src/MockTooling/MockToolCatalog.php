<?php
declare(strict_types=1);

namespace ImagineAI\MockTooling;

use ImagineAI\Domain\MockError;

/** Versioned, closed, in-memory fixtures; never remote tool discovery. */
final class MockToolCatalog
{
    public const VERSION = 'mock-tool-v1';
    public const READ = 'SYNTHETIC_LEAD_READ';
    public const SUGGEST = 'SYNTHETIC_SUMMARY_SUGGEST';
    public const MUTATE = 'SYNTHETIC_TASK_CREATE';

    private const DEFINITIONS = [
        self::READ => 'READ',
        self::SUGGEST => 'SUGGEST',
        self::MUTATE => 'MUTATE',
    ];

    /** @param list<string> $disabled */
    public function __construct(private readonly array $disabled = [])
    {
        if (!array_is_list($disabled)) MockError::fail('VALIDATION_FAILED');
        foreach ($disabled as $id) {
            if (!is_string($id) || !isset(self::DEFINITIONS[$id])) MockError::fail('VALIDATION_FAILED');
        }
    }

    public function kind(string $toolId, string $version): string
    {
        if ($version !== self::VERSION || !isset(self::DEFINITIONS[$toolId])
            || in_array($toolId, $this->disabled, true)) MockError::fail('TOOL_DENIED');
        return self::DEFINITIONS[$toolId];
    }

    /** A small closed schema, not a CRM API contract. */
    public function validate(string $kind, array $params): void
    {
        if (!in_array($kind, ['READ', 'SUGGEST', 'MUTATE'], true)) MockError::fail('TOOL_DENIED');
        if ($kind === 'READ') {
            if (array_keys($params) !== ['fields'] || !is_array($params['fields'])
                || !array_is_list($params['fields']) || $params['fields'] === [] || count($params['fields']) > 2
                || count(array_filter($params['fields'], 'is_string')) !== count($params['fields'])) MockError::fail('PARAMETER_DENIED');
            if (count(array_unique($params['fields'])) !== count($params['fields'])) MockError::fail('PARAMETER_DENIED');
            foreach ($params['fields'] as $field) {
                if (!is_string($field) || !in_array($field, ['ref', 'stage'], true)) MockError::fail('PARAMETER_DENIED');
            }
            return;
        }
        if ($kind === 'SUGGEST') {
            if (array_keys($params) !== ['text'] || !is_string($params['text'])
                || !str_starts_with($params['text'], 'SYNTHETIC') || strlen($params['text']) > 256) {
                MockError::fail('PARAMETER_DENIED');
            }
            return;
        }
        if (array_keys($params) !== ['material'] || !is_string($params['material'])
            || !str_starts_with($params['material'], 'SYNTHETIC_')
            || strlen($params['material']) > 256) MockError::fail('PARAMETER_DENIED');
    }
}
