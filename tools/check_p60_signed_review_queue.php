<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Automation/ReviewQueueReadModel.php';
require_once __DIR__.'/../src/Automation/SignedReviewQueueReadModel.php';

use ImagineAI\Automation\ReviewQueueReadModel;
use ImagineAI\Automation\SignedReviewQueueReadModel;

$checks=0;
function p60Assert(bool $v,string $name):void {
    global $checks;
    $checks++;
    if(!$v)throw new RuntimeException('AIA_P60_FAIL_'.$name);
}
function p60Deny(callable $f,string $name):void {
    try{$f();}
    catch(InvalidArgumentException){p60Assert(true,$name);return;}
    throw new RuntimeException('AIA_P60_EXPECTED_DENIAL_'.$name);
}
function p60Draft(int $n,string $kind):array {
    return [
        'request_ref'=>'SYNTHETIC_P60_REQUEST_'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
        'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.strtoupper(str_pad(dechex($n),24,'0',STR_PAD_LEFT)),
        'rule_ref'=>'SYNTHETIC_P60_RULE_'.$n,
        'draft_kind'=>$kind,
        'metric'=>'projected_leads',
        'observed'=>(string)$n,
        'threshold'=>'12',
        'state'=>'AWAITING_HUMAN_REVIEW_LAB'
    ];
}
function p60Status(string $decision,array $roles):array {
    return [
        'decision'=>$decision,
        'observations'=>count($roles),
        'roles_observed'=>$roles,
        'can_approve'=>false,
        'can_execute'=>false,
        'operational_authorization'=>false,
        'ai_calls'=>0,
        'service_effects'=>0
    ];
}
$tenant='SYNTHETIC_TENANT_A';
$drafts=[
    p60Draft(1,'HUMAN_REVIEW_DRAFT'),
    p60Draft(2,'CRM_TASK_DRAFT'),
    p60Draft(3,'OPERATOR_NOTICE_DRAFT'),
    p60Draft(4,'HUMAN_REVIEW_DRAFT')
];
$queue=(new ReviewQueueReadModel())->present([
    'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
    'tenant_ref'=>$tenant,
    'drafts'=>$drafts,'ai_calls'=>0,'service_effects'=>0
],$tenant);
$statuses=[
    $drafts[0]['proposal_ref']=>p60Status('LAB_AWAITING_REVIEWS_NO_ACTION',[]),
    $drafts[1]['proposal_ref']=>p60Status('LAB_PARTIAL_REVIEW_NO_ACTION',['PRODUCT']),
    $drafts[2]['proposal_ref']=>p60Status('LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION',['PRODUCT','SECURITY']),
    $drafts[3]['proposal_ref']=>p60Status('LAB_REVIEW_REJECTED_NO_ACTION',['SECURITY'])
];
$model=new SignedReviewQueueReadModel();
$result=$model->present($queue,$statuses,$tenant);
p60Assert($result['schema']==='AIA_SIGNED_REVIEW_QUEUE_READ_V1'
    &&$result['decision']==='NO_GO'
    &&$result['status']==='LAB_SIGNED_REVIEW_READONLY'
    &&$result['total_pending']===4,'closed_signed_read_model');
p60Assert($result['review_summary']===[
    'awaiting'=>1,'partial'=>1,'two_observations'=>1,'rejected'=>1
], 'four_review_state_summary_counts');
p60Assert($result['by_kind']===[
    'human_review'=>2,'crm_task_draft'=>1,'operator_notice_draft'=>1
], 'by_kind_matches_P53');
p60Assert($result['can_approve']===false
    &&$result['can_execute']===false
    &&$result['operational_authorization']===false
    &&$result['ai_api_calls']===0
    &&$result['credits_debited']===0
    &&$result['service_effects']===0,
    'two_signatures_still_no_authorization');
p60Assert($result['items'][2]['signed_observations']===2
    &&$result['items'][2]['human_action_available']===false
    &&$result['items'][2]['execution']==='NOT_EXECUTED'
    &&!array_key_exists('reviewer_ref',$result['items'][2]),
    'reviewer_identity_not_exposed_in_ui');
p60Assert($model->present($queue,$statuses,$tenant)===$result,
    'deterministic_read_projection');

