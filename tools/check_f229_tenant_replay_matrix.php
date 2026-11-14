<?php
declare(strict_types=1);

/**
 * F229: real Agents fixture issuer -> pinned CRM PHP verifier.
 * Three independent synthetic tenants, mutation and replay tests.
 * No HTTP, credentials, database, live business data or authorization.
 */
use App\Support\Synthetic\{AiaHmacFixturePreflight, AiaFixtureNonceLedger, AiaReadOnlyMockBoundary};
use ImagineAI\MockBridge\MockCrmReadRequest;

$root = dirname(__DIR__);
$crm = $root.'/_synthetic_crm_fixture/app/Support/Synthetic';
foreach (['AiaReadOnlyMockBoundary.php', 'AiaFixtureNonceLedger.php',
          'AiaHmacFixturePreflight.php'] as $file) {
    if (!is_file($crm.'/'.$file)) {
        fwrite(STDERR, "F229_PINNED_CRM_UNAVAILABLE operational_decision=NO_GO\n");
        exit(2);
    }
    require_once $crm.'/'.$file;
}
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});

function f229Check(bool $ok): void {
    if (!$ok) {
        throw new RuntimeException('F229_ASSERTION_DENIED');
    }
}
function f229Wire(string $tenant): array {
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F229_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $tenant,
        'principal_ref' => 'SYNTHETIC_F229_USER',
        'resource_ref' => 'SYNTHETIC_F229_LEAD',
        'purpose_ref' => 'SYNTHETIC_F229_TEST',
        'fields' => ['ref', 'stage'],
    ];
}
function f229PublicDeny(array $out): bool {
    return $out === [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'projection' => null,
        'real_authorization' => false,
        'external_calls' => 0,
    ];
}

