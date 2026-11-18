<?php
declare(strict_types=1);
/** Prepare only disposable PG16 + temporary read-only CI identity for P56. */
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/PgScopedReviewQueueBridgeLab.php';

use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\PgScopedReviewQueueBridgeLab;

if(PHP_SAPI!=='cli'
    ||getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P55_SCOPED_LAB')!=='true'
    ||getenv('AIA_P56_BROWSER_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P56_DISPOSABLE_CI_ONLY');
}
$temp=getenv('RUNNER_TEMP');
if(!is_string($temp)||!is_dir($temp)){
    throw new RuntimeException('P56_RUNNER_TEMP_REQUIRED');
}
$db=new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$registry=new PgDeterministicDraftRegistryLab($db);
$registry->createTables();
$db->exec('TRUNCATE TABLE aia_p51_lab.review_drafts,aia_p51_lab.preview_batches');

function p56Rules(string $tenant):array{
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P56_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P56_LEADS',
                'metric'=>'projected_leads',
                'operator'=>'gte',
                'threshold'=>12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT'
            ],
            [
                'rule_ref'=>'SYNTHETIC_P56_CUSTOMERS',
                'metric'=>'projected_customers',
                'operator'=>'eq',
                'threshold'=>2,
                'draft_kind'=>'CRM_TASK_DRAFT'
            ]
        ]
    ];
}
function p56Command(string $tenant,string $request):array{
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,
        'request_ref'=>$request,
        'weekly_visits'=>120,
        'lead_rate_bp'=>1000,
        'client_rate_bp'=>2000
    ];
}
foreach([
    ['SYNTHETIC_TENANT_A','SYNTHETIC_P56_REQUEST_A'],
    ['SYNTHETIC_TENANT_B','SYNTHETIC_P56_REQUEST_B']
] as [$tenant,$request]){
    $result=$registry->stage(
        p56Command($tenant,$request),p56Rules($tenant)
    );
    if($result['decision']!=='LAB_DRAFTS_STAGED_NO_ACTION'
        ||$result['proposal_count']!==2){
        throw new RuntimeException('P56_FIXTURE_STAGE_DENIED');
    }
}
$password=bin2hex(random_bytes(18));
$pwFile=$temp.'/aia_p56_ci_reader_password';
if(file_exists($pwFile)){
    throw new RuntimeException('P56_UNEXPECTED_PREVIOUS_CREDENTIAL_FILE');
}
$db->exec("CREATE ROLE aia_p55_ci_read_a LOGIN PASSWORD '{$password}'
    NOSUPERUSER NOCREATEDB NOCREATEROLE");
$db->exec('GRANT USAGE ON SCHEMA aia_p51_lab TO aia_p55_ci_read_a');
$db->exec('GRANT SELECT ON aia_p51_lab.review_drafts,
    aia_p51_lab.preview_batches TO aia_p55_ci_read_a');
foreach(['review_drafts','preview_batches'] as $table){
    $db->exec("ALTER TABLE aia_p51_lab.{$table} ENABLE ROW LEVEL SECURITY");
    $db->exec("ALTER TABLE aia_p51_lab.{$table} FORCE ROW LEVEL SECURITY");
    $db->exec("CREATE POLICY p56_read_a ON aia_p51_lab.{$table}
        FOR SELECT TO aia_p55_ci_read_a
        USING(tenant_ref='SYNTHETIC_TENANT_A')");
}
if(file_put_contents($pwFile,$password,LOCK_EX)!==strlen($password)){
    throw new RuntimeException('P56_PASSWORD_FILE_CREATION_FAILED');
}
chmod($pwFile,0400);
$readonly=new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'aia_p55_ci_read_a',$password,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$view=(new PgScopedReviewQueueBridgeLab($readonly))->read(
    'SYNTHETIC_TENANT_A'
);
if($view['decision']!=='LAB_READONLY_PG_REVIEW_NO_GO'
    ||$view['checked_batches']!==1
    ||$view['checked_drafts']!==2
    ||$view['presentation']['can_approve']!==false){
    throw new RuntimeException('P56_SNAPSHOT_PRECONDITION_DENIED');
}
echo "AIA_P56_BROWSER_LAB_SEEDED role=synthetic_pg_rls_only tenants=2 visible=2 no_ai=1 NO_GO\n";
