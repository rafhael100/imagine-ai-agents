<?php
declare(strict_types=1);

/**
 * F208 - Reciprocal OP-01 syntax/denial witness against a PINNED CRM source.
 * This fixture executes only pure PHP from two repos; no HTTP, Laravel boot,
 * SQL, credentials, customer records, authentication or operational grant.
 * Run only after verifying the CRM checkout SHA in the CI workflow.
 */
use App\Core\Leads\AgentsReadResponseAssessment;
use App\Core\Leads\AgentsReadResponseInspector;
use App\Support\Synthetic\AiaReadOnlyMockBoundary;
use ImagineAI\Domain\MockError;
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\MockCrmPublicResponseContract;
use ImagineAI\MockBridge\MockCrmReadRequest;

$root = dirname(__DIR__);
$crm = $root . '/_synthetic_crm_fixture';
foreach ([
    '/app/Support/Synthetic/AiaReadOnlyMockBoundary.php',
    '/app/Core/Leads/AgentsReadResponseAssessment.php',
    '/app/Core/Leads/AgentsReadResponseInspector.php',
] as $file) {
    if (!is_file($crm . $file)) {
        fwrite(STDERR, "F208_CRM_SOURCE_MISSING NO_GO\n");
        exit(2);
    }
    require_once $crm . $file;
}
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

function f208Check(bool $ok): void {
    if (!$ok) throw new RuntimeException('F208_PARITY_DENIED');
}
function f208Wire(): array {
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F208_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'principal_ref' => 'SYNTHETIC_USER_A',
        'resource_ref' => 'SYNTHETIC_LEAD_A',
        'purpose_ref' => 'SYNTHETIC_TEST',
        'fields' => ['ref', 'stage'],
    ];
}
function f208AgentShape(array $wire): bool {
    try {
        new MockCrmReadRequest($wire);
        return true;
    } catch (MockError) {
        return false;
    }
}
function f208NoRights(AgentsReadResponseAssessment $assessment): void {
    f208Check(!$assessment->canReadRealLead());
    f208Check(!$assessment->canUseCustomerData());
    f208Check(!$assessment->canGrantAgentAccess());
    f208Check(!$assessment->canWriteLead());
    f208Check(!$assessment->canExportLead());
    f208Check(!$assessment->canEnableRoute());
}