$invalid=$statuses;
unset($invalid[$drafts[0]['proposal_ref']]);
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'missing_review_proposal_is_denied');
$invalid=$statuses;
$invalid['SYNTHETIC_PROPOSAL_'.str_repeat('F',24)]=p60Status(
    'LAB_AWAITING_REVIEWS_NO_ACTION',[]
);
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'extra_review_proposal_is_denied');
p60Deny(static fn()=> $model->present($queue,$statuses,
    'SYNTHETIC_TENANT_B'),'cross_company_scope_denied');
$bad=$queue;$bad['can_execute']=true;
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'fake_UI_execution_denied');
$bad=$queue;$bad['by_kind']['crm_task_draft']=4;
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'forged_category_count_denied');
$bad=$queue;$bad['items'][0]['human_action_available']=true;
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'forged_action_button_denied');
$bad=$queue;$bad['items'][0]['metric']='api_key';
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'unrecognized_metric_denied');
$bad=$queue;$bad['total_pending']=5;
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'forged_pending_count_denied');
$bad=$queue;$bad['unsafe_extra_key']='execute';
p60Deny(static fn()=> $model->present($bad,$statuses,$tenant),
    'extra_presentation_field_denied');
$invalid=$statuses;
$invalid[$drafts[2]['proposal_ref']]['can_approve']=true;
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'signed_votes_cannot_claim_approval');
$invalid=$statuses;
$invalid[$drafts[2]['proposal_ref']]['decision']='LIVE_APPROVED';
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'untrusted_live_approved_state_denied');
$invalid=$statuses;
$invalid[$drafts[2]['proposal_ref']]['observations']=1;
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'two_signed_state_requires_two_signatures');
$invalid=$statuses;
$invalid[$drafts[2]['proposal_ref']]['roles_observed']=['PRODUCT','PRODUCT'];
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'double_product_cannot_pass_two_observation_state');
$invalid=$statuses;
$invalid[$drafts[1]['proposal_ref']]['observations']=0;
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'partial_review_requires_one_role');
$invalid=$statuses;
$invalid[$drafts[0]['proposal_ref']]['observations']=1;
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'awaiting_review_cannot_have_observations');
$invalid=$statuses;
$invalid[$drafts[3]['proposal_ref']]['roles_observed']=[];
$invalid[$drafts[3]['proposal_ref']]['observations']=0;
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'rejected_state_requires_at_least_one_observation');
$invalid=$statuses;
$invalid[$drafts[1]['proposal_ref']]['roles_observed']=['SUPERUSER'];
p60Deny(static fn()=> $model->present($queue,$invalid,$tenant),
    'unknown_review_role_denied');

$empty=(new ReviewQueueReadModel())->present([
    'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
    'tenant_ref'=>$tenant,
    'drafts'=>[],'ai_calls'=>0,'service_effects'=>0
],$tenant);
p60Assert($model->present($empty,[],$tenant)['total_pending']===0,
    'empty_queue_and_no_statuses_allowed');

mt_srand(60);
for($i=0;$i<1300;$i++){
    $count=mt_rand(0,2);
    $roles=match($count){
        0=>[],
        1=>[mt_rand(0,1)===0?'PRODUCT':'SECURITY'],
        2=>['PRODUCT','SECURITY']
    };
    $decision=match($count){
        0=>'LAB_AWAITING_REVIEWS_NO_ACTION',
        1=>'LAB_PARTIAL_REVIEW_NO_ACTION',
        2=>'LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'
    };
    $randomStatus=[$drafts[0]['proposal_ref']=>p60Status($decision,$roles)];
    $singleQueue=(new ReviewQueueReadModel())->present([
        'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
        'tenant_ref'=>$tenant,'drafts'=>[$drafts[0]],
        'ai_calls'=>0,'service_effects'=>0
    ],$tenant);
    $out=$model->present($singleQueue,$randomStatus,$tenant);
    p60Assert($out['total_pending']===1
        &&$out['items'][0]['signed_observations']===$count
        &&$out['can_approve']===false
        &&$out['can_execute']===false,'fuzz_count_roles_no_actions_'.$i);
}
echo "AIA_P60_SIGNED_REVIEW_QUEUE_PASS checks={$checks} fuzz=1300 tenant_guard=1 review_statuses=4 visible_actions=0 no_ai=1 NO_GO\n";
