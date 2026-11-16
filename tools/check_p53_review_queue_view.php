<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Automation/ReviewQueueReadModel.php';

use ImagineAI\Automation\ReviewQueueReadModel;

$view=new ReviewQueueReadModel();
$checks=0;
function p53Check(bool $ok,string $label):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('AIA_P53_FAIL_'.$label);
}
function p53Deny(callable $f,string $label):void {
    try{$f();}
    catch(InvalidArgumentException){p53Check(true,$label);return;}
    throw new RuntimeException('AIA_P53_EXPECTED_DENY_'.$label);
}
function p53Row(int $n,string $kind='HUMAN_REVIEW_DRAFT'):array {
    return [
        'request_ref'=>'SYNTHETIC_P53_REQUEST_'.str_pad((string)$n,3,'0',STR_PAD_LEFT),
        'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.strtoupper(str_pad(dechex($n),24,'0',STR_PAD_LEFT)),
        'rule_ref'=>'SYNTHETIC_P53_RULE_'.$n,
        'draft_kind'=>$kind,
        'metric'=>'projected_leads',
        'observed'=>(string)$n,
        'threshold'=>'12',
        'state'=>'AWAITING_HUMAN_REVIEW_LAB',
    ];
}
function p53Input(array $rows,string $tenant='SYNTHETIC_TENANT_A'):array{
    return [
        'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
        'tenant_ref'=>$tenant,
        'drafts'=>$rows,
        'ai_calls'=>0,
        'service_effects'=>0,
    ];
}
$rows=[
    p53Row(12),
    p53Row(2,'CRM_TASK_DRAFT'),
    p53Row(7,'OPERATOR_NOTICE_DRAFT')
];
$base=p53Input($rows);
$result=$view->present($base,'SYNTHETIC_TENANT_A');
p53Check(array_keys($result)===[
    'schema','tenant_ref','status','decision','total_pending','by_kind',
    'items','can_approve','can_execute','operational_authorization',
    'ai_api_calls','credits_debited','crm_tasks_created',
    'operator_messages_sent'
], 'closed_read_model');
p53Check($result['schema']==='AIA_REVIEW_QUEUE_READ_V1'
    &&$result['decision']==='NO_GO'
    &&$result['total_pending']===3
    &&$result['by_kind']===[
        'human_review'=>1,'crm_task_draft'=>1,'operator_notice_draft'=>1
    ], 'summary_counts');
p53Check($result['items'][0]['observed']===12
    &&$result['items'][0]['threshold']===12
    &&$result['items'][0]['execution']==='NOT_EXECUTED'
    &&$result['items'][0]['human_action_available']===false,
    'numeric_values_and_no_action');
p53Check($result['can_approve']===false
    &&$result['can_execute']===false
    &&$result['operational_authorization']===false
    &&$result['ai_api_calls']===0
    &&$result['credits_debited']===0
    &&$result['crm_tasks_created']===0
    &&$result['operator_messages_sent']===0,
    'strictly_nonoperational_read_only');
p53Check($view->present($base,'SYNTHETIC_TENANT_A')===$result,
    'deterministic_projection');
p53Check($view->present(p53Input([]),'SYNTHETIC_TENANT_A')
    ['total_pending']===0,'empty_queue_is_valid');

p53Deny(static fn()=> $view->present($base,'SYNTHETIC_TENANT_B'),
    'cross_tenant_context_denied');
$bad=$base;$bad['decision']='ALLOW_APPROVAL';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'unreviewed_registry_decision_denied');
$bad=$base;$bad['service_effects']=1;
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'external_effect_claim_denied');
$bad=$base;$bad['untrusted_approval']=true;
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'extra_status_flag_denied');
$bad=$base;$bad['drafts'][1]['state']='APPROVED';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'fake_approved_row_denied');
$bad=$base;$bad['drafts'][1]['draft_kind']='SEND_WHATSAPP';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'external_operation_kind_denied');
$bad=$base;$bad['drafts'][0]['metric']='owner_private_secret';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'unsupported_metric_denied');
$bad=$base;$bad['drafts'][0]['observed']=-1;
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'negative_number_denied');
$bad=$base;$bad['drafts'][0]['observed']='00012';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'noncanonical_number_denied');
$bad=$base;$bad['drafts'][0]['observed']='100000001';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'large_numeric_denied');
$bad=$base;$bad['drafts'][0]['observed']=true;
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'boolean_numeric_denied');
$bad=$base;$bad['drafts'][1]['proposal_ref']=$bad['drafts'][0]['proposal_ref'];
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'duplicate_proposal_denied');
$bad=$base;$bad['drafts'][0]['private_note']='secret';
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'extra_untrusted_field_denied');
$bad=$base;$bad['drafts']=[1=>p53Row(1)];
p53Deny(static fn()=> $view->present($bad,'SYNTHETIC_TENANT_A'),
    'nonlist_rows_denied');
$many=[];
for($i=1;$i<=129;$i++)$many[]=p53Row($i);
p53Deny(static fn()=> $view->present(p53Input($many),
    'SYNTHETIC_TENANT_A'),'over_capacity_denied');
p53Check($view->present(p53Input(array_slice($many,0,128)),
    'SYNTHETIC_TENANT_A')['total_pending']===128,
    'max_allowed_preview_rows');

mt_srand(53);
for($i=0;$i<1800;$i++){
    $n=mt_rand(0,100000000);
    $row=p53Row($i);
    $row['observed']=(string)$n;
    $data=$view->present(p53Input([$row]),'SYNTHETIC_TENANT_A');
    p53Check($data['total_pending']===1
        &&$data['items'][0]['observed']===$n
        &&$data['can_execute']===false
        &&$data['ai_api_calls']===0,'random_read_view_'.$i);
}
echo "AIA_P53_READ_QUEUE_MODEL_PASS checks={$checks} read_only=true deny_cross_tenant=true human_approval=false no_ai=1 NO_GO\n";
