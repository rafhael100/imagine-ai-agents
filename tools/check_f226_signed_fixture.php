<?php
declare(strict_types=1);

/** F226: actual Agents canonical JSON -> pinned CRM SHA HMAC preflight.
 * Pure PHP, public synthetic fixture key, no network or service permission.
 */
use App\Support\Synthetic\{
    AiaHmacFixturePreflight, AiaFixtureNonceLedger, AiaReadOnlyMockBoundary
};
use ImagineAI\MockBridge\MockCrmReadRequest;

$root=dirname(__DIR__);
$crm=$root.'/_synthetic_crm_fixture/app/Support/Synthetic';
foreach (['AiaReadOnlyMockBoundary.php','AiaFixtureNonceLedger.php',
          'AiaHmacFixturePreflight.php'] as $file) {
    if (!is_file($crm.'/'.$file)) {
        fwrite(STDERR,"F226_CRM_SOURCE_MISSING operational_decision=NO_GO\n");
        exit(2);
    }
    require_once $crm.'/'.$file;
}
spl_autoload_register(static function(string $name) use ($root): void {
    $prefix='ImagineAI\\';
    if (str_starts_with($name,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',
            substr($name,strlen($prefix))).'.php';
    }
});
function f226Check(bool $condition): void {
    if (!$condition) throw new RuntimeException('F226_DENIED');
}
function f226Proof(
    string $json,
    string $nonce,
    string $key,
    int $issued=1794690000,
    int $expires=1794690025,
    string $issuer='SYNTHETIC_AGENT_SERVICE',
    string $audience='SYNTHETIC_CRM_API',
    string $tenant='SYNTHETIC_TENANT_A',
): array {
    $p=[
        'issuer'=>$issuer, 'audience'=>$audience, 'tenant_ref'=>$tenant,
        'nonce'=>$nonce, 'issued_at'=>$issued, 'expires_at'=>$expires,
        'body_sha256'=>hash('sha256',$json),
    ];
    $p['signature']=hash_hmac('sha256',implode("\n",[
        'SYNTHETIC_AIA_HMAC_PROOF_V1',...array_values($p),
    ]),$key);
    return $p;
}

