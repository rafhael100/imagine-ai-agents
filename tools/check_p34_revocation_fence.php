<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\PgTenantAuthorityLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true' ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')throw new RuntimeException('P34_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgRevocationBoundCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$authority=$cycle->authority();
$checks=0;
function p34Check(bool $condition,string $reason):void {
    global $checks;$checks++;
    if(!$condition)throw new RuntimeException('P34_AUTHORITY_FAIL_'.$reason);
}
function p34Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):array {
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    return $cycle->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
}
function p34Zero(array $r,string $label):array {
    p34Check($r['ai_calls']===0 && $r['service_effects']===0,
        $label.'_zero_effects');
    return $r;
}
try{
    $a='SYNTHETIC_TENANT_A';
    $b='SYNTHETIC_TENANT_B';
    $worker='SYNTHETIC_WORKER_P34';
    $now=P18Fixture::NOW;
    p34Check($authority->seedActive($a),'seed_A');
    p34Check($authority->seedActive($b),'seed_B');
    p34Check($authority->snapshot($a)===['active'=>true,'revision'=>1],'revision_one');
    p34Check(p34Zero(p34Stage($cycle,$a,'SYNTHETIC_P34_A'),'stage_A')
        ['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','A_staged');
    p34Check(p34Zero(p34Stage($cycle,$b,'SYNTHETIC_P34_B'),'stage_B')
        ['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','B_staged');

    $first=p34Zero($cycle->reserve($a,$worker,$now,1),'A_first_lease');
    p34Check($first['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        && $first['attempt']===1,'A_reserved_version1');

    p34Check($authority->revoke($a)===2,'revoke_increments_revision');
    p34Check($authority->snapshot($a)===['active'=>false,'revision'=>2],
        'active_revoked');
    $old=p34Zero($cycle->acknowledge($a,'SYNTHETIC_P34_A',$worker,1,$now+1,1),
        'old_epoch_ack');
    p34Check($old['decision']==='DENY'
        && $old['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE',
        'ack_after_revocation_denied');
    p34Check(p34Zero($cycle->reserve($a,'SYNTHETIC_WORKER_RETRY',$now+60,1),
        'old_epoch_lease')['decision']==='DENY','retry_after_revocation_denied');
    p34Check(p34Zero(p34Stage($cycle,$a,'SYNTHETIC_P34_REVOKED'),
        'revoked_stage')['decision']==='DENY','no_new_stage_after_revocation');

    $bLease=p34Zero($cycle->reserve($b,'SYNTHETIC_WORKER_B',$now,1),'B_lease');
    p34Check($bLease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY','B_still_active');
    p34Check(p34Zero($cycle->acknowledge($b,'SYNTHETIC_P34_B',
        'SYNTHETIC_WORKER_B',1,$now+1,1),'B_ack')['decision']
        ==='LAB_ACKED_NO_DELIVERY_PROOF','B_ack_isolated');

    p34Check($authority->reactivate($a)===3,'reactivate_increments_revision');
    p34Check(p34Zero($cycle->reserve($a,'SYNTHETIC_WORKER_RETRY',$now+60,1),
        'stale_revision')['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE',
        'old_epoch_not_accepted_after_reactivate');
    $new=p34Zero($cycle->reserve($a,'SYNTHETIC_WORKER_RETRY',$now+60,3),
        'new_revision_lease');
    p34Check($new['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
        && $new['attempt']===2,'fresh_epoch_allowed');
    p34Check(p34Zero($cycle->acknowledge($a,'SYNTHETIC_P34_A',
        $worker,1,$now+61,3),'stale_old_worker')['decision']==='DENY',
        'worker_fencing_preserved');
    p34Check(p34Zero($cycle->acknowledge($a,'SYNTHETIC_P34_A',
        'SYNTHETIC_WORKER_RETRY',2,$now+61,3),'fresh_ack')['decision']
        ==='LAB_ACKED_NO_DELIVERY_PROOF','new_revision_ack');

    // Prove the actual PostgreSQL FOR SHARE lock semantics: a revoker
    // cannot update this authority row until the shared reader commits.
    $db->beginTransaction();
    $lock=$db->prepare('SELECT revision FROM aia_p34_lab.tenant_policy
      WHERE tenant_ref=:tenant FOR SHARE');
    $lock->execute([':tenant'=>$a]);
    p34Check((int)$lock->fetchColumn()===3,'held_share_lock');
    $second=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $second->exec('SET statement_timeout=250');
    $remote=new PgTenantAuthorityLab($second);
    p34Check($remote->revoke($a)===null,'revocation_waits_on_share_lock');
    p34Check($authority->snapshot($a)['active']===true,'failed_revoke_did_not_change_state');
    $db->commit();
    $second->exec('SET statement_timeout=0');
    p34Check($remote->revoke($a)===4,'revocation_succeeds_after_share_lock_released');
    p34Check($authority->snapshot($a)===['active'=>false,'revision'=>4],
        'post_lock_revocation_persisted');
    p34Check($cycle->audit()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION',
        'revision_does_not_corrupt_event_evidence');

    echo "AIA_P34_REVOCATION_FENCE_PASS checks={$checks} post_stage_revocation_denied=true serialized=true zero_credits=true NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
