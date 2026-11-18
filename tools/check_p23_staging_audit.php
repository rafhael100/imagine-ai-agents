<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/PgAtomicStagingAuditLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\PgAtomicStagingAuditLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P23_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$audit=new PgAtomicStagingAuditLab($db);
$checks=0;
function p23Check(bool $ok,string $name):void{
    global $checks;
    $checks++;
    if(!$ok)throw new RuntimeException('P23_INTEGRITY_FAIL_'.$name);
}
function p23Reasons(array $result):array {
    return array_column($result['issues'],'reason');
}
try {
    $payloads=[];
    foreach(['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'] as $tenant) {
        [$p7,$signed,$policy]=P18Fixture::make($tenant,'SYNTHETIC_P23_EVENT');
        $seen=[];
        $r=$stager->stage($p7,$signed,$policy,P18Fixture::NOW,
            ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
        p23Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','initial_stage_'.$tenant);
        $payloads[$tenant]=$p7;
    }
    $result=$audit->scan();
    p23Check($result['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION'
        && $result['checked']===2 && $result['issues']===[],'clean_audit');
    p23Check($result['ai_calls']===0 && $result['external_calls']===0,'no_call');

    // P25: request and nonce metadata must match the original pinned CONV-02
    // adapter output, not merely the event and payload hash.
    $original=$db->query("SELECT request_ref,nonce_ref FROM aia_p16_lab.event_replay
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->fetch(PDO::FETCH_ASSOC);
    p23Check(is_array($original),'captured_replay_bindings');
    $db->exec("UPDATE aia_p16_lab.event_replay
        SET request_ref='SYNTHETIC_P25_ALTERED_REQUEST'
        WHERE tenant_ref='SYNTHETIC_TENANT_A'");
    p23Check(in_array('REPLAY_REQUEST_BINDING_MISMATCH',p23Reasons($audit->scan()),true),
        'altered_request_detected');
    $db->prepare("UPDATE aia_p16_lab.event_replay SET request_ref=:original
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->execute([':original'=>$original['request_ref']]);

    $db->exec("UPDATE aia_p16_lab.event_replay
        SET nonce_ref='SYNTHETIC_P25_ALTERED_NONCE'
        WHERE tenant_ref='SYNTHETIC_TENANT_A'");
    p23Check(in_array('REPLAY_NONCE_BINDING_MISMATCH',p23Reasons($audit->scan()),true),
        'altered_nonce_detected');
    $db->prepare("UPDATE aia_p16_lab.event_replay SET nonce_ref=:original
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->execute([':original'=>$original['nonce_ref']]);
    p23Check($audit->scan()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION',
        'clean_after_binding_fixture_restore');

    // Adversarial changes here are performed ONLY on the disposable CI database.
    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6 SET event_hash='".str_repeat('a',64)."'
        WHERE tenant_ref='SYNTHETIC_TENANT_A'");
    p23Check(in_array('STORED_HASH_MISMATCH',p23Reasons($audit->scan()),true),'hash_tamper_detected');
    $db->prepare("UPDATE public.aia_lab_n8n_outbox_p6 SET event_hash=:hash
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->execute([
            ':hash'=>hash('sha256',$payloads['SYNTHETIC_TENANT_A'])
        ]);

    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6 SET canonical_event='{}'
        WHERE tenant_ref='SYNTHETIC_TENANT_A'");
    p23Check(in_array('INVALID_OUTBOX_CANONICAL',p23Reasons($audit->scan()),true),'invalid_canonical_detected');
    $db->prepare("UPDATE public.aia_lab_n8n_outbox_p6 SET canonical_event=:canonical
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->execute([
            ':canonical'=>$payloads['SYNTHETIC_TENANT_A']
        ]);

    $backup=$db->query("SELECT * FROM aia_p16_lab.event_replay
        WHERE tenant_ref='SYNTHETIC_TENANT_B'")->fetch(PDO::FETCH_ASSOC);
    $db->exec("DELETE FROM aia_p16_lab.event_replay WHERE tenant_ref='SYNTHETIC_TENANT_B'");
    p23Check(in_array('OUTBOX_ONLY',p23Reasons($audit->scan()),true),'outbox_orphan_detected');
    $db->prepare("INSERT INTO aia_p16_lab.event_replay
       (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
       VALUES (:t,:e,:r,:n,:h)")->execute([
        ':t'=>$backup['tenant_ref'],':e'=>$backup['event_ref'],
        ':r'=>$backup['request_ref'],':n'=>$backup['nonce_ref'],
        ':h'=>$backup['payload_hash'],
    ]);

    $db->exec("DELETE FROM public.aia_lab_n8n_outbox_p6 WHERE tenant_ref='SYNTHETIC_TENANT_A'");
    p23Check(in_array('REPLAY_ONLY',p23Reasons($audit->scan()),true),'replay_orphan_detected');
    p23Check($outbox->enqueue($payloads['SYNTHETIC_TENANT_A'])==='CREATED','repair_only_in_test_harness');
    p23Check($audit->scan()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION','clean_after_fixture_restore');

    $outbox->dropTables();
    p23Check($audit->scan()['decision']==='INDETERMINATE','missing_table_fails_closed');
    echo "AIA_P23_STAGING_AUDIT_PASS checks={$checks} read_only_auditor=true tamper_detection=true no_repair_actions=true ai_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
