<?php
declare(strict_types=1);

/** F238: pure signed HTTP-shape fixture, never a live client. */
use ImagineAI\MockBridge\{
    MockCrmReadRequest, MockServiceIdentityPreflight,
    MockSharedNonceLedger, MockSyntheticEvidenceGate, MockSyntheticHttpEnvelope
};

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f238Assert(bool $ok): void {
    if (!$ok) throw new RuntimeException('F238_DENIED');
}
try {
    $tenant = 'SYNTHETIC_TENANT_A';
    $now = 1794690010;
    $kid = 'SYNTHETIC_F238_KEY_A';
    $pair = sodium_crypto_sign_seed_keypair(hash('sha256','PUBLIC_F238_TEST_ONLY', true));
    $public = [$kid => ['public_key'=>sodium_crypto_sign_publickey($pair),'enabled'=>true]];
    $secret = sodium_crypto_sign_secretkey($pair); // Deterministic PUBLIC fixture; never operational.
    $requestArray = [
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F238_REQUEST_A',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_F238_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_F238_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_F238_PURPOSE_A',
        'fields'=>['ref','stage'],
    ];
    $scope = [
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_F238_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_F238_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_F238_PURPOSE_A',
        'fields'=>['ref','stage'],
    ];
    $issue = static function (
        array $reqDiff=[], array $claimDiff=[], string $nonce='SYNTHETIC_F238_NONCE_A'
    ) use ($requestArray,$tenant,$kid,$now,$secret): array {
        $request=MockCrmReadRequest::encodeCanonicalJson(array_replace($requestArray,$reqDiff));
        $claims=[
            'contract'=>'SYNTHETIC_AIA_SERVICE_IDENTITY_V1',
            'issuer'=>'SYNTHETIC_AGENT_SERVICE',
            'audience'=>'SYNTHETIC_CRM_API',
            'key_id'=>$kid,
            'subject'=>'SYNTHETIC_AGENTS_READ_ONLY',
            'tenant_ref'=>$tenant,
            'operation'=>'crm.lead.read_minimal',
            'nonce'=>$nonce,
            'issued_at'=>$now-10,
            'expires_at'=>$now+20,
            'request_sha256'=>hash('sha256',$request),
        ];
        $claims=array_replace($claims,$claimDiff);
        $json=json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $signature=base64_encode(sodium_crypto_sign_detached(
            "SYNTHETIC_AIA_SERVICE_ED25519_V1\n".$json,$secret
        ));
        return [$request,$json,$signature];
    };
    $gate = static fn() => new MockSyntheticEvidenceGate(
        new MockServiceIdentityPreflight(), new MockSharedNonceLedger()
    );
    [$body,$claims,$signature]=$issue();
    $shared=$gate();
    $wire=MockSyntheticHttpEnvelope::assemble(
        $shared,$body,$claims,$signature,$public,$scope,$tenant,$now
    );
    f238Assert($wire!==null);
    f238Assert(array_keys($wire)===[
        'method','headers','body','transmission_authorized','real_authorization','operational_decision'
    ]);
    f238Assert($wire['method']==='POST' && $wire['body']===$body);
    f238Assert($wire['headers'] === [
        'content-type'=>'application/json',
        'x-aia-claims'=>$claims,
        'x-aia-signature'=>$signature,
    ]);
    f238Assert($wire['transmission_authorized'] === false);
    f238Assert($wire['real_authorization'] === false);
    f238Assert($wire['operational_decision'] === 'NO_GO');
    // The same evidence gate cannot be used again to emit a second fixture.
    f238Assert(MockSyntheticHttpEnvelope::assemble(
        $shared,$body,$claims,$signature,$public,$scope,$tenant,$now
    )===null);

    $cases=[];
    $cases[]=[[$body,$claims,base64_encode(str_repeat("\x00",64))],$public,$scope,$now];
    $cases[]=[[$body,$claims,$signature],[],$scope,$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['tenant_ref'=>'SYNTHETIC_TENANT_B']),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['fields'=>['ref']]),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['fields'=>['ref','email']]),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_reverse($scope,true),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['principal_ref'=>'SYNTHETIC_ANOTHER']),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['resource_ref'=>'SYNTHETIC_OTHER_LEAD']),$now];
    $cases[]=[[$body,$claims,$signature],$public,array_replace($scope,['purpose_ref'=>'SYNTHETIC_WRONG_PURPOSE']),$now];
    $cases[]=[[$body,$claims,$signature],$public,$scope,$now+31];
    $cases[]=[[$body."\n",$claims,$signature],$public,$scope,$now];
    $cases[]=[[$body,$claims."\n",$signature],$public,$scope,$now];
    $cases[]=[[$body,$claims,$signature."\n"],$public,$scope,$now];
    $cases[]=[[$body,$claims,'notbase64'], $public,$scope,$now];
    $cases[]=[[$body,'{}',$signature],$public,$scope,$now];
    $cases[]=[[$body,str_repeat('X',1025),$signature],$public,$scope,$now];
    $cases[]=[[$body,$claims,$signature],$public,$scope,-1];
    $cases[]=[[$body,$claims,$signature],$public,$scope,$now-11];
    $cases[]=[[$body,$claims,$signature],$public,array_merge($scope,['admin'=>true]),$now];
    $cases[]=[[$body,$claims,$signature],[$kid=>['public_key'=>str_repeat('a',32),'enabled'=>false]],$scope,$now];
    $cases[]=[[$body,$claims,$signature],[$kid=>['public_key'=>str_repeat('b',32),'enabled'=>true]],$scope,$now];
    $cases[]=[[$body,$claims,$signature],$public,$scope,4102444801];
    $cases[]=[[$body,$claims,$signature],$public,$scope,0];
    $cases[]=[[$body,$claims,$signature],$public,$scope,$now+20];
    $cases[]=[[$body,$claims,$signature],$public,$scope,$now+21];
    $cases[]=[[$body,$claims,$signature],$public,$scope,$now+200];
    $cases[]=[[$body,$claims,$signature],$public,$scope,4102444800];
    // Legitimately signed but policy-invalid payloads.
    $cases[]=[$issue(['resource_ref'=>'SYNTHETIC_LEAD_B']),$public,$scope,$now];
    $cases[]=[$issue([],['issuer'=>'SYNTHETIC_F238_OTHER']),$public,$scope,$now];
    $cases[]=[$issue([],['audience'=>'SYNTHETIC_F238_OTHER']),$public,$scope,$now];
    $cases[]=[$issue([],['operation'=>'crm.lead.write']),$public,$scope,$now];
    $cases[]=[$issue([],['request_sha256'=>str_repeat('0',64)]),$public,$scope,$now];

    $denied=0;
    foreach($cases as [$bundle,$keys,$expected,$clock]) {
        f238Assert(MockSyntheticHttpEnvelope::assemble(
            $gate(),$bundle[0],$bundle[1],$bundle[2],$keys,$expected,$tenant,$clock
        )===null);
        ++$denied;
    }
    f238Assert($denied===32);
    echo "F238_SYNTHETIC_HTTP_ENVELOPE_PASS positives=1 negatives=32 send_authorized=false operational_decision=NO_GO\n";
} catch (Throwable $e) {
    fwrite(STDERR,"F238_SYNTHETIC_HTTP_ENVELOPE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
