<?php
declare(strict_types=1);

/**
 * F210 — Execute the REAL Agents in-process synthetic facade and verify its
 * actual responses with CRM Core PHP from a SHA-pinned checkout.
 * No HTTP, Laravel, service identity, customer data, persistence or IAM.
 */
use App\Core\Leads\AgentsReadResponseInspector;
use App\Core\Leads\AgentsReadResponseAssessment;
use App\Support\Synthetic\AiaReadOnlyMockBoundary;

$root = dirname(__DIR__);
$crmRoot = $root . '/_synthetic_crm_fixture';
foreach ([
    '/app/Core/Leads/AgentsReadResponseAssessment.php',
    '/app/Core/Leads/AgentsReadResponseInspector.php',
    '/app/Support/Synthetic/AiaReadOnlyMockBoundary.php',
] as $name) {
    if (!is_file($crmRoot . $name)) {
        fwrite(STDERR, "F210_CRM_SOURCE_MISSING NO_GO\n");
        exit(2);
    }
    require_once $crmRoot . $name;
}

function f210Assert(bool $condition): void {
    if (!$condition) throw new RuntimeException('F210_CROSS_CORE_DENIED');
}

function f210NoRights(AgentsReadResponseAssessment $assessment): void {
    f210Assert(!$assessment->canReadRealLead()
        && !$assessment->canUseCustomerData()
        && !$assessment->canGrantAgentAccess()
        && !$assessment->canWriteLead()
        && !$assessment->canExportLead()
        && !$assessment->canEnableRoute());
    // An assessment never serializes the projection or any tenant/lead ref.
    f210Assert(array_keys((array) $assessment) === ['reasonCode']);
}

try {
    // This fixed, argument-free probe constructs MockCrmReadPublicFacade,
    // MockCrmOwnerPolicy and MockTrustGateway (not a hand-built success body).
    ob_start();
    require $root . '/tools/op01_php_public_response_probe.php';
    $json = ob_get_clean();
    f210Assert(is_string($json) && strlen($json) < 16384
        && $json !== '');
    $record = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    f210Assert(is_array($record) && !array_is_list($record)
        && array_keys($record) === [
            'probe', 'real_authorization', 'external_calls', 'cases'
        ]);
    f210Assert($record['probe'] === 'SYNTHETIC_OP01_PUBLIC_RESPONSE_PROBE_V1'
        && $record['real_authorization'] === false
        && $record['external_calls'] === 0);

    $expected = [
        'single_ref' => [['ref'], 'READ_SYNTHETIC_ONLY'],
        'replay' => [['ref'], 'DENIED_OR_NOT_FOUND'],
        'ordered_two_fields' => [['stage', 'ref'], 'READ_SYNTHETIC_ONLY'],
        'ungranted' => [['stage'], 'DENIED_OR_NOT_FOUND'],
        'cross_tenant' => [['ref'], 'DENIED_OR_NOT_FOUND'],
        'unknown_field' => [['email'], 'DENIED_OR_NOT_FOUND'],
    ];
    f210Assert(is_array($record['cases']) && array_is_list($record['cases'])
        && count($record['cases']) === count($expected));
    $inspector = new AgentsReadResponseInspector();
    $denial = [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'projection' => null,
        'real_authorization' => false,
        'external_calls' => 0,
    ];
    $successes = 0;
    $denials = 0;
    foreach ($record['cases'] as $index => $case) {
        $name = array_keys($expected)[$index];
        [$fields, $state] = $expected[$name];
        f210Assert(is_array($case) && !array_is_list($case)
            && array_keys($case) === ['case', 'fields', 'response']
            && $case['case'] === $name && $case['fields'] === $fields);
        $out = $case['response'];
        f210Assert(is_array($out) && !array_is_list($out)
            && array_keys($out) === [
                'contract', 'state', 'projection', 'real_authorization',
                'external_calls'
            ]
            && $out['contract'] === 'SYNTHETIC_CRM_AIA_PUBLIC_V1'
            && $out['state'] === $state
            && $out['real_authorization'] === false
            && $out['external_calls'] === 0);

        if ($state === 'DENIED_OR_NOT_FOUND') {
            ++$denials;
            f210Assert($out === $denial);
        } else {
            ++$successes;
            f210Assert(is_array($out['projection'])
                && !array_is_list($out['projection'])
                && array_keys($out['projection']) === $fields);
            foreach ($out['projection'] as $field => $value) {
                f210Assert($value === (
                    $field === 'ref' ? 'SYNTHETIC_A_LEAD' : 'SYNTHETIC_NEW'
                ));
            }
        }
        $assessment = $inspector->inspect($out, $fields);
        f210Assert($assessment->reasonCode === (
            $state === 'DENIED_OR_NOT_FOUND'
                ? 'SYNTHETIC_DENIAL_ONLY'
                : 'SYNTHETIC_PROJECTION_UNTRUSTED'
        ));
        f210Assert($assessment->isSyntheticFormatConsistent());
        f210NoRights($assessment);

        // Even an actual fictional positive reply must never grant access
        // when supplied as an untrusted request to the CRM mock boundary.
        f210Assert(AiaReadOnlyMockBoundary::inspect($out) === $denial);

        // Recheck actual responses with changed/untrusted requested fields.
        if ($state === 'READ_SYNTHETIC_ONLY') {
            $wrongOrder = array_reverse($fields);
            if ($wrongOrder === $fields) $wrongOrder = ['stage', 'ref'];
            $wrong = $inspector->inspect($out, $wrongOrder);
            f210NoRights($wrong);
            f210Assert($wrong->reasonCode === 'PROJECTION_INVALID');
        }
    }
    f210Assert($successes === 2 && $denials === 4);

    // Adversarial mutations are fed through the ACTUAL CRM Core inspector.
    $good = $record['cases'][2]['response'];  // ordered_two_fields
    $mutations = [
        ['real_authorization' => true],
        ['external_calls' => 1],
        ['contract' => 'UNREVIEWED_V2'],
        ['state' => 'ALLOWED'],
        ['projection' => null],
        ['projection' => 'SYNTHETIC_FAKE'],
        ['projection' => ['stage' => 'SYNTHETIC_NEW',
                          'ref' => 'name@example.test']],
        ['projection' => ['stage' => 'SYNTHETIC_NEW',
                          'ref' => 'SYNTHETIC_A_LEAD', 'email' => 'SYNTHETIC_FAKE']],
        ['projection' => ['ref' => 'SYNTHETIC_A_LEAD',
                          'stage' => 'SYNTHETIC_NEW']],
        ['grant' => true],
    ];
    foreach ($mutations as $patch) {
        $altered = array_replace($good, $patch);
        $assessment = $inspector->inspect($altered, ['stage', 'ref']);
        f210NoRights($assessment);
        f210Assert(!$assessment->isSyntheticFormatConsistent());
    }
    foreach ([
        'SYNTHETIC_EVID10_REQUEST', 'SYNTHETIC_EVID10_NONCE',
        'SYNTHETIC_EVID10_OWNER', 'SYNTHETIC_PRINCIPAL',
        'access_token', 'Bearer ', 'stack_trace'
    ] as $marker) {
        f210Assert(!str_contains($json, $marker));
    }
    echo 'F210_REAL_FACADE_CRM_CORE_PASS cases=6 mock_reads=2 denials=4 ' .
         'mutations=10 operational_decision=NO_GO real_authorization=false ' .
         'external_calls=0' . PHP_EOL;
} catch (Throwable) {
    if (ob_get_level() > 0) ob_end_clean();
    fwrite(STDERR, "F210_REAL_FACADE_CRM_CORE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
