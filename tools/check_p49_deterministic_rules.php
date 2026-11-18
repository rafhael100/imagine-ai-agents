<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Automation/DeterministicAutomationService.php';
require_once __DIR__.'/../src/Automation/DeterministicRuleEngine.php';

use ImagineAI\Automation\DeterministicRuleEngine;

$engine=new DeterministicRuleEngine();
$checks=0;
function p49Assert(bool $ok,string $name):void {
    global $checks;
    $checks++;
    if(!$ok)throw new RuntimeException('AIA_P49_FAIL_'.$name);
}
function p49Denied(callable $callback,string $name):void {
    try{
        $callback();
    }catch(InvalidArgumentException){
        p49Assert(true,$name);
        return;
    }
    throw new RuntimeException('AIA_P49_EXPECTED_DENIAL_'.$name);
}
function p49Command(string $tenant='SYNTHETIC_TENANT_A',
    string $request='SYNTHETIC_REQUEST_P49',int $visits=120):array{
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,
        'request_ref'=>$request,
        'weekly_visits'=>$visits,
        'lead_rate_bp'=>1000,
        'client_rate_bp'=>2000
    ];
}
function p49Rule(string $name,string $metric,
    string $operator,int $threshold,string $kind='HUMAN_REVIEW_DRAFT'):array{
    return [
        'rule_ref'=>$name,'metric'=>$metric,
        'operator'=>$operator,'threshold'=>$threshold,'draft_kind'=>$kind
    ];
}
function p49Rules():array{
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'ruleset_ref'=>'SYNTHETIC_RULESET_P49',
        'revision'=>1,
        'rules'=>[
            p49Rule('SYNTHETIC_RULE_A','projected_leads','gte',12),
            p49Rule('SYNTHETIC_RULE_B','projected_customers','eq',2,'CRM_TASK_DRAFT'),
            p49Rule('SYNTHETIC_RULE_C','visits','lte',119,'OPERATOR_NOTICE_DRAFT'),
        ]
    ];
}
$input=p49Command();$ruleset=p49Rules();
$baseline=$engine->evaluate($input,$ruleset);
p49Assert(array_keys($baseline)===[
    'schema','tenant_ref','request_ref','ruleset_ref','revision',
    'ruleset_sha256','evaluated_rules','matched_rules','proposals',
    'decision','status','human_approval_required',
    'operational_authorization','crm_tasks_created',
    'operator_messages_sent','ai_api_calls','ai_tokens',
    'commercial_credits_debited'
], 'closed_result_shape');
p49Assert($baseline['evaluated_rules']===3
    &&$baseline['matched_rules']===2
    &&count($baseline['proposals'])===2,
    'two_matches_one_nonmatch');
p49Assert(array_column($baseline['proposals'],'rule_ref')===[
    'SYNTHETIC_RULE_A','SYNTHETIC_RULE_B'
], 'preserve_rule_priority_order');
p49Assert($baseline['decision']==='NO_AI'
    &&$baseline['status']==='LAB_PROPOSALS_ONLY'
    &&$baseline['human_approval_required']===true
    &&$baseline['operational_authorization']===false
    &&$baseline['crm_tasks_created']===0
    &&$baseline['operator_messages_sent']===0
    &&$baseline['ai_api_calls']===0
    &&$baseline['ai_tokens']===0
    &&$baseline['commercial_credits_debited']===0,
    'proposals_are_not_effects');
p49Assert($baseline===$engine->evaluate($input,$ruleset),
    'deterministic_output_and_fingerprint');
p49Assert($baseline['proposals'][0]['execution']==='NOT_EXECUTED'
    &&$baseline['proposals'][0]['human_approval_required']===true
    &&str_starts_with($baseline['proposals'][0]['proposal_ref'],
        'SYNTHETIC_PROPOSAL_'),'explicit_review_draft');
$newRevision=$ruleset;
$newRevision['revision']=2;
$changed=$engine->evaluate($input,$newRevision);
p49Assert($changed['ruleset_sha256']!==$baseline['ruleset_sha256']
    &&$changed['proposals'][0]['proposal_ref']
        !==$baseline['proposals'][0]['proposal_ref'],
    'revision_changes_proposal_identity');
$newRequest=p49Command('SYNTHETIC_TENANT_A','SYNTHETIC_REQUEST_NEW');
p49Assert($engine->evaluate($newRequest,$ruleset)['proposals'][0]['proposal_ref']
    !==$baseline['proposals'][0]['proposal_ref'],
    'request_bound_proposal_identity');
