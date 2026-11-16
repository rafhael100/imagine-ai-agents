<?php
declare(strict_types=1);

/**
 * F230: original CRM synthetic deny -> Agents sealed-denial lab verification.
 * Public TEST fixture keys only, no real service/authorization/network.
 */
use App\Support\Synthetic\AiaReadOnlyMockBoundary;
use ImagineAI\MockBridge\{
    MockCrmReadRequest, MockCrmSignedDenialFixture, MockCrmPublicResponseContract
};
use ImagineAI\Domain\MockError;

$root = dirname(__DIR__);
$crm = $root.'/_synthetic_crm_fixture/app/Support/Synthetic';
if (!is_file($crm.'/AiaReadOnlyMockBoundary.php')) {
    fwrite(STDERR, "F230_PINNED_CRM_MISSING operational_decision=NO_GO\n");
    exit(2);
}
require_once $crm.'/AiaReadOnlyMockBoundary.php';
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});
function f230Check(bool $valid): void {
    if (!$valid) throw new RuntimeException('F230_INVARIANT_DENIED');
}
function f230Wire(string $tenant): array {
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F230_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $tenant,
        'principal_ref' => 'SYNTHETIC_F230_USER',
        'resource_ref' => 'SYNTHETIC_F230_LEAD',
        'purpose_ref' => 'SYNTHETIC_F230_TEST',
        'fields' => ['ref', 'stage'],
    ];
}
function f230MustRejectIssue(callable $fn): void {
    try {
        $fn();
    } catch (MockError $e) {
        f230Check($e->getMessage() === 'BRIDGE_SCHEMA_DENIED');
        return;
    }
    throw new RuntimeException('F230_UNSAFE_ISSUE_ACCEPT');
}

