<?php
declare(strict_types=1);

require_once __DIR__.'/../../src/Automation/DeterministicAutomationService.php';

use ImagineAI\Automation\DeterministicFunnelProjector;
use ImagineAI\Automation\DeterministicAutomationService;

$checks=0;
function p48Check(bool $ok,string $label):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P48_FAIL_'.$label);
}
function p48Denied(callable $fn,string $name):void{
    $denied=false;
    try{$fn();}
    catch(InvalidArgumentException){$denied=true;}
    p48Check($denied,$name);
}
function p48Command(int $visits=120,int $leadBp=1000,int $clientBp=2000):array{
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
        'request_ref'=>'SYNTHETIC_P48_REQUEST',
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>$leadBp,
        'client_rate_bp'=>$clientBp
    ];
}
$service=new DeterministicAutomationService();
$input=p48Command();
$result=$service->calculate($input);
p48Check($result===[
    'schema'=>'AIA_DETERMINISTIC_RESULT_V1',
    'operation'=>'funnel.calculate',
    'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
    'request_ref'=>'SYNTHETIC_P48_REQUEST',
    'visits'=>120,
    'projected_leads'=>12,
    'projected_customers'=>2,
    'decision'=>'NO_AI',
    'status'=>'LAB_PREVIEW_ONLY',
    'ai_api_calls'=>0,
    'ai_tokens'=>0,
    'commercial_credits_debited'=>0,
    'operational_authorization'=>false,
],'native_RC07_120_visits_12_leads_2_customers_parity');
p48Check($service->calculate($input)===$result,
    'repeatable_identical_output_no_side_effects');
p48Check($input===p48Command(),'input_not_mutated');

foreach([
    [0,1000,2000,0,0],
    [5,1000,5000,1,1],
    [5,3000,5000,2,1],
    [100,0,10000,0,0],
    [100,10000,10000,100,100],
    [100_000_000,10000,10000,100_000_000,100_000_000],
] as $row){
    [$visits,$lead,$client,$expectedLead,$expectedCustomer]=$row;
    $r=DeterministicFunnelProjector::project($visits,$lead,$client);
    p48Check($r['projected_leads']===$expectedLead
        &&$r['projected_customers']===$expectedCustomer,
        'bounded_integer_projection_'.$visits.'_'.$lead.'_'.$client);
}
foreach([
    [-1,1000,2000],[100_000_001,1000,2000],
    [120,-1,2000],[120,10001,2000],
    [120,1000,-1],[120,1000,10001],
] as $bad){
    p48Denied(static fn()=>DeterministicFunnelProjector::project(...$bad),
        'invalid_rate_or_volume_bounds_'.implode('_',$bad));
}
$mutations=[
    'operation'=>['campaign.publish'],
    'schema'=>['AIA_UNREVIEWED_COMMAND'],
    'tenant_ref'=>['TENANT_UNKNOWN','SYNTHETIC_lowercase'],
    'request_ref'=>['SYNTHETIC_','SYNTHETIC_'.str_repeat('A',65)],
    'weekly_visits'=>[-1,100_000_001,120.0,'120'],
    'lead_rate_bp'=>[10001,-1,'1000',10.0],
    'client_rate_bp'=>[10001,-1,'2000',20.0],
];
foreach($mutations as $field=>$values){
    foreach($values as $value){
        $bad=p48Command();
        $bad[$field]=$value;
        p48Denied(static fn()=>$service->calculate($bad),
            'input_mutation_'.$field.'_'.gettype($value));
    }
}
$extra=p48Command();$extra['provider_api_key']='SYNTHETIC_NOT_A_SECRET';
p48Denied(static fn()=>$service->calculate($extra),'no_unreviewed_extra_fields');
$missing=p48Command();unset($missing['request_ref']);
p48Denied(static fn()=>$service->calculate($missing),'missing_identity_denied');
$reordered=p48Command();
$reordered=['operation'=>$reordered['operation'],'schema'=>$reordered['schema']]
    +array_diff_key($reordered,['operation'=>true,'schema'=>true]);
p48Denied(static fn()=>$service->calculate($reordered),
    'noncanonical_field_order_denied');
p48Check($result['ai_api_calls']===0
    &&$result['ai_tokens']===0
    &&$result['commercial_credits_debited']===0
    &&$result['operational_authorization']===false,
    'model_and_credit_never_used');

echo "AIA_P48_DETERMINISTIC_CORE_PASS checks={$checks} visits=120 leads=12 customers=2 model_calls=0 credits=0 operational=false\n";
