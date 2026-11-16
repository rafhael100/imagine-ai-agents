<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticAckEvidenceLab.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\PgAtomicSignedEvidenceAckLab;
use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')throw new RuntimeException('P38_CI_ONLY');
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
$evidence=new PgSyntheticAckEvidenceLab($db);
$evidence->createTables();
$ack=new PgAtomicSignedEvidenceAckLab($db);
$pair=sodium_crypto_sign_keypair();
$public=sodium_crypto_sign_publickey($pair);
$secret=sodium_crypto_sign_secretkey($pair);
$now=P18Fixture::NOW;
$checks=0;
function p38Check(bool $ok,string $name):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P38_FAIL_'.$name);
}
function p38Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):void{
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$cycle->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p38Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p38Hash(PDO $db,string $tenant,string $event):string {
    $s=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
      WHERE tenant_ref=:t AND event_ref=:e');
    $s->execute([':t'=>$tenant,':e'=>$event]);
    $v=$s->fetchColumn();
    if(!is_string($v))throw new RuntimeException('P38_HASH_NOT_FOUND');
    return $v;
}
function p38Receipt(string $secret,string $tenant,string $event,string $worker,
    int $revision,string $hash,int $now):array {
    $r=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR','audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>1,'authority_revision'=>$revision,'payload_hash'=>$hash,
        'issued_at'=>$now,'expires_at'=>$now+60,'result'=>'NO_AI_COMPLETED_LAB',
    ];
    $canonical=json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|
        JSON_UNESCAPED_UNICODE);
    return [$canonical,base64_encode(sodium_crypto_sign_detached($canonical,$secret))];
}
function p38Ack(PgAtomicSignedEvidenceAckLab $ack,string $raw,string $sig,
    string $tenant,string $event,string $worker,int $revision,string $key):array {
    $result=$ack->acknowledgeWithAtomicEvidence(
        $raw,$sig,$tenant,$event,$worker,1,P18Fixture::NOW+1,$revision,$key
    );
    p38Check($result['ai_calls']===0 && $result['service_effects']===0,
        'ack_zero_external_effects');
    return $result;
}
function p38State(PDO $db,string $tenant,string $event):string {
    $s=$db->prepare('SELECT state FROM public.aia_lab_n8n_outbox_p6
      WHERE tenant_ref=:t AND event_ref=:e');
    $s->execute([':t'=>$tenant,':e'=>$event]);
    return (string)$s->fetchColumn();
}
try{
    $a='SYNTHETIC_TENANT_A';$b='SYNTHETIC_TENANT_B';
    $worker='SYNTHETIC_WORKER_P38';
    $key='SYNTHETIC_EXECUTOR_SIGNER';
    p38Check($cycle->authority()->seedActive($a),'seed_A');
    p38Check($cycle->authority()->seedActive($b),'seed_B');
    p38Check($registry->registerActive($a,$key,$public),'register_A_signer');
    p38Check($registry->registerActive($b,$key,$public),'register_B_signer');

    p38Stage($cycle,$a,'SYNTHETIC_P38_GOOD');
    p38Check($cycle->reserve($a,$worker,$now,1)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','good_lease');
    [$raw,$sig]=p38Receipt($secret,$a,'SYNTHETIC_P38_GOOD',$worker,1,
        p38Hash($db,$a,'SYNTHETIC_P38_GOOD'),$now);
    p38Check(p38Ack($ack,$raw,$sig,$a,'SYNTHETIC_P38_GOOD',$worker,1,$key)
        ['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK','atomic_ack_positive');
    p38Check($evidence->countEvidence()===1,'exactly_one_evidence');
    $q=$db->query("SELECT canonical_receipt,signature_b64,receipt_sha256,
            key_fingerprint,payload_hash
        FROM aia_p38_lab.signed_ack_evidence
        WHERE event_ref='SYNTHETIC_P38_GOOD'")->fetch(PDO::FETCH_ASSOC);
    p38Check(is_array($q)
        &&$q['canonical_receipt']===$raw
        &&$q['signature_b64']===$sig
        &&hash_equals(hash('sha256',$raw),$q['receipt_sha256'])
        &&hash_equals(hash('sha256',$public),$q['key_fingerprint'])
        &&hash_equals(p38Hash($db,$a,'SYNTHETIC_P38_GOOD'),$q['payload_hash']),
        'durable_signed_bytes_match');
    p38Check(p38Ack($ack,$raw,$sig,$a,'SYNTHETIC_P38_GOOD',$worker,1,$key)
        ['decision']==='DENY','no_second_ack');
    p38Check($evidence->countEvidence()===1,'no_duplicate_evidence');

    p38Stage($cycle,$a,'SYNTHETIC_P38_INVALID_HASH');
    p38Check($cycle->reserve($a,$worker,$now,1)['attempt']===1,'invalid_hash_lease');
    [$wrong,$wrongSig]=p38Receipt($secret,$a,'SYNTHETIC_P38_INVALID_HASH',
        $worker,1,str_repeat('a',64),$now);
    p38Check(p38Ack($ack,$wrong,$wrongSig,$a,
        'SYNTHETIC_P38_INVALID_HASH',$worker,1,$key)
        ['reason']==='SIGNED_RECEIPT_PAYLOAD_HASH_MISMATCH',
        'valid_sig_wrong_payload_no_ack');
    p38Check(p38State($db,$a,'SYNTHETIC_P38_INVALID_HASH')==='LEASED',
        'bad_hash_does_not_mutate_queue');
    p38Check($evidence->countEvidence()===1,'bad_hash_no_evidence');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6
        WHERE event_ref='SYNTHETIC_P38_INVALID_HASH'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay
        WHERE event_ref='SYNTHETIC_P38_INVALID_HASH'");

    p38Stage($cycle,$a,'SYNTHETIC_P38_EVIDENCE_FAILURE');
    p38Check($cycle->reserve($a,$worker,$now,1)['attempt']===1,'fault_lease');
    [$pending,$pendingSig]=p38Receipt($secret,$a,'SYNTHETIC_P38_EVIDENCE_FAILURE',
        $worker,1,p38Hash($db,$a,'SYNTHETIC_P38_EVIDENCE_FAILURE'),$now);
    $evidence->dropTables();
    $noEvidence=p38Ack($ack,$pending,$pendingSig,$a,
        'SYNTHETIC_P38_EVIDENCE_FAILURE',$worker,1,$key);
    p38Check($noEvidence['decision']==='DENY',
        'missing_evidence_storage_causes_deny');
    p38Check(p38State($db,$a,'SYNTHETIC_P38_EVIDENCE_FAILURE')==='LEASED',
        'ack_rolled_back_when_evidence_missing');
    $evidence->createTables();
    p38Check(p38Ack($ack,$pending,$pendingSig,$a,
        'SYNTHETIC_P38_EVIDENCE_FAILURE',$worker,1,$key)
        ['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK',
        'retry_succeeds_after_test_table_restore');
    p38Check($evidence->countEvidence()===1,
        'retry_creates_exactly_one_atomic_evidence');

    p38Stage($cycle,$b,'SYNTHETIC_P38_TENANT_B');
    p38Check($cycle->reserve($b,'SYNTHETIC_WORKER_B',$now,1)['attempt']===1,
        'B_lease');
    [$br,$bs]=p38Receipt($secret,$b,'SYNTHETIC_P38_TENANT_B',
        'SYNTHETIC_WORKER_B',1,p38Hash($db,$b,'SYNTHETIC_P38_TENANT_B'),$now);
    p38Check(p38Ack($ack,$br,$bs,$b,'SYNTHETIC_P38_TENANT_B',
        'SYNTHETIC_WORKER_B',1,$key)
        ['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK','B_independent');
    p38Check($evidence->countEvidence()===2,'B_evidence_written');

    echo "AIA_P38_SIGNED_EVIDENCE_PASS checks={$checks} rollback_without_evidence=true signed_ack_atomic=true real_n8n=false NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $evidence->dropTables();
    $outbox->dropTables();
}
