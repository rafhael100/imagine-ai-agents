<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/PgReadOnlyDraftAuditLab.php';

use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\PgReadOnlyDraftAuditLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P52_DISPOSABLE_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$registry=new PgDeterministicDraftRegistryLab($db);
$registry->createTables();
$db->exec('TRUNCATE TABLE aia_p51_lab.review_drafts, aia_p51_lab.preview_batches');
$audit=new PgReadOnlyDraftAuditLab($db);

$checks=0;
function p52Check(bool $yes,string $name):void {
    global $checks;$checks++;
    if(!$yes)throw new RuntimeException('AIA_P52_FAIL_'.$name);
}
function p52Input(string $tenant,string $request,int $visits=120):array {
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
function p52Rules(string $tenant,bool $noMatches=false):array {
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P52_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P52_RULE_A',
                'metric'=>'projected_leads',
                'operator'=>'gte',
                'threshold'=>$noMatches?13:12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT'
            ],
            [
                'rule_ref'=>'SYNTHETIC_P52_RULE_B',
                'metric'=>'projected_customers',
                'operator'=>'eq',
                'threshold'=>$noMatches?3:2,
                'draft_kind'=>'CRM_TASK_DRAFT'
            ],
        ],
    ];
}
function p52Issue(array $report,string $name):bool {
    return in_array($name,array_column($report['issues'],'reason'),true);
}
try{
    $a='SYNTHETIC_TENANT_A';
    $b='SYNTHETIC_TENANT_B';
    $request='SYNTHETIC_P52_REQUEST';
    $empty=$audit->scan();
    p52Check($empty['decision']==='LAB_READONLY_DRAFTS_INTEGRITY_NO_GO'
        &&$empty['checked_batches']===0
        &&$empty['checked_drafts']===0,'empty_registry_is_consistent');
    p52Check($empty['ai_calls']===0
        &&$empty['external_calls']===0
        &&$empty['repaired_rows']===0,'zero_external_effects');
    p52Check($registry->stage(p52Input($a,$request),p52Rules($a))
        ['decision']==='LAB_DRAFTS_STAGED_NO_ACTION','stage_tenant_A');
    p52Check($registry->stage(p52Input($b,$request),p52Rules($b))
        ['decision']==='LAB_DRAFTS_STAGED_NO_ACTION','stage_tenant_B');
    $clean=$audit->scan();
    p52Check($clean['decision']==='LAB_READONLY_DRAFTS_INTEGRITY_NO_GO'
        &&$clean['checked_batches']===2
        &&$clean['checked_drafts']===4,'two_tenants_four_intact_drafts');

    $q=$db->query(<<<'SQL'
SELECT proposal_ref,observed,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref='SYNTHETIC_TENANT_A'
  AND rule_ref='SYNTHETIC_P52_RULE_A'
SQL)->fetch(PDO::FETCH_ASSOC);
    p52Check(is_array($q),'saved_fingerprint_fixture');
    $db->prepare(<<<'SQL'
UPDATE aia_p51_lab.review_drafts
SET observed=:changed
WHERE tenant_ref=:tenant AND proposal_ref=:proposal
SQL)->execute([
        ':changed'=>(int)$q['observed']+1,
        ':tenant'=>$a,':proposal'=>$q['proposal_ref']
    ]);
    $mutated=$audit->scan();
    p52Check($mutated['decision']==='DENY_INCONSISTENT'
        &&p52Issue($mutated,'DRAFT_FINGERPRINT_MISMATCH'),
        'read_only_audit_detects_value_tampering');
    p52Check($mutated['repaired_rows']===0,
        'auditor_never_repairs_modified_draft');
    $db->prepare(<<<'SQL'
UPDATE aia_p51_lab.review_drafts
SET observed=:original
WHERE tenant_ref=:tenant AND proposal_ref=:proposal
SQL)->execute([
        ':original'=>(int)$q['observed'],
        ':tenant'=>$a,':proposal'=>$q['proposal_ref']
    ]);
    p52Check($audit->scan()['decision']
        ==='LAB_READONLY_DRAFTS_INTEGRITY_NO_GO',
        'restored_original_row_passes_again');

    $db->prepare(<<<'SQL'
DELETE FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND proposal_ref=:proposal
SQL)->execute([':tenant'=>$a,':proposal'=>$q['proposal_ref']]);
    $missing=$audit->scan();
    p52Check($missing['decision']==='DENY_INCONSISTENT'
        &&p52Issue($missing,'DRAFT_COUNT_MISMATCH'),
        'missing_human_review_draft_detected');
    p52Check($missing['checked_drafts']===3 &&$missing['repaired_rows']===0,
        'missing_draft_not_recreated');

    $db->exec("UPDATE aia_p51_lab.preview_batches
      SET proposal_count=3
      WHERE tenant_ref='SYNTHETIC_TENANT_B'");
    p52Check(p52Issue($audit->scan(),'DRAFT_COUNT_MISMATCH'),
        'declared_cardinality_tamper_detected');

    // Add a legitimate zero-proposal batch. It must not be misclassified
    // as incomplete just because there are no child rows.
    p52Check($registry->stage(
        p52Input($b,'SYNTHETIC_P52_ZERO'),
        p52Rules($b,true)
    )['decision']==='LAB_DRAFTS_STAGED_NO_ACTION',
        'zero_proposal_batch_staged');

    $report=$audit->scan();
    p52Check($report['checked_batches']===3
        &&p52Issue($report,'DRAFT_COUNT_MISMATCH'),
        'zero_batch_does_not_hide_existing_inconsistency');
    $json=json_encode($report,JSON_THROW_ON_ERROR);
    p52Check(!str_contains($json,'draft_sha256')
        &&!str_contains($json,'canonical_input')
        &&!str_contains($json,$q['draft_sha256']),
        'only_issue_category_and_synthetic_refs_emitted');

    $db->exec('DROP TABLE aia_p51_lab.review_drafts');
    p52Check($audit->scan()['decision']==='INDETERMINATE',
        'missing_registry_table_never_reports_pass');
    echo "AIA_P52_DRAFT_INTEGRITY_AUDIT_PASS checks={$checks} tamper_detected=1 missing_detected=1 zero_batch_allowed=1 readonly=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
