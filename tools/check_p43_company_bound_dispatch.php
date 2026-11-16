<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgCompanyBoundSignedDispatchLab.php';
require_once __DIR__.'/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgCompanyBoundSignedDispatchLab;
use ImagineAI\Convergence\PgTenantAuthorityLab;
use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P43_DISPOSABLE_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgAtomicCompanyTenantStageLab($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p42_lab.consumed_transport_proofs');
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$authority=new PgTenantAuthorityLab($db);
$authority->createTables();
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$keys=new PgSyntheticSignerRegistryLab($db);
$keys->createTables();
$db->exec('TRUNCATE TABLE aia_p37_lab.executor_keys');
$evidence=new PgSyntheticAckEvidenceLab($db);
$evidence->createTables();
$dispatch=new PgCompanyBoundSignedDispatchLab($db);

$checks=0;
function p43Check(bool $ok,string $label):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P43_FAIL_'.$label);
}
function p43Stage(PgAtomicCompanyTenantStageLab $stager,
    string $company,string $tenant,array $p7,string $event,int $now):void{
    [$canonical,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$stager->stageFromAdmittedTransport(
        $company,$tenant,$p7['request_id'],$p7['nonce'],$p7['request_digest'],
        1,$now,$canonical,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen
    );
    p43Check($r['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY',
        'atomic_stage_'.$event);
}
function p43Hash(PDO $db,string $tenant,string $event):string{
    $q=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
        WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    $r=$q->fetchColumn();
    if(!is_string($r))throw new RuntimeException('P43_HASH_MISSING');
    return $r;
}
function p43Signed(string $secret,string $tenant,string $event,string $worker,
    string $hash,int $when):array{
    $fields=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>1,'authority_revision'=>1,
        'payload_hash'=>$hash,'issued_at'=>$when,'expires_at'=>$when+60,
        'result'=>'NO_AI_COMPLETED_LAB'
    ];
    $canonical=json_encode($fields,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
        |JSON_UNESCAPED_UNICODE);
    return [$canonical,base64_encode(sodium_crypto_sign_detached($canonical,$secret))];
}
function p43State(PDO $db,string $tenant,string $event):array{
    $q=$db->prepare('SELECT state,attempts FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref=:t AND event_ref=:e');
    $q->execute([':t'=>$tenant,':e'=>$event]);
    return $q->fetch(PDO::FETCH_ASSOC)?:[];
}
try{
    $company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $tenant='SYNTHETIC_TENANT_A';
    $worker='SYNTHETIC_WORKER_P43';
    $key='SYNTHETIC_KEY_P43_EXECUTOR';
    $now=time();
    $proofs=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest,issued_at
 FROM central_auto_lab.mtls_nonce_ledger
 WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
 ORDER BY request_id LIMIT 3
SQL)->fetchAll(PDO::FETCH_ASSOC);
    p43Check(count($proofs)===3,'P7_mtls_proofs_available');
    p43Check($stager->bridge()->seedLabBinding($company,$tenant),'company_binding');
    p43Check($authority->seedActive($tenant),'tenant_active');
    $pair=sodium_crypto_sign_keypair();
    $private=sodium_crypto_sign_secretkey($pair);
    $public=sodium_crypto_sign_publickey($pair);
    p43Check($keys->registerActive($tenant,$key,$public),'signer_registered');

    p43Stage($stager,$company,$tenant,$proofs[0],'SYNTHETIC_P43_A00',$now);
    $r=$dispatch->reserve($company,$tenant,$worker,P18Fixture::NOW,1,1);
    p43Check($r['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        &&$r['event_ref']==='SYNTHETIC_P43_A00','company_bound_lease');
    [$raw,$sig]=p43Signed($private,$tenant,'SYNTHETIC_P43_A00',
        $worker,p43Hash($db,$tenant,'SYNTHETIC_P43_A00'),P18Fixture::NOW);
    $ack=$dispatch->acknowledgeSigned(
        $raw,$sig,$company,$tenant,'SYNTHETIC_P43_A00',
        $worker,1,P18Fixture::NOW+1,1,1,$key
    );
    p43Check($ack['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK',
        'company_bound_signed_ack_pass');
    p43Check($ack['ai_calls']===0 &&$ack['service_effects']===0,
        'no_service_effects');
    p43Check($evidence->countEvidence()===1,'signed_evidence_persisted');

    p43Stage($stager,$company,$tenant,$proofs[1],'SYNTHETIC_P43_A01',$now);
    p43Stage($stager,$company,$tenant,$proofs[2],'SYNTHETIC_P43_A02',$now);
    $r=$dispatch->reserve($company,$tenant,$worker,P18Fixture::NOW,1,1);
    p43Check($r['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        &&$r['event_ref']==='SYNTHETIC_P43_A01','lease_before_revocation');
    [$lateRaw,$lateSig]=p43Signed($private,$tenant,'SYNTHETIC_P43_A01',
        $worker,p43Hash($db,$tenant,'SYNTHETIC_P43_A01'),P18Fixture::NOW);

    p43Check($dispatch->reserve($company,$tenant,'SYNTHETIC_BAD_SCOPE',
        P18Fixture::NOW,1,2)['decision']==='DENY',
        'wrong_company_revision_fails_before_lease');
    $db->exec("UPDATE aia_p42_lab.consumed_transport_proofs
      SET payload_hash='".str_repeat('a',64)."'
      WHERE event_ref='SYNTHETIC_P43_A02'");
    $mismatch=$dispatch->reserve($company,$tenant,'SYNTHETIC_WORKER_ALT',
        P18Fixture::NOW,1,1);
    p43Check($mismatch['reason']==='COMPANY_EVENT_TRANSPORT_PROOF_INVALID',
        'tampered_P42_proof_denies_lease');
    p43Check(p43State($db,$tenant,'SYNTHETIC_P43_A02')===[
        'state'=>'PENDING','attempts'=>0
    ],'proof_mismatch_does_not_increment_attempt');
    $repair=$db->prepare("UPDATE aia_p42_lab.consumed_transport_proofs
      SET payload_hash=:hash WHERE event_ref='SYNTHETIC_P43_A02'");
    $repair->execute([':hash'=>p43Hash($db,$tenant,'SYNTHETIC_P43_A02')]);

    p43Check($stager->bridge()->revoke($company)===2,
        'revoke_company_after_stage_and_one_lease');
    $denied=$dispatch->acknowledgeSigned(
        $lateRaw,$lateSig,$company,$tenant,'SYNTHETIC_P43_A01',
        $worker,1,P18Fixture::NOW+1,1,1,$key
    );
    p43Check($denied['reason']==='COMPANY_MAPPING_REVOKED_OR_STALE',
        'signed_ACK_denied_after_company_mapping_revoked');
    p43Check(p43State($db,$tenant,'SYNTHETIC_P43_A01')['state']==='LEASED',
        'revoked_ack_keeps_unacked');
    $deniedLease=$dispatch->reserve(
        $company,$tenant,'SYNTHETIC_WORKER_ALT',P18Fixture::NOW,1,1
    );
    p43Check($deniedLease['reason']==='COMPANY_MAPPING_REVOKED_OR_STALE',
        'pending_event_denied_after_mapping_revocation');
    p43Check(p43State($db,$tenant,'SYNTHETIC_P43_A02')===[
        'state'=>'PENDING','attempts'=>0
    ],'post_revocation_no_lease');
    p43Check($evidence->countEvidence()===1,'no_false_signed_ack_evidence');
    p43Check(PgCompanyBoundSignedDispatchLab::operationalReadiness()['decision']
        ==='NO_GO','never_operational_authorization');

    echo "AIA_P43_COMPANY_BOUND_DISPATCH_PASS checks={$checks} post_stage_revoke_blocks_lease=1 post_lease_revoke_blocks_ack=1 evidence_atomic=1 no_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $evidence->dropTables();
    $outbox->dropTables();
}
