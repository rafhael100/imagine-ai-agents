<?php
declare(strict_types=1);

/** CENTRAL-AUTO-P5: original CRM classes build a synthetic state fixture. */
require_once __DIR__.'/../../../_crm_contract_pin/app/Core/Automation/RuleDefinition.php';
require_once __DIR__.'/../../../_crm_contract_pin/app/Homologation/AutomationSettings.php';
use App\Core\Automation\RuleDefinition;
use App\Homologation\AutomationSettings;

if (getenv('GITHUB_ACTIONS')!=='true' ||
    getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents') {
    throw new RuntimeException('CI_ONLY');
}
$now=time();
$s=AutomationSettings::read('[]')->revise(0,['followup_qualified'],'SYNTHETIC_ACTOR',$now);
$r=RuleDefinition::fromArray([
    'tenant'=>'t-synthetic-tenant','id'=>'followup_qualified',
    'version'=>$s->version,'template'=>'followup_qualified',
    'delay_seconds'=>0,'recipient'=>'p-synthetic-owner',
]);
$event=['tenant'=>'t-synthetic-tenant','id'=>'e-synthetic-01',
    'resource'=>'r-synthetic-lead','stage'=>'qualified',
    'trigger'=>'lead.stage.changed','occurred_at'=>$now];
$command=$r->plan('t-synthetic-tenant',$event,$now);
if (!is_array($command)||$command['ai_calls']!==0||
    $command['action']!=='task.followup.create') {
    throw new RuntimeException('CRM_NO_AI_CONTRACT_CHANGED');
}
$state=[
  'schema'=>'CRM_AUTOMATION_STATE_V1',
  'source_kind'=>'synthetic_private_database',
  'company_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'observed_at'=>$now,
  'settings_version'=>$s->version,
  'counts'=>['pending'=>0,'completed'=>1,'cancelled'=>0,'failed'=>0],
  'events'=>[['action'=>'automation.task_created','at'=>$now]],
  'ai_provider_calls'=>0,
  'operational_authorization'=>false,
];
echo json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
