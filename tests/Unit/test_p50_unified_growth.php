<?php
declare(strict_types=1);

require_once __DIR__.'/../../src/Automation/UnifiedGrowthProjectionService.php';

use ImagineAI\Automation\UnifiedGrowthProjectionService;

$checks=0;
function p50Check(bool $ok,string $case):void{
    global $checks;
    $checks++;
    if(!$ok)throw new RuntimeException('P50_FAIL_'.$case);
}
function p50Denied(callable $fn,string $case):void{
    $rejected=false;
    try{$fn();}
    catch(InvalidArgumentException){$rejected=true;}
    p50Check($rejected,'fail_closed_'.$case);
}
function p50Weekly(int $visits=120,int $lead=1000,int $client=2000):array{
    return [
        'schema'=>'AIA_GROWTH_PROJECTION_COMMAND_V1',
        'mode'=>'n8n_weekly',
        'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
        'request_ref'=>'SYNTHETIC_P50_REQ_WEEK',
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>$lead,
        'client_rate_bp'=>$client
    ];
}
function p50Monthly(int $visits=120,string $scenario='reference'):array{
    return [
        'schema'=>'AIA_GROWTH_PROJECTION_COMMAND_V1',
        'mode'=>'comercial_monthly',
        'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
        'request_ref'=>'SYNTHETIC_P50_REQ_MONTH',
        'weekly_visits'=>$visits,
        'scenario'=>$scenario
    ];
}
$service=new UnifiedGrowthProjectionService();
$weekly=$service->project(p50Weekly());
$monthly=$service->project(p50Monthly());
p50Check($weekly['period']==='week'
    &&$weekly['weekly_visits']===120
    &&$weekly['visits']===120
    &&$weekly['leads']===12
    &&$weekly['customers']===2
    &&$weekly['lead_rate_bp']===1000
    &&$weekly['client_rate_bp']===2000,'n8n_weekly_exact');
p50Check($monthly['period']==='illustrative_4_week_month'
    &&$monthly['weekly_visits']===120
    &&$monthly['visits']===480
    &&$monthly['leads']===19
    &&$monthly['customers']===4
    &&$monthly['scenario']==='reference'
    &&$monthly['weeks_per_month']===4,'comercial_monthly_exact');
p50Check($weekly['customers']!==$monthly['customers']
    &&$weekly['visits']!==$monthly['visits'],
    'different_periods_must_never_be_conflated');
foreach([$weekly,$monthly] as $result){
    p50Check($result['decision']==='NO_AI'
        &&$result['status']==='LAB_PREVIEW_ONLY'
        &&$result['assumption_only']===true
        &&$result['actual_performance_data']===false
        &&$result['real_results_guaranteed']===false
        &&$result['operational_authorization']===false
        &&$result['ai_api_calls']===0
        &&$result['ai_tokens']===0
        &&$result['commercial_credits_debited']===0,
        'explicit_zero_ai_zero_commitments_'.$result['mode']);
}
foreach([
    ['conservative',10,1],['reference',19,4],['optimistic',29,9]
] as [$scenario,$leads,$customers]){
    $result=$service->project(p50Monthly(120,$scenario));
    p50Check($result['leads']===$leads
        &&$result['customers']===$customers
        &&$result['visits']===480,'three_monthly_scenarios_'.$scenario);
}
p50Check($service->project(p50Weekly())===$weekly
    &&$service->project(p50Monthly())===$monthly,
    'deterministic_no_mutations');

$invalid=[];
$bad=p50Weekly();$bad['scenario']='reference';$invalid[]=['weekly_plus_monthly_scenario',$bad];
$bad=p50Monthly();$bad['lead_rate_bp']=1000;$invalid[]=['monthly_plus_weekly_rate',$bad];
$bad=p50Monthly();$bad['client_rate_bp']=2000;$invalid[]=['monthly_plus_client_rate',$bad];
$bad=p50Weekly();$bad['mode']='comercial_monthly';$invalid[]=['silent_mode_switch',$bad];
$bad=p50Monthly();$bad['mode']='n8n_weekly';$invalid[]=['silent_reverse_switch',$bad];
$bad=p50Weekly();$bad['weekly_visits']='120';$invalid[]=['string_visits',$bad];
$bad=p50Weekly();$bad['weekly_visits']=120.0;$invalid[]=['float_visits',$bad];
$bad=p50Weekly();$bad['lead_rate_bp']='1000';$invalid[]=['string_lead_rate',$bad];
$bad=p50Weekly();$bad['client_rate_bp']=20.0;$invalid[]=['float_close_rate',$bad];
$bad=p50Monthly();$bad['scenario']=100;$invalid[]=['numeric_monthly_scenario',$bad];
$bad=p50Monthly();$bad['scenario']='unknown';$invalid[]=['unknown_monthly_scenario',$bad];
$bad=p50Monthly();$bad['weekly_visits']=100001;$invalid[]=['monthly_above_cap',$bad];
$bad=p50Weekly();$bad['weekly_visits']=100000001;$invalid[]=['weekly_above_cap',$bad];
$bad=p50Weekly();$bad['lead_rate_bp']=-1;$invalid[]=['negative_rate',$bad];
$bad=p50Weekly();$bad['lead_rate_bp']=10001;$invalid[]=['above_max_rate',$bad];
$bad=p50Weekly();$bad['client_rate_bp']=-1;$invalid[]=['negative_close_rate',$bad];
$bad=p50Weekly();$bad['client_rate_bp']=10001;$invalid[]=['above_max_close_rate',$bad];
$bad=p50Monthly();$bad['tenant_ref']='SYNTHETIC_lowercase';$invalid[]=['lowercase_tenant',$bad];
$bad=p50Weekly();$bad['request_ref']='REAL_REFERENCE';$invalid[]=['non_synthetic_request',$bad];
$bad=p50Weekly();$bad['schema']='LEGACY_UNREVIEWED';$invalid[]=['schema_change',$bad];
$bad=p50Monthly();$bad['api_token']='SYNTHETIC_NOT_REAL';$invalid[]=['extra_secret_field',$bad];
$bad=p50Weekly();unset($bad['request_ref']);$invalid[]=['missing_request_ref',$bad];
$bad=p50Monthly();unset($bad['scenario']);$invalid[]=['missing_monthly_scenario',$bad];
$bad=p50Weekly();
$bad=['mode'=>$bad['mode'],'schema'=>$bad['schema']]
    +array_diff_key($bad,['mode'=>true,'schema'=>true]);
$invalid[]=['noncanonical_field_order',$bad];
foreach($invalid as [$name,$input]){
    p50Denied(static fn()=>$service->project($input),$name);
}
p50Check($service->project(p50Weekly(0,0,0))['customers']===0,'weekly_zero_safe');
p50Check($service->project(p50Monthly(0,'conservative'))['customers']===0,
    'monthly_zero_safe');
echo "AIA_P50_EXPLICIT_GROWTH_MODES_PASS checks={$checks} weekly=120/12/2 monthly=480/19/4 no_ai=1 no_credit=1 NO_GO\n";
