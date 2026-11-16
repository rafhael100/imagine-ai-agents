<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Automation/DeterministicAutomationService.php';

use ImagineAI\Automation\DeterministicAutomationService;
use ImagineAI\Automation\DeterministicFunnelProjector;

$checks=0;
function p48Assert(bool $ok,string $label):void {
    global $checks;
    ++$checks;
    if(!$ok)throw new RuntimeException('AIA_P48_FAIL_'.$label);
}
function p48Denied(callable $fn,string $label):void {
    try {
        $fn();
    } catch (InvalidArgumentException) {
        p48Assert(true,$label);
        return;
    }
    throw new RuntimeException('AIA_P48_EXPECTED_DENY_'.$label);
}
function p48Input(int $visits=120,int $lead=1000,int $client=2000):array {
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'request_ref'=>'SYNTHETIC_REQUEST_01',
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>$lead,
        'client_rate_bp'=>$client
    ];
}

$calculator=new DeterministicAutomationService();
$golden=$calculator->calculate(p48Input());
p48Assert(array_keys($golden)===[
    'schema','operation','tenant_ref','request_ref','visits',
    'projected_leads','projected_customers','decision','status',
    'ai_api_calls','ai_tokens','commercial_credits_debited',
    'operational_authorization'
], 'closed_output_schema');
p48Assert($golden===[
    'schema'=>'AIA_DETERMINISTIC_RESULT_V1',
    'operation'=>'funnel.calculate',
    'tenant_ref'=>'SYNTHETIC_TENANT_A',
    'request_ref'=>'SYNTHETIC_REQUEST_01',
    'visits'=>120,
    'projected_leads'=>12,
    'projected_customers'=>2,
    'decision'=>'NO_AI',
    'status'=>'LAB_PREVIEW_ONLY',
    'ai_api_calls'=>0,
    'ai_tokens'=>0,
    'commercial_credits_debited'=>0,
    'operational_authorization'=>false
], 'n8n_rc07_120_12_2_golden_parity');
p48Assert($calculator->calculate(p48Input())===$golden,'determinism');
p48Assert(DeterministicFunnelProjector::project(0,10000,10000)===
    ['visits'=>0,'projected_leads'=>0,'projected_customers'=>0],
    'empty_funnel');
p48Assert(DeterministicFunnelProjector::project(1,5000,5000)===
    ['visits'=>1,'projected_leads'=>1,'projected_customers'=>1],
    'integer_half_up_rounding');
p48Assert(DeterministicFunnelProjector::project(100000000,10000,10000)===
    ['visits'=>100000000,'projected_leads'=>100000000,
     'projected_customers'=>100000000],'maximum_nonoverflowing_bounds');
p48Assert(DeterministicFunnelProjector::project(33,10000,0)===
    ['visits'=>33,'projected_leads'=>33,'projected_customers'=>0],
    'zero_second_stage_rate');

foreach([[-1,1000,2000],[100000001,1000,2000],
    [1,-1,1000],[1,10001,1000],[1,1000,-1],
    [1,1000,10001]] as [$v,$l,$c]) {
    p48Denied(static fn()=>DeterministicFunnelProjector::project($v,$l,$c),
        'out_of_range_'.implode('_',[$v,$l,$c]));
}

$mutations=[
    ['schema','AIA_DETERMINISTIC_COMMAND_V2'],
    ['operation','campaign.publish'],
    ['tenant_ref','SYNTHETIC_TENANT_B/../../'],
    ['tenant_ref',''],
    ['tenant_ref','synthetic_tenant'],
    ['request_ref','SYNTHETIC_'],
    ['request_ref',str_repeat('A',100)],
    ['weekly_visits','120'],
    ['weekly_visits',120.0],
    ['weekly_visits',null],
    ['lead_rate_bp',true],
    ['client_rate_bp',2000.0]
];
foreach($mutations as $i=>$mutation){
    [$field,$value]=$mutation;
    $input=p48Input();
    $input[$field]=$value;
    p48Denied(static fn()=> $calculator->calculate($input),'closed_input_'.$i);
}
$extra=p48Input();
$extra['model_api_key']='SYNTHETIC_FORBIDDEN_SECRET';
p48Denied(static fn()=> $calculator->calculate($extra),'reject_extra_field');
$reordered=p48Input();
unset($reordered['schema']);
$reordered['schema']='AIA_DETERMINISTIC_COMMAND_V1';
p48Denied(static fn()=> $calculator->calculate($reordered),'reject_field_reorder');
$missing=p48Input();
unset($missing['client_rate_bp']);
p48Denied(static fn()=> $calculator->calculate($missing),'reject_missing_field');

mt_srand(48);
for($i=0;$i<6000;$i++){
    $visits=mt_rand(0,100000000);
    $lead=mt_rand(0,10000);
    $client=mt_rand(0,10000);
    $r=DeterministicFunnelProjector::project($visits,$lead,$client);
    p48Assert($r['visits']===$visits
        &&$r['projected_leads']>=0
        &&$r['projected_leads']<=$visits
        &&$r['projected_customers']>=0
        &&$r['projected_customers']<=$r['projected_leads'],
        'fuzz_no_amplification_'.$i);
    p48Assert($r===DeterministicFunnelProjector::project($visits,$lead,$client),
        'fuzz_repeat_deterministic_'.$i);
}
echo "AIA_P48_DETERMINISTIC_NO_AI_PASS checks={$checks} golden=120_12_2 fuzz_cases=6000 api_calls=0 credits=0 storage=0 NO_GO\n";
