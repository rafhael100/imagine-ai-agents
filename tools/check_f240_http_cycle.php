<?php
declare(strict_types=1);

/**
 * F240: whole *synthetic* cycle: signed fixture -> deny-only CRM shape.
 * There is NO URL, network, Laravel boot or authority to read a real lead.
 */
use ImagineAI\MockBridge\{
    MockCrmReadRequest, MockCrmPublicResponseContract,
    MockServiceIdentityPreflight, MockSharedNonceLedger,
    MockSyntheticEvidenceGate, MockSyntheticHttpEnvelope,
    MockSyntheticHttpDenialReceipt
};

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='ImagineAI\\';
    if(str_starts_with($class,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f240Assert(bool $value):void {
    if(!$value) throw new RuntimeException('F240_FAIL_CLOSED');
}
try {
    $tenant='SYNTHETIC_TENANT_A';
    $now=1794690010;
    $kid='SYNTHETIC_F240_KEY';
    $pair=sodium_crypto_sign_seed_keypair(hash('sha256','PUBLIC_F240_OFFLINE_ONLY',true));
    $private=sodium_crypto_sign_secretkey($pair); // PUBLIC deterministic lab fixture
    $public=[$kid=>['public_key'=>sodium_crypto_sign_publickey($pair),'enabled'=>true]];
    $req=[
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F240_REQUEST',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_F240_USER',
        'resource_ref'=>'SYNTHETIC_F240_LEAD',
        'purpose_ref'=>'SYNTHETIC_F240_PURPOSE',
        'fields'=>['ref','stage'],
    ];
    $scope=[
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_F240_USER',
        'resource_ref'=>'SYNTHETIC_F240_LEAD',
        'purpose_ref'=>'SYNTHETIC_F240_PURPOSE',
        'fields'=>['ref','stage'],
    ];
    $body=MockCrmReadRequest::encodeCanonicalJson($req);
    $claims=[
        'contract'=>'SYNTHETIC_AIA_SERVICE_IDENTITY_V1',
        'issuer'=>'SYNTHETIC_AGENT_SERVICE',
        'audience'=>'SYNTHETIC_CRM_API',
        'key_id'=>$kid,
        'subject'=>'SYNTHETIC_AGENTS_READ_ONLY',
        'tenant_ref'=>$tenant,
        'operation'=>'crm.lead.read_minimal',
        'nonce'=>'SYNTHETIC_F240_NONCE_A',
        'issued_at'=>$now-5,
        'expires_at'=>$now+15,
        'request_sha256'=>hash('sha256',$body),
    ];
    $claimBytes=json_encode($claims,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $signature=base64_encode(sodium_crypto_sign_detached(
        "SYNTHETIC_AIA_SERVICE_ED25519_V1\n".$claimBytes,$private
    ));
    $ledger=new MockSharedNonceLedger();
    $evidence=new MockSyntheticEvidenceGate(new MockServiceIdentityPreflight(),$ledger);
    $wire=MockSyntheticHttpEnvelope::assemble(
        $evidence,$body,$claimBytes,$signature,$public,$scope,$tenant,$now
    );
    f240Assert($wire!==null);
    f240Assert($wire['method']==='POST');
    f240Assert($wire['headers'] === [
        'content-type'=>'application/json',
        'x-aia-claims'=>$claimBytes,
        'x-aia-signature'=>$signature,
    ]);
    f240Assert($wire['body']===$body && $wire['transmission_authorized']===false);
    f240Assert($ledger->consumedCount()===1);
    // No second emission of the same request evidence.
    f240Assert(MockSyntheticHttpEnvelope::assemble(
        $evidence,$body,$claimBytes,$signature,$public,$scope,$tenant,$now
    )===null);

    $denial=json_encode(MockCrmPublicResponseContract::denial(),
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $responseHeaders=[
        'content-type'=>'application/json',
        'cache-control'=>'no-store, private',
    ];
    f240Assert(MockSyntheticHttpDenialReceipt::matches(404,$responseHeaders,$denial));
    $invalidResponses=[
        [200,$responseHeaders,$denial],
        [201,$responseHeaders,$denial],
        [204,$responseHeaders,$denial],
        [302,$responseHeaders,$denial],
        [403,$responseHeaders,$denial],
        [500,$responseHeaders,$denial],
        [404,$responseHeaders,str_replace('"DENIED_OR_NOT_FOUND"','"READ_SYNTHETIC_ONLY"',$denial)],
        [404,$responseHeaders,str_replace('"real_authorization":false','"real_authorization":true',$denial)],
        [404,$responseHeaders,str_replace('"projection":null','"projection":{"ref":"SYNTHETIC_F240_LEAD"}',$denial)],
        [404,array_merge($responseHeaders,['set-cookie'=>'fake_session']),$denial],
        [404,array_replace($responseHeaders,['cache-control'=>'public']),$denial],
        [404,$responseHeaders,$denial."\n"],
        [404,$responseHeaders,''],
        [404,$responseHeaders,'{}'],
        [404,$responseHeaders,str_repeat('x',513)],
    ];
    foreach($invalidResponses as [$status,$headers,$bytes]) {
        f240Assert(!MockSyntheticHttpDenialReceipt::matches($status,$headers,$bytes));
    }
    f240Assert(count($invalidResponses)===15);
    echo "F240_SYNTHETIC_HTTP_CYCLE_PASS denied_response_accepted=true negative_responses=15 real_authorization=false external_calls=0 operational_decision=NO_GO\n";
} catch (Throwable) {
    fwrite(STDERR,"F240_SYNTHETIC_HTTP_CYCLE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