try {
    $key='SYNTHETIC_PUBLIC_TEST_ONLY_AGENTS_SIGNED_FIXED_NOT_SECRET_1234567';
    $wire=[
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F226_REQUEST',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'principal_ref'=>'SYNTHETIC_PRINCIPAL',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_TEST',
        'fields'=>['ref','stage'],
    ];
    $raw=MockCrmReadRequest::encodeCanonicalJson($wire);
    f226Check(MockCrmReadRequest::decodeCanonicalJson($raw)===$wire);
    f226Check(AiaReadOnlyMockBoundary::hasCanonicalFixtureShape($raw));
    $proof=f226Proof($raw,'SYNTHETIC_F226_NONCE_A',$key);
    $store=new AiaFixtureNonceLedger(2);
    $verify=static fn(string $body,array $p,AiaFixtureNonceLedger $ledger,
        int $now=1794690010,string $tenant='SYNTHETIC_TENANT_A',
        string $secret=''): bool =>
        AiaHmacFixturePreflight::matchesFixture(
            $body,$p,$tenant,$now,$secret===''?$key:$secret,$ledger
        );
    f226Check($verify($raw,$proof,$store));
    f226Check($store->countConsumed()===1);
    f226Check(!$verify($raw,$proof,$store)); // same proof cannot replay
    f226Check($store->countConsumed()===1);

    $deny=AiaReadOnlyMockBoundary::inspectCanonicalJson($raw);
    f226Check($deny['state']==='DENIED_OR_NOT_FOUND'
        && $deny['projection']===null
        && $deny['real_authorization']===false
        && $deny['external_calls']===0);
    $newProof=f226Proof($raw,'SYNTHETIC_F226_NONCE_B',$key);
    f226Check(AiaHmacFixturePreflight::inspectFixture(
        $raw,$newProof,'SYNTHETIC_TENANT_A',1794690010,$key,$store
    )===$deny);
    f226Check($store->countConsumed()===2);
    f226Check(!$verify($raw,f226Proof(
        $raw,'SYNTHETIC_F226_NONCE_C',$key),$store));

    $duplicate=substr($raw,0,-1).',"tenant_ref":"SYNTHETIC_TENANT_A"}';
    $mutated=str_replace('SYNTHETIC_LEAD_A','SYNTHETIC_LEAD_B',$raw);
    $mutations=[
        'BAD_BODY_DIGEST'=>[$mutated,$proof,1794690010,'SYNTHETIC_TENANT_A',$key],
        'DUPLICATE_JSON'=>[$duplicate,f226Proof(
            $duplicate,'SYNTHETIC_F226_NONCE_D',$key),1794690010,'SYNTHETIC_TENANT_A',$key],
        'WRONG_ISSUER'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_E',$key,1794690000,1794690025,
            'SYNTHETIC_FALSE_ISSUER'),1794690010,'SYNTHETIC_TENANT_A',$key],
        'WRONG_AUDIENCE'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_F',$key,1794690000,1794690025,
            'SYNTHETIC_AGENT_SERVICE','SYNTHETIC_FALSE_AUD'),1794690010,'SYNTHETIC_TENANT_A',$key],
        'WRONG_TENANT'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_G',$key),1794690010,'SYNTHETIC_TENANT_B',$key],
        'WRONG_KEY'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_H',$key),1794690010,'SYNTHETIC_TENANT_A',
            'SYNTHETIC_PUBLIC_TEST_ONLY_DIFFERENT_KNOWN_KEY_123456789'],
        'NOT_YET_VALID'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_I',$key),1794689999,'SYNTHETIC_TENANT_A',$key],
        'EXPIRED'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_J',$key),1794690025,'SYNTHETIC_TENANT_A',$key],
        'LONG_LIFETIME'=>[$raw,f226Proof(
            $raw,'SYNTHETIC_F226_NONCE_K',$key,1794690000,1794690032),1794690010,'SYNTHETIC_TENANT_A',$key],
        'TRAILING_JSON'=>[$raw.'{}',f226Proof(
            $raw.'{}','SYNTHETIC_F226_NONCE_L',$key),1794690010,'SYNTHETIC_TENANT_A',$key],
    ];
    $wrong=$proof; $wrong['role']='ADMIN';
    $mutations['EXTRA_CLAIM']=[$raw,$wrong,1794690010,'SYNTHETIC_TENANT_A',$key];
    $wrong=$proof; $wrong['signature']=str_repeat('0',64);
    $mutations['FORGED_SIGNATURE']=[$raw,$wrong,1794690010,'SYNTHETIC_TENANT_A',$key];
    $negative=0;
    foreach($mutations as [$body,$claim,$time,$tenant,$givenKey]) {
        // Fresh local ledger per mutation prevents replay from masking
        // a failed cryptographic, time, tenant or raw schema check.
        $lab=new AiaFixtureNonceLedger();
        f226Check(!$verify($body,$claim,$lab,$time,$tenant,$givenKey));
        f226Check($lab->countConsumed()===0);
        ++$negative;
    }
    f226Check($negative===12);
    $outage=new AiaFixtureNonceLedger();
    $outage->setAvailable(false);
    f226Check(!$verify($raw,f226Proof(
        $raw,'SYNTHETIC_F226_NONCE_X',$key),$outage));
    $outage->setAvailable(true);
    f226Check($outage->countConsumed()===0);
    f226Check($verify($raw,f226Proof(
        $raw,'SYNTHETIC_F226_NONCE_X',$key),$outage));
    f226Check(!$verify($raw,f226Proof(
        $raw,'SYNTHETIC_F226_NONCE_X',$key),$outage));
    echo "F226_SIGNED_FIXTURE_CROSS_SOURCE_PASS negatives=$negative ".
        "operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch(Throwable) {
    fwrite(STDERR,"F226_SIGNED_FIXTURE_CROSS_SOURCE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
