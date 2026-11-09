<?php
declare(strict_types=1);

use ImagineAI\MockBridge\MockCrmReadRequest;

/**
 * Consumer-side contract fixtures for a FAKE OP-01 request.
 * No CRM API, authorization, credentials, database or network.
 */
$fixturePath = dirname(__DIR__, 2) . '/contracts/fixtures/f115-crm-op01-conformance.synthetic.json';
$source = file_get_contents($fixturePath);
if ($source === false) throw new RuntimeException('F115 fixtures missing');
$suite = json_decode($source, true, 32, JSON_THROW_ON_ERROR);
if (!is_array($suite)
    || array_keys($suite) !== ['suite', 'contract', 'description', 'cases']
    || $suite['suite'] !== 'SYNTHETIC_F115_CONFORMANCE_V1'
    || $suite['contract'] !== 'SYNTHETIC_CRM_AIA_READ_V1'
    || !is_array($suite['cases']) || !array_is_list($suite['cases'])
    || count($suite['cases']) !== 12) {
    throw new RuntimeException('F115 fixtures incompatible');
}
$tests = [];
$denials = 0;
foreach ($suite['cases'] as $case) {
    if (!is_array($case) || !isset($case['id'], $case['title'], $case['patch'], $case['expect'])
        || !is_string($case['id']) || !preg_match('/^U15-(0[1-9]|1[0-2])$/D', $case['id'])
        || !is_string($case['title']) || !is_array($case['patch'])
        || !is_string($case['expect'])
        || !in_array($case['expect'], ['BRIDGE_SCHEMA_DENIED', 'BRIDGE_CONTEXT_DENIED',
            'READ_SYNTHETIC_ONLY'], true)) {
        throw new RuntimeException('F115 fixture invalid');
    }
    if ($case['expect'] !== 'READ_SYNTHETIC_ONLY') ++$denials;
    $name = $case['id'] . ' ' . $case['title'];
    if (isset($tests[$name])) throw new RuntimeException('F115 duplicate test');
    $tests[$name] = function () use ($case): void {
        [$boundary, $envelope, $owner, $context] = f112Fixture();
        $wire = f112Wire($context);
        foreach ($case['patch'] as $key => $value) {
            $wire[$key] = $value;
        }
        $call = static fn () => $boundary->evaluate(
            new MockCrmReadRequest($wire), $envelope, $owner, $context, 15);
        if ($case['expect'] !== 'READ_SYNTHETIC_ONLY') {
            denied($case['expect'], $call);
            return;
        }
        if (!isset($case['expected_fields']) || !is_array($case['expected_fields'])) {
            throw new RuntimeException('F115 expected projection missing');
        }
        $out = $call();
        check($out['state'] === 'READ_SYNTHETIC_ONLY'
            && $out['real_authorization'] === false && $out['external_calls'] === 0);
        check(array_keys($out['projection']) === $case['expected_fields']);
        foreach ($out['projection'] as $value) {
            check(is_string($value) && str_starts_with($value, 'SYNTHETIC_'));
        }
    };
}
if ($denials !== 10 || count($tests) !== 12) {
    throw new RuntimeException('F115 unexpected coverage');
}
return $tests;
