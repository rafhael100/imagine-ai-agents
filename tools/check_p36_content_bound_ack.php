<?php
declare(strict_types=1);
require_once __DIR__.'/labs/MockContentBoundExecutorReceiptV2.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgContentBoundExecutorAckLab;
use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P36_DISPOSABLE_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgRevocationBoundCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$ack=new PgContentBoundExecutorAckLab($db);
$pair=sodium_crypto_sign_keypair();
$private=sodium_crypto_sign_secretkey($pair);
$public=sodium_crypto_sign_publickey($pair);
$now=P18Fixture::NOW;
$checks=0;
function p36Check(bool $condition,string $reason):void {
    global $checks; $checks++;
    if(!$condition)throw new RuntimeException('P36_FAIL_'.$reason);
}
function p36Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):void {
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$cycle->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p36Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p36Hash(PDO $db,string $tenant,string $event):string {
    $s=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
      WHERE tenant_ref=:t AND event_ref=:e');
    $s->execute([':t'=>$tenant,':e'=>$event]);
    $h=$s->fetchColumn();
    if(!is_string($h) || preg_match('/\A[a-f0-9]{64}\z/D',$h)!==1)
        throw new RuntimeException('P36_HASH_FIXTURE_MISSING');
    return $h;
}
function p36Sign(string $private,string $tenant,string $event,string $worker,
    int $attempt,int $revision,string $hash,int $now,array $changes=[]):array {
    $p=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>$attempt,'authority_revision'=>$revision,
        'payload_hash'=>$hash,'issued_at'=>$now,'expires_at'=>$now+60,
        'result'=>'NO_AI_COMPLETED_LAB',
    ];
    $p=array_replace($p,$changes);
    $bytes=json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|
        JSON_UNESCAPED_UNICODE);
    return [$bytes,base64_encode(sodium_crypto_sign_detached($bytes,$private))];
}
function p36Ack(PgContentBoundExecutorAckLab $ack,string $raw,string $sig,
    string $pub,string $tenant,string $event,string $worker,
    int $attempt,int $now,int $revision):array {
    $r=$ack->acknowledgeSignedV2(
        $raw,$sig,$pub,$tenant,$event,$worker,$attempt,$now,$revision
    );
    p36Check($r['ai_calls']===0 && $r['service_effects']===0,'zero_external_effects');
    return $r;
}
function p36State(PDO $db,string $tenant,string $event):string {
    $s=$db->prepare('SELECT state FROM public.aia_lab_n8n_outbox_p6
      WHERE tenant_ref=:t AND event_ref=:e');
    $s->execute([':t'=>$tenant,':e'=>$event]);
    return (string)$s->fetchColumn();
}
try {
    $a='SYNTHETIC_TENANT_A'; $b='SYNTHETIC_TENANT_B';
    $worker='SYNTHETIC_P36_WORKER';
    p36Check($cycle->authority()->seedActive($a),'seed_A');
    p36Check($cycle->authority()->seedActive($b),'seed_B');
    p36Stage($cycle,$a,'SYNTHETIC_P36_GOOD');
    $lease=$cycle->reserve($a,$worker,$now,1);
    p36Check($lease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_good');
    [$raw,$sig]=p36Sign($private,$a,'SYNTHETIC_P36_GOOD',$worker,1,1,
        p36Hash($db,$a,'SYNTHETIC_P36_GOOD'),$now);
    p36Check(p36Ack($ack,$raw,$sig,$public,$a,'SYNTHETIC_P36_GOOD',
        $worker,1,$now+1,1)['decision']==='LAB_V2_ACKED_NO_EXTERNAL_DELIVERY_PROOF',
        'valid_content_bound_ack');
    p36Check(p36Ack($ack,$raw,$sig,$public,$a,'SYNTHETIC_P36_GOOD',
        $worker,1,$now+1,1)['decision']==='DENY','duplicate_receipt_rejected');

    p36Stage($cycle,$a,'SYNTHETIC_P36_PAYLOAD');
    p36Check($cycle->reserve($a,$worker,$now,1)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_payload');
    $goodhash=p36Hash($db,$a,'SYNTHETIC_P36_PAYLOAD');
    [$badHash,$badHashSig]=p36Sign($private,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,1,str_repeat('a',64),$now);
    $r=p36Ack($ack,$badHash,$badHashSig,$public,$a,
        'SYNTHETIC_P36_PAYLOAD',$worker,1,$now+1,1);
    p36Check($r['reason']==='SIGNED_RECEIPT_PAYLOAD_HASH_MISMATCH',
        'valid_signature_wrong_hash_denied_under_lock');
    p36Check(p36State($db,$a,'SYNTHETIC_P36_PAYLOAD')==='LEASED',
        'wrong_hash_cannot_mutate_ack_state');

    [$right,$rightSig]=p36Sign($private,$a,'SYNTHETIC_P36_PAYLOAD',$worker,1,1,
        $goodhash,$now);
    p36Check(p36Ack($ack,$right,$rightSig,$public,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,2)['decision']==='DENY',
        'signed_epoch1_cannot_claim_epoch2');
    [$wrongEpoch,$wrongEpochSig]=p36Sign($private,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,2,$goodhash,$now);
    p36Check(p36Ack($ack,$wrongEpoch,$wrongEpochSig,$public,$a,
        'SYNTHETIC_P36_PAYLOAD',$worker,1,$now+1,1)['decision']==='DENY',
        'different_signed_epoch_denied');
    $altered=json_decode($right,true,8,JSON_THROW_ON_ERROR);
    $altered['payload_hash']=str_repeat('b',64);
    $modified=json_encode($altered,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    p36Check(p36Ack($ack,$modified,$rightSig,$public,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,1)['decision']==='DENY',
        'altered_payload_breaks_signature');
    p36Check(p36Ack($ack,$right,'',$public,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,1)['decision']==='DENY','no_signature_denied');
    p36Check(p36Ack($ack,$right,$rightSig,'',$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,1)['decision']==='DENY','missing_trusted_key_denied');

    $extra=json_decode($right,true,8,JSON_THROW_ON_ERROR);
    $extra['service_authorized']=true;
    [$extraJson,$extraSig]=p36Sign($private,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,1,$goodhash,$now,['service_authorized'=>true]);
    p36Check(p36Ack($ack,$extraJson,$extraSig,$public,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,1)['decision']==='DENY','extra_authority_field_denied');
    p36Check(p36Ack($ack,$right,$rightSig,$public,$a,'SYNTHETIC_P36_PAYLOAD',
        'SYNTHETIC_OTHER_WORKER',1,$now+1,1)['decision']==='DENY',
        'other_worker_denied');

    p36Check($cycle->authority()->revoke($a)===2,'revoked_after_lease');
    p36Check(p36Ack($ack,$right,$rightSig,$public,$a,'SYNTHETIC_P36_PAYLOAD',
        $worker,1,$now+1,1)['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE',
        'valid_receipt_denied_after_revocation');

    p36Stage($cycle,$b,'SYNTHETIC_P36_B');
    p36Check($cycle->reserve($b,'SYNTHETIC_P36_B_WORKER',$now,1)['attempt']===1,
        'tenant_B_lease');
    [$rb,$sb]=p36Sign($private,$b,'SYNTHETIC_P36_B','SYNTHETIC_P36_B_WORKER',
        1,1,p36Hash($db,$b,'SYNTHETIC_P36_B'),$now);
    p36Check(p36Ack($ack,$rb,$sb,$public,$b,'SYNTHETIC_P36_B',
        'SYNTHETIC_P36_B_WORKER',1,$now+1,1)['decision']
        ==='LAB_V2_ACKED_NO_EXTERNAL_DELIVERY_PROOF','tenant_B_ack_independent');

    echo "AIA_P36_CONTENT_BOUND_ACK_PASS checks={$checks} canonical_hash_bound=1 epoch_bound=1 revocation_denies=1 real_n8n=false NO_GO\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
