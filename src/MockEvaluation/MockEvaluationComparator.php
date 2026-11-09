<?php
declare(strict_types=1);

namespace ImagineAI\MockEvaluation;

use ImagineAI\Domain\{MockError, TenantContext};

/**
 * Fail-closed A/B report on fixed synthetic observations.
 * Never promotes a version or claims production/semantic approval.
 */
final class MockEvaluationComparator
{
    public function compare(
        MockEvaluationSuite $suite,
        MockEvaluationCandidate $baseline,
        MockEvaluationCandidate $candidate,
        TenantContext $context,
    ): array {
        if ($suite->context->key() !== $context->key()
            || $baseline->context->key() !== $context->key()
            || $candidate->context->key() !== $context->key()) {
            MockError::fail('EVAL_DENIED');
        }
        if ($baseline->suiteDigest !== $suite->digest || $candidate->suiteDigest !== $suite->digest) {
            MockError::fail('EVAL_MANIFEST_MISMATCH');
        }
        if ($baseline->ref === $candidate->ref || $baseline->version === $candidate->version) {
            MockError::fail('EVAL_VERSION_CONFLICT');
        }

        // A comparison against a broken or incomplete baseline is misleading.
        foreach ($suite->cases as $case) {
            if (($baseline->observed[$case['id']] ?? null) !== $case['expected']) {
                MockError::fail('EVAL_BASELINE_INVALID');
            }
        }

        $pass = 0;
        $fail = 0;
        $critical = 0;
        $inconclusive = 0;
        $results = [];
        foreach ($suite->cases as $case) {
            $actual = $candidate->observed[$case['id']] ?? 'NOT_EXECUTED';
            $status = $actual === 'NOT_EXECUTED'
                ? 'INCONCLUSIVE'
                : ($actual === $case['expected'] ? 'PASS' : 'FAIL');
            if ($status === 'PASS') ++$pass;
            if ($status === 'FAIL') {
                ++$fail;
                if ($case['severity'] === 'CRITICAL') ++$critical;
            }
            if ($status === 'INCONCLUSIVE') ++$inconclusive;
            $results[] = [
                'case_ref' => $case['id'],
                'severity' => $case['severity'],
                'baseline' => $case['expected'],
                'candidate' => $actual,
                'result' => $status,
            ];
        }
        $gate = $critical > 0 ? 'BLOCKED_CRITICAL'
            : ($inconclusive > 0 ? 'INCONCLUSIVE'
                : ($fail > 0 ? 'REVIEW_WITH_FAILURES' : 'REVIEW_REQUIRED'));
        return [
            'mode' => 'SYNTHETIC_ONLY',
            'suite_ref' => $suite->ref,
            'suite_digest' => $suite->digest,
            'baseline_ref' => $baseline->ref,
            'baseline_digest' => $baseline->digest,
            'candidate_ref' => $candidate->ref,
            'candidate_digest' => $candidate->digest,
            'cases' => count($suite->cases),
            'passed' => $pass,
            'failed' => $fail,
            'critical_failed' => $critical,
            'inconclusive' => $inconclusive,
            'gate' => $gate,
            'human_review_required' => true,
            'promotion_allowed' => false,
            'results' => $results,
        ];
    }
}
