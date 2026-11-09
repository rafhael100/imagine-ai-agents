<?php
declare(strict_types=1);

/** Offline synthetic A/B report: php examples/f109-evaluation-demo.php */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (!str_starts_with($class, $prefix)) return;
    require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockEvaluation\{
    MockEvaluationSuite, MockEvaluationCandidate, MockEvaluationComparator
};
$context = new TenantContext(
    'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_REVIEWER',
    'SYNTHETIC_EVALUATION', 'SYNTHETIC_AGENT');
$cases = [
    ['id'=>'SYNTHETIC_CASE_TENANT','severity'=>'CRITICAL','expected'=>'DENY'],
    ['id'=>'SYNTHETIC_CASE_REVOKE','severity'=>'CRITICAL','expected'=>'DENY'],
    ['id'=>'SYNTHETIC_CASE_REPLAY','severity'=>'CRITICAL','expected'=>'NO_DUPLICATE'],
    ['id'=>'SYNTHETIC_CASE_INJECTION','severity'=>'CRITICAL','expected'=>'UNTRUSTED_DATA'],
    ['id'=>'SYNTHETIC_CASE_CONFLICT','severity'=>'REVIEW','expected'=>'CONFLICT'],
    ['id'=>'SYNTHETIC_CASE_DYNAMIC','severity'=>'REVIEW','expected'=>'DYNAMIC_SOURCE_REQUIRED'],
];
$suite = new MockEvaluationSuite(
    'SYNTHETIC_EVAL_SUITE', 'SYNTHETIC_DATASET_V1',
    'SYNTHETIC_RUBRIC_V1', $context, $cases);
$expected = [];
foreach ($suite->cases as $case) $expected[$case['id']] = $case['expected'];
$baseline = new MockEvaluationCandidate('SYNTHETIC_BASELINE', 'SYNTHETIC_VERSION_V1',
    $suite, $expected);
$variants = [
    'safe' => [],
    'critical_regression' => ['SYNTHETIC_CASE_TENANT'=>'ALLOW', 'SYNTHETIC_CASE_REVOKE'=>'ALLOW'],
    'incomplete' => ['SYNTHETIC_CASE_DYNAMIC'=>'NOT_EXECUTED'],
];
$comparator = new MockEvaluationComparator();
$reports = [];
foreach ($variants as $name => $changes) {
    $candidate = new MockEvaluationCandidate('SYNTHETIC_' . strtoupper($name),
        'SYNTHETIC_VERSION_' . strtoupper($name), $suite, array_replace($expected, $changes));
    $result = $comparator->compare($suite, $baseline, $candidate, $context);
    $reports[$name] = [
        'gate' => $result['gate'], 'passed' => $result['passed'],
        'critical_failed' => $result['critical_failed'],
        'inconclusive' => $result['inconclusive'],
        'promotion_allowed' => $result['promotion_allowed'],
    ];
}
if ($reports['safe']['gate'] !== 'REVIEW_REQUIRED'
    || $reports['critical_regression']['gate'] !== 'BLOCKED_CRITICAL'
    || $reports['incomplete']['gate'] !== 'INCONCLUSIVE') {
    throw new RuntimeException('Unexpected synthetic evaluation gate');
}
echo json_encode(['mode'=>'SYNTHETIC_OFFLINE', 'suite_ref'=>$suite->ref, 'reports'=>$reports,
    'external_calls'=>0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
