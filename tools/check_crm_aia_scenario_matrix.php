<?php
declare(strict_types=1);

/**
 * F209: cross-source synthetic *rehearsal*, NOT bilateral CRM acceptance.
 * All 13 operational scenarios remain NAO_EXECUTADO and pilot remains NO_GO.
 */
use App\Core\Leads\AgentsReadResponseInspector;
use App\Support\Synthetic\AiaReadOnlyMockBoundary;
use ImagineAI\Domain\MockError;
use ImagineAI\MockBridge\MockCrmPublicResponseContract;
use ImagineAI\MockBridge\MockCrmReadRequest;

$root = dirname(__DIR__);
$crm = $root . '/_synthetic_crm_fixture';
foreach ([
    '/app/Support/Synthetic/AiaReadOnlyMockBoundary.php',
    '/app/Core/Leads/AgentsReadResponseAssessment.php',
    '/app/Core/Leads/AgentsReadResponseInspector.php',
] as $path) {
    if (!is_file($crm . $path)) {
        fwrite(STDERR, "F209_SOURCE_MISSING NO_GO\n");
        exit(2);
    }
    require_once $crm . $path;
}
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f209Request(): array {
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F209_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'principal_ref' => 'SYNTHETIC_USER_A',
        'resource_ref' => 'SYNTHETIC_LEAD_A',
        'purpose_ref' => 'SYNTHETIC_TEST',
        'fields' => ['ref', 'stage'],
    ];
}
function f209Assert(bool $condition): void {
    if (!$condition) throw new RuntimeException('F209_MATRIX_INVARIANT_DENIED');
}
function f209AgentsShape(array $request): bool {
    try {
        new MockCrmReadRequest($request);
        return true;
    } catch (MockError) {
        return false;
    }
}
try {
    $packet = json_decode(
        (string) file_get_contents($root .
            '/docs/evidencias/op01-pacote-aceite-bilateral.json'),
        true, 48, JSON_THROW_ON_ERROR
    );
    f209Assert(is_array($packet)
        && ($packet['decisao_piloto'] ?? null) === 'NO_GO'
        && ($packet['integracao_autorizada'] ?? null) === false
        && ($packet['api_crm_verificada'] ?? null) === false
        && ($packet['contagens']['cenarios_nao_executados'] ?? null) === 13);
    $packetCases = [];
    foreach ($packet['decisoes'] as $decision) {
        f209Assert(($decision['aprovado'] ?? null) === false);
        foreach ($decision['cenarios_homologacao'] as $case) {
            f209Assert(($case['estado'] ?? null) === 'NAO_EXECUTADO');
            $packetCases[$case['id']] = true;
        }
    }
    $expectedIds = array_merge(
        array_map(static fn (int $n): string => sprintf('OP01-N%02d', $n),
                  range(1, 11)),
        ['OP01-P01', 'OP01-P02']
    );
    $found = array_keys($packetCases);
    sort($found, SORT_STRING);
    $sorted = $expectedIds;
    sort($sorted, SORT_STRING);
    f209Assert($found === $sorted);

    $base = f209Request();
    $cases = [
        // The labels are governance test scenarios, NOT real test executions.
        ['OP01-N01', ['request_ref' => 'SYNTHETIC_'], false],
        ['OP01-N02', ['principal_ref' => 'UNTRUSTED'], false],
        ['OP01-N03', ['tenant_ref' => 'SYNTHETIC_TENANT_B'], true],
        ['OP01-N04', ['resource_ref' => 'SYNTHETIC_FOREIGN_LEAD'], true],
        ['OP01-N05', ['fields' => ['email']], false],
        ['OP01-N06', ['operation' => 'crm.lead.write'], false],
        ['OP01-N07', [], true], // No distributed replay tested.
        ['OP01-N08', [], true], // No real revocation-in-queue tested.
        ['OP01-N09', [], true], // No real CRM outage tested.
        ['OP01-N10', ['telemetry_payload' => 'SYNTHETIC_NOISE'], false],
        ['OP01-N11', [], true], // No real telemetry failure tested.
        ['OP01-P01', [], true], // Syntactically positive, NEVER authorized.
        ['OP01-P02', ['fields' => ['stage', 'ref']], true],
    ];
    f209Assert(count($cases) === 13);
    $reply = MockCrmPublicResponseContract::denial();
    $inspector = new AgentsReadResponseInspector();
    $publicRows = [];
    foreach ($cases as [$id, $changes, $shape]) {
        f209Assert(isset($packetCases[$id]));
        $wire = array_replace($base, $changes);
        f209Assert(f209AgentsShape($wire) === $shape);
        f209Assert(AiaReadOnlyMockBoundary::hasFixtureShape($wire) === $shape);
        $crmResponse = AiaReadOnlyMockBoundary::inspect($wire);
        f209Assert($crmResponse === $reply);
        $assessment = $inspector->inspect($crmResponse, ['ref', 'stage']);
        f209Assert($assessment->reasonCode === 'SYNTHETIC_DENIAL_ONLY'
            && !$assessment->canReadRealLead()
            && !$assessment->canGrantAgentAccess()
            && !$assessment->canUseCustomerData()
            && !$assessment->canWriteLead()
            && !$assessment->canExportLead()
            && !$assessment->canEnableRoute());
        if ($id === 'OP01-N07') {
            f209Assert(AiaReadOnlyMockBoundary::inspect($wire) === $crmResponse);
        }
        $publicRows[] = [
            'id' => $id,
            'synthetic_request_shape_valid' => $shape,
            'mock_response' => 'DENIED_OR_NOT_FOUND',
            'operational_status' => 'NAO_EXECUTADO',
        ];
    }
    echo json_encode([
        'contract' => 'SYNTHETIC_CRM_AIA_OP01_MATRIX_V1',
        'scope' => 'TWO_REPOSITORY_SOURCE_CODE_ONLY',
        'cases' => $publicRows,
        'cases_real_executed' => 0,
        'operational_decision' => 'NO_GO',
        'real_authorization' => false,
        'external_calls' => 0,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "F209_MATRIX_DENIED operational_decision=NO_GO\n");
    exit(1);
}