$none=$ruleset;$none['rules']=[
    p49Rule('SYNTHETIC_RULE_NONE','projected_customers','gte',3)
];
$r=$engine->evaluate($input,$none);
p49Assert($r['matched_rules']===0&&$r['proposals']===[],
    'no_match_means_no_action_not_failure');

$bad=$ruleset;$bad['tenant_ref']='SYNTHETIC_TENANT_B';
p49Denied(static fn()=> $engine->evaluate($input,$bad),
    'tenant_mismatch');
$bad=$ruleset;$bad['schema']='AIA_RULESET_V2';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'unknown_version');
$bad=$ruleset;$bad['revision']=0;
p49Denied(static fn()=> $engine->evaluate($input,$bad),'invalid_revision');
$bad=$ruleset;$bad['revision']='1';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'revision_type');
$bad=$ruleset;$bad['rules']=[];
p49Denied(static fn()=> $engine->evaluate($input,$bad),'no_rules');
$bad=$ruleset;$bad['rules']=array_fill(0,9,$ruleset['rules'][0]);
p49Denied(static fn()=> $engine->evaluate($input,$bad),'too_many_rules');
$bad=$ruleset;$bad['rules'][1]['rule_ref']='SYNTHETIC_RULE_A';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'duplicate_rule_refs');
$bad=$ruleset;$bad['rules'][0]['metric']='owner_private_key';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'unapproved_metric');
$bad=$ruleset;$bad['rules'][0]['operator']='php_eval';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'unapproved_operator');
$bad=$ruleset;$bad['rules'][0]['draft_kind']='SEND_WHATSAPP';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'unapproved_side_effect');
$bad=$ruleset;$bad['rules'][0]['threshold']=-1;
p49Denied(static fn()=> $engine->evaluate($input,$bad),'negative_threshold');
$bad=$ruleset;$bad['rules'][0]['threshold']=100000001;
p49Denied(static fn()=> $engine->evaluate($input,$bad),'too_large_threshold');
$bad=$ruleset;$bad['rules'][0]['threshold']='12';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'threshold_string');
$bad=$ruleset;$bad['rules'][0]['threshold']=true;
p49Denied(static fn()=> $engine->evaluate($input,$bad),'threshold_boolean');
$bad=$ruleset;$bad['rules'][0]['hidden_credentials']='key';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'rule_extra_field');
$bad=$ruleset;$bad['unreviewed_action']='HTTP_POST';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'ruleset_extra_field');
$bad=$ruleset;$bad['rules']=[1=>$ruleset['rules'][0]];
p49Denied(static fn()=> $engine->evaluate($input,$bad),'non_list_rule_array');
$bad=$ruleset;
unset($bad['revision']);$bad['revision']=1;
p49Denied(static fn()=> $engine->evaluate($input,$bad),'ruleset_field_reorder');
$bad=$ruleset;$bad['tenant_ref']='SYNTHETIC_TENANT_A/../';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'untrusted_tenant_string');
$bad=$ruleset;$bad['rules'][0]['rule_ref']='SYNTHETIC_a';
p49Denied(static fn()=> $engine->evaluate($input,$bad),'untrusted_rule_ref');
$bad=$input;$bad['weekly_visits']=-1;
p49Denied(static fn()=> $engine->evaluate($bad,$ruleset),
    'forbid_untrusted_calculation_input');

mt_srand(49);
for($i=0;$i<2500;$i++){
    $v=mt_rand(0,100000000);
    $count=mt_rand(0,100000000);
    $cmd=p49Command('SYNTHETIC_TENANT_A',
        'SYNTHETIC_TEST_REQUEST_'.$i,$v);
    $rset=$ruleset;
    $rset['rules']=[p49Rule(
        'SYNTHETIC_RULE_RANDOM','visits','gte',$count
    )];
    $out=$engine->evaluate($cmd,$rset);
    p49Assert($out['matched_rules']===($v>=$count?1:0)
        &&$out['ai_api_calls']===0
        &&$out['crm_tasks_created']===0
        &&$out['operator_messages_sent']===0
        &&$out['commercial_credits_debited']===0,
        'fuzz_threshold_correct_no_effect_'.$i);
}

echo "AIA_P49_RULE_ENGINE_PASS checks={$checks} fuzz=2500 proposals_only=1 real_effects=0 no_ai=1 NO_GO\n";
