<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAiCreditReservationLab.php';
require_once __DIR__.'/../src/MockOrchestration/MockAiOrAutomationRouter.php';

use ImagineAI\Labs\PgAiCreditReservationLab;

function checkP8(bool $ok,string $label): void
{
    if (!$ok) throw new RuntimeException('P8_CREDIT_LAB_DENIED_'.$label);
}
function connectP8(): PDO
{
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
}
if (getenv('AIA_P8_PG_LAB')!=='true'
    || getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('DB_HOST')!=='127.0.0.1'
    || getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P8_CI_ONLY');
}
$db=connectP8();
$lab=new PgAiCreditReservationLab($db);
$lab->createTables();
try {
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_A',100),'seed A');
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_B',30),'seed B');

    // P5 -> P8 integration contract: deterministic tasks never even
    // request a credit hold; semantic tasks require all eligibility checks.
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_C',40),'seed model test tenant');
    $intent=[
        'tenant_ref'=>'SYNTHETIC_TENANT_C',
        'run_ref'=>'SYNTHETIC_RUN_01',
        'operation'=>'mock.funnel.estimate',
        'content_class'=>'SYNTHETIC_ONLY',
        'requires_external_effect'=>false,
        'estimated_model_units'=>0,
    ];
    $policy=[
        'tenant_ref'=>'SYNTHETIC_TENANT_C',
        'ai_opt_in'=>false,'consent_verified'=>false,
        'approved_for_model'=>false,'available_units'=>40,
        'per_call_limit'=>0,'provider_configured'=>false,'ledger_certain'=>false,
    ];
    $route=\ImagineAI\MockOrchestration\MockAiOrAutomationRouter::inspect($intent,$policy);
    checkP8($route['decision']==='DETERMINISTIC_ONLY'
        && $route['proposed_units']===0
        && $route['credits_consumed']===0,'deterministic preflight is zero-credit');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')['available']===40,
        'preflight must not touch money or PG quota');
    $intent['operation']='mock.copy.draft';
    $intent['estimated_model_units']=15;
    $policy=array_replace($policy,[
        'ai_opt_in'=>true,'consent_verified'=>true,
        'approved_for_model'=>true,'per_call_limit'=>20,
        'provider_configured'=>true,'ledger_certain'=>true,
    ]);
    $model=\ImagineAI\MockOrchestration\MockAiOrAutomationRouter::inspect($intent,$policy);
    checkP8($model['decision']==='MODEL_ELIGIBLE_NOT_EXECUTED'
        && $model['proposed_units']===15 && $model['credits_consumed']===0
        && $model['external_calls']===0,'model only eligible no API');
    checkP8($lab->reserve('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL',
        'SYNTHETIC_PURPOSE_MARKETING',$model['proposed_units'])===
        ['state'=>'RESERVED','estimated'=>15],'reserve separately after preflight');
    checkP8($lab->markUnknown('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL'),
        'uncertain future provider response keeps hold');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')['held']===15,
        'no automatic refund of unknown credit');
    checkP8($lab->reconcile('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL',12),
        'verified synthetic observed measure');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')===[
        'allowance'=>40,'spent'=>12,'held'=>0,'available'=>28,'frozen'=>false,
    ],'only observed usage charged after reconciliation');

    checkP8(!$lab->seedSynthetic('SYNTHETIC_TENANT_A',100),'reject double funding');
    checkP8(!$lab->seedSynthetic('REAL_CUSTOMER',100),'reject real customer');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','INVALID_ID',
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,'reject invalid request');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_REQUEST_ZERO',
        'SYNTHETIC_PURPOSE_MARKETING',0)===null,'zero estimate denied');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_REQUEST_OVER',
        'SYNTHETIC_PURPOSE_MARKETING',5001)===null,'oversize estimate denied');

    // Concurrent physical PG16 row locks. No overspend and no double hold.
    $processes=[];
    for($i=1;$i<=10;$i++){
        $request='SYNTHETIC_REQUEST_PARALLEL_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $cmd=[PHP_BINARY,__DIR__.'/p8_credit_worker.php','reserve',$request,'20'];
        $proc=proc_open($cmd,[
            0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],
        ],$pipes,__DIR__);
        checkP8(is_resource($proc),'worker spawned');
        fclose($pipes[0]);
        $processes[]=[$proc,$pipes,$request];
    }
    $accepted=[];$denied=0;
    foreach($processes as [$proc,$pipes,$request]){
        $stdout=trim(stream_get_contents($pipes[1]));
        $stderr=trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        checkP8(proc_close($proc)===0 && $stderr==='','worker success');
        $result=json_decode($stdout,true,5,JSON_THROW_ON_ERROR);
        if($result===null){$denied++;continue;}
        checkP8($result===['state'=>'RESERVED','estimated'=>20],'unique hold');
        $accepted[]=$request;
    }
    checkP8(count($accepted)===5 && $denied===5,'five accepted five denied');
    sort($accepted);
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>0,'held'=>100,'available'=>0,'frozen'=>false,
    ],'zero overbooking');

    [$first,$second,$third]=$accepted;
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===['state'=>'REPLAY','estimated'=>20],
        'idempotent replay same credit');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_OTHER',20)===null,'conflicting id denied');

    checkP8($lab->markUnknown('SYNTHETIC_TENANT_A',$first),'unknown holds credit');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$first),
        'cannot refund unknown provider result');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')['available']===0,
        'timeout reserves whole amount');
    checkP8(!$lab->markUnknown('SYNTHETIC_TENANT_A',$first),
        'second timeout signal cannot alter state');

    checkP8($lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$second),
        'verified cancellation before dispatch');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$second),
        'cannot double refund');
    checkP8(!$lab->reconcile('SYNTHETIC_TENANT_A',$second,0),
        'cancelled cannot become provider charge');

    checkP8($lab->reconcile('SYNTHETIC_TENANT_A',$third,12),
        'verified observed 12');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>12,'held'=>60,'available'=>28,'frozen'=>false,
    ],'net after cancellation and 12 actual');
    checkP8($lab->reconcile('SYNTHETIC_TENANT_A',$first,19),
        'late observed provider proof frees uncertain hold');
    checkP8($lab->reconcile('SYNTHETIC_TENANT_A',$first,19),
        'same measured proof idempotent');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>31,'held'=>40,'available'=>29,'frozen'=>false,
    ],'late proof only counted once');
    checkP8(!$lab->reconcile('SYNTHETIC_TENANT_B',$first,19),
        'cross tenant cannot reconcile');

    // Conflicting late proof must freeze the account rather than guessing
    // which charge to trust.
    checkP8(!$lab->reconcile('SYNTHETIC_TENANT_A',$first,18),
        'contradictory proof denied');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')['frozen']===true,
        'ledger frozen on contradiction');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_NEW_REQUEST',
        'SYNTHETIC_PURPOSE_MARKETING',10)===null,'frozen cannot spend');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$accepted[3]),
        'frozen cannot refund blindly');

    // Tenant B remains independent, even with the same idempotency ref.
    checkP8($lab->reserve('SYNTHETIC_TENANT_B',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===['state'=>'RESERVED','estimated'=>20],
        'tenant B independent request');
    checkP8($lab->reconcile('SYNTHETIC_TENANT_B',$first,20),
        'B exact observation');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_B')===[
        'allowance'=>30,'spent'=>20,'held'=>0,'available'=>10,'frozen'=>false,
    ],'B ledger independent');

    $lab->dropTables();
    checkP8($lab->reserve('SYNTHETIC_TENANT_B','SYNTHETIC_OUTAGE',
        'SYNTHETIC_PURPOSE_MARKETING',5)===null,
        'database outage fail closed');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_B')===null,
        'database outage no fictitious fallback');
    echo "P8_PG16_CREDIT_RESERVATION_PASS workers=10 accepted=5 rejected=5 timeout_hold=true duplicate_charge=none provider_calls=0 NO_GO\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    $lab->dropTables();
}
