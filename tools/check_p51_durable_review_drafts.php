<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';

use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P51_DISPOSABLE_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$registry=new PgDeterministicDraftRegistryLab($db);
$registry->createTables();
$db->exec('TRUNCATE TABLE aia_p51_lab.review_drafts');
$db->exec('TRUNCATE TABLE aia_p51_lab.preview_batches');
$checks=0;
function p51Check(bool $ok,string $name):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('AIA_P51_FAIL_'.$name);
}
function p51Command(string $tenant,string $request,int $visits=120):array{
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,'request_ref'=>$request,
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>1000,'client_rate_bp'=>2000
    ];
}
function p51Rules(string $tenant):array{
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P51_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P51_RULE_A',
                'metric'=>'projected_leads',
                'operator'=>'gte','threshold'=>12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT'
            ],
            [
                'rule_ref'=>'SYNTHETIC_P51_RULE_B',
                'metric'=>'projected_customers',
                'operator'=>'eq','threshold'=>2,
                'draft_kind'=>'CRM_TASK_DRAFT'
            ]
        ]
    ];
}
function p51Counts(PDO $db):array {
    return [
        (int)$db->query('SELECT COUNT(*) FROM aia_p51_lab.preview_batches')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM aia_p51_lab.review_drafts')->fetchColumn()
    ];
}
function p51NoEffect(array $r,string $label):void{
    p51Check($r['ai_calls']===0 &&$r['service_effects']===0,
        'no_external_effects_'.$label);
}
try {
    $tenantA='SYNTHETIC_TENANT_A';
    $tenantB='SYNTHETIC_TENANT_B';
    $request='SYNTHETIC_P51_SHARED_REQUEST';
    $cmdA=p51Command($tenantA,$request);
    $rulesA=p51Rules($tenantA);
    $first=$registry->stage($cmdA,$rulesA);
    p51NoEffect($first,'first');
    p51Check($first['decision']==='LAB_DRAFTS_STAGED_NO_ACTION'
        &&$first['proposal_count']===2,'first_preview_committed');
    p51Check(p51Counts($db)===[1,2],'batch_and_two_drafts_atomic');
    $a=$registry->readPending($tenantA);
    p51Check($a['decision']==='LAB_READONLY_DRAFTS_NO_APPROVAL'
        &&count($a['drafts'])===2,'reviewer_reads_two_pending_only');
    p51Check(array_unique(array_column($a['drafts'],'state'))===
        ['AWAITING_HUMAN_REVIEW_LAB'],'no_invented_approval_state');

    $again=$registry->stage($cmdA,$rulesA);
    p51NoEffect($again,'replay');
    p51Check($again['decision']==='LAB_DRAFTS_ALREADY_STAGED'
        &&$again['proposal_count']===2,'exact_idempotent_replay');
    p51Check(p51Counts($db)===[1,2],'replay_does_not_duplicate');

    $differentVisits=p51Command($tenantA,$request,121);
    $changed=$registry->stage($differentVisits,$rulesA);
    p51NoEffect($changed,'payload_mutation');
    p51Check($changed['reason']==='REPLAY_INPUT_CHANGED_OR_TAMPERED',
        'request_payload_mutation_refused');

    $changedRules=$rulesA;
    $changedRules['revision']=2;
    $changed=$registry->stage($cmdA,$changedRules);
    p51Check($changed['reason']==='REPLAY_INPUT_CHANGED_OR_TAMPERED',
        'same_request_version_change_denied');
    p51Check(p51Counts($db)===[1,2],'conflicts_do_not_mutate');

    $wrongTenant=p51Rules($tenantB);
    $wrong=$registry->stage($cmdA,$wrongTenant);
    p51Check($wrong['decision']==='DENY'
        &&p51Counts($db)===[1,2],'cross_tenant_refused');

    // A completely distinct tenant may use the same request_ref without
    // stealing the other tenant's drafts or idempotency namespace.
    $cmdB=p51Command($tenantB,$request);
    $rulesB=p51Rules($tenantB);
    $b=$registry->stage($cmdB,$rulesB);
    p51Check($b['decision']==='LAB_DRAFTS_STAGED_NO_ACTION',
        'other_tenant_isolated_batch');
    p51Check(p51Counts($db)===[2,4],'two_tenants_two_batches_four_drafts');
    $readB=$registry->readPending($tenantB);
    p51Check(count($readB['drafts'])===2
        &&count($registry->readPending($tenantA)['drafts'])===2,
        'tenant_reads_do_not_cross');
    p51Check($registry->readPending('UNTRUSTED_TENANT')['decision']==='DENY',
        'reject_untrusted_reader_scope');

    // The registry has no auto-repair: a historically missing review draft
    // makes an exact request replay fail closed.
    $db->exec("DELETE FROM aia_p51_lab.review_drafts
      WHERE tenant_ref='SYNTHETIC_TENANT_A'
      AND rule_ref='SYNTHETIC_P51_RULE_B'");
    $corrupted=$registry->stage($cmdA,$rulesA);
    p51NoEffect($corrupted,'missing_draft');
    p51Check($corrupted['reason']==='STORED_DRAFTS_INCOMPLETE',
        'lost_draft_detected_and_not_recreated');
    p51Check(p51Counts($db)===[2,3],
        'no_silent_repair_after_partial_loss');

    // PostgreSQL lock conflict, not a substitute for distributed IAM.
    $db->beginTransaction();
    $lock=$db->prepare('SELECT preview_sha256 FROM aia_p51_lab.preview_batches
        WHERE tenant_ref=:tenant AND request_ref=:request FOR UPDATE');
    $lock->execute([':tenant'=>$tenantB,':request'=>$request]);
    p51Check(is_string($lock->fetchColumn()),'batch_lock_held');
    $second=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $second->exec('SET statement_timeout=250');
    $other=new PgDeterministicDraftRegistryLab($second);
    $inConflict=$other->stage($cmdB,$rulesB);
    p51Check($inConflict['decision']==='DENY',
        'race_fails_closed_instead_of_overwriting');
    $db->commit();
    $second->exec('SET statement_timeout=0');
    p51Check($other->stage($cmdB,$rulesB)['decision']==='LAB_DRAFTS_ALREADY_STAGED',
        'idempotent_after_lock_release');
    p51Check(p51Counts($db)===[2,3],'racing_replay_no_effect');

    echo "AIA_P51_DURABLE_DRAFTS_PASS checks={$checks} scoped_batches=2 approvals=0 idempotency=1 tamper_deny=1 no_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
