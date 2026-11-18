<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgBoundOutboxLeaseLab.php';
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgBoundOutboxLeaseLab;
use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true' || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true' ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1' ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P28_DISPOSABLE_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$lease=new PgBoundOutboxLeaseLab($db);
$checks=0;
function p28Check(bool $v,string $name):void {
    global $checks; $checks++;
    if(!$v)throw new RuntimeException('P28_FAIL_'.$name);
}
function p28Stage(PgSyntheticAtomicStager $stager,string $tenant,string $ref):string {
    [$body,$signed,$policy]=P18Fixture::make($tenant,$ref);
    $seen=[];
    $r=$stager->stage($body,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p28Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$tenant.'_'.$ref);
    return $body;
}
function p28Res(PgBoundOutboxLeaseLab $gate,string $tenant,string $worker,int $now):array {
    $r=$gate->reserve($tenant,$worker,$now);
    p28Check($r['ai_calls']===0 && $r['service_effects']===0,'no_effects');
    return $r;
}
function p28State(PDO $db,string $event,string $tenant='SYNTHETIC_TENANT_A'):array {
    $stmt=$db->prepare('SELECT state,attempts FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref=:tenant AND event_ref=:event');
    $stmt->execute([':tenant'=>$tenant,':event'=>$event]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
try {
    $now=P18Fixture::NOW;
    $tenant='SYNTHETIC_TENANT_A';
    $worker='SYNTHETIC_WORKER_P28';
    p28Stage($stager,$tenant,'SYNTHETIC_P28_VALID');
    $ok=p28Res($lease,$tenant,$worker,$now);
    p28Check($ok['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY','valid_lease');
    p28Check($ok['attempt']===1 && $ok['event_ref']==='SYNTHETIC_P28_VALID','fenced_attempt');
    p28Check($ok['lease_until']===$now+60,'lease_window');
    p28Check(p28Res($lease,$tenant,'SYNTHETIC_WORKER_ALT',$now+10)['decision']==='LAB_NO_PENDING',
        'cannot_double_lease');
    p28Check($outbox->acknowledge($tenant,'SYNTHETIC_P28_VALID',$worker,1,$now+1),
        'original_fenced_ack_unchanged');

    // Outbox without P16 proof may have passed P6-only enqueue; guarded gate denies.
    [$orphan]=P18Fixture::make($tenant,'SYNTHETIC_P28_ORPHAN');
    p28Check($outbox->enqueue($orphan)==='CREATED','orphan_fixture_created');
    $denied=p28Res($lease,$tenant,$worker,$now);
    p28Check($denied['reason']==='CROSS_TABLE_BINDING_INVALID','orphan_denied');
    p28Check(p28State($db,'SYNTHETIC_P28_ORPHAN')===['state'=>'PENDING','attempts'=>0],
        'orphan_not_leased');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P28_ORPHAN'");

    $body=p28Stage($stager,$tenant,'SYNTHETIC_P28_HASH');
    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6 SET event_hash='".str_repeat('a',64)."'
        WHERE event_ref='SYNTHETIC_P28_HASH'");
    p28Check(p28Res($lease,$tenant,$worker,$now)['reason']==='CROSS_TABLE_BINDING_INVALID',
        'tampered_digest_denied');
    p28Check(p28State($db,'SYNTHETIC_P28_HASH')===['state'=>'PENDING','attempts'=>0],
        'tampered_digest_cannot_increment');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P28_HASH'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay WHERE event_ref='SYNTHETIC_P28_HASH'");

    p28Stage($stager,$tenant,'SYNTHETIC_P28_REQUEST');
    $db->exec("UPDATE aia_p16_lab.event_replay SET request_ref='SYNTHETIC_P28_FAKE_REQUEST'
        WHERE event_ref='SYNTHETIC_P28_REQUEST'");
    p28Check(p28Res($lease,$tenant,$worker,$now)['reason']==='REQUEST_OR_NONCE_BOUNDARY_INVALID',
        'request_mismatch_denied');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P28_REQUEST'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay WHERE event_ref='SYNTHETIC_P28_REQUEST'");

    p28Stage($stager,$tenant,'SYNTHETIC_P28_NONCE');
    $db->exec("UPDATE aia_p16_lab.event_replay SET nonce_ref='SYNTHETIC_P28_FAKE_NONCE'
        WHERE event_ref='SYNTHETIC_P28_NONCE'");
    p28Check(p28Res($lease,$tenant,$worker,$now)['reason']==='REQUEST_OR_NONCE_BOUNDARY_INVALID',
        'nonce_mismatch_denied');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P28_NONCE'");
    $db->exec("DELETE FROM aia_p16_lab.event_replay WHERE event_ref='SYNTHETIC_P28_NONCE'");

    p28Stage($stager,'SYNTHETIC_TENANT_B','SYNTHETIC_P28_B_ONLY');
    p28Check(p28Res($lease,$tenant,$worker,$now)['decision']==='LAB_NO_PENDING',
        'other_tenant_not_visible');
    p28Check(p28Res($lease,'SYNTHETIC_TENANT_B',$worker,$now)['decision']
        ==='LAB_LEASE_RESERVED_NO_DELIVERY','tenant_B_independent');
    p28Check(p28Res($lease,'BAD_TENANT',$worker,$now)['decision']==='DENY',
        'invalid_tenant_denied');

    p28Check(p28Res($lease,$tenant,$worker,$now+70)['decision']==='LAB_NO_PENDING',
        'acked_event_not_retried');
    echo "AIA_P28_BOUND_LEASE_PASS checks={$checks} cross_table_required=1 no_provider=1 no_n8n=1 NO_GO\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