try {
    $inspector = new AgentsReadResponseInspector();
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_TENANT_A', 'SYNTHETIC_USER_A',
        'SYNTHETIC_TEST', 'SYNTHETIC_LEAD_A'
    );
    $denied = MockCrmPublicResponseContract::denial();
    f208Check($denied === AiaReadOnlyMockBoundary::inspect(f208Wire()));
    f208Check(array_keys($denied) === [
        'contract', 'state', 'projection', 'real_authorization', 'external_calls'
    ]);
    f208Check($denied['real_authorization'] === false
        && $denied['external_calls'] === 0
        && $denied['projection'] === null);
    $cases = [];
    $cases[] = [f208Wire(), true];
    foreach ([['ref'], ['stage'], ['stage', 'ref']] as $fields) {
        $wire = f208Wire();
        $wire['fields'] = $fields;
        $cases[] = [$wire, true];
    }
    $wire = f208Wire();
    $wire['tenant_ref'] = 'SYNTHETIC_TENANT_B';
    $cases[] = [$wire, true]; // Syntax is NOT tenant authorization.
    foreach ([
        ['contract' => 'SYNTHETIC_CRM_AIA_READ_V2'],
        ['operation' => 'crm.lead.write'],
        ['principal_ref' => 'someone@example.test'],
        ['resource_ref' => 99],
        ['purpose_ref' => 'SYNTHETIC_'],
        ['fields' => []],
        ['fields' => ['ref', 'ref']],
        ['fields' => ['email']],
        ['fields' => [['ref']]],
        ['fields' => ['ref', 'stage', 'email']],
        ['fields' => ['ref' => 'stage']],
        ['fields' => true],
        ['authorization' => 'ALLOW'],
        ['role' => 'admin'],
        ['allowed' => true],
        ['signature' => 'SYNTHETIC_FAKE'],
    ] as $change) {
        $cases[] = [array_replace(f208Wire(), $change), false];
    }
    $wide = f208Wire();
    for ($i = 0; $i < 512; ++$i) $wide['injected_' . $i] = 'SYNTHETIC_NOISE';
    $cases[] = [$wide, false];
    $cases[] = [array_replace(f208Wire(), ['request_ref' =>
        'SYNTHETIC_' . str_repeat('A', 81)]), false];
    foreach (array_keys(f208Wire()) as $missing) {
        $wire = f208Wire();
        unset($wire[$missing]);
        $cases[] = [$wire, false];
    }

    foreach ($cases as [$wire, $expected]) {
        f208Check(AiaReadOnlyMockBoundary::hasFixtureShape($wire) === $expected);
        f208Check(f208AgentShape($wire) === $expected);
        $actual = AiaReadOnlyMockBoundary::inspect($wire);
        f208Check($actual === $denied);
        $assessment = $inspector->inspect($actual, ['ref', 'stage']);
        f208Check($assessment->reasonCode === 'SYNTHETIC_DENIAL_ONLY');
        f208NoRights($assessment);
        $canonical = new MockCrmReadRequest(f208Wire());
        f208Check(MockCrmPublicResponseContract::isSafe($actual, $canonical, $ctx));
        $encoded = json_encode($actual, JSON_THROW_ON_ERROR);
        foreach (['SYNTHETIC_USER_A', 'SYNTHETIC_LEAD_A',
                  'SYNTHETIC_TENANT_A'] as $secretMarker) {
            f208Check(!str_contains($encoded, $secretMarker));
        }
    }

    // The owner must deny a syntactically correct *other tenant* too.
    $other = f208Wire();
    $other['tenant_ref'] = 'SYNTHETIC_TENANT_B';
    $otherRequest = new MockCrmReadRequest($other);
    $contextDenied = false;
    try {
        $otherRequest->assertMatches($ctx);
    } catch (MockError) {
        $contextDenied = true;
    }
    f208Check($contextDenied);
    f208Check(AiaReadOnlyMockBoundary::inspect($other) === $denied);

    // A fictional successful response is parseable, but is NEVER authority.
    $fictionalSuccess = [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'READ_SYNTHETIC_ONLY',
        'projection' => ['ref' => 'SYNTHETIC_LEAD_A',
                         'stage' => 'SYNTHETIC_NEW'],
        'real_authorization' => false,
        'external_calls' => 0,
    ];
    $request = new MockCrmReadRequest(f208Wire());
    f208Check(MockCrmPublicResponseContract::isAcceptedRead(
        $fictionalSuccess, $request, $ctx
    ));
    $accepted = $inspector->inspect($fictionalSuccess, ['ref', 'stage']);
    f208Check($accepted->reasonCode === 'SYNTHETIC_PROJECTION_UNTRUSTED');
    f208NoRights($accepted);

    foreach ([
        ['real_authorization' => true],
        ['external_calls' => 1],
        ['projection' => ['ref' => 'someone@example.test',
                          'stage' => 'SYNTHETIC_NEW']],
        ['projection' => ['ref' => 'SYNTHETIC_LEAD_A',
                          'email' => 'synthetic@example.test']],
        ['projection' => null],
        ['state' => 'ALLOW'],
        ['api_token' => 'SYNTHETIC_FAKE'],
    ] as $mutation) {
        $bad = array_replace($fictionalSuccess, $mutation);
        f208Check(!MockCrmPublicResponseContract::isAcceptedRead($bad, $request, $ctx));
        f208NoRights($inspector->inspect($bad, ['ref', 'stage']));
    }
    echo 'F208_CROSS_SOURCE_PASS cases=' . count($cases) .
        ' operational_decision=NO_GO real_authorization=false external_calls=0' . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "F208_CROSS_SOURCE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
