<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auto_ia_pinned_source/tools/auto_ia/AutoIaRouter.php';

use ImagineAI\AutomationFirst\AutoIaRouter;

$checks=0;
function p20Check(bool $ok,string $name):void {
    global $checks;
    $checks++;
    if(!$ok)throw new RuntimeException('P20_ROUTE_FAIL_'.$name);
}
$matrix=AutoIaRouter::matrix();
p20Check(count($matrix)>=8,'expected_products');
$noAiCount=0;
foreach($matrix as $product=>$operations) {
    foreach($operations as $operation=>$reason) {
        $event=[
            'schema'=>'AUTO_IA_EVENT_V1',
            'product'=>$product,
            'operation'=>$operation,
            'tenant_ref'=>'SYNTHETIC_TENANT_P20',
            'event_ref'=>'SYNTHETIC_EVENT_P20',
            'semantic_reason'=>$reason==='NO_AI'?'NONE':$reason,
        ];
        $result=AutoIaRouter::classify($event);
        p20Check($result['provider_calls']===0 && $result['model_tokens']===0,'zero_provider_'.$product.'_'.$operation);
        if ($reason==='NO_AI') {
            p20Check($result['decision']==='NO_AI','deterministic_'.$product.'_'.$operation);
            $noAiCount++;
        } else {
            p20Check($result['decision']==='AI_REVIEW_REQUIRED','creative_requires_review_'.$product.'_'.$operation);
        }
    }
}
p20Check($noAiCount>=12,'many_noai_routes');
$unknown=AutoIaRouter::classify([
    'schema'=>'AUTO_IA_EVENT_V1','product'=>'imagine-ai-agents',
    'operation'=>'billing.charge','tenant_ref'=>'SYNTHETIC_TENANT_P20',
    'event_ref'=>'SYNTHETIC_EVENT_P20','semantic_reason'=>'NONE',
]);
p20Check($unknown['decision']==='DENY' && $unknown['provider_calls']===0,'unknown_denied');
echo "AIA_P20_ROUTE_MATRIX_PASS checks={$checks} no_ai={$noAiCount} ai_api_calls=0 operational_authority=false NO_GO\n";
