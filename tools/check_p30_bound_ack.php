<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgBoundOutboxAckLab.php';
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgBoundOutboxAckLab;
use ImagineAI\Convergence\PgBoundOutboxLeaseLab;
use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('AIA_P16_PG_LAB')!=='true'||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')throw new RuntimeException('P30_CI_ONLY');
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$lease=new PgBoundOutboxLeaseLab($db);
$ack=new PgBoundOutboxAckLab($db);
$now=P18Fixture::NOW;
$tenant='SYNTHETIC_TENANT_A';
$worker='SYNTHETIC_WORKER_P30';
$checks=0;
function p30Check(bool $ok,string $case):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P30_FAIL_'.$case);
}
function p30Stage(PgSyntheticAtomicStager $stager,string $tenant,string $event):void {
    [$canonical,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$stager->stage($canonical,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p30Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p30Ack(PgBoundOutboxAckLab $ack,string $tenant,string $event,
    string $worker,int $attempt,int $now):array {
    $r=$ack->acknowledge($tenant,$event,$worker,$attempt,$now);
    p30Check($r['ai_calls']===0 && $r['service_effects']===0,'zero_external_effects');
    return $r;
}
function p30State(PDO $db,string $tenant,string $event):string {
    $stmt=$db->prepare('SELECT state FROM public.aia_lab_n8n_outbox_p6
      WHERE tenant_ref=:tenant AND event_ref=:event');
    $stmt->execute([':tenant'=>$tenant,':event'=>$event]);
    return (string)$stmt->fetchColumn();
}
try{
    p30Stage($stager,$tenant,'SYNTHETIC_P30_GOOD');
    $leaseResult=$lease->reserve($tenant,$worker,$now);
    p30Check($leaseResult['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY','valid_lease');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_GOOD',
        $worker,1,$now+1)['decision']==='LAB_ACKED_NO_DELIVERY_PROOF','good_mock_ack');
    p30Check(p30State($db,$tenant,'SYNTHETIC_P30_GOOD')==='ACKED','ack_persisted');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_GOOD',
        $worker,1,$now+1)['decision']==='DENY','duplicate_ack_denied');

    p30Stage($stager,$tenant,'SYNTHETIC_P30_TAMPER_HASH');
    p30Check($lease->reserve($tenant,$worker,$now)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_hash_fixture');
    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6
        SET event_hash='".str_repeat('a',64)."'
        WHERE event_ref='SYNTHETIC_P30_TAMPER_HASH'");
    $denied=p30Ack($ack,$tenant,'SYNTHETIC_P30_TAMPER_HASH',$worker,1,$now+1);
    p30Check($denied['reason']==='CANONICAL_OR_LEDGER_MISMATCH','tampered_digest_denied');
    p30Check(p30State($db,$tenant,'SYNTHETIC_P30_TAMPER_HASH')==='LEASED','tamper_keeps_lease');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6
        WHERE event_ref='SYNTHETIC_P30_TAMPER_HASH'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay
        WHERE event_ref='SYNTHETIC_P30_TAMPER_HASH'");

    p30Stage($stager,$tenant,'SYNTHETIC_P30_TAMPER_NONCE');
    p30Check($lease->reserve($tenant,$worker,$now)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','lease_nonce_fixture');
    $db->exec("UPDATE aia_p16_lab.event_replay
        SET nonce_ref='SYNTHETIC_P30_SPOOFED_NONCE'
        WHERE event_ref='SYNTHETIC_P30_TAMPER_NONCE'");
    $denied=p30Ack($ack,$tenant,'SYNTHETIC_P30_TAMPER_NONCE',$worker,1,$now+1);
    p30Check($denied['reason']==='REQUEST_OR_NONCE_MISMATCH','tampered_nonce_denied');
    p30Check(p30State($db,$tenant,'SYNTHETIC_P30_TAMPER_NONCE')==='LEASED','bad_nonce_keeps_lease');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6
        WHERE event_ref='SYNTHETIC_P30_TAMPER_NONCE'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay
        WHERE event_ref='SYNTHETIC_P30_TAMPER_NONCE'");

    p30Stage($stager,$tenant,'SYNTHETIC_P30_FENCING');
    p30Check($lease->reserve($tenant,$worker,$now)['attempt']===1,'first_token');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        'SYNTHETIC_FORGED_WORKER',1,$now+1)['decision']==='DENY','forged_worker_denied');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        $worker,0,$now+1)['decision']==='DENY','zero_attempt_denied');
    p30Check(p30Ack($ack,'SYNTHETIC_TENANT_B','SYNTHETIC_P30_FENCING',
        $worker,1,$now+1)['decision']==='DENY','wrong_tenant_denied');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        $worker,1,$now+60)['decision']==='DENY','expired_lease_denied');
    p30Check($lease->reserve($tenant,'SYNTHETIC_WORKER_P30_RETRY',$now+60)['attempt']===2,
        'reassigned_second_attempt');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        $worker,1,$now+61)['decision']==='DENY','stale_worker_denied');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        'SYNTHETIC_WORKER_P30_RETRY',1,$now+61)['decision']==='DENY',
        'same_worker_wrong_attempt_denied');
    p30Check(p30Ack($ack,$tenant,'SYNTHETIC_P30_FENCING',
        'SYNTHETIC_WORKER_P30_RETRY',2,$now+61)['decision']==='LAB_ACKED_NO_DELIVERY_PROOF',
        'current_worker_attempt_mock_ack');

    p30Stage($stager,'SYNTHETIC_TENANT_B','SYNTHETIC_P30_B');
    $b=$lease->reserve('SYNTHETIC_TENANT_B','SYNTHETIC_WORKER_B',$now);
    p30Check($b['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY','tenant_b_valid_lease');
    p30Check(p30Ack($ack,'SYNTHETIC_TENANT_B','SYNTHETIC_P30_B',
        'SYNTHETIC_WORKER_B',1,$now+1)['decision']==='LAB_ACKED_NO_DELIVERY_PROOF',
        'tenant_b_independent');
    echo "AIA_P30_BOUND_ACK_PASS checks={$checks} zero_ai_calls=1 untrusted_receipts_denied=1 real_delivery=false NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
