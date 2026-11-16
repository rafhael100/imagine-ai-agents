<?php
declare(strict_types=1);
require_once __DIR__.'/../_auto_ia_pinned_source/tools/auto_ia/AutoIaRouter.php';

use ImagineAI\AutomationFirst\AutoIaRouter;

$checks=0;
function p33Check(bool $c,string $label):void {
    global $checks; $checks++;
    if(!$c)throw new RuntimeException('P33_ROUTE_CONTRACT_FAIL_'.$label);
}
foreach([
    ['imagine-comercial','funnel.calculate'],
    ['imagine-ai-agents','run.status.project'],
    ['imagine-ai-agents','usage.quota.evaluate'],
] as [$product,$operation]) {
    $route=AutoIaRouter::classify([
        'schema'=>'AUTO_IA_EVENT_V1',
        'product'=>$product,
        'operation'=>$operation,
        'tenant_ref'=>'SYNTHETIC_P33_TENANT',
        'event_ref'=>'SYNTHETIC_P33_EVENT',
        'semantic_reason'=>'UNSTRUCTURED_LANGUAGE',
    ]);
    p33Check($route['decision']==='NO_AI' && $route['provider_calls']===0
        && $route['model_tokens']===0,'zero_model_'.$product.'_'.$operation);
}
$creative=AutoIaRouter::classify([
    'schema'=>'AUTO_IA_EVENT_V1',
    'product'=>'imagine-comercial',
    'operation'=>'campaign.copy.propose',
    'tenant_ref'=>'SYNTHETIC_P33_TENANT',
    'event_ref'=>'SYNTHETIC_P33_CREATIVE',
    'semantic_reason'=>'CREATIVE_DRAFT',
]);
p33Check($creative['decision']==='AI_REVIEW_REQUIRED'
    && $creative['provider_calls']===0,'creative_never_auto_dispatch');
echo "AIA_P33_CONV_RC04_ROUTING_PASS checks={$checks} noai=3 provider_calls=0 NO_GO\n";
