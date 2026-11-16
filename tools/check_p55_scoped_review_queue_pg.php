<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/PgScopedReviewQueueBridgeLab.php';

use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\PgScopedReviewQueueBridgeLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P55_SCOPED_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('AIA_P55_DISPOSABLE_CI_ONLY');
}
$db=new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$registry=new PgDeterministicDraftRegistryLab($db);
$registry->createTables();
$db->exec('TRUNCATE TABLE aia_p51_lab.review_drafts, aia_p51_lab.preview_batches');
$checks=0;
function p55Check(bool $pass,string $label):void {
    global $checks;$checks++;
    if(!$pass)throw new RuntimeException('AIA_P55_FAIL_'.$label);
}
function p55Command(string $tenant,string $request,int $visits=120):array {
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,
        'request_ref'=>$request,
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>1000,
        'client_rate_bp'=>2000
    ];
}
function p55Rules(string $tenant,bool $noMatch=false):array {
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P55_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P55_RULE_LEADS',
                'metric'=>'projected_leads',
                'operator'=>'gte',
                'threshold'=>$noMatch?13:12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT'
            ],
            [
                'rule_ref'=>'SYNTHETIC_P55_RULE_CUSTOMERS',
                'metric'=>'projected_customers',
                'operator'=>'eq',
                'threshold'=>$noMatch?3:2,
                'draft_kind'=>'CRM_TASK_DRAFT'
            ]
        ]
    ];
}
function p55Client(string $role,string $password):PDO{
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        $role,$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
}
try{
    $tenantA='SYNTHETIC_TENANT_A';
    $tenantB='SYNTHETIC_TENANT_B';
    $request='SYNTHETIC_P55_SHARED_REQUEST';
    p55Check($registry->stage(
        p55Command($tenantA,$request),p55Rules($tenantA)
    )['decision']==='LAB_DRAFTS_STAGED_NO_ACTION','stage_tenant_A');
    p55Check($registry->stage(
        p55Command($tenantB,$request),p55Rules($tenantB)
    )['decision']==='LAB_DRAFTS_STAGED_NO_ACTION','stage_tenant_B');
    p55Check($registry->stage(
        p55Command($tenantA,'SYNTHETIC_P55_NO_MATCH'),
        p55Rules($tenantA,true)
    )['decision']==='LAB_DRAFTS_STAGED_NO_ACTION','legitimate_empty_batch');
    $passwordA=bin2hex(random_bytes(18));
    $passwordB=bin2hex(random_bytes(18));
    $db->exec("CREATE ROLE aia_p55_ci_read_a LOGIN PASSWORD '{$passwordA}' NOSUPERUSER NOCREATEDB NOCREATEROLE");
    $db->exec("CREATE ROLE aia_p55_ci_read_b LOGIN PASSWORD '{$passwordB}' NOSUPERUSER NOCREATEDB NOCREATEROLE");
    $db->exec('GRANT USAGE ON SCHEMA aia_p51_lab TO aia_p55_ci_read_a,aia_p55_ci_read_b');
    $db->exec('GRANT SELECT ON aia_p51_lab.preview_batches,aia_p51_lab.review_drafts TO aia_p55_ci_read_a,aia_p55_ci_read_b');
    foreach(['preview_batches','review_drafts'] as $table){
        $db->exec("ALTER TABLE aia_p51_lab.{$table} ENABLE ROW LEVEL SECURITY");
        $db->exec("ALTER TABLE aia_p51_lab.{$table} FORCE ROW LEVEL SECURITY");
        $db->exec("CREATE POLICY p55_read_a ON aia_p51_lab.{$table}
            FOR SELECT TO aia_p55_ci_read_a
            USING (tenant_ref='SYNTHETIC_TENANT_A')");
        $db->exec("CREATE POLICY p55_read_b ON aia_p51_lab.{$table}
            FOR SELECT TO aia_p55_ci_read_b
            USING (tenant_ref='SYNTHETIC_TENANT_B')");
    }

    $readerA=p55Client('aia_p55_ci_read_a',$passwordA);
    $readerB=p55Client('aia_p55_ci_read_b',$passwordB);
    $a=new PgScopedReviewQueueBridgeLab($readerA);
    $b=new PgScopedReviewQueueBridgeLab($readerB);
    $admin=new PgScopedReviewQueueBridgeLab($db);

    $safeA=$a->read($tenantA);
    p55Check($safeA['decision']==='LAB_READONLY_PG_REVIEW_NO_GO'
        &&$safeA['checked_batches']===2
        &&$safeA['checked_drafts']===2
        &&$safeA['presentation']['total_pending']===2
        &&$safeA['presentation']['can_approve']===false
        &&$safeA['presentation']['can_execute']===false
        &&$safeA['ai_calls']===0 &&$safeA['service_effects']===0,
        'one_consistent_db_snapshot_tenant_A');
    $safeB=$b->read($tenantB);
    p55Check($safeB['decision']==='LAB_READONLY_PG_REVIEW_NO_GO'
        &&$safeB['checked_batches']===1
        &&$safeB['checked_drafts']===2
        &&$safeB['presentation']['total_pending']===2
        &&$safeB['presentation']['can_execute']===false,
        'one_consistent_db_snapshot_tenant_B');
    p55Check($a->read($tenantB)['reason']==='DATABASE_ROLE_TENANT_SCOPE_DENIED'
        &&$b->read($tenantA)['reason']==='DATABASE_ROLE_TENANT_SCOPE_DENIED',
        'role_scope_checked_before_any_tenant_rows');
    p55Check($admin->read($tenantA)['reason']==='DATABASE_ROLE_TENANT_SCOPE_DENIED',
        'privileged_db_user_cannot_read_via_ci_facade');
    p55Check($a->read('TENANT_A')['decision']==='DENY'
        &&$a->read('SYNTHETIC_')['decision']==='DENY',
        'invalid_tenant_denied');

    // Mandatory role-based RLS itself must exclude other tenants even
    // if code in a future UI incorrectly constructs an unfiltered query.
    p55Check((int)$readerA->query(
        'SELECT COUNT(*) FROM aia_p51_lab.review_drafts'
    )->fetchColumn()===2,'db_rls_A_sees_only_A');
    p55Check((int)$readerB->query(
        'SELECT COUNT(*) FROM aia_p51_lab.review_drafts'
    )->fetchColumn()===2,'db_rls_B_sees_only_B');
    p55Check((int)$readerA->query(
        'SELECT COUNT(*) FROM aia_p51_lab.preview_batches'
    )->fetchColumn()===2,'db_rls_A_sees_zero_proposal_batch');
    try{
        $readerA->exec(
            "UPDATE aia_p51_lab.review_drafts SET observed=33 WHERE tenant_ref='SYNTHETIC_TENANT_A'"
        );
        throw new RuntimeException('AIA_P55_WRITE_MUST_BE_DENIED');
    }catch(PDOException){
        p55Check(true,'database_role_has_no_mutation_privilege');
    }

    // Tampering with one row must DENY only that tenant, with no raw SQL
    // leak and no silent self-repair. Tenant B stays independently readable.
    $db->exec("UPDATE aia_p51_lab.review_drafts SET observed=31
       WHERE tenant_ref='SYNTHETIC_TENANT_A'
       AND rule_ref='SYNTHETIC_P55_RULE_LEADS'");
    $tampered=$a->read($tenantA);
    p55Check($tampered['reason']==='REVIEW_DRAFT_HASH_INCONSISTENT'
        &&$tampered['presentation']===null
        &&$tampered['ai_calls']===0,'tampered_row_fails_closed');
    p55Check($b->read($tenantB)['decision']==='LAB_READONLY_PG_REVIEW_NO_GO',
        'tamper_in_A_does_not_expose_or_block_B');
    $db->exec("UPDATE aia_p51_lab.review_drafts SET observed=12
       WHERE tenant_ref='SYNTHETIC_TENANT_A'
       AND rule_ref='SYNTHETIC_P55_RULE_LEADS'");
    p55Check($a->read($tenantA)['decision']==='LAB_READONLY_PG_REVIEW_NO_GO',
        'restore_original_row_revalidated');

    $db->exec("DELETE FROM aia_p51_lab.review_drafts
        WHERE tenant_ref='SYNTHETIC_TENANT_A'
        AND rule_ref='SYNTHETIC_P55_RULE_CUSTOMERS'");
    p55Check($a->read($tenantA)['reason']==='REVIEW_DRAFT_COUNT_INCONSISTENT',
        'lost_review_draft_does_not_return_partial_queue');

    // In a transaction held by the caller we must deny rather than try
    // to weaken transaction guarantees or accept stale snapshots.
    $readerA->beginTransaction();
    p55Check($a->read($tenantA)['decision']==='DENY',
        'nested_transaction_never_treated_as_trusted_snapshot');
    $readerA->rollBack();
    p55Check($b->read($tenantB)['decision']==='LAB_READONLY_PG_REVIEW_NO_GO',
        'read_after_nested_tx_denial_is_still_consistent');

    echo "AIA_P55_SCOPED_PG_REVIEW_BRIDGE_PASS checks={$checks} real_pg_rls=1 readonly_roles=2 one_snapshot=1 cross_tenant_deny=1 no_approval=1 no_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if(isset($readerA)&&$readerA->inTransaction())$readerA->rollBack();
    if(isset($readerB)&&$readerB->inTransaction())$readerB->rollBack();
}
