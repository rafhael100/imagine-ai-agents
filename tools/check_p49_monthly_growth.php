<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Automation/ComercialGrowthScenarioProjector.php';
use ImagineAI\Automation\ComercialGrowthScenarioProjector as Growth;

$samples=[0,1,2,3,4,5,6,7,9,10,13,17,23,29,37,49,57,99,100,101,
    120,171,199,200,250,317,501,999,2000,10000,100000];
$goals=[0,1,2,3,7,10,100,1000,3000,7200,10000];
$scenarios=['conservative','reference','optimistic'];
$out=['schema'=>'AIA_P49_MONTHLY_FUNNEL_PARITY_V1','weeklySamples'=>[],
    'goalSamples'=>[],'ai_calls'=>0,'credits_debited'=>0,
    'operational_authorization'=>false];
foreach($samples as $visits){
    $item=['weeklyVisits'=>$visits,'scenarios'=>[]];
    foreach($scenarios as $scenario){
        $item['scenarios'][$scenario]=Growth::project($visits,$scenario);
    }
    $out['weeklySamples'][]=$item;
}
foreach($goals as $goal){
    foreach($scenarios as $scenario){
        $out['goalSamples'][]=Growth::planVisitsForCustomerGoal($goal,$scenario);
    }
}
echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
