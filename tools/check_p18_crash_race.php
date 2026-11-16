<?php
declare(strict_types=1);
require_once __DIR__ . '/labs/PgSyntheticAtomicStager.php';
require_once __DIR__ . '/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('AIA_P16_PG_LAB')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('GITHUB_ACTIONS')!=='true'||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci') throw new RuntimeException('P18_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$checks=0;
function p18Check(bool $value,string $reason):void {
    global $checks;
    $checks++;
    if(!$value) throw new RuntimeException('P18_FAIL_'.$reason);
}
function p18Spawn(string $mode,string $tenant,string $event):array {
    $p=proc_open([PHP_BINARY,__DIR__.'/p18_atomic_worker.php',$mode,$tenant,$event],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('P18_CANNOT_SPAWN');
    fclose($pipes[0]);
    return [$p,$pipes[1],$pipes[2]];
}
function p18Result(array $job):string {
    [$p,$stdout,$stderr]=$job;
    $out=trim(stream_get_contents($stdout));
    $err=trim(stream_get_contents($stderr));
    fclose($stdout);fclose($stderr);
    if(proc_close($p)!==0)throw new RuntimeException('P18_CHILD_FAILURE_'.$err);
    return $out;
}
function p18Counts(PDO $db):array {
    return [
        (int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn(),
    ];
}
try{
    $crash=p18Result(p18Spawn('crash_after_replay','SYNTHETIC_TENANT_A','SYNTHETIC_P18_CRASH'));
    p18Check($crash==='SIMULATED_CRASH_UNCOMMITTED_REPLAY','crash_happened');
    p18Check(p18Counts($db)===[0,0],'pg_rolled_back_dead_worker');
    p18Check(str_starts_with(p18Result(p18Spawn('stage','SYNTHETIC_TENANT_A','SYNTHETIC_P18_CRASH')),
        'LAB_ATOMIC_STAGED_NO_DISPATCH'),'recovered_after_crash');
    p18Check(p18Counts($db)===[1,1],'recovery_has_two_records');

    $jobs=[];
    for($i=0;$i<10;$i++){
        $jobs[]=p18Spawn('stage','SYNTHETIC_TENANT_A','SYNTHETIC_P18_RACE');
    }
    $results=array_map('p18Result',$jobs);
    $winners=count(array_filter($results,static fn($r)=>str_starts_with($r,'LAB_ATOMIC_STAGED_NO_DISPATCH')));
    $denied=count(array_filter($results,static fn($r)=>$r==='DENY REPLAY_OR_POLICY_DENIED'));
    p18Check($winners===1,'one_race_winner');
    p18Check($denied===9,'nine_replay_denials');
    p18Check(p18Counts($db)===[2,2],'no_partial_race_rows');

    p18Check(p18Result(p18Spawn('revoked','SYNTHETIC_TENANT_A','SYNTHETIC_P18_REVOKED'))==='DENY REPLAY_OR_POLICY_DENIED','revocation_denies');
    p18Check(p18Counts($db)===[2,2],'no_revoked_write');
    p18Check(p18Result(p18Spawn('stage','SYNTHETIC_TENANT_B','SYNTHETIC_P18_RACE'))==='LAB_ATOMIC_STAGED_NO_DISPATCH PERSISTED_TO_CI_ONLY_NO_IAM_NO_DELIVERY','separate_tenant');
    p18Check(p18Counts($db)===[3,3],'different_tenant_recorded');

    // Reuse the original P6 fenced leasing rules on a record staged by P17.
    $claim=$outbox->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_P18',P18Fixture::NOW+10);
    p18Check(is_array($claim) && $claim['attempt']===1,'fenced_claim_on_staged_event');
    p18Check($outbox->acknowledge('SYNTHETIC_TENANT_A',$claim['event_ref'],
        'SYNTHETIC_OTHER_WORKER',1,P18Fixture::NOW+11)===false,'forged_worker_ack');
    $next=$outbox->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_P18',P18Fixture::NOW+70);
    p18Check(is_array($next) && $next['event_ref']===$claim['event_ref']
        && $next['attempt']===2,'reclaim_after_lease_timeout');
    p18Check($outbox->acknowledge('SYNTHETIC_TENANT_A',$claim['event_ref'],
        'SYNTHETIC_WORKER_P18',1,P18Fixture::NOW+71)===false,'stale_same_worker_ack_denied');
    p18Check($outbox->acknowledge('SYNTHETIC_TENANT_A',$claim['event_ref'],
        'SYNTHETIC_WORKER_P18',2,P18Fixture::NOW+71)===true,'current_attempt_ack');
    echo "AIA_P18_CRASH_RACE_PASS checks={$checks} crash_recovery=true race_workers=10 single_winner=true partial_rows=0 ai_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
