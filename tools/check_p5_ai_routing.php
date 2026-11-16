<?php
declare(strict_types=1);
require_once __DIR__.'/../src/MockOrchestration/MockAiOrAutomationRouter.php';
use ImagineAI\MockOrchestration\MockAiOrAutomationRouter as Router;

function must(bool $v,string $label): void
{
    if (!$v) throw new RuntimeException('P5_ROUTE_TEST_FAILED_'.$label);
}

$intent=[
    'tenant_ref'=>'SYNTHETIC_TENANT_A',
    'run_ref'=>'SYNTHETIC_RUN_01',
    'operation'=>'mock.lead.normalize_stage',
    'content_class'=>'SYNTHETIC_ONLY',
    'requires_external_effect'=>false,
    'estimated_model_units'=>0,
];
$policy=[
    'tenant_ref'=>'SYNTHETIC_TENANT_A',
    'ai_opt_in'=>false,
    'consent_verified'=>false,
    'approved_for_model'=>false,
    'available_units'=>0,
    'per_call_limit'=>0,
    'provider_configured'=>false,
    'ledger_certain'=>false,
];
$cases=0;
foreach (['mock.lead.normalize_stage','mock.lead.route_approval',
          'mock.funnel.estimate','mock.quota.check',
          'mock.run.deduplicate'] as $operation) {
    $i=array_replace($intent,['operation'=>$operation]);
    $decision=Router::inspect($i,$policy);
    must($decision['decision']==='DETERMINISTIC_ONLY','zero_credit_automation');
    must($decision['proposed_units']===0,'no_automation_credit');
    must($decision['external_calls']===0,'no_automation_api');
    $cases++;
}
$semantic=array_replace($intent,[
    'operation'=>'mock.copy.draft','estimated_model_units'=>30
]);
must(Router::inspect($semantic,$policy)['decision']==='DENIED','no_model_permission');
$allow=array_replace($policy,[
    'ai_opt_in'=>true,
    'consent_verified'=>true,
    'approved_for_model'=>true,
    'available_units'=>100,
    'per_call_limit'=>50,
    'provider_configured'=>true,
    'ledger_certain'=>true,
]);
foreach (['mock.copy.draft','mock.text.classify'] as $op) {
    $s=array_replace($semantic,['operation'=>$op]);
    $decision=Router::inspect($s,$allow);
    must($decision['decision']==='MODEL_ELIGIBLE_NOT_EXECUTED','model_candidate_only');
    must($decision['proposed_units']===30,'estimate_only');
    must($decision['credits_consumed']===0,'model_not_charged');
    must($decision['execution_allowed']===false,'model_not_authorized');
    must($decision['external_calls']===0,'model_not_called');
    $cases++;
}
$negatives=[
    [array_replace($intent,['tenant_ref'=>'SYNTHETIC_TENANT_B']),$allow],
    [array_replace($intent,['run_ref'=>'INVALID']),$allow],
    [array_replace($intent,['operation'=>'mock.crm.leads.write']),$allow],
    [array_replace($intent,['operation'=>'n8n.execute']),$allow],
    [array_replace($intent,['estimated_model_units'=>1]),$allow],
    [array_replace($intent,['requires_external_effect'=>true]),$allow],
    [array_replace($intent,['content_class'=>'REVIEW_REQUIRED']),$allow],
    [array_replace($intent,['content_class'=>'SENSITIVE']),$allow],
    [array_replace($intent,['unapproved_role'=>'superuser']),$allow],
    [array_replace($semantic,['estimated_model_units'=>0]),$allow],
    [array_replace($semantic,['estimated_model_units'=>-1]),$allow],
    [array_replace($semantic,['estimated_model_units'=>10001]),$allow],
    [array_replace($semantic,['requires_external_effect'=>true]),$allow],
    [array_replace($semantic,['content_class'=>'SENSITIVE']),$allow],
    [array_replace($semantic,['content_class'=>'REVIEW_REQUIRED']),$allow],
    [$semantic,array_replace($allow,['ai_opt_in'=>false])],
    [$semantic,array_replace($allow,['consent_verified'=>false])],
    [$semantic,array_replace($allow,['approved_for_model'=>false])],
    [$semantic,array_replace($allow,['provider_configured'=>false])],
    [$semantic,array_replace($allow,['ledger_certain'=>false])],
    [$semantic,array_replace($allow,['available_units'=>29])],
    [$semantic,array_replace($allow,['per_call_limit'=>29])],
    [$semantic,array_replace($allow,['per_call_limit'=>0])],
    [$semantic,array_replace($allow,['tenant_ref'=>'SYNTHETIC_TENANT_B'])],
    [$semantic,array_replace($allow,['available_units'=>-1])],
    [$semantic,array_replace($allow,['available_units'=>1000001])],
    [$semantic,array_replace($allow,['ai_opt_in'=>'true'])],
    [$semantic,array_replace($allow,['user_role'=>'administrator'])],
    [array_replace($intent,['estimated_model_units'=>'0']),$allow],
    [array_replace($intent,['content_class'=>['SYNTHETIC_ONLY']]),$allow],
];
foreach ($negatives as [$i,$p]) {
    $result=Router::inspect($i,$p);
    must($result['decision']==='DENIED','unsafe_preflight_'.$cases);
    must($result['proposed_units']===0,'no_estimated_spend_after_denial_'.$cases);
    must($result['credits_consumed']===0 && $result['external_calls']===0,'no_effect_'.$cases);
    must($result['execution_allowed']===false,'not_permission_'.$cases);
    $cases++;
}
// Deterministic routing stays zero-credit even with a model permission
// and a positive budget, including when the credit ledger is uncertain.
$d=Router::inspect($intent,$allow);
must($d['decision']==='DETERMINISTIC_ONLY','avoid_unnecessary_model');
must($d['credits_consumed']===0,'deterministic_no_charge');
must($d['operational_decision']==='NO_GO','all_samples_nonoperational');
echo "P5_AI_OR_AUTOMATION_PREFLIGHT_PASS checks=$cases deterministic=5 model_candidates=2 denied=30 charged=0 external_calls=0 NO_GO\n";
