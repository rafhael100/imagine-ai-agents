<?php
declare(strict_types=1);
/** F227: actual Agents signed fixture and independent MAC check, no I/O. */
use ImagineAI\Domain\MockError;
use ImagineAI\MockBridge\MockCrmReadRequest;

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='ImagineAI\\';
    if (str_starts_with($class,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f227Check(bool $value): void {
    if (!$value) throw new RuntimeException('F227_DENIED');
}
function f227ExpectDeny(callable $run): void {
    try { $run(); }
    catch (MockError $e) {
        f227Check($e->getMessage()==='BRIDGE_SCHEMA_DENIED');
        return;
    }
    throw new RuntimeException('F227_UNEXPECTED_ACCEPT');
}
try {
    $wire=[
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F227_REQ',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'principal_ref'=>'SYNTHETIC_USER_A',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_TEST',
        'fields'=>['ref','stage'],
    ];
    $key='SYNTHETIC_PUBLIC_TEST_ONLY_F227_FIXED_PUBLIC_NOT_SECRET_123456';
    $issued=1794690000; $expires=1794690025;
    $out=MockCrmReadRequest::issueSyntheticSignedFixture(
        $wire,'SYNTHETIC_NONCE_F227_A',$issued,$expires,$key
    );
    f227Check(array_keys($out)===['body','proof']);
    $body=MockCrmReadRequest::encodeCanonicalJson($wire);
    f227Check($out['body']===$body);
    f227Check(MockCrmReadRequest::decodeCanonicalJson($out['body'])===$wire);
    $proof=$out['proof'];
    f227Check(array_keys($proof)===[
        'issuer','audience','tenant_ref','nonce',
        'issued_at','expires_at','body_sha256','signature',
    ]);
    f227Check($proof['tenant_ref']===$wire['tenant_ref']);
    f227Check($proof['body_sha256']===hash('sha256',$body));
    $independent=hash_hmac('sha256',implode("\n",[
        'SYNTHETIC_AIA_HMAC_PROOF_V1','SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API','SYNTHETIC_TENANT_A','SYNTHETIC_NONCE_F227_A',
        (string)$issued,(string)$expires,hash('sha256',$body),
    ]),$key);
    f227Check(hash_equals($independent,$proof['signature']));
    $other=MockCrmReadRequest::issueSyntheticSignedFixture(
        $wire,'SYNTHETIC_NONCE_F227_B',$issued,$expires,$key
    );
    f227Check($other['body']===$body
        && $other['proof']['signature']!==$proof['signature']);
    $bad=[
        ['nonce'=>'INVALID_NONCE'],
        ['nonce'=>'SYNTHETIC_A'],
        ['nonce'=>str_repeat('SYNTHETIC_A',50)],
        ['key'=>'real-secret-not-allowed'],
        ['key'=>'SYNTHETIC_PUBLIC_TEST_ONLY_short'],
        ['key'=>str_repeat('SYNTHETIC_PUBLIC_TEST_ONLY_',20)],
        ['issued'=>-1],
        ['issued'=>1794690025],
        ['expires'=>1794690000],
        ['expires'=>1794690031],
        ['expires'=>4102444801],
        ['wire'=>array_replace($wire,['fields'=>['email']])],
        ['wire'=>array_replace($wire,['admin'=>true])],
        ['wire'=>array_reverse($wire,true)],
    ];
    foreach($bad as $change) {
        f227ExpectDeny(static fn() => MockCrmReadRequest::issueSyntheticSignedFixture(
            $change['wire'] ?? $wire,
            $change['nonce'] ?? 'SYNTHETIC_NONCE_F227_X',
            $change['issued'] ?? $issued,
            $change['expires'] ?? $expires,
            $change['key'] ?? $key
        ));
    }
    f227Check(count($bad)===14);
    echo "F227_SIGNED_FIXTURE_PASS invalids=14 ".
        "operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch (Throwable) {
    fwrite(STDERR,"F227_SIGNED_FIXTURE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
