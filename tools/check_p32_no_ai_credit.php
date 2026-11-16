<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgGuardedAutomationCycleLab.php';
require_once __DIR__.'/labs/PgAiCreditReservationLab.php';
require_once __DIR__.'/labs/P18Fixture.php';
require_once __DIR__.'/../_auto_ia_pinned_source/tools/auto_ia/AutoIaRouter.php';

use ImagineAI\Convergence\PgGuardedAutomationCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgAiCreditReservationLab;
use ImagineAI\Labs\PgN8nOutboxLab;
use ImagineAI\AutomationFirst\AutoIaRouter;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P8_PG_LAB')!=='true'
    ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'
    ||getenv('DB_USERNAME')!=='postgres')
    throw new RuntimeException('P32_DISPOSABLE_CI_REQUIRED');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$accounting=new PgAiCreditReservationLab($db);
$accounting->createTables();
$cycle=new PgGuardedAutomationCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);

$checks=0;
function p32Check(bool $v,string $label):void {
    global $checks; $checks++;
    if(!$v)throw new RuntimeException('P32_ZERO_CREDIT_FAIL_'.$label);
}
function p32Requests(PDO $db):int {
    return (int)$db->query('SELECT COUNT(*) FROM public.aia_lab_credit_requests_p8')
        ->fetchColumn();
}
function p32Route(string $tenant,string $event,string $operation):array {
    return AutoIaRouter::classify([
        'schema'=>'AUTO_IA_EVENT_V1',
        'product'=>'imagine-ai-agents',
        'operation'=>$operation,
        'tenant_ref'=>$tenant,
        'event_ref'=>$event,
        'semantic_reason'=>$operation==='document.summary.propose'
            ?'MULTI_SOURCE_INTERPRETATION':'UNSTRUCTURED_LANGUAGE',
    ]);
}
try {
    p32Check($accounting->seedSynthetic('SYNTHETIC_TENANT_A',100),'seed_A');
    p32Check($accounting->seedSynthetic('SYNTHETIC_TENANT_B',0),'seed_B_no_credits');
    $before=[
        'SYNTHETIC_TENANT_A'=>$accounting->snapshot('SYNTHETIC_TENANT_A'),
        'SYNTHETIC_TENANT_B'=>$accounting->snapshot('SYNTHETIC_TENANT_B'),
    ];
    p32Check($before['SYNTHETIC_TENANT_A']['available']===100,'A_has_100');
    p32Check($before['SYNTHETIC_TENANT_B']['available']===0,'B_has_zero');
    p32Check(p32Requests($db)===0,'initially_no_ai_credit_requests');

    $total=0;
    foreach(['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'] as $tenant) {
        for($i=1;$i<=12;$i++) {
            $event='SYNTHETIC_P32_EVENT_'.($tenant==='SYNTHETIC_TENANT_A'?'A_':'B_').
                str_pad((string)$i,2,'0',STR_PAD_LEFT);
            // Even maliciously supplied semantic_reason cannot reclassify a
            // deterministic operation as needing an AI model.
            $route=p32Route($tenant,$event,'run.status.project');
            p32Check($route['decision']==='NO_AI'
                && $route['provider_calls']===0 && $route['model_tokens']===0,
                'deterministic_router_'.$event);

            [$canonical,$signed,$policy]=P18Fixture::make($tenant,$event);
            $seen=[];
            $staged=$cycle->stage($canonical,$signed,$policy,P18Fixture::NOW,
                ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
            p32Check($staged['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH'
                && $staged['ai_calls']===0,'stage_without_ai_'.$event);
            $worker='SYNTHETIC_P32_WORKER_'.($tenant==='SYNTHETIC_TENANT_A'?'A':'B');
            $lease=$cycle->reserve($tenant,$worker,P18Fixture::NOW);
            p32Check($lease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'
                && $lease['event_ref']===$event
                && $lease['ai_calls']===0,'lease_without_ai_'.$event);
            $ack=$cycle->acknowledge($tenant,$event,$worker,$lease['attempt'],
                P18Fixture::NOW+1);
            p32Check($ack['decision']==='LAB_ACKED_NO_DELIVERY_PROOF'
                && $ack['ai_calls']===0,'local_ack_without_ai_'.$event);

            p32Check($accounting->snapshot($tenant)===$before[$tenant],
                'unchanged_credit_snapshot_'.$event);
            p32Check(p32Requests($db)===0,'never_reserve_ai_credit_'.$event);
            $total++;
        }
    }
    $audit=$cycle->audit();
    p32Check($audit['decision']==='LAB_CONSISTENT_NO_AUTHORIZATION'
        && $audit['checked']===24,'all_24_events_consistent');
    p32Check($audit['ai_calls']===0 && $audit['external_calls']===0,
        'audit_without_ai_api');
    p32Check($outbox->stats('SYNTHETIC_TENANT_A')['acked']===12
        && $outbox->stats('SYNTHETIC_TENANT_B')['acked']===12,
        'both_tenants_ack_twelve');

    // A semantic request is not processed by this deterministic facade.
    // Routing it for review alone must neither invoke models nor hold credits.
    $semantic=p32Route('SYNTHETIC_TENANT_B','SYNTHETIC_P32_SEMANTIC_REVIEW',
        'document.summary.propose');
    p32Check($semantic['decision']==='AI_REVIEW_REQUIRED'
        && $semantic['provider_calls']===0 && $semantic['model_tokens']===0,
        'semantic_requires_external_approval');
    p32Check($accounting->snapshot('SYNTHETIC_TENANT_B')===$before['SYNTHETIC_TENANT_B']
        && p32Requests($db)===0,'no_charge_from_semantic_review');

    echo "AIA_P32_NO_AI_CREDIT_PASS checks={$checks} events={$total} no_credit_tenant=1 credit_requests=0 api_calls=0 n8n_calls=0 NO_GO\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
    $accounting->dropTables();
}
