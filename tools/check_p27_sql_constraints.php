<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if (getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('AIA_P6_PG_LAB')!=='true'
    || getenv('AIA_P16_PG_LAB')!=='true'
    || getenv('DB_HOST')!=='127.0.0.1'
    || getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P27_CI_ONLY');
}

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$outbox=new PgN8nOutboxLab($db);
$checks=0;
function p27Assert(bool $condition,string $label):void {
    global $checks;
    $checks++;
    if(!$condition) throw new RuntimeException('P27_FAIL_'.$label);
}
function p27Violation(PDO $db,string $sql,array $params,string $label):void {
    $rejected=false;
    try {
        $statement=$db->prepare($sql);
        $statement->execute($params);
    } catch(PDOException $e) {
        $rejected=$e->getCode()==='23514';
    }
    p27Assert($rejected,$label);
}
function p27ReplayFields(string $tenant,string $event,string $request,
    string $nonce,string $hash):array {
    return [
        ':tenant'=>$tenant,':event'=>$event,':request'=>$request,
        ':nonce'=>$nonce,':hash'=>$hash,
    ];
}
const P27_REPLAY_INSERT='INSERT INTO aia_p16_lab.event_replay
    (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
    VALUES (:tenant,:event,:request,:nonce,:hash)';
const P27_OUTBOX_INSERT='INSERT INTO public.aia_lab_n8n_outbox_p6
    (tenant_ref,event_ref,canonical_event,event_hash,lease_owner)
    VALUES (:tenant,:event,:canonical,:hash,:lease_owner)';
try {
    [$p7,$signed,$policy]=P18Fixture::make(
        'SYNTHETIC_TENANT_A','SYNTHETIC_P27_VALID'
    );
    $seen=[];
    $r=$stager->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p27Assert($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','valid_stage_survives_constraints');

    $valid=p27ReplayFields('SYNTHETIC_TENANT_A','SYNTHETIC_P27_R01',
        'SYNTHETIC_P27_REQUEST_01','SYNTHETIC_P27_NONCE_01',str_repeat('a',64));
    $invalid=$valid;
    $invalid[':tenant']='TENANT_NONCANONICAL';
    p27Violation($db,P27_REPLAY_INSERT,$invalid,'replay_tenant_not_synthetic');
    $invalid=$valid;
    $invalid[':event']='SYNTHETIC_lowercase';
    p27Violation($db,P27_REPLAY_INSERT,$invalid,'replay_event_not_canonical');
    $invalid=$valid;
    $invalid[':request']='FAKE_REQUEST';
    p27Violation($db,P27_REPLAY_INSERT,$invalid,'replay_request_not_synthetic');
    $invalid=$valid;
    $invalid[':nonce']='SYNTHETIC_bad_nonce';
    p27Violation($db,P27_REPLAY_INSERT,$invalid,'replay_nonce_not_canonical');
    $invalid=$valid;
    $invalid[':hash']=str_repeat('g',64);
    p27Violation($db,P27_REPLAY_INSERT,$invalid,'replay_digest_not_hex');

    $base=[
        ':tenant'=>'SYNTHETIC_TENANT_A',
        ':event'=>'SYNTHETIC_P27_RAW_01',
        ':canonical'=>'{}',
        ':hash'=>str_repeat('a',64),
        ':lease_owner'=>null,
    ];
    $invalid=$base;
    $invalid[':tenant']='NOT_VALID_TENANT';
    p27Violation($db,P27_OUTBOX_INSERT,$invalid,'outbox_tenant_not_synthetic');
    $invalid=$base;
    $invalid[':event']='SYNTHETIC_lowercase';
    p27Violation($db,P27_OUTBOX_INSERT,$invalid,'outbox_event_not_canonical');
    $invalid=$base;
    $invalid[':hash']=str_repeat('z',64);
    p27Violation($db,P27_OUTBOX_INSERT,$invalid,'outbox_digest_not_hex');
    $invalid=$base;
    $invalid[':canonical']=str_repeat('é',270);
    p27Violation($db,P27_OUTBOX_INSERT,$invalid,'outbox_canonical_bytes_exceeded');
    $invalid=$base;
    $invalid[':lease_owner']='UNSCOPED_WORKER';
    p27Violation($db,P27_OUTBOX_INSERT,$invalid,'outbox_lease_owner_malformed');

    p27Assert((int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn()===1,
        'replay_has_no_failed_writes');
    p27Assert((int)$db->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn()===1,
        'outbox_has_no_failed_writes');
    echo "AIA_P27_PG_SCHEMA_CHECKS_PASS checks={$checks} invalid_writes=10 ai_calls=0 external_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
