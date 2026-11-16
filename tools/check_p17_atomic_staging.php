<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\Labs\PgN8nOutboxLab;

if (getenv('AIA_P16_PG_LAB') !== 'true' || getenv('AIA_P6_PG_LAB') !== 'true'
    || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'imagine_agents_ci'
    || getenv('DB_USERNAME') !== 'postgres') throw new RuntimeException('P17_CI_ONLY');

$db = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager = new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox = new PgN8nOutboxLab($db);
$checks = 0;
$now = 1700000000;
$key = 'SYNTHETIC_P17_TEST_SIGNER_NOT_PRODUCTION_1234';
function p17Assert(bool $pass, string $reason): void {
    global $checks;
    $checks++;
    if (!$pass) throw new RuntimeException('P17_FAIL_' . $reason);
}
function p17Fixture(string $tenant, string $event, int $now, string $key, string $run='SYNTHETIC_RUN_A'): array {
    $p7 = MockN8nOutboxContract::encode([
        'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref'=>$event,
        'tenant_ref'=>$tenant,
        'run_ref'=>$run,
        'kind'=>'mock.run.completed',
        'reference_ref'=>'SYNTHETIC_P17_REFERENCE',
        'occurred_at'=>$now-5,
    ]);
    if ($p7 === null) throw new RuntimeException('P17_BAD_FIXTURE');
    $proposal = MockP7ToAutoIaAdapter::propose($p7,$tenant,$now)['proposal'];
    if (!is_array($proposal)) throw new RuntimeException('P17_NO_PROPOSAL');
    $signed=[
        'schema'=>'AUTO_IA_S2S_V1',
        'issuer'=>$proposal['issuer'],
        'audience'=>$proposal['audience'],
        'product'=>$proposal['product'],
        'operation'=>$proposal['operation'],
        'tenant_ref'=>$proposal['tenant_ref'],
        'event_ref'=>$proposal['event_ref'],
        'nonce_ref'=>$proposal['nonce_ref'],
        'request_ref'=>$proposal['request_ref'],
        'issued_at'=>$now-5,
        'expires_at'=>$now+60,
        'payload_hash'=>$proposal['payload_hash'],
        'key_id'=>'SYNTHETIC_KEY_A',
        'signature'=>'',
    ];
    $fields=[];
    foreach ($signed as $k=>$v) if($k!=='signature') $fields[]=(string)$v;
    $signed['signature']=hash_hmac('sha256',implode("\n",$fields),$key);
    return [$p7,$signed,[
        'service'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,
        'operations'=>['run.status.project'],
        'active'=>true,
    ]];
}
function p17Counts(PDO $db): array {
    return [
        (int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn(),
    ];
}
try {
    p17Assert($outbox->enqueueWithinTransaction('bad')===null,'no_implicit_tx');
    [$p7,$signed,$policy]=p17Fixture('SYNTHETIC_TENANT_A','SYNTHETIC_P17_EVENT_A',$now,$key);
    $seen=[];
    $result=$stager->stage($p7,$signed,$policy,$now,['SYNTHETIC_KEY_A'=>$key],$seen);
    p17Assert($result['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','first_stage');
    p17Assert($result['ai_calls']===0 && $result['service_effects']===0,'zero_effects');
    p17Assert(p17Counts($db)===[1,1],'joint_commit');
    p17Assert(count($seen)===1,'nonce_committed_after_joint_commit');

    $newSeen=[];
    p17Assert($stager->stage($p7,$signed,$policy,$now,['SYNTHETIC_KEY_A'=>$key],$newSeen)['decision']==='DENY','durable_replay_denied');
    p17Assert(p17Counts($db)===[1,1] && $newSeen===[],'replay_cannot_mutate');

    [$conflict,$conflictSig,$conflictPolicy]=p17Fixture(
        'SYNTHETIC_TENANT_A','SYNTHETIC_P17_EVENT_CONFLICT',$now,$key
    );
    [$otherPayload]=p17Fixture(
        'SYNTHETIC_TENANT_A','SYNTHETIC_P17_EVENT_CONFLICT',$now,$key,'SYNTHETIC_OTHER_RUN'
    );
    p17Assert($outbox->enqueue($otherPayload)==='CREATED','preseed_conflicting_outbox');
    $pre=p17Counts($db);
    $seenConflict=[];
    $failure=$stager->stage($conflict,$conflictSig,$conflictPolicy,$now,
        ['SYNTHETIC_KEY_A'=>$key],$seenConflict);
    p17Assert($failure['reason']==='OUTBOX_CONFLICT_OR_ERROR','detect_conflicting_existing_outbox');
    p17Assert(p17Counts($db)===$pre,'claim_rolled_back_on_outbox_mismatch');
    p17Assert($seenConflict===[],'local_nonce_rolled_back');
    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref='SYNTHETIC_TENANT_A' AND event_ref='SYNTHETIC_P17_EVENT_CONFLICT'");
    p17Assert($stager->stage($conflict,$conflictSig,$conflictPolicy,$now,
        ['SYNTHETIC_KEY_A'=>$key],$seenConflict)['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','retry_after_rollback');
    p17Assert(p17Counts($db)===[2,2],'joint_commit_after_retry');

    [$beta,$betaSigned,$betaPolicy]=p17Fixture('SYNTHETIC_TENANT_B','SYNTHETIC_P17_EVENT_A',$now,$key);
    $betaSeen=[];
    p17Assert($stager->stage($beta,$betaSigned,$betaPolicy,$now,
        ['SYNTHETIC_KEY_A'=>$key],$betaSeen)['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','tenant_B_independent');
    p17Assert(p17Counts($db)===[3,3],'isolated_count');

    $db->beginTransaction();
    $nestedSeen=[];
    p17Assert($stager->stage($beta,$betaSigned,$betaPolicy,$now,
        ['SYNTHETIC_KEY_A'=>$key],$nestedSeen)['reason']==='NESTED_TX_NOT_ALLOWED','nested_blocked');
    p17Assert($db->inTransaction(),'caller_tx_preserved');
    $db->rollBack();

    echo "AIA_P17_ATOMIC_STAGING_PASS checks={$checks} replay_and_outbox_same_tx=true ai_calls=0 n8n_calls=0 NO_GO\n";
} finally {
    if($db->inTransaction()) $db->rollBack();
    $outbox->dropTables();
}
