<?php
declare(strict_types=1);

/** F235: real Ed25519 math, synthetic fixtures only, zero CRM calls. */
use ImagineAI\MockBridge\{
    MockCrmReadRequest, MockServiceIdentityPreflight,
    MockSharedNonceLedger, MockSyntheticEvidenceGate
};
$root = dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f235Assert(bool $v): void { if (!$v) throw new RuntimeException('F235_DENIED'); }

try {
    $tenant = 'SYNTHETIC_TENANT_A';
    $now = 1794690010;
    $keyId = 'SYNTHETIC_F235_KID_A';
    // Deliberately PUBLIC deterministic fixture seed, NEVER operational.
    $kp = sodium_crypto_sign_seed_keypair(hash('sha256','PUBLIC_F235_SEED',true));
    $secret = sodium_crypto_sign_secretkey($kp);
    $trusted = [$keyId => [
        'public_key' => sodium_crypto_sign_publickey($kp), 'enabled' => true
    ]];
    $wire = [
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F235_REQUEST',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_PURPOSE_A',
        'fields'=>['ref','stage'],
    ];
    $scope = [
        'tenant_ref'=>$tenant,
        'principal_ref'=>'SYNTHETIC_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_PURPOSE_A',
        'fields'=>['ref','stage'],
    ];
    $issue = static function (
        string $nonce, array $wireDiff=[], array $claimDiff=[]
    ) use ($wire,$tenant,$keyId,$now,$secret): array {
        $request = MockCrmReadRequest::encodeCanonicalJson(array_replace($wire,$wireDiff));
        $claims = [
            'contract'=>'SYNTHETIC_AIA_SERVICE_IDENTITY_V1',
            'issuer'=>'SYNTHETIC_AGENT_SERVICE',
            'audience'=>'SYNTHETIC_CRM_API',
            'key_id'=>$keyId,
            'subject'=>'SYNTHETIC_AGENTS_READ_ONLY',
            'tenant_ref'=>$tenant,
            'operation'=>'crm.lead.read_minimal',
            'nonce'=>$nonce,
            'issued_at'=>$now-10,
            'expires_at'=>$now+20,
            'request_sha256'=>hash('sha256',$request),
        ];
        $claims = array_replace($claims,$claimDiff);
        $json = json_encode($claims,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $signature = base64_encode(sodium_crypto_sign_detached(
            "SYNTHETIC_AIA_SERVICE_ED25519_V1\n".$json,$secret
        ));
        return [$request,$json,$signature];
    };
    $gate = static fn(MockSharedNonceLedger $ledger) =>
        new MockSyntheticEvidenceGate(new MockServiceIdentityPreflight(),$ledger);
    [$req,$claim,$sig] = $issue('SYNTHETIC_F235_NONCE_1');
    $ledger = new MockSharedNonceLedger(3);
    f235Assert($gate($ledger)->checkEvidenceOnce($req,$claim,$sig,$trusted,$scope,$tenant,$now));
    f235Assert(!$gate($ledger)->checkEvidenceOnce($req,$claim,$sig,$trusted,$scope,$tenant,$now));
    f235Assert($ledger->consumedCount() === 1);

    $invalid = [];
    foreach ([
        ['tenant_ref'=>'SYNTHETIC_TENANT_B'],
        ['principal_ref'=>'SYNTHETIC_PRINCIPAL_B'],
        ['resource_ref'=>'SYNTHETIC_LEAD_B'],
        ['purpose_ref'=>'SYNTHETIC_PURPOSE_B'],
    ] as $diff) {
        $invalid[] = [$issue('SYNTHETIC_F235_NEG_'.count($invalid),$diff),$scope,$trusted];
    }
    $invalid[] = [[$req,$claim,$sig],array_replace($scope,['fields'=>['ref']]),$trusted];
    $invalid[] = [[$req,$claim,$sig],array_reverse($scope,true),$trusted];
    $invalid[] = [[$req,$claim,$sig],array_replace($scope,['tenant_ref'=>'SYNTHETIC_TENANT_B']),$trusted];
    foreach ([
        ['issuer'=>'SYNTHETIC_OTHER'],
        ['audience'=>'SYNTHETIC_OTHER'],
        ['subject'=>'SYNTHETIC_ADMIN'],
        ['tenant_ref'=>'SYNTHETIC_TENANT_B'],
        ['request_sha256'=>str_repeat('0',64)],
        ['operation'=>'crm.lead.write'],
    ] as $diff) {
        $invalid[] = [$issue('SYNTHETIC_F235_BADCLAIM_'.count($invalid),[],$diff),$scope,$trusted];
    }
    $invalid[] = [[$req,$claim,base64_encode(str_repeat("\x00",64))],$scope,$trusted];
    $invalid[] = [[$req,$claim,$sig],$scope,[]];
    $invalid[] = [[$req,$claim,$sig],array_replace($scope,['fields'=>['ref','ref']]),$trusted];

    $denied = 0;
    foreach ($invalid as [$bundle,$expected,$keys]) {
        [$request,$claims,$signature] = $bundle;
        $emptyLedger = new MockSharedNonceLedger();
        f235Assert(!$gate($emptyLedger)->checkEvidenceOnce(
            $request,$claims,$signature,$keys,$expected,$tenant,$now
        ));
        f235Assert($emptyLedger->consumedCount() === 0);
        ++$denied;
    }
    f235Assert($denied===16);

    $limited = new MockSharedNonceLedger(1);
    [$a,$aClaims,$aSig] = $issue('SYNTHETIC_F235_CAP_A');
    [$b,$bClaims,$bSig] = $issue('SYNTHETIC_F235_CAP_B');
    f235Assert($gate($limited)->checkEvidenceOnce($a,$aClaims,$aSig,$trusted,$scope,$tenant,$now));
    f235Assert(!$gate($limited)->checkEvidenceOnce($b,$bClaims,$bSig,$trusted,$scope,$tenant,$now));
    f235Assert($limited->consumedCount() === 1);

    $outage = new MockSharedNonceLedger();
    $outage->setAvailable(false);
    f235Assert(!$gate($outage)->checkEvidenceOnce($a,$aClaims,$aSig,$trusted,$scope,$tenant,$now));
    f235Assert($outage->consumedCount()===0);
    echo "F235_COMPOSED_GATE_PASS negatives=16 replay_fail_closed=true real_authorization=false operational_decision=NO_GO\n";
} catch (Throwable) {
    fwrite(STDERR,"F235_COMPOSED_GATE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
