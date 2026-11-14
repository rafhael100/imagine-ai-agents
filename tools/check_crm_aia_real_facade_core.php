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

        // F220: prove the actual Agents facade response remains safe after
        // byte-exact JSON serialization and deserialization. CRM Core still
        // refuses all operational rights; no API is contacted or enabled.
        $syntheticContext = new \ImagineAI\Domain\TenantContext(
            'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
            'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
        );
        $safeRequestedFields = $state === 'READ_SYNTHETIC_ONLY'
            ? $fields : ['ref'];
        $syntheticWire = \ImagineAI\MockBridge\MockCrmReadRequestBuilder::fromContext(
            $syntheticContext, 'SYNTHETIC_F220_INSPECTION',
            $safeRequestedFields
        );
        $syntheticRequest = new \ImagineAI\MockBridge\MockCrmReadRequest(
            $syntheticWire
        );
        $encoded = \ImagineAI\MockBridge\MockCrmPublicResponseContract::encodeCanonicalJson(
            $out, $syntheticRequest, $syntheticContext
        );
        f210Assert(is_string($encoded) && strlen($encoded) <= 512);
        $decoded = \ImagineAI\MockBridge\MockCrmPublicResponseContract::decodeCanonicalJson(
            $encoded, $syntheticRequest, $syntheticContext
        );
        f210Assert($decoded === $out);
        $roundtripAssessment = $inspector->inspect($decoded, $fields);
        f210Assert($roundtripAssessment->reasonCode === $assessment->reasonCode);
        f210NoRights($roundtripAssessment);

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
    // F221: one complete in-process request/response byte exchange through
    // the actual Agents synthetic read edge. The REAL pinned CRM inspector
    // must still deny operational rights for a well-formed mock success.
    $f221Context = new \ImagineAI\Domain\TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
    );
    $f221Policy = new \ImagineAI\MockBridge\MockCrmOwnerPolicy();
    $f221Policy->grantFixture($f221Context, ['ref']);
    $f221Audit = new \ImagineAI\MockAudit\MockCrmReadAuditTrail();
    $f221Counters = new \ImagineAI\MockTelemetry\MockCrmReadEdgeCounters();
    $f221Edge = new \ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary(
        new \ImagineAI\MockBridge\MockCrmReadPublicFacade(
            new \ImagineAI\MockTrust\MockTrustGateway(
                'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
                ['SYNTHETIC_LEAD_READ']
            ),
            new \ImagineAI\Mocks\MockCrmReader(), $f221Policy, $f221Audit
        ), $f221Counters
    );
    $f221Wire = \ImagineAI\MockBridge\MockCrmReadRequestBuilder::fromContext(
        $f221Context, 'SYNTHETIC_F221_CRM_REQUEST', ['ref']
    );
    $f221Payload = \ImagineAI\MockBridge\MockCrmReadRequest::encodeCanonicalJson(
        $f221Wire
    );
    $f221Envelope = new \ImagineAI\MockTrust\MockTrustEnvelope(
        'SYNTHETIC_F221_CRM_ENV', 'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', $f221Context, 'SYNTHETIC_F221_CRM_NONCE',
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    $f221Owner = new \ImagineAI\MockTrust\MockOwnerScopeEvidence(
        'SYNTHETIC_F221_CRM_OWNER', $f221Envelope->digest,
        $f221Context, 'SYNTHETIC_LEAD_READ', 10, 40, true
    );
    $f221Request = new \ImagineAI\MockBridge\MockCrmReadRequest($f221Wire);
    $f221ReplyBytes = $f221Edge->exchangeCanonicalJson(
        $f221Payload, $f221Envelope, $f221Owner, $f221Context, 15
    );
    $f221Reply = \ImagineAI\MockBridge\MockCrmPublicResponseContract::decodeCanonicalJson(
        $f221ReplyBytes, $f221Request, $f221Context
    );
    f210Assert(is_array($f221Reply)
        && $f221Reply['state'] === 'READ_SYNTHETIC_ONLY');
    $f221Review = $inspector->inspect($f221Reply, ['ref']);
    f210Assert($f221Review->reasonCode === 'SYNTHETIC_PROJECTION_UNTRUSTED');
    f210NoRights($f221Review);
    f210Assert($f221Edge->exchangeCanonicalJson(
        $f221Payload, $f221Envelope, $f221Owner, $f221Context, 16
    ) === json_encode($denial, JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $f221Denied = $inspector->inspect($denial, ['ref']);
    f210Assert($f221Denied->reasonCode === 'SYNTHETIC_DENIAL_ONLY');
    f210NoRights($f221Denied);
    f210Assert($f221Counters->snapshot()['attempts'] === 2
        && $f221Audit->count() === 2);


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
