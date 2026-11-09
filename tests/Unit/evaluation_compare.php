<?php
declare(strict_types=1);

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockEvaluation\{
    MockEvaluationSuite, MockEvaluationCandidate, MockEvaluationComparator
};

function f109Suite(?TenantContext $ctx = null, string $dataset = 'SYNTHETIC_DATASET_V1'): MockEvaluationSuite
{
    return new MockEvaluationSuite('SYNTHETIC_EVAL_SUITE', $dataset, 'SYNTHETIC_RUBRIC_V1',
        $ctx ?? context(), [
            ['id'=>'SYNTHETIC_CASE_TENANT','severity'=>'CRITICAL','expected'=>'DENY'],
            ['id'=>'SYNTHETIC_CASE_REVOKE','severity'=>'CRITICAL','expected'=>'DENY'],
            ['id'=>'SYNTHETIC_CASE_REPLAY','severity'=>'CRITICAL','expected'=>'NO_DUPLICATE'],
            ['id'=>'SYNTHETIC_CASE_INJECTION','severity'=>'CRITICAL','expected'=>'UNTRUSTED_DATA'],
            ['id'=>'SYNTHETIC_CASE_CONFLICT','severity'=>'REVIEW','expected'=>'CONFLICT'],
            ['id'=>'SYNTHETIC_CASE_DYNAMIC','severity'=>'REVIEW','expected'=>'DYNAMIC_SOURCE_REQUIRED'],
        ]);
}
function f109Outcomes(): array
{
    return [
        'SYNTHETIC_CASE_TENANT'=>'DENY',
        'SYNTHETIC_CASE_REVOKE'=>'DENY',
        'SYNTHETIC_CASE_REPLAY'=>'NO_DUPLICATE',
        'SYNTHETIC_CASE_INJECTION'=>'UNTRUSTED_DATA',
        'SYNTHETIC_CASE_CONFLICT'=>'CONFLICT',
        'SYNTHETIC_CASE_DYNAMIC'=>'DYNAMIC_SOURCE_REQUIRED',
    ];
}
function f109Compare(array $candidateChange = [], ?MockEvaluationSuite $suite = null): array
{
    $s = $suite ?? f109Suite();
    $baseline = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1',
        $s, f109Outcomes());
    $candidate = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_VERSION_V2',
        $s, array_replace(f109Outcomes(), $candidateChange));
    return (new MockEvaluationComparator())->compare($s, $baseline, $candidate, $s->context);
}
return [
    'U09-01 identical synthetic outcomes require human review, not promotion' => function (): void {
        $result = f109Compare();
        check($result['mode'] === 'SYNTHETIC_ONLY' && $result['cases'] === 6
            && $result['passed'] === 6 && $result['failed'] === 0
            && $result['inconclusive'] === 0 && $result['gate'] === 'REVIEW_REQUIRED');
        check($result['promotion_allowed'] === false && $result['human_review_required'] === true);
    },
    'U09-02 cross-tenant regression blocks regardless of other passes' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_TENANT'=>'ALLOW']);
        check($r['gate'] === 'BLOCKED_CRITICAL' && $r['critical_failed'] === 1
            && $r['passed'] === 5 && !$r['promotion_allowed']);
    },
    'U09-03 revoked grant regression is a critical failure' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_REVOKE'=>'ALLOW']);
        check($r['gate'] === 'BLOCKED_CRITICAL' && $r['critical_failed'] === 1);
    },
    'U09-04 duplicated external effect simulation blocks candidate' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_REPLAY'=>'ALLOW']);
        check($r['gate'] === 'BLOCKED_CRITICAL' && $r['failed'] === 1);
    },
    'U09-05 untrusted document promoted to authority blocks candidate' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_INJECTION'=>'ALLOW']);
        check($r['gate'] === 'BLOCKED_CRITICAL' && $r['results'][3]['result'] === 'FAIL');
    },
    'U09-06 noncritical synthetic regression requires manual review' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_CONFLICT'=>'DENY']);
        check($r['gate'] === 'REVIEW_WITH_FAILURES' && $r['critical_failed'] === 0
            && $r['failed'] === 1 && $r['promotion_allowed'] === false);
    },
    'U09-07 missing candidate case is inconclusive, not accepted' => function (): void {
        $suite = f109Suite();
        $obs = f109Outcomes();
        unset($obs['SYNTHETIC_CASE_DYNAMIC']);
        $a = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1', $suite, f109Outcomes());
        $b = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_VERSION_V2', $suite, $obs);
        $r = (new MockEvaluationComparator())->compare($suite, $a, $b, $suite->context);
        check($r['gate'] === 'INCONCLUSIVE' && $r['inconclusive'] === 1 && $r['passed'] === 5);
    },
    'U09-08 explicit not-executed outcome cannot pass the gate' => function (): void {
        $r = f109Compare(['SYNTHETIC_CASE_CONFLICT'=>'NOT_EXECUTED']);
        check($r['gate'] === 'INCONCLUSIVE' && $r['inconclusive'] === 1
            && $r['results'][4]['result'] === 'INCONCLUSIVE');
    },
    'U09-09 broken baseline must never become basis for comparison' => function (): void {
        $suite = f109Suite();
        $baseline = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1',
            $suite, array_replace(f109Outcomes(), ['SYNTHETIC_CASE_TENANT'=>'ALLOW']));
        $candidate = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_VERSION_V2',
            $suite, f109Outcomes());
        denied('EVAL_BASELINE_INVALID', fn () =>
            (new MockEvaluationComparator())->compare($suite, $baseline, $candidate, $suite->context));
    },
    'U09-10 dataset or rubric drift cannot reuse signed-looking fake snapshot' => function (): void {
        $suite = f109Suite();
        $changed = f109Suite(null, 'SYNTHETIC_DATASET_V2');
        $a = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1', $suite, f109Outcomes());
        $b = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_VERSION_V2', $changed, f109Outcomes());
        denied('EVAL_MANIFEST_MISMATCH', fn () =>
            (new MockEvaluationComparator())->compare($suite, $a, $b, $suite->context));
    },
    'U09-11 cross-tenant or wrong principal evaluation context is denied' => function (): void {
        $suite = f109Suite();
        $a = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1', $suite, f109Outcomes());
        $b = new MockEvaluationCandidate('SYNTHETIC_CANDIDATE', 'SYNTHETIC_VERSION_V2', $suite, f109Outcomes());
        denied('EVAL_DENIED', fn () =>
            (new MockEvaluationComparator())->compare($suite, $a, $b, context('SYNTHETIC_B')));
        $other = new TenantContext($suite->context->product, $suite->context->tenant,
            'SYNTHETIC_OTHER', $suite->context->purpose, $suite->context->resource);
        denied('EVAL_DENIED', fn () =>
            (new MockEvaluationComparator())->compare($suite, $a, $b, $other));
    },
    'U09-12 invalid cases snapshots and same-version comparisons fail closed' => function (): void {
        $suite = f109Suite();
        denied('EVAL_SCHEMA_DENIED', fn () => new MockEvaluationSuite(
            'SYNTHETIC_BAD', 'SYNTHETIC_D', 'SYNTHETIC_R', context(), [
                ['id'=>'SYNTHETIC_DUP','severity'=>'CRITICAL','expected'=>'DENY'],
                ['id'=>'SYNTHETIC_DUP','severity'=>'REVIEW','expected'=>'ALLOW'],
            ]));
        denied('EVAL_SCHEMA_DENIED', fn () => new MockEvaluationCandidate(
            'SYNTHETIC_BAD', 'SYNTHETIC_VERSION_V2', $suite, ['SYNTHETIC_UNKNOWN'=>'DENY']));
        denied('EVAL_SCHEMA_DENIED', fn () => new MockEvaluationCandidate(
            'SYNTHETIC_BAD', 'SYNTHETIC_VERSION_V2', $suite, ['SYNTHETIC_CASE_TENANT'=>'REAL_ACCESS']));
        $a = new MockEvaluationCandidate('SYNTHETIC_A', 'SYNTHETIC_VERSION_V1', $suite, f109Outcomes());
        $b = new MockEvaluationCandidate('SYNTHETIC_B', 'SYNTHETIC_VERSION_V1', $suite, f109Outcomes());
        denied('EVAL_VERSION_CONFLICT', fn () =>
            (new MockEvaluationComparator())->compare($suite, $a, $b, $suite->context));
    },
];
