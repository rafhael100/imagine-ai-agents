<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/labs/PgAtomicStagingAuditLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgAtomicStagingAuditLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P42_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$bound=new PgAtomicCompanyTenantStageLab($db);
$bound->createTables();
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$db->exec('TRUNCATE TABLE aia_p42_lab.consumed_transport_proofs');
$outbox=new PgN8nOutboxLab($db);
$auditor=new PgAtomicStagingAuditLab($db);
$checks=0;
function p42Check(bool $ok,string $name):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P42_FAIL_'.$name);
}
function p42Counts(PDO $db):array{
    return [
        (int)$db->query('SELECT COUNT(*) FROM aia_p42_lab.consumed_transport_proofs')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn()
    ];
}
function p42Run(PgAtomicCompanyTenantStageLab $bound,string $company,
    string $tenant,array $proof,string $event,int $transportNow,
    bool $invalidSignature=false):array{
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    if($invalidSignature)$signed['signature']=str_repeat('0',64);
    $seen=[];
    $r=$bound->stageFromAdmittedTransport(
        $company,$tenant,$proof['request_id'],$proof['nonce'],
        $proof['request_digest'],1,$transportNow,
        $p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen
    );
    p42Check($r['ai_calls']===0 &&$r['service_effects']===0,'no_calls_'.$event);
    if($r['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY')
        p42Check(count($seen)===1,'nonce_committed_only_after_db_commit');
    else
        p42Check($seen===[],'nonce_not_committed_on_denial');
    return $r;
}
try{
    $company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $tenant='SYNTHETIC_TENANT_A';
    $other='SYNTHETIC_TENANT_B';
    $now=time();
    $proofs=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest,issued_at
 FROM central_auto_lab.mtls_nonce_ledger
 WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
 ORDER BY request_id LIMIT 3
SQL)->fetchAll(PDO::FETCH_ASSOC);
    p42Check(count($proofs)===3,'three_independent_p7_mtls_admissions');
    foreach($proofs as $row)p42Check((int)$row['issued_at']>=$now-120,
        'fresh_ledger_proof');
    p42Check($bound->bridge()->seedLabBinding($company,$tenant),
        'approved_synthetic_company_tenant_mapping');
    p42Check($bound->bridge()->seedLabBinding(
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',$other),
        'B_mapping_without_admission_fixture');

    $first=p42Run($bound,$company,$tenant,$proofs[0],
        'SYNTHETIC_P42_EVENT_01',$now);
    p42Check($first['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY',
        'first_transport_and_event_commit');
    p42Check(p42Counts($db)===[1,1,1],'one_proof_one_ledger_one_outbox');
    p42Check($auditor->scan()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION',
        'first_event_integrity');

    $repeat=p42Run($bound,$company,$tenant,$proofs[0],
        'SYNTHETIC_P42_DIFFERENT_EVENT',$now);
    p42Check($repeat['reason']==='TRANSPORT_PROOF_ALREADY_CONSUMED',
        'same_transport_cannot_authorize_other_event');
    p42Check(p42Counts($db)===[1,1,1],'no_multiple_events_per_admission');

    $bad=p42Run($bound,$company,$tenant,$proofs[1],
        'SYNTHETIC_P42_EVENT_02',$now,true);
    p42Check($bad['reason']==='EVENT_OR_OUTBOX_DENIED_ROLLED_BACK',
        'invalid_event_signature_denied');
    p42Check(p42Counts($db)===[1,1,1],
        'failed_event_rolls_back_proof_consumption');
    $success=p42Run($bound,$company,$tenant,$proofs[1],
        'SYNTHETIC_P42_EVENT_02',$now);
    p42Check($success['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY',
        'proof_usable_after_failed_uncommitted_attempt');
    p42Check(p42Counts($db)===[2,2,2],
        'second_proof_committed_once');

    $forged=p42Run($bound,$company,$other,$proofs[2],
        'SYNTHETIC_P42_TENANT_B',$now);
    p42Check($forged['decision']==='DENY',
        'other_tenant_cannot_consume_company_A_proof');
    p42Check(p42Counts($db)===[2,2,2],'no_cross_tenant_rows');

    p42Check($bound->bridge()->revoke($company)===2,'mapping_revoked');
    $revoked=p42Run($bound,$company,$tenant,$proofs[2],
        'SYNTHETIC_P42_REVOKED',$now);
    p42Check($revoked['reason']==='MTLS_COMPANY_TENANT_PROOF_DENIED',
        'revocation_before_stage_blocks_proof');
    p42Check(p42Counts($db)===[2,2,2],'revocation_cannot_partially_stage');
    p42Check($auditor->scan()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION',
        'both_committed_events_remain_consistent');

    echo "AIA_P42_ATOMIC_COMPANY_STAGE_PASS checks={$checks} consumed=2 ledger=2 outbox=2 no_credits=1 no_external_dispatch=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
