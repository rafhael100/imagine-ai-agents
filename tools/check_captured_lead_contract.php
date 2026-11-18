<?php
declare(strict_types=1);
require_once __DIR__.'/labs/LabAutomationTaskContract.php';

use ImagineAI\MockOrchestration\CapturedLeadTaskContract;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\Convergence\LabAutomationTaskContract;

$checks=0;
function leadContractCheck(bool $ok,string $case):void
{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('CAPTURED_LEAD_CONTRACT_FAIL_'.$case);
}
$now=1800000000;
$task=['contract'=>'SYNTHETIC_CRM_LEAD_INGEST_V1',
    'event_ref'=>'SYNTHETIC_11111111111141118111111111111111',
    'tenant_ref'=>'SYNTHETIC_TENANT_A',
    'company_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'lead_id'=>'abcdef12-3456-7123-8abc-0123456789ab',
    'source_payload_hash'=>str_repeat('a',64),'kind'=>'crm.lead.ingest','occurred_at'=>$now];
$raw=CapturedLeadTaskContract::encode($task);
leadContractCheck(is_string($raw)&&strlen($raw)<=512,'bounded_canonical');
leadContractCheck(CapturedLeadTaskContract::decode($raw)===$task,'round_trip_uuid_v7_lead');
leadContractCheck(MockN8nOutboxContract::decode($raw)===null,'lead_is_not_a_mock_run');
leadContractCheck(LabAutomationTaskContract::decode($raw)===$task,'explicit_dispatch');
leadContractCheck(LabAutomationTaskContract::operation($raw)==='lead.ingest','owner_operation');
foreach(['contract'=>'SYNTHETIC_AIA_AUTOMATION_V1','kind'=>'mock.run.completed',
    'company_id'=>'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa',
    'lead_id'=>'ABCDEF12-3456-7123-8ABC-0123456789AB',
    'source_payload_hash'=>str_repeat('A',64),'tenant_ref'=>'REAL_CUSTOMER',
    'event_ref'=>'SYNTHETIC_'.str_repeat('A',49),'occurred_at'=>(string)$now] as $field=>$bad){
    $changed=$task;$changed[$field]=$bad;
    leadContractCheck(CapturedLeadTaskContract::encode($changed)===null,'reject_'.$field);
}
$extra=$task;$extra['run_ref']='SYNTHETIC_FAKE_RUN';
leadContractCheck(CapturedLeadTaskContract::encode($extra)===null,'reject_extra_run');
$reordered=$task;unset($reordered['kind']);$reordered['kind']='crm.lead.ingest';
leadContractCheck(CapturedLeadTaskContract::encode($reordered)===null,'reject_key_order');
leadContractCheck(CapturedLeadTaskContract::decode(' '.$raw)===null,'reject_whitespace');
leadContractCheck(CapturedLeadTaskContract::decode(substr($raw,0,-1).',"kind":"crm.lead.ingest"}')===null,
    'reject_duplicate_key');
leadContractCheck(CapturedLeadTaskContract::decode(substr($raw,0,-1).',"k\\u0069nd":"crm.lead.ingest"}')===null,
    'reject_escaped_duplicate_key');
leadContractCheck(CapturedLeadTaskContract::decode(str_repeat('x',513))===null,'reject_size');
$proposal=CapturedLeadTaskContract::propose($raw,$task['tenant_ref'],$now);
leadContractCheck($proposal['decision']==='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
    &&$proposal['proposal']['operation']==='lead.ingest'
    &&$proposal['proposal']['product']==='imagine-crm','proposal_exact_operation');
leadContractCheck($proposal['proposal']['payload_hash']===hash('sha256',$raw)
    &&$proposal['proposal']['payload_hash']!==$task['source_payload_hash'],'task_hash_distinct_from_source');
leadContractCheck(CapturedLeadTaskContract::propose($raw,'SYNTHETIC_TENANT_B',$now)['decision']==='DENY',
    'cross_tenant_denied');
leadContractCheck(CapturedLeadTaskContract::propose($raw,$task['tenant_ref'],$now+60)['decision']==='DENY',
    'expiry_exact_boundary');
leadContractCheck(CapturedLeadTaskContract::propose($raw,$task['tenant_ref'],$now-6)['decision']==='DENY',
    'future_task_denied');
$changed=$task;$changed['source_payload_hash']=str_repeat('b',64);
$other=CapturedLeadTaskContract::encode($changed);
leadContractCheck(hash('sha256',$raw)!==hash('sha256',$other),'source_transitively_bound');
$legacy=['contract'=>'SYNTHETIC_AIA_AUTOMATION_V1','event_ref'=>'SYNTHETIC_OLD_EVENT',
    'tenant_ref'=>'SYNTHETIC_TENANT_A','run_ref'=>'SYNTHETIC_RUN',
    'kind'=>'mock.run.completed','reference_ref'=>'SYNTHETIC_REFERENCE','occurred_at'=>$now];
$old=MockN8nOutboxContract::encode($legacy);
leadContractCheck(is_string($old)&&LabAutomationTaskContract::decode($old)===$legacy
    &&CapturedLeadTaskContract::decode($old)===null,'legacy_contract_preserved');
leadContractCheck(LabAutomationTaskContract::operation($old)==='run.status.project',
    'owner_operations_cannot_cross');
echo "CAPTURED_LEAD_CONTRACT_PASS checks={$checks} source_task_hashes_distinct=1 legacy_preserved=1 NO_GO\n";
