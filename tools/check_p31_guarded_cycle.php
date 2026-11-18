<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgGuardedAutomationCycleLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgGuardedAutomationCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P31_EPHEMERAL_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgGuardedAutomationCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$checks=0;
function p31Check(bool $pass,string $reason):void {
    global $checks;$checks++;
    if(!$pass)throw new RuntimeException('P31_FAIL_'.$reason);
}
function p31Zero(array $result,string $what):array {
    p31Check(($result['ai_calls']??-1)===0 && ($result['service_effects']??-1)===0,
        $what.'_zero_provider_and_service_effect');
    return $result;
}
$now=P18Fixture::NOW;
$worker='SYNTHETIC_P31_WORKER';
try {
    p31Check(PgGuardedAutomationCycleLab::operationalReadiness()['decision']==='NO_GO',
        'never_operationally_ready');
    p31Check(!method_exists($cycle,'unsafeEnqueue'),'facade_has_no_raw_enqueue');
    p31Check(!method_exists($cycle,'dispatch'),'facade_has_no_dispatch');
    p31Check(!method_exists($cycle,'chargeCredits'),'facade_has_no_billing');

    // Separate tenants may reuse the SAME event ID without identity collision.
    foreach(['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'] as $tenant) {
        [$body,$signed,$policy]=P18Fixture::make($tenant,'SYNTHETIC_P31_SAME_EVENT');
        $seen=[];
        $staged=p31Zero($cycle->stage($body,$signed,$policy,$now,
            ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen),'stage_'.$tenant);
        p31Check($staged['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH',
            'staged_'.$tenant);
        p31Check(count($seen)===1,'local_replay_bookkeeping_'.$tenant);
        p31Check(p31Zero($cycle->stage($body,$signed,$policy,$now,
            ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen),'replay_'.$tenant)
            ['decision']==='DENY','no_replay_'.$tenant);
    }
    $audit=$cycle->audit();
    p31Check($audit['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION'
        && $audit['checked']===2,'audit_two_tenants_consistent');
    p31Check($audit['external_calls']===0 && $audit['ai_calls']===0,
        'audit_never_contacts_provider');

    foreach(['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'] as $tenant) {
        $r=p31Zero($cycle->reserve($tenant,$worker,$now),'reserve_'.$tenant);
        p31Check($r['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
            && $r['event_ref']==='SYNTHETIC_P31_SAME_EVENT'
            && $r['attempt']===1,'reserved_'.$tenant);
        p31Check(p31Zero($cycle->reserve($tenant,'SYNTHETIC_P31_ALT',$now+1),
            'double_lease_'.$tenant)['decision']==='LAB_NO_PENDING',
            'no_duplicate_worker_'.$tenant);
        $a=p31Zero($cycle->acknowledge($tenant,$r['event_ref'],
            $worker,$r['attempt'],$now+1),'ack_'.$tenant);
        p31Check($a['decision']==='LAB_ACKED_NO_DELIVERY_PROOF','ack_state_'.$tenant);
        p31Check(p31Zero($cycle->acknowledge($tenant,$r['event_ref'],
            $worker,$r['attempt'],$now+1),'duplicate_ack_'.$tenant)
            ['decision']==='DENY','no_double_ack_'.$tenant);
    }
    p31Check($cycle->audit()['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION',
        'ack_preserves_canonical_evidence');

    // Revocation of the trusted synthetic policy MUST prevent staging.
    [$bad,$badSigned,$revoked]=P18Fixture::make(
        'SYNTHETIC_TENANT_A','SYNTHETIC_P31_REVOKED',false
    );
    $revokedSeen=[];
    p31Check(p31Zero($cycle->stage($bad,$badSigned,$revoked,$now,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$revokedSeen),'revoked_stage')
        ['decision']==='DENY','revoked_policy_rejected');
    p31Check($revokedSeen===[],'revoked_nonce_not_recorded');

    // A raw legacy P6 insertion remains possible, but the guarded facade
    // cannot lease or ACK the unpaired P16-less event. Audit detects it.
    [$orphan]=P18Fixture::make('SYNTHETIC_TENANT_A','SYNTHETIC_P31_ORPHAN');
    p31Check($outbox->enqueue($orphan)==='CREATED','synthetic_orphan_injected');
    p31Check(p31Zero($cycle->reserve('SYNTHETIC_TENANT_A',$worker,$now),
        'orphan_reserve')['decision']==='DENY','facade_denies_orphan');
    p31Check($cycle->audit()['decision']==='DENY_INCONSISTENT',
        'auditor_flags_legacy_orphan');
    p31Check(p31Zero($cycle->acknowledge('SYNTHETIC_TENANT_A',
        'SYNTHETIC_P31_ORPHAN',$worker,1,$now+1),'orphan_ack')
        ['decision']==='DENY','orphan_cannot_be_acked');

    echo "AIA_P31_GUARDED_CYCLE_PASS checks={$checks} tenants=2 no_bypass_api=1 no_n8n=1 no_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
