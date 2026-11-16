<?php
declare(strict_types=1);
require_once __DIR__.'/../../src/Automation/ComercialGrowthScenarioProjector.php';

use ImagineAI\Automation\ComercialGrowthScenarioProjector as Growth;

$checks=0;
function p49Check(bool $ok,string $reason):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P49_FAIL_'.$reason);
}
function p49Denied(callable $f,string $reason):void {
    $denied=false;
    try{$f();}catch(InvalidArgumentException){$denied=true;}
    p49Check($denied,$reason);
}
$scenarios=Growth::compareScenarios(100);
p49Check($scenarios['conservative']['monthlyVisits']===400
    &&$scenarios['conservative']['leads']===8
    &&$scenarios['conservative']['customers']===1,'commercial_100_cons');
p49Check($scenarios['reference']['monthlyVisits']===400
    &&$scenarios['reference']['leads']===16
    &&$scenarios['reference']['customers']===3,'commercial_100_ref');
p49Check($scenarios['optimistic']['monthlyVisits']===400
    &&$scenarios['optimistic']['leads']===24
    &&$scenarios['optimistic']['customers']===7,'commercial_100_opt');

$scenarios=Growth::compareScenarios(120);
p49Check($scenarios['conservative']['leads']===10
    &&$scenarios['conservative']['customers']===1,'commercial_120_cons');
p49Check($scenarios['reference']['leads']===19
    &&$scenarios['reference']['customers']===4,'commercial_120_ref');
p49Check($scenarios['optimistic']['leads']===29
    &&$scenarios['optimistic']['customers']===9,'commercial_120_opt');

p49Check(Growth::project(0)['leads']===0
    &&Growth::project(0)['customers']===0,'zero_inputs');
p49Check(Growth::project(100_000,'optimistic')['monthlyVisits']===400_000,
    'weekly_input_ceiling');
p49Check(Growth::planVisitsForCustomerGoal(0)['weeklyVisits']===0,
    'zero_goal_needs_no_visits');
p49Check(Growth::planVisitsForCustomerGoal(10_000)['reachable']===false,
    'unreachable_goal_has_no_fake_promise');
p49Check(Growth::project(5,'reference')['leads']===1
    &&Growth::project(5,'reference')['customers']===0,
    'customer_projection_uses_unrounded_expected_leads');

foreach(['conservative','reference','optimistic'] as $scenario){
    foreach([0,1,2,3,7,25,50,100,1000,3000,7200,10000] as $goal){
        $r=Growth::planVisitsForCustomerGoal($goal,$scenario);
        p49Check($r['scenario']===$scenario&&$r['monthlyCustomerGoal']===$goal
            &&$r['assumptionOnly']===true
            &&$r['realResultsGuaranteed']===false,
            'planning_is_illustrative_'.$scenario.'_'.$goal);
        if($r['reachable']){
            $w=$r['weeklyVisits'];
            p49Check($w>=0&&$w<=Growth::MAX_WEEKLY_VISITS
                &&Growth::project($w,$scenario)['customers']>=$goal,
                'goal_reachable_at_first_found_'.$scenario.'_'.$goal);
            if($w>0){
                p49Check(Growth::project($w-1,$scenario)['customers']<$goal,
                    'goal_solution_is_minimal_'.$scenario.'_'.$goal);
            }
        }else{
            p49Check($r['maxIllustratedCustomers']<$goal,
                'unreachable_due_to_cap_'.$scenario.'_'.$goal);
        }
    }
}
foreach([-1,100001] as $weekly){
    p49Denied(static fn()=>Growth::project($weekly),
        'weekly_boundary_'.$weekly);
}
foreach(['bad','__proto__','reference__'] as $scenario){
    p49Denied(static fn()=>Growth::project(120,$scenario),
        'unrecognized_scenario_'.$scenario);
    p49Denied(static fn()=>Growth::planVisitsForCustomerGoal(20,$scenario),
        'unrecognized_goal_scenario_'.$scenario);
}
foreach([-1,10001] as $goal){
    p49Denied(static fn()=>Growth::planVisitsForCustomerGoal($goal),
        'invalid_goal_'.$goal);
}
p49Check(Growth::project(120,'reference')['actualPerformanceData']===false,
    'no_live_traffic_or_sales_claims');
echo "AIA_P49_COMERCIAL_GROWTH_CORE_PASS checks={$checks} weekly=120 monthly=480 conservative=10/1 reference=19/4 optimistic=29/9 NO_AI NO_GO\n";
