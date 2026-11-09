<?php
declare(strict_types=1);

namespace ImagineAI\MockEvaluation;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Synthetic observed outcomes only. Not an agent, runtime, or validated release. */
final class MockEvaluationCandidate
{
    public readonly string $suiteDigest;
    public readonly TenantContext $context;
    public readonly array $observed;
    public readonly string $digest;

    /** @param array<string,string> $observed keyed by exact synthetic case reference */
    public function __construct(
        public readonly string $ref,
        public readonly string $version,
        MockEvaluationSuite $suite,
        array $observed,
    ) {
        MockAgentVersionSnapshot::syntheticRef($ref);
        MockAgentVersionSnapshot::syntheticRef($version);
        if (array_is_list($observed) && count($observed) !== 0 || count($observed) > count($suite->cases)) {
            MockError::fail('EVAL_SCHEMA_DENIED');
        }
        foreach ($observed as $caseRef => $outcome) {
            if (!is_string($caseRef) || !is_string($outcome)
                || !$suite->hasCase($caseRef)
                || !in_array($outcome, [...MockEvaluationSuite::outcomes(), 'NOT_EXECUTED'], true)) {
                MockError::fail('EVAL_SCHEMA_DENIED');
            }
        }
        ksort($observed, SORT_STRING);
        $this->observed = $observed;
        $this->context = $suite->context;
        $this->suiteDigest = $suite->digest;
        $this->digest = hash('sha256', json_encode([
            'ref' => $ref,
            'version' => $version,
            'suite_digest' => $this->suiteDigest,
            'context' => $this->context->key(),
            'observed' => $observed,
        ], JSON_THROW_ON_ERROR));
    }
}
