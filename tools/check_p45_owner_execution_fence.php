<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/labs/PgOwnerBoundSignedDispatchLab.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;
use ImagineAI\Convergence\PgOwnerDecisionBoundStageLab;
use ImagineAI\Convergence\PgOwnerBoundSignedDispatchLab;
use ImagineAI\Convergence\PgTenantAuthorityLab;
use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P45_DISPOSABLE_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgAtomicCompanyTenantStageLab($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p42_lab.consumed_transport_proofs');
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$owner=new PgOwnerDecisionLab($db);
$owner->createTables();
$db->exec('TRUNCATE TABLE aia_p44_lab.consumed_owner_grants');
$db->exec('TRUNCATE TABLE aia_p44_lab.owner_keys');
$authority=new PgTenantAuthorityLab($db);
$authority->createTables();
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$signers=new PgSyntheticSignerRegistryLab($db);
$signers->createTables();
$db->exec('TRUNCATE TABLE aia_p37_lab.executor_keys');
$evidence=new PgSyntheticAckEvidenceLab($db);
$evidence->createTables();
$admit=new PgOwnerDecisionBoundStageLab($db);
$dispatch=new PgOwnerBoundSignedDispatchLab($db);
$now=P18Fixture::NOW;
$checks=0;
function p45Check(bool $ok,string $label):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P45_FAIL_'.$label);
}
function p45OwnerGrant(string $secret,string $company,string $tenant,
    string $event,string $hash,string $grant,string $key,int $now):array{
    $d=[
        'schema'=>'AIA_OWNER_DECISION_V1',
        'issuer'=>'SYNTHETIC_CRM_POLICY_OWNER',
        'audience'=>'imagine-ai-agents',
        'company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,
        'grant_ref'=>$grant,'operation'=>'run.status.project',
        'owner_revision'=>1,'payload_hash'=>$hash,
        'issued_at'=>$now-5,'expires_at'=>$now+60,
        'decision'=>'ALLOW_NO_AI_LAB','key_id'=>$key
    ];
    $canonical=json_encode($d,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$canonical,base64_encode(sodium_crypto_sign_detached($canonical,$secret))];
}
function p45Stage(PgOwnerDecisionBoundStageLab $admit,
    string $company,string $tenant,array $transport,string $event,
    string $secret,string $ownerKey,int $transportNow,int $eventNow):void{
    [$canonical,$envelope,$policy]=P18Fixture::make($tenant,$event);
    [$grant,$sig]=p45OwnerGrant(
        $secret,$company,$tenant,$event,hash('sha256',$canonical),
        'SYNTHETIC_P45_GRANT_'.substr($event,-2),$ownerKey,$eventNow
    );
    $seen=[];
    $r=$admit->stage(
        $company,$tenant,$transport['request_id'],$transport['nonce'],
        $transport['request_digest'],1,$transportNow,
        $canonical,$envelope,$policy,$eventNow,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen,
        $grant,$sig,$ownerKey
    );
    p45Check($r['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY'
        &&count($seen)===1,'owner_signed_atomic_stage_'.$event);
}
function p45Hash(PDO $db,string $tenant,string $event):string{
    $q=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
        WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    $r=$q->fetchColumn();
    if(!is_string($r))throw new RuntimeException('P45_DIGEST_MISSING');
    return $r;
}
function p45ExecutorReceipt(string $secret,string $tenant,string $event,
    string $worker,string $hash,int $now):array{
    $d=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>1,'authority_revision'=>1,
        'payload_hash'=>$hash,'issued_at'=>$now,'expires_at'=>$now+60,
        'result'=>'NO_AI_COMPLETED_LAB'
    ];
    $raw=json_encode($d,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
function p45QueueState(PDO $db,string $tenant,string $event):array{
    $q=$db->prepare('SELECT state,attempts FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    return $q->fetch(PDO::FETCH_ASSOC)?:[];
}
try{
    $company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $tenant='SYNTHETIC_TENANT_A';
    $worker='SYNTHETIC_P45_WORKER';
    $ownerKey='SYNTHETIC_P45_OWNER_KEY';
    $executorKey='SYNTHETIC_P45_EXECUTOR_KEY';
    $transportNow=time();
    $proofs=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest,issued_at
FROM central_auto_lab.mtls_nonce_ledger
WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
ORDER BY request_id LIMIT 3
SQL)->fetchAll(PDO::FETCH_ASSOC);
    p45Check(count($proofs)===3,'three_independent_P7_admissions');
    p45Check($stager->bridge()->seedLabBinding($company,$tenant),'company_mapping');
    p45Check($authority->seedActive($tenant),'tenant_active');
    $ownerPair=sodium_crypto_sign_keypair();
    $ownerPrivate=sodium_crypto_sign_secretkey($ownerPair);
    p45Check($owner->registerKey(
        $company,$ownerKey,sodium_crypto_sign_publickey($ownerPair)),
        'trusted_owner_pubkey_registered'
    );
    $execPair=sodium_crypto_sign_keypair();
    $execPrivate=sodium_crypto_sign_secretkey($execPair);
    p45Check($signers->registerActive(
        $tenant,$executorKey,sodium_crypto_sign_publickey($execPair)),
        'trusted_synthetic_executor_pubkey'
    );

    p45Stage($admit,$company,$tenant,$proofs[0],'SYNTHETIC_P45_A00',
        $ownerPrivate,$ownerKey,$transportNow,$now);
    $lease=$dispatch->reserve($company,$tenant,$worker,$now,1,1);
    p45Check($lease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        &&$lease['event_ref']==='SYNTHETIC_P45_A00','active_grant_allows_lease');
    [$ackRaw,$ackSig]=p45ExecutorReceipt(
        $execPrivate,$tenant,'SYNTHETIC_P45_A00',$worker,
        p45Hash($db,$tenant,'SYNTHETIC_P45_A00'),$now
    );
    $ack=$dispatch->acknowledgeSigned(
        $ackRaw,$ackSig,$company,$tenant,'SYNTHETIC_P45_A00',
        $worker,1,$now+1,1,1,$executorKey
    );
    p45Check($ack['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK',
        'signed_ack_with_live_owner_grant');
    p45Check($evidence->countEvidence()===1,'one_ack_evidence');

    p45Stage($admit,$company,$tenant,$proofs[1],'SYNTHETIC_P45_A01',
        $ownerPrivate,$ownerKey,$transportNow,$now);
    $lease=$dispatch->reserve($company,$tenant,$worker,$now,1,1);
    p45Check($lease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        &&$lease['event_ref']==='SYNTHETIC_P45_A01',
        'owner_valid_before_revocation');
    [$lateRaw,$lateSig]=p45ExecutorReceipt(
        $execPrivate,$tenant,'SYNTHETIC_P45_A01',$worker,
        p45Hash($db,$tenant,'SYNTHETIC_P45_A01'),$now
    );
    p45Stage($admit,$company,$tenant,$proofs[2],'SYNTHETIC_P45_A02',
        $ownerPrivate,$ownerKey,$transportNow,$now);

    $stored=$db->query("SELECT signature_b64 FROM aia_p44_lab.consumed_owner_grants
        WHERE event_ref='SYNTHETIC_P45_A02'")->fetchColumn();
    p45Check(is_string($stored),'signed_owner_grant_persisted');
    $db->exec("UPDATE aia_p44_lab.consumed_owner_grants
        SET signature_b64='".str_repeat('A',88)."'
        WHERE event_ref='SYNTHETIC_P45_A02'");
    $denied=$dispatch->reserve($company,$tenant,'SYNTHETIC_P45_WORKER_OTHER',
        $now,1,1);
    p45Check($denied['reason']==='OWNER_DECISION_REVOKED_EXPIRED_OR_INVALID',
        'tampered_owner_signature_rejected_at_lease');
    p45Check(p45QueueState($db,$tenant,'SYNTHETIC_P45_A02')===[
        'state'=>'PENDING','attempts'=>0
    ],'bad_owner_signature_does_not_lease');
    $fix=$db->prepare("UPDATE aia_p44_lab.consumed_owner_grants
        SET signature_b64=:sig WHERE event_ref='SYNTHETIC_P45_A02'");
    $fix->execute([':sig'=>$stored]);

    $expired=$dispatch->reserve($company,$tenant,
        'SYNTHETIC_P45_WORKER_OTHER',$now+61,1,1);
    p45Check($expired['reason']==='OWNER_DECISION_REVOKED_EXPIRED_OR_INVALID',
        'owner_grant_expiration_checked_at_lease');

    p45Check($owner->revokeKey($company,$ownerKey)===2,
        'owner_key_revoked_after_stage_and_lease');
    $refused=$dispatch->acknowledgeSigned(
        $lateRaw,$lateSig,$company,$tenant,'SYNTHETIC_P45_A01',
        $worker,1,$now+1,1,1,$executorKey
    );
    p45Check($refused['reason']==='OWNER_DECISION_REVOKED_EXPIRED_OR_INVALID',
        'owner_revocation_blocks_valid_executor_signature');
    p45Check(p45QueueState($db,$tenant,'SYNTHETIC_P45_A01')['state']==='LEASED',
        'invalid_owner_cannot_ack');
    $refusedLease=$dispatch->reserve($company,$tenant,
        'SYNTHETIC_P45_WORKER_OTHER',$now,1,1);
    p45Check($refusedLease['reason']==='OWNER_DECISION_REVOKED_EXPIRED_OR_INVALID',
        'owner_revocation_blocks_pending_event');
    p45Check($evidence->countEvidence()===1,
        'revocation_prevents_false_ack_evidence');
    p45Check(PgOwnerBoundSignedDispatchLab::operationalReadiness()['decision']
        ==='NO_GO','never_real_IAM');
    echo "AIA_P45_OWNER_EXECUTION_FENCE_PASS checks={$checks} revoked_after_stage_denies_lease=1 revoked_after_lease_denies_ack=1 expired_grant_denied=1 zero_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $evidence->dropTables();
    $outbox->dropTables();
}
