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
        // Validate here too: standalone providers can be called without the
        // coordinator. Neither an oversized value nor an enormous array may
        // reach a model or be held as a synthetic projection.
        if (count($fields) > 3) MockError::fail('VALIDATION_FAILED');
        foreach ($fields as $field => $value) {
            if (!in_array($field, ['ref', 'stage', 'text'], true)
                || !is_string($value) || strlen($value) > 256
                || !str_starts_with($value, 'SYNTHETIC')) {
                MockError::fail('VALIDATION_FAILED');
            }
        }
        return ['kind' => 'SYNTHETIC_SUGGESTION', 'version' => 'mock-v1', 'run' => $run->id,
            'source' => $run->context->resource, 'text' => 'SYNTHETIC summary proposal only'];
    }
}
