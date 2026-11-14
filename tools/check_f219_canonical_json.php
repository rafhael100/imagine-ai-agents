<?php
declare(strict_types=1);

/** F219: actual PHP edge, trust and audit; strictly synthetic local bytes. */
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmReadPublicFacade, MockCrmReadRequest, MockCrmReadRequestBuilder, MockCrmOwnerPolicy};
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary, MockCrmReadEdgeCounters};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTrust\{MockTrustGateway, MockTrustEnvelope, MockOwnerScopeEvidence};
use ImagineAI\Mocks\MockCrmReader;

spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f219Check(bool $ok): void
{
    if (!$ok) throw new RuntimeException('F219_DENIED');
}
function f219Proof(TenantContext $ctx, string $label): array
{
    $env = new MockTrustEnvelope(
        'SYNTHETIC_F219_ENV_' . $label, 'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', $ctx, 'SYNTHETIC_F219_NONCE_' . $label,
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    return [$env, new MockOwnerScopeEvidence(
        'SYNTHETIC_F219_OWNER_' . $label, $env->digest,
        $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true
    )];
}
function f219Fixture(string $label): array
{
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
    );
    [$env, $owner] = f219Proof($ctx, $label);
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref', 'stage']);
    $counter = new MockCrmReadEdgeCounters();
    $audit = new MockCrmReadAuditTrail();
    $facade = new MockCrmReadPublicFacade(
        new MockTrustGateway(
            'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']
        ),
        new MockCrmReader(), $policy, $audit
    );
    $edge = new MockCrmReadEdgeBoundary($facade, $counter);
    $wire = MockCrmReadRequestBuilder::fromContext(
        $ctx, 'SYNTHETIC_F219_REQUEST', ['ref']
    );
    return [$edge, $counter, $audit, $ctx, $env, $owner, $wire];
}
function f219Denial(): array
{
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND', 'projection' => null,
        'real_authorization' => false, 'external_calls' => 0,
    ];
}
try {
    [$edge, $counter, $audit, $ctx, $env, $owner, $wire] =
        f219Fixture('BASE');
    $raw = MockCrmReadRequest::encodeCanonicalJson($wire);
    f219Check(MockCrmReadRequest::decodeCanonicalJson($raw) === $wire);
    f219Check($edge->handleCanonicalJson(
        $raw, $env, $owner, $ctx, 15
    )['state'] === 'READ_SYNTHETIC_ONLY');
    f219Check($edge->recheck($wire, $env, $owner, $ctx, 16));

    $extra = $wire; $extra['is_admin'] = true;
    $repeat = $wire; $repeat['fields'] = ['ref', 'ref'];
    $personal = $wire; $personal['fields'] = ['email'];
    $wrongContract = $wire; $wrongContract['contract'] = 'SYNTHETIC_V2';
    $cases = [
        'DUPLICATE' => substr($raw, 0, -1) . ',"tenant_ref":"SYNTHETIC_A"}',
        'WHITESPACE' => str_replace(',"request_ref"', ', "request_ref"', $raw),
        'ESCAPED' => str_replace('SYNTHETIC_CRM_AIA_READ_V1',
                                '\u0053YNTHETIC_CRM_AIA_READ_V1', $raw),
        'TRAILING' => $raw . '{}',
        'REORDER' => json_encode(array_reverse($wire, true), JSON_THROW_ON_ERROR),
        'UNKNOWNKEY' => json_encode($extra, JSON_THROW_ON_ERROR),
        'DUPFIELD' => json_encode($repeat, JSON_THROW_ON_ERROR),
        'PERSONAL' => json_encode($personal, JSON_THROW_ON_ERROR),
        'BADVERSION' => json_encode($wrongContract, JSON_THROW_ON_ERROR),
        'OVERSIZE' => str_repeat('X', 2048),
        'INVALIDUTF8' => $raw . "\xFF",
        'NULLROOT' => 'null',
        'ARRAYROOT' => '[]',
        'DEEP' => '{"x":' . str_repeat('[', 8) . 'null'
            . str_repeat(']', 8) . '}',
        'EMPTY' => '',
    ];
    $negative = 0;
    foreach ($cases as $label => $bad) {
        [$edge, $counter, $audit, $ctx, $env, $owner, $wire] =
            f219Fixture($label);
        $good = MockCrmReadRequest::encodeCanonicalJson($wire);
        f219Check($edge->handleCanonicalJson(
            $good, $env, $owner, $ctx, 15
        )['state'] === 'READ_SYNTHETIC_ONLY');
        f219Check($edge->recheck($wire, $env, $owner, $ctx, 16));
        f219Check($edge->handleCanonicalJson(
            $bad, $env, $owner, $ctx, 16
        ) === f219Denial());
        f219Check(!$edge->recheck($wire, $env, $owner, $ctx, 17));
        f219Check($audit->count() === 1);
        f219Check($counter->snapshot()['attempts'] === 2
            && $counter->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);
        [$nextEnv, $nextOwner] = f219Proof($ctx, $label . '_FRESH');
        f219Check($edge->handleCanonicalJson(
            $good, $nextEnv, $nextOwner, $ctx, 17
        )['state'] === 'READ_SYNTHETIC_ONLY');
        f219Check($edge->recheck($wire, $nextEnv, $nextOwner, $ctx, 18));
        f219Check($audit->count() === 2);
        ++$negative;
    }
    [$edge, $counter, $audit, $ctx, $env, $owner, $wire] =
        f219Fixture('ORDERED');
    $wire['fields'] = ['stage', 'ref'];
    $result = $edge->handleCanonicalJson(
        MockCrmReadRequest::encodeCanonicalJson($wire),
        $env, $owner, $ctx, 15
    );
    f219Check($result['state'] === 'READ_SYNTHETIC_ONLY');
    f219Check($result['projection'] === [
        'stage' => 'SYNTHETIC_NEW', 'ref' => 'SYNTHETIC_A_LEAD',
    ]);
    f219Check($counter->snapshot()['attempts'] === 1
        && $audit->count() === 1);
    echo 'F219_CANONICAL_JSON_PASS negatives=' . $negative .
        ' positives=2 operational_decision=NO_GO real_authorization=false' .
        ' external_calls=0' . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "F219_CANONICAL_JSON_DENIED operational_decision=NO_GO\n");
    exit(1);
}