try {
    $tenants = ['SYNTHETIC_TENANT_A', 'SYNTHETIC_TENANT_B', 'SYNTHETIC_TENANT_C'];
    $issued = 1794690000;
    $expires = 1794690020;
    $now = 1794690010;
    $negatives = 0;
    $positives = 0;

    foreach ($tenants as $index => $tenant) {
        $other = $tenants[($index + 1) % count($tenants)];
        $wire = f229Wire($tenant);
        $key = 'SYNTHETIC_PUBLIC_TEST_ONLY_F229_'.$tenant.'_KNOWN_PUBLIC_123456789';
        $nonce = 'SYNTHETIC_F229_NONCE_'.($index + 1);
        $issuedFixture = MockCrmReadRequest::issueSyntheticSignedFixture(
            $wire, $nonce, $issued, $expires, $key
        );
        f229Check(array_keys($issuedFixture) === ['body', 'proof']);
        $body = $issuedFixture['body'];
        $proof = $issuedFixture['proof'];
        f229Check(MockCrmReadRequest::decodeCanonicalJson($body) === $wire);

        // Real CRM preflight accepts a legitimate TEST fixture once, not twice.
        $one = new AiaFixtureNonceLedger();
        f229Check(AiaHmacFixturePreflight::matchesFixture(
            $body, $proof, $tenant, $now, $key, $one
        ));
        f229Check(!AiaHmacFixturePreflight::matchesFixture(
            $body, $proof, $tenant, $now, $key, $one
        ));
        f229Check($one->countConsumed() === 1);
        $publicLedger = new AiaFixtureNonceLedger();
        f229Check(f229PublicDeny(AiaHmacFixturePreflight::inspectFixture(
            $body, $proof, $tenant, $now, $key, $publicLedger
        )));
        f229Check($publicLedger->countConsumed() === 1);
        ++$positives;

        $differentWire = $wire;
        $differentWire['fields'] = ['ref'];
        $differentBody = MockCrmReadRequest::encodeCanonicalJson($differentWire);
        $extra = $proof;
        $extra['admin'] = true;

        // Each mutation uses a FRESH ledger: replay cannot mask an acceptance.
        // Tuple: body, proof, expected tenant, clock, public synthetic key.
        $mutations = [
            ['CROSS_TENANT', $body, $proof, $other, $now, $key],
            ['DIFFERENT_KEY', $body, $proof, $tenant, $now, $key.'_MISMATCH'],
            ['DIFFERENT_BODY', $differentBody, $proof, $tenant, $now, $key],
            ['WHITESPACE', $body."\n", $proof, $tenant, $now, $key],
            ['DUPLICATE_JSON', substr($body, 0, -1).',"tenant_ref":"'.$tenant.'"}', $proof, $tenant, $now, $key],
            ['CLAIM_TENANT', $body, array_replace($proof, ['tenant_ref' => $other]), $tenant, $now, $key],
            ['CLAIM_NONCE', $body, array_replace($proof, ['nonce' => 'SYNTHETIC_F229_OTHER']), $tenant, $now, $key],
            ['CLAIM_ISSUER', $body, array_replace($proof, ['issuer' => 'SYNTHETIC_OTHER']), $tenant, $now, $key],
            ['CLAIM_AUDIENCE', $body, array_replace($proof, ['audience' => 'SYNTHETIC_OTHER']), $tenant, $now, $key],
            ['CLAIM_DIGEST', $body, array_replace($proof, ['body_sha256' => str_repeat('0', 64)]), $tenant, $now, $key],
            ['CLAIM_MAC', $body, array_replace($proof, ['signature' => str_repeat('0', 64)]), $tenant, $now, $key],
            ['CLAIM_EXPIRES', $body, array_replace($proof, ['expires_at' => $expires + 1]), $tenant, $now, $key],
            ['CLAIM_ISSUED', $body, array_replace($proof, ['issued_at' => $issued + 1]), $tenant, $now, $key],
            ['EXTRA_CLAIM', $body, $extra, $tenant, $now, $key],
            ['KEY_ORDER', $body, array_reverse($proof, true), $tenant, $now, $key],
            ['BEFORE_ISSUED', $body, $proof, $tenant, $issued - 1, $key],
            ['AFTER_EXPIRES', $body, $proof, $tenant, $expires, $key],
            ['OVERSIZED_BODY', str_repeat('X', 1025), $proof, $tenant, $now, $key],
        ];
        f229Check(count($mutations) === 18);
        foreach ($mutations as [$label, $tamperedBody, $tamperedProof, $expectedTenant, $clock, $givenKey]) {
            $ledger = new AiaFixtureNonceLedger();
            f229Check(!AiaHmacFixturePreflight::matchesFixture(
                $tamperedBody, $tamperedProof, $expectedTenant, $clock, $givenKey, $ledger
            ));
            f229Check($ledger->countConsumed() === 0);
            f229Check(f229PublicDeny(AiaHmacFixturePreflight::inspectFixture(
                $tamperedBody, $tamperedProof, $expectedTenant, $clock, $givenKey, $ledger
            )));
            f229Check($ledger->countConsumed() === 0);
            ++$negatives;
        }
        // Outage cannot consume or forget a nonce.
        $outage = new AiaFixtureNonceLedger();
        $outage->setAvailable(false);
        f229Check(!AiaHmacFixturePreflight::matchesFixture(
            $body, $proof, $tenant, $now, $key, $outage
        ) && $outage->countConsumed() === 0);
        $outage->setAvailable(true);
        f229Check(AiaHmacFixturePreflight::matchesFixture(
            $body, $proof, $tenant, $now, $key, $outage
        ) && $outage->countConsumed() === 1);
        f229Check(!AiaHmacFixturePreflight::matchesFixture(
            $body, $proof, $tenant, $now, $key, $outage
        ));
    }

    // Isolate replay from capacity: a 3-slot ledger refuses cross-tenant
    // reuse of the SAME nonce even when it has spare capacity.
    $shared = new AiaFixtureNonceLedger(3);
    $a = MockCrmReadRequest::issueSyntheticSignedFixture(
        f229Wire($tenants[0]), 'SYNTHETIC_F229_SHARED', $issued, $expires,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_A_123456789'
    );
    $b = MockCrmReadRequest::issueSyntheticSignedFixture(
        f229Wire($tenants[1]), 'SYNTHETIC_F229_SHARED', $issued, $expires,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_B_123456789'
    );
    f229Check(AiaHmacFixturePreflight::matchesFixture(
        $a['body'], $a['proof'], $tenants[0], $now,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_A_123456789', $shared
    ));
    f229Check(!AiaHmacFixturePreflight::matchesFixture(
        $b['body'], $b['proof'], $tenants[1], $now,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_B_123456789', $shared
    ));
    f229Check($shared->countConsumed() === 1);

    // Independently prove that saturation rejects a DIFFERENT valid nonce.
    $freshB = MockCrmReadRequest::issueSyntheticSignedFixture(
        f229Wire($tenants[1]), 'SYNTHETIC_F229_FRESH_B', $issued, $expires,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_B_123456789'
    );
    $saturated = new AiaFixtureNonceLedger(1);
    f229Check(AiaHmacFixturePreflight::matchesFixture(
        $a['body'], $a['proof'], $tenants[0], $now,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_A_123456789', $saturated
    ));
    f229Check(!AiaHmacFixturePreflight::matchesFixture(
        $freshB['body'], $freshB['proof'], $tenants[1], $now,
        'SYNTHETIC_PUBLIC_TEST_ONLY_F229_SHARED_PUBLIC_KEY_B_123456789', $saturated
    ));
    f229Check($saturated->countConsumed() === 1);
    f229Check($positives === 3 && $negatives === 54);
    echo "F229_TENANT_REPLAY_MATRIX_PASS tenants=3 negatives=54 ".
        "operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch (Throwable) {
    fwrite(STDERR, "F229_TENANT_REPLAY_MATRIX_DENIED operational_decision=NO_GO\n");
    exit(1);
}
