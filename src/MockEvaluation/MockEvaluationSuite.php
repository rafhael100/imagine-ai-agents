<?php
declare(strict_types=1);

namespace ImagineAI\MockEvaluation;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/**
 * Immutable, synthetic-only, deterministic evaluation contract.
 * No LLM, network, CRM authorization, or real evaluation is performed.
 */
final class MockEvaluationSuite
{
    public readonly array $cases;
    public readonly string $digest;

    /** @param list<array{id:string,severity:string,expected:string}> $cases */
    public function __construct(
        public readonly string $ref,
        public readonly string $datasetRef,
        public readonly string $ruleRef,
        public readonly TenantContext $context,
        array $cases,
    ) {
        foreach ([$ref, $datasetRef, $ruleRef] as $id) {
            MockAgentVersionSnapshot::syntheticRef($id);
        }
        if (!array_is_list($cases) || count($cases) === 0 || count($cases) > 12) {
            MockError::fail('EVAL_SCHEMA_DENIED');
        }
        $seen = [];
        foreach ($cases as $case) {
            if (!is_array($case) || array_keys($case) !== ['id', 'severity', 'expected']
                || !is_string($case['id']) || !is_string($case['severity'])
                || !is_string($case['expected'])) {
                MockError::fail('EVAL_SCHEMA_DENIED');
            }
            MockAgentVersionSnapshot::syntheticRef($case['id']);
            if (isset($seen[$case['id']]) || !in_array($case['severity'], ['CRITICAL', 'REVIEW'], true)
                || !in_array($case['expected'], self::outcomes(), true)) {
                MockError::fail('EVAL_SCHEMA_DENIED');
            }
            $seen[$case['id']] = true;
        }
        $this->cases = $cases;
        $this->digest = hash('sha256', json_encode([
            'ref' => $ref,
            'dataset' => $datasetRef,
            'rules' => $ruleRef,
            'context' => $context->key(),
            'cases' => $cases,
        ], JSON_THROW_ON_ERROR));
    }

    public static function outcomes(): array
    {
        return ['ALLOW', 'DENY', 'CONFLICT', 'DYNAMIC_SOURCE_REQUIRED',
            'UNTRUSTED_DATA', 'NO_DUPLICATE'];
    }

    public function hasCase(string $ref): bool
    {
        foreach ($this->cases as $case) {
            if ($case['id'] === $ref) return true;
        }
        return false;
    }
}
