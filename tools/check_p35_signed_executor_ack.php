<?php
declare(strict_types=1);

require_once __DIR__.'/labs/MockSignedN8nAckReceipt.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\MockSignedN8nAckReceipt;
use ImagineAI\Convergence\PgSignedExecutorAckLab;
use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P35_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgRevocationBoundCycleLab($db);
$cycle->createTables();
// Earlier P34 test runs in this same disposable CI database.
// Isolate fresh synthetic authority fixtures before the signature suite.
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$authority=$cycle->authority();
$bound=new PgSignedExecutorAckLab($db);
$pair=sodium_crypto_sign_keypair();
$private=sodium_crypto_sign_secretkey($pair);
$public=sodium_crypto_sign_publickey($pair);
$now=P18Fixture::NOW;
$checks=0;

function p35Check(bool $value,string $label):void{
    global $checks;$checks++;
    if(!$value)throw new RuntimeException('P35_FAIL_'.$label);
}
function p35Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):void{
    [$body,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$cycle->stage($body,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p35Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p35Fixture(string $tenant,string $event,string $worker,
    int $attempt,int $issued,int $expires,string $private):array {
    $receipt=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V1',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>$attempt,'issued_at'=>$issued,'expires_at'=>$expires,
        'result'=>'NO_AI_COMPLETED_LAB',
    ];
    $raw=json_encode($receipt,
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$private))];
}
function p35Ack(PgSignedExecutorAckLab $bound,
    string $raw,string $signature,string $pub,
    string $tenant,string $event,string $worker,
    int $attempt,int $now,int $revision):array {
    $r=$bound->acknowledgeSigned($raw,$signature,$pub,
        $tenant,$event,$worker,$attempt,$now,$revision);
    p35Check($r['ai_calls']===0&&$r['service_effects']===0,'zero_external_calls');
    return $r;
}
try {
    $a='SYNTHETIC_TENANT_A';
    $b='SYNTHETIC_TENANT_B';
    $worker='SYNTHETIC_P35_WORKER';
    p35Check($authority->seedActive($a),'seed_A');
    p35Check($authority->seedActive($b),'seed_B');

    p35Stage($cycle,$a,'SYNTHETIC_P35_GOOD');
    p35Check($cycle->reserve($a,$worker,$now,1)['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY',
        'positive_lease');
    [$raw,$sig]=p35Fixture($a,'SYNTHETIC_P35_GOOD',$worker,1,$now,$now+60,$private);
    p35Check(p35Ack($bound,$raw,$sig,$public,$a,'SYNTHETIC_P35_GOOD',
        $worker,1,$now+1,1)['decision']==='LAB_SIGNED_ACK_RECORDED_NO_N8N_DELIVERY_PROOF',
        'signed_ack_synthetic');
    p35Check(p35Ack($bound,$raw,$sig,$public,$a,'SYNTHETIC_P35_GOOD',
        $worker,1,$now+1,1)['decision']==='DENY','duplicate_signature_cannot_ack_twice');

    p35Stage($cycle,$a,'SYNTHETIC_P35_ADVERSARIAL');
    p35Check($cycle->reserve($a,$worker,$now,1)['attempt']===1,'adversarial_lease');
    [$valid,$validSig]=p35Fixture($a,'SYNTHETIC_P35_ADVERSARIAL',
        $worker,1,$now,$now+60,$private);

    $tampered=json_decode($valid,true,5,JSON_THROW_ON_ERROR);
    $tampered['event_ref']='SYNTHETIC_P35_SPOOFED';
    $altered=json_encode($tampered,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    p35Check(p35Ack($bound,$altered,$validSig,$public,$a,'SYNTHETIC_P35_ADVERSARIAL',
        $worker,1,$now+1,1)['decision']==='DENY','changed_receipt_denied');
    p35Check(p35Ack($bound,$valid,$validSig,$public,$b,'SYNTHETIC_P35_ADVERSARIAL',
        $worker,1,$now+1,1)['decision']==='DENY','wrong_tenant_denied');
    p35Check(p35Ack($bound,$valid,$validSig,$public,$a,'SYNTHETIC_P35_ADVERSARIAL',
        'SYNTHETIC_P35_FORGED',1,$now+1,1)['decision']==='DENY','wrong_worker_denied');
    p35Check(p35Ack($bound,$valid,$validSig,$public,$a,'SYNTHETIC_P35_ADVERSARIAL',
        $worker,2,$now+1,1)['decision']==='DENY','wrong_attempt_denied');

    $untrustedPair=sodium_crypto_sign_keypair();
    $otherPrivate=sodium_crypto_sign_secretkey($untrustedPair);
    [$sameBody,$untrustedSig]=p35Fixture($a,'SYNTHETIC_P35_ADVERSARIAL',
        $worker,1,$now,$now+60,$otherPrivate);
    p35Check(p35Ack($bound,$sameBody,$untrustedSig,$public,$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+1,1)['decision']==='DENY',
        'foreign_ed25519_key_denied');
    p35Check(p35Ack($bound,$valid,'',$public,$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+1,1)['decision']==='DENY',
        'unsigned_ack_denied');
    p35Check(p35Ack($bound,$valid,$validSig,'',$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+1,1)['decision']==='DENY',
        'untrusted_public_key_missing');
    p35Check(p35Ack($bound,$valid,$validSig,$public,$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+60,1)['decision']==='DENY',
        'expired_receipt_denied');

    $extra=json_decode($valid,true,5,JSON_THROW_ON_ERROR);
    $extra['external_authorization']=true;
    $extraBody=json_encode($extra,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    $extraSig=base64_encode(sodium_crypto_sign_detached($extraBody,$private));
    p35Check(p35Ack($bound,$extraBody,$extraSig,$public,$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+1,1)['decision']==='DENY',
        'additional_unreviewed_authority_field_denied');

    // Even a perfectly signed test receipt cannot override tenant revocation.
    p35Check($authority->revoke($a)===2,'A_revoked');
    $revoked=p35Ack($bound,$valid,$validSig,$public,$a,
        'SYNTHETIC_P35_ADVERSARIAL',$worker,1,$now+1,1);
    p35Check($revoked['decision']==='DENY'
        && $revoked['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE',
        'valid_signature_cannot_override_revocation');

    p35Stage($cycle,$b,'SYNTHETIC_P35_B');
    p35Check($cycle->reserve($b,'SYNTHETIC_P35_B_WORKER',$now,1)['attempt']===1,
        'tenant_B_still_active');
    [$br,$bs]=p35Fixture($b,'SYNTHETIC_P35_B',
        'SYNTHETIC_P35_B_WORKER',1,$now,$now+60,$private);
    p35Check(p35Ack($bound,$br,$bs,$public,$b,'SYNTHETIC_P35_B',
        'SYNTHETIC_P35_B_WORKER',1,$now+1,1)
        ['decision']==='LAB_SIGNED_ACK_RECORDED_NO_N8N_DELIVERY_PROOF',
        'tenant_B_signed_ack');

    echo "AIA_P35_SIGNED_EXECUTOR_ACK_PASS checks={$checks} ed25519=true revision_bound=true real_n8n=false ai_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