try {
    $tenants = ['SYNTHETIC_TENANT_A', 'SYNTHETIC_TENANT_B', 'SYNTHETIC_TENANT_C'];
    $issued = 1794690000; $expires = 1794690020; $now = 1794690010;
    $negatives = 0; $positives = 0;
    foreach ($tenants as $index => $tenant) {
        $other = $tenants[($index + 1) % count($tenants)];
        $req = MockCrmReadRequest::encodeCanonicalJson(f230Wire($tenant));
        $originalCrmResponse = AiaReadOnlyMockBoundary::inspectCanonicalJson($req);
        f230Check($originalCrmResponse === MockCrmPublicResponseContract::denial());
        $key = 'SYNTHETIC_PUBLIC_TEST_ONLY_F230_'.$tenant.'_PUBLIC_KEY_123456789';
        $nonce = 'SYNTHETIC_F230_NONCE_'.($index + 1);
        $sealed = MockCrmSignedDenialFixture::issue(
            $req, $originalCrmResponse, $nonce, $issued, $expires, $key
        );
        f230Check(array_keys($sealed) === ['body', 'proof']);
        $body = $sealed['body'];
        $proof = $sealed['proof'];
        f230Check(json_decode($body, true, 4, JSON_THROW_ON_ERROR) === $originalCrmResponse);
        f230Check($body === json_encode($originalCrmResponse, JSON_THROW_ON_ERROR));
        $good = new MockCrmSignedDenialFixture();
        f230Check($good->verifyOnce($req,$body,$proof,$tenant,$nonce,$now,$key));
        f230Check(!$good->verifyOnce($req,$body,$proof,$tenant,$nonce,$now,$key));
        f230Check($good->countConsumed() === 1);
        ++$positives;

        $badBody = json_encode(array_replace(
            $originalCrmResponse, ['real_authorization'=>true]
        ), JSON_THROW_ON_ERROR);
        $wrongRequest = MockCrmReadRequest::encodeCanonicalJson(f230Wire($other));
        $duplicate = substr($body, 0, -1).',"state":"DENIED_OR_NOT_FOUND"}';
        $changes = [
            ['TENANT_EXPECTED', $req,$body,$proof,$other,$nonce,$now,$key],
            ['NONCE_EXPECTED', $req,$body,$proof,$tenant,'SYNTHETIC_NONCE_OTHER',$now,$key],
            ['KEY_CHANGED', $req,$body,$proof,$tenant,$nonce,$now,$key.'_WRONG'],
            ['REQUEST_SWAP', $wrongRequest,$body,$proof,$tenant,$nonce,$now,$key],
            ['REQUEST_WHITESPACE', $req."\n",$body,$proof,$tenant,$nonce,$now,$key],
            ['RESPONSE_WHITESPACE', $req,$body."\n",$proof,$tenant,$nonce,$now,$key],
            ['RESPONSE_DUPLICATE', $req,$duplicate,$proof,$tenant,$nonce,$now,$key],
            ['RESPONSE_TAMPERED', $req,$badBody,$proof,$tenant,$nonce,$now,$key],
            ['CLAIM_TENANT', $req,$body,array_replace($proof,['tenant_ref'=>$other]),$tenant,$nonce,$now,$key],
            ['CLAIM_NONCE', $req,$body,array_replace($proof,['nonce'=>'SYNTHETIC_NONCE_OTHER']),$tenant,$nonce,$now,$key],
            ['CLAIM_REQUEST_DIGEST', $req,$body,array_replace($proof,['request_sha256'=>str_repeat('0',64)]),$tenant,$nonce,$now,$key],
            ['CLAIM_RESPONSE_DIGEST', $req,$body,array_replace($proof,['body_sha256'=>str_repeat('0',64)]),$tenant,$nonce,$now,$key],
            ['CLAIM_SIGNATURE', $req,$body,array_replace($proof,['signature'=>str_repeat('0',64)]),$tenant,$nonce,$now,$key],
            ['CLAIM_ISSUER', $req,$body,array_replace($proof,['issuer'=>'SYNTHETIC_OTHER']),$tenant,$nonce,$now,$key],
            ['CLAIM_AUDIENCE', $req,$body,array_replace($proof,['audience'=>'SYNTHETIC_OTHER']),$tenant,$nonce,$now,$key],
            ['CLAIM_KEY_ORDER', $req,$body,array_reverse($proof,true),$tenant,$nonce,$now,$key],
            ['TOO_EARLY', $req,$body,$proof,$tenant,$nonce,$issued-1,$key],
            ['EXPIRED', $req,$body,$proof,$tenant,$nonce,$expires,$key],
        ];
        f230Check(count($changes) === 18);
        foreach ($changes as [$label,$reqX,$bodyX,$proofX,$tenX,$nonceX,$clockX,$keyX]) {
            $fresh = new MockCrmSignedDenialFixture();
            f230Check(!$fresh->verifyOnce(
                $reqX,$bodyX,$proofX,$tenX,$nonceX,$clockX,$keyX
            ));
            f230Check($fresh->countConsumed() === 0);
            ++$negatives;
        }
        // A backend outage never consumes a test nonce, while recovery does.
        $outage = new MockCrmSignedDenialFixture();
        $outage->setAvailable(false);
        f230Check(!$outage->verifyOnce($req,$body,$proof,$tenant,$nonce,$now,$key));
        f230Check($outage->countConsumed() === 0);
        $outage->setAvailable(true);
        f230Check($outage->verifyOnce($req,$body,$proof,$tenant,$nonce,$now,$key));
        f230Check(!$outage->verifyOnce($req,$body,$proof,$tenant,$nonce,$now,$key));
        f230Check($outage->countConsumed() === 1);

        // Issuer cannot seal a fictitious success, a real-looking key, or stale proof.
        $fakeAllow = $originalCrmResponse; $fakeAllow['state'] = 'READ_SYNTHETIC_ONLY';
        f230MustRejectIssue(static fn() => MockCrmSignedDenialFixture::issue(
            $req,$fakeAllow,$nonce,$issued,$expires,$key
        ));
        f230MustRejectIssue(static fn() => MockCrmSignedDenialFixture::issue(
            $req,$originalCrmResponse,$nonce,$issued,$expires,'REAL_PROD_KEY'
        ));
        f230MustRejectIssue(static fn() => MockCrmSignedDenialFixture::issue(
            $req,$originalCrmResponse,$nonce,$issued,$issued+31,$key
        ));
    }
    // Distinguish independent capacity denial from replay.
    $aReq = MockCrmReadRequest::encodeCanonicalJson(f230Wire($tenants[0]));
    $aBody = AiaReadOnlyMockBoundary::inspectCanonicalJson($aReq);
    $key = 'SYNTHETIC_PUBLIC_TEST_ONLY_F230_CAPACITY_123456789_A';
    $a = MockCrmSignedDenialFixture::issue($aReq,$aBody,'SYNTHETIC_F230_CAPACITY_A',$issued,$expires,$key);
    $b = MockCrmSignedDenialFixture::issue($aReq,$aBody,'SYNTHETIC_F230_CAPACITY_B',$issued,$expires,$key);
    $limited = new MockCrmSignedDenialFixture(1);
    f230Check($limited->verifyOnce($aReq,$a['body'],$a['proof'],$tenants[0],'SYNTHETIC_F230_CAPACITY_A',$now,$key));
    f230Check(!$limited->verifyOnce($aReq,$b['body'],$b['proof'],$tenants[0],'SYNTHETIC_F230_CAPACITY_B',$now,$key));
    f230Check($limited->countConsumed() === 1);
    f230Check($positives === 3 && $negatives === 54);
    echo "F230_SIGNED_CRM_DENIAL_PASS tenants=3 negatives=54 ".
        "operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch (Throwable) {
    fwrite(STDERR, "F230_SIGNED_CRM_DENIAL_DENIED operational_decision=NO_GO\n");
    exit(1);
}
