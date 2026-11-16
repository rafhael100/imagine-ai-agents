<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticSignerRegistryLab.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgRegistryBoundExecutorAckLab;
use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P37_DISPOSABLE_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgRevocationBoundCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$registry=new PgSyntheticSignerRegistryLab($db);
$registry->createTables();
$db->exec('TRUNCATE TABLE aia_p37_lab.executor_keys');
$ack=new PgRegistryBoundExecutorAckLab($db);
$pair1=sodium_crypto_sign_keypair();
$priv1=sodium_crypto_sign_secretkey($pair1);
$pub1=sodium_crypto_sign_publickey($pair1);
$pair2=sodium_crypto_sign_keypair();
$priv2=sodium_crypto_sign_secretkey($pair2);
$pub2=sodium_crypto_sign_publickey($pair2);
$pairB=sodium_crypto_sign_keypair();
$privB=sodium_crypto_sign_secretkey($pairB);
$pubB=sodium_crypto_sign_publickey($pairB);
$checks=0;$now=P18Fixture::NOW;
function p37Check(bool $ok,string $name):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P37_FAIL_'.$name);
}
function p37Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):void {
    [$canonical,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$cycle->stage($canonical,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p37Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p37Hash(PDO $db,string $tenant,string $event):string {
    $q=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
      WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    $value=$q->fetchColumn();
    if(!is_string($value))throw new RuntimeException('P37_DIGEST_MISSING');
    return $value;
}
function p37Receipt(string $private,string $tenant,string $event,
    string $worker,int $revision,string $digest,int $now):array {
    $r=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>1,'authority_revision'=>$revision,
        'payload_hash'=>$digest,'issued_at'=>$now,'expires_at'=>$now+60,
        'result'=>'NO_AI_COMPLETED_LAB',
    ];
    $raw=json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|
        JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$private))];
}
function p37Ack(PgRegistryBoundExecutorAckLab $ack,string $raw,string $sig,
    string $tenant,string $event,string $worker,int $revision,string $keyId):array {
    $r=$ack->acknowledgeWithRegisteredSigner(
        $raw,$sig,$tenant,$event,$worker,1,P18Fixture::NOW+1,$revision,$keyId
    );
    p37Check($r['ai_calls']===0 && $r['service_effects']===0,'zero_effects');
    return $r;
}
function p37State(PDO $db,string $tenant,string $event):string {
    $q=$db->prepare('SELECT state FROM public.aia_lab_n8n_outbox_p6
      WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    return (string)$q->fetchColumn();
}
try{
    $a='SYNTHETIC_TENANT_A';$b='SYNTHETIC_TENANT_B';
    $worker='SYNTHETIC_WORKER_P37';
    $old='SYNTHETIC_SIGNER_V1';$new='SYNTHETIC_SIGNER_V2';
    p37Check($cycle->authority()->seedActive($a),'seed_tenant_A');
    p37Check($cycle->authority()->seedActive($b),'seed_tenant_B');
    p37Check($registry->registerActive($a,$old,$pub1),'register_signer1');
    p37Check(!$registry->registerActive($a,$old,$pub1),'repeated_register_denied');
    p37Check(!$registry->registerActive($a,'SYNTHETIC_DUPLICATE_KEY',$pub1),
        'duplicate_public_key_denied');
    p37Check(!$registry->registerActive($a,'SYNTHETIC_INVALID','short'),
        'wrong_length_key_denied');
    p37Check($registry->registerActive($b,$old,$pubB),'register_other_tenant');

    p37Stage($cycle,$a,'SYNTHETIC_P37_ROTATE');
    p37Check($cycle->reserve($a,$worker,$now,1)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_before_rotation');
    [$raw1,$sig1]=p37Receipt($priv1,$a,'SYNTHETIC_P37_ROTATE',
        $worker,1,p37Hash($db,$a,'SYNTHETIC_P37_ROTATE'),$now);
    p37Check($registry->rotate($a,$old,$new,$pub2),'atomic_key_rotation');
    p37Check($registry->snapshot($a,$old)['active']===false
        &&$registry->snapshot($a,$old)['revision']===2,'old_key_revoked');
    p37Check($registry->resolveActive($a,$old)===null,'old_key_never_resolves');
    p37Check($registry->resolveActive($a,$new)===$pub2,'new_key_resolves');
    p37Check(p37Ack($ack,$raw1,$sig1,$a,'SYNTHETIC_P37_ROTATE',
        $worker,1,$old)['reason']==='SIGNER_NOT_ACTIVE_OR_TRUSTED',
        'old_receipt_rejected_after_rotation');
    p37Check(p37State($db,$a,'SYNTHETIC_P37_ROTATE')==='LEASED',
        'old_receipt_does_not_ack');
    p37Check(p37Ack($ack,$raw1,$sig1,$a,'SYNTHETIC_P37_ROTATE',
        $worker,1,$new)['decision']==='DENY',
        'old_signature_invalid_with_new_public_key');
    [$raw2,$sig2]=p37Receipt($priv2,$a,'SYNTHETIC_P37_ROTATE',
        $worker,1,p37Hash($db,$a,'SYNTHETIC_P37_ROTATE'),$now);
    p37Check(p37Ack($ack,$raw2,$sig2,$a,'SYNTHETIC_P37_ROTATE',
        $worker,1,$new)['decision']==='LAB_TRUSTED_SIGNER_ACK_NO_DELIVERY',
        'new_key_acks');
    p37Check(!$registry->rotate($a,$old,'SYNTHETIC_SIGNER_V3',$pub1),
        'revoked_old_key_cannot_rotate');
    p37Check(!$registry->registerActive($a,$old,$pub1),'old_key_id_not_reused');
    p37Check(!$registry->registerActive($a,'SYNTHETIC_REUSE_KEY',$pub1),
        'old_pubkey_not_renamed');

    p37Stage($cycle,$a,'SYNTHETIC_P37_REVOKED');
    p37Check($cycle->reserve($a,$worker,$now,1)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_before_key_revoke');
    [$pending,$pendingSig]=p37Receipt($priv2,$a,'SYNTHETIC_P37_REVOKED',
        $worker,1,p37Hash($db,$a,'SYNTHETIC_P37_REVOKED'),$now);
    p37Check($registry->revoke($a,$new)===2,'revoke_new_key');
    p37Check(p37Ack($ack,$pending,$pendingSig,$a,'SYNTHETIC_P37_REVOKED',
        $worker,1,$new)['decision']==='DENY','valid_math_after_revoke_denied');
    p37Check(p37State($db,$a,'SYNTHETIC_P37_REVOKED')==='LEASED',
        'revoked_signer_leaves_lease_unacked');

    p37Stage($cycle,$b,'SYNTHETIC_P37_OTHER');
    p37Check($cycle->reserve($b,'SYNTHETIC_WORKER_B',$now,1)['attempt']===1,
        'tenant_B_lease');
    [$bRaw,$bSig]=p37Receipt($privB,$b,'SYNTHETIC_P37_OTHER',
        'SYNTHETIC_WORKER_B',1,p37Hash($db,$b,'SYNTHETIC_P37_OTHER'),$now);
    p37Check(p37Ack($ack,$bRaw,$bSig,$b,'SYNTHETIC_P37_OTHER',
        'SYNTHETIC_WORKER_B',1,$old)['decision']
        ==='LAB_TRUSTED_SIGNER_ACK_NO_DELIVERY','tenant_B_not_affected');
    p37Check(p37Ack($ack,$bRaw,$bSig,$a,'SYNTHETIC_P37_OTHER',
        'SYNTHETIC_WORKER_B',1,$old)['decision']==='DENY',
        'cross_tenant_key_lookup_denied');

    // Validate lock serialisation directly: concurrent revocation must wait
    // for the shared signer row lock used by the guarded ACK transaction.
    $db->beginTransaction();
    $lock=$db->prepare('SELECT key_fingerprint FROM aia_p37_lab.executor_keys
        WHERE tenant_ref=:t AND key_id=:k FOR SHARE');
    $lock->execute([':t'=>$b,':k'=>$old]);
    p37Check(is_string($lock->fetchColumn()),'read_lock_created');
    $db2=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db2->exec('SET statement_timeout=250');
    $other=new PgSyntheticSignerRegistryLab($db2);
    p37Check($other->revoke($b,$old)===null,'key_revocation_waits_on_share_lock');
    $db->commit();
    $db2->exec('SET statement_timeout=0');
    p37Check($other->revoke($b,$old)===2,'revocation_commits_after_unlock');
    p37Check($registry->snapshot($b,$old)['active']===false,
        'revocation_persisted');

    echo "AIA_P37_KEY_ROTATION_PASS checks={$checks} revoked_key_denied=1 atomic_rotation=1 key_lock_serialized=1 no_n8n=true NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
