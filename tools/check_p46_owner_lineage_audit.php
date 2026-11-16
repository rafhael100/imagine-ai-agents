<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/labs/PgTransportOwnerLineageAuditLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;
use ImagineAI\Convergence\PgOwnerDecisionBoundStageLab;
use ImagineAI\Convergence\PgTransportOwnerLineageAuditLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P46_EPHEMERAL_CI_ONLY');
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$atomic=new PgAtomicCompanyTenantStageLab($db);
$atomic->createTables();
$db->exec('TRUNCATE TABLE aia_p42_lab.consumed_transport_proofs');
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$owner=new PgOwnerDecisionLab($db);
$owner->createTables();
$db->exec('TRUNCATE TABLE aia_p44_lab.consumed_owner_grants');
$db->exec('TRUNCATE TABLE aia_p44_lab.owner_keys');
$facade=new PgOwnerDecisionBoundStageLab($db);
$audit=new PgTransportOwnerLineageAuditLab($db);
$checks=0;
function p46Check(bool $v,string $name):void {
    global $checks;$checks++;
    if(!$v)throw new RuntimeException('P46_FAIL_'.$name);
}
function p46Contains(array $report,string $reason):bool{
    return in_array($reason,array_column($report['issues'],'reason'),true);
}
function p46Grant(string $secret,string $company,string $tenant,string $event,
    string $hash,string $grant,string $key):array {
    $now=P18Fixture::NOW;
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
    $raw=json_encode($d,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
function p46Stage(PgOwnerDecisionBoundStageLab $facade,
    string $company,string $tenant,array $transport,string $event,
    string $secret,string $key,int $transportNow):void{
    [$p7,$envelope,$policy]=P18Fixture::make($tenant,$event);
    [$grant,$sig]=p46Grant(
        $secret,$company,$tenant,$event,hash('sha256',$p7),
        'SYNTHETIC_GRANT_'.substr($event,-2),$key
    );
    $seen=[];
    $r=$facade->stage(
        $company,$tenant,$transport['request_id'],$transport['nonce'],
        $transport['request_digest'],1,$transportNow,
        $p7,$envelope,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen,
        $grant,$sig,$key
    );
    p46Check($r['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY'
        &&count($seen)===1,'stage_signed_owner_'.$event);
}
try {
    $company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $tenant='SYNTHETIC_TENANT_A';
    $key='SYNTHETIC_CRM_OWNER_P46';
    $now=time();
    $proofs=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest FROM central_auto_lab.mtls_nonce_ledger
WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
ORDER BY request_id LIMIT 2
SQL)->fetchAll(PDO::FETCH_ASSOC);
    p46Check(count($proofs)===2,'two_authenticated_CI_P7_proofs');
    p46Check($atomic->bridge()->seedLabBinding($company,$tenant),'company_map');
    $pair=sodium_crypto_sign_keypair();
    $secret=sodium_crypto_sign_secretkey($pair);
    $public=sodium_crypto_sign_publickey($pair);
    p46Check($owner->registerKey($company,$key,$public),'owner_pubkey_registered');
    p46Stage($facade,$company,$tenant,$proofs[0],'SYNTHETIC_P46_EVENT_01',
        $secret,$key,$now);
    p46Stage($facade,$company,$tenant,$proofs[1],'SYNTHETIC_P46_EVENT_02',
        $secret,$key,$now);
    $clean=$audit->scan();
    p46Check($clean['decision']==='LAB_HISTORICAL_OWNER_LINEAGE_CONSISTENT_NO_GO'
        &&$clean['checked']===2 &&$clean['issues']===[],
        'both_signed_grant_chains_historically_consistent');
    p46Check($clean['ai_calls']===0&&$clean['external_calls']===0,
        'audit_is_only_read');

    // An owner key revoked AFTER the historical stage is still valid evidence;
    // this scan never re-enables that key as current authorization.
    p46Check($owner->revokeKey($company,$key)===2,'owner_key_revoked_after_stage');
    p46Check($audit->scan()['decision']
        ==='LAB_HISTORICAL_OWNER_LINEAGE_CONSISTENT_NO_GO',
        'historical_signature_still_verifiable_after_revocation');

    $row=$db->query("SELECT signature_b64,payload_hash FROM
        aia_p44_lab.consumed_owner_grants
        WHERE event_ref='SYNTHETIC_P46_EVENT_01'")->fetch(PDO::FETCH_ASSOC);
    p46Check(is_array($row),'original_owner_proof_read');

    $db->exec("UPDATE aia_p44_lab.consumed_owner_grants
      SET signature_b64='".str_repeat('A',88)."'
      WHERE event_ref='SYNTHETIC_P46_EVENT_01'");
    p46Check(p46Contains($audit->scan(),'OWNER_HISTORICAL_SIGNATURE_INVALID'),
        'tampered_grant_signature_found');
    $fix=$db->prepare('UPDATE aia_p44_lab.consumed_owner_grants
        SET signature_b64=:sig WHERE event_ref=:event');
    $fix->execute([':sig'=>$row['signature_b64'],
        ':event'=>'SYNTHETIC_P46_EVENT_01']);
    p46Check($audit->scan()['decision']
        ==='LAB_HISTORICAL_OWNER_LINEAGE_CONSISTENT_NO_GO',
        'signature_restore_reverifies');

    $db->exec("UPDATE aia_p42_lab.consumed_transport_proofs
      SET payload_hash='".str_repeat('a',64)."'
      WHERE event_ref='SYNTHETIC_P46_EVENT_01'");
    p46Check(p46Contains($audit->scan(),'EVENT_LINEAGE_HASH_MISMATCH'),
        'mismatched_transport_payload_hash_found');
    $repair=$db->prepare('UPDATE aia_p42_lab.consumed_transport_proofs
      SET payload_hash=:digest WHERE event_ref=:event');
    $repair->execute([
        ':digest'=>$row['payload_hash'],':event'=>'SYNTHETIC_P46_EVENT_01'
    ]);

    $db->exec("DELETE FROM aia_p44_lab.consumed_owner_grants
      WHERE event_ref='SYNTHETIC_P46_EVENT_02'");
    $report=$audit->scan();
    p46Check($report['decision']==='DENY_INCONSISTENT'
        &&p46Contains($report,'P42_EVENT_WITHOUT_OWNER_GRANT'),
        'legacy_transport_only_event_flagged');
    p46Check((int)$db->query(
        'SELECT COUNT(*) FROM aia_p44_lab.consumed_owner_grants'
    )->fetchColumn()===1,'readonly_audit_did_not_repair_missing_grant');
    $out=json_encode($report,JSON_THROW_ON_ERROR);
    p46Check(!str_contains($out,$row['signature_b64'])
        &&!str_contains($out,'canonical_decision')
        &&!str_contains($out,'public_key_b64'),'no_proof_or_signature_leak');

    $q=$db->prepare('DELETE FROM central_auto_lab.mtls_nonce_ledger
      WHERE company_id=CAST(:company AS uuid) AND request_id=:request AND nonce=:nonce');
    $q->execute([
        ':company'=>$company,
        ':request'=>$proofs[0]['request_id'],
        ':nonce'=>$proofs[0]['nonce'],
    ]);
    p46Check(p46Contains($audit->scan(),'MISSING_ORIGINAL_MTLS_ADMISSION'),
        'lost_CI_transport_ledger_detected');

    $outbox->dropTables();
    p46Check($audit->scan()['decision']==='INDETERMINATE',
        'missing_outbox_schema_never_reports_clean');

    echo "AIA_P46_OWNER_LINEAGE_AUDIT_PASS checks={$checks} grant_gap_detected=1 signed_hash_tamper_detected=1 original_nonce_loss_detected=1 readonly=true NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
