<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/P18Fixture.php';
require_once __DIR__.'/labs/PgBoundOutboxLeaseLab.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Convergence\PgBoundOutboxLeaseLab;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('AIA_P16_PG_LAB')!=='true'||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('GITHUB_ACTIONS')!=='true'||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')throw new RuntimeException('P29_DISPOSABLE_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$gate=new PgBoundOutboxLeaseLab($db);
$checks=0;
function p29Assert(bool $ok,string $name):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P29_FAIL_'.$name);
}
function p29Stage(PgSyntheticAtomicStager $stager,string $tenant,string $ref):void {
    [$canonical,$signed,$policy]=P18Fixture::make($tenant,$ref);
    $seen=[];
    $r=$stager->stage($canonical,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p29Assert($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$tenant.'_'.$ref);
}
function p29Start(string $tenant,string $worker,int $now):array {
    $proc=proc_open([PHP_BINARY,__DIR__.'/p29_lease_worker.php',$tenant,$worker,(string)$now],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc))throw new RuntimeException('P29_CANNOT_SPAWN');
    fclose($pipes[0]);
    return [$proc,$pipes[1],$pipes[2]];
}
function p29Finish(array $process):array {
    [$p,$stdout,$stderr]=$process;
    $raw=trim(stream_get_contents($stdout));
    $err=trim(stream_get_contents($stderr));
    fclose($stdout);fclose($stderr);
    if(proc_close($p)!==0)throw new RuntimeException('P29_CHILD_FAIL_'.$err);
    return json_decode($raw,true,8,JSON_THROW_ON_ERROR);
}
function p29One(string $tenant,string $worker,int $now):array {
    return p29Finish(p29Start($tenant,$worker,$now));
}
function p29State(PDO $db,string $tenant,string $event):array {
    $stmt=$db->prepare('SELECT state,attempts,lease_owner FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref=:t AND event_ref=:e');
    $stmt->execute([':t'=>$tenant,':e'=>$event]);
    return $stmt->fetch(PDO::FETCH_ASSOC)?:[];
}
try {
    $tenant='SYNTHETIC_TENANT_A';
    $event='SYNTHETIC_P29_SINGLE_EVENT';
    $now=P18Fixture::NOW;
    p29Stage($stager,$tenant,$event);

    $jobs=[];
    for($i=0;$i<10;$i++) {
        $jobs[]=p29Start($tenant,'SYNTHETIC_WORKER_P29_'.$i,$now);
    }
    $results=array_map('p29Finish',$jobs);
    $winners=array_values(array_filter($results,static fn($r)=>
        $r['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'));
    $empty=count(array_filter($results,static fn($r)=>
        $r['decision']==='LAB_NO_PENDING'));
    p29Assert(count($winners)===1,'exactly_one_lease_for_ten_workers');
    p29Assert($empty===9,'nine_contenders_observe_lease');
    foreach($results as $r) p29Assert($r['ai_calls']===0 && $r['service_effects']===0,
        'ten_workers_zero_effects');
    $first=$winners[0];
    p29Assert($first['event_ref']===$event && $first['attempt']===1,
        'initial_fencing_token');
    $owner=p29State($db,$tenant,$event)['lease_owner'];
    p29Assert(is_string($owner) && str_starts_with($owner,'SYNTHETIC_WORKER_P29_'),
        'one_trusted_synthetic_worker');
    p29Assert(!$outbox->acknowledge($tenant,$event,$owner,1,$now+60),
        'expired_lease_cannot_ack');

    $second=p29One($tenant,'SYNTHETIC_WORKER_P29_RETRY2',$now+60);
    p29Assert($second['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        && $second['attempt']===2,'retry_at_exact_expiry');
    p29Assert(!$outbox->acknowledge($tenant,$event,$owner,1,$now+61),
        'stale_ack_old_worker_denied');
    p29Assert(!$outbox->acknowledge($tenant,$event,'SYNTHETIC_WORKER_P29_RETRY2',1,$now+61),
        'stale_token_same_worker_denied');

    $third=p29One($tenant,'SYNTHETIC_WORKER_P29_RETRY3',$now+120);
    p29Assert($third['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        && $third['attempt']===3,'last_permitted_attempt');
    p29Assert(!$outbox->acknowledge($tenant,$event,'SYNTHETIC_WORKER_P29_RETRY2',2,$now+121),
        'retry2_ack_denied_after_retry3');
    $final=p29One($tenant,'SYNTHETIC_WORKER_P29_RETRY4',$now+180);
    p29Assert($final['decision']==='LAB_NO_PENDING','fourth_attempt_denied');
    $state=p29State($db,$tenant,$event);
    p29Assert($state['state']==='DEAD' && (int)$state['attempts']===3,
        'exhausted_retained_as_dead');
    p29Assert(!$outbox->acknowledge($tenant,$event,'SYNTHETIC_WORKER_P29_RETRY3',3,$now+181),
        'dead_letter_cannot_be_acked');

    p29Stage($stager,'SYNTHETIC_TENANT_B',$event);
    $other=p29One('SYNTHETIC_TENANT_B','SYNTHETIC_WORKER_P29_B',$now);
    p29Assert($other['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        && $other['attempt']===1,'other_tenant_independent');
    p29Assert($outbox->stats($tenant)===[
        'pending'=>0,'leased'=>0,'acked'=>0,'dead'=>1
    ],'tenant_A_terminal');
    p29Assert($outbox->stats('SYNTHETIC_TENANT_B')===[
        'pending'=>0,'leased'=>1,'acked'=>0,'dead'=>0
    ],'tenant_B_separate_state');

    echo "AIA_P29_BOUND_LEASE_RACE_PASS checks={$checks} workers=10 winners=1 stale_ack_denied=true retries=3 no_n8n=true NO_GO\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
