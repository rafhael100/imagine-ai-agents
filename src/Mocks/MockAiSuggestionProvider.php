<?php
declare(strict_types=1);

namespace ImagineAI\Mocks;

use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\{Run, MockError};
/** Only transforms pre-filtered synthetic values; no independent reader or mutation. */
final class MockAiSuggestionProvider implements AiSuggestionProvider
{
    public function suggest(Run $run, array $fields): array {
        $run->assertActive();
        // The caller must supply an already-filtered projection. Providers
        // never open an independent CRM/read gateway (even in this mock).
        foreach ($fields as $field => $value) {
            if (!in_array($field, ['ref', 'stage', 'text'], true) || !is_string($value)
                || !str_starts_with($value, 'SYNTHETIC')) MockError::fail('VALIDATION_FAILED');
        }
        return ['kind' => 'SYNTHETIC_SUGGESTION', 'version' => 'mock-v1', 'run' => $run->id,
            'source' => $run->context->resource, 'text' => 'SYNTHETIC summary proposal only'];
    }
}
