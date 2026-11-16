<?php
declare(strict_types=1);

/**
 * CENTRAL-AUTO-P3: execute three exact source classes from independent PR heads
 * in one offline PHP process. This is a contract rehearsal, NOT service IAM.
 *
 * Sources are readonly GitHub Actions checkouts with validated commit SHA.
 */
require_once __DIR__ . '/../../../_crm_contract_pin/app/Core/Automation/RuleDefinition.php';
require_once __DIR__ . '/../../../_crm_contract_pin/app/Homologation/AutomationSettings.php';
require_once __DIR__ . '/../../../_portal_contract_pin/apps/portal/app/Domain/AutomationAdministration/AutomationStateReader.php';
require_once __DIR__ . '/../../../_agents_lock_pin/tools/auto_ia/MockS2SEnvelopeVerifier.php';

use App\Core\Automation\RuleDefinition;
use App\Homologation\AutomationSettings;
use App\Domain\AutomationAdministration\AutomationStateReader;
use ImagineAI\AutomationFirst\MockS2SEnvelopeVerifier;

$checks=0;
function tested(bool $ok,string $name):void {
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('CENTRAL_AUTO_P3_FAIL_'.$name);
}
function denied(callable $action,string $name):void {
    global $checks;
    try { $action(); } catch (\Throwable) { ++$checks; return; }
    throw new RuntimeException('CENTRAL_AUTO_P3_DID_NOT_DENY_'.$name);
}
function snapshot(string $company,int $version,array $events,int $now):array {
    return [
      'schema'=>'CRM_AUTOMATION_STATE_V1',
      'source_kind'=>'synthetic_private_database',
      'company_id'=>$company,'observed_at'=>$now,
      'settings_version'=>$version,
      'counts'=>['pending'=>0,'completed'=>1,'cancelled'=>0,'failed'=>0],
      'events'=>$events,'ai_provider_calls'=>0,
      'operational_authorization'=>false,
    ];
}
function fixtureEnvelope(string $operation,int $now,array $other=[]):array {
    $source=[
       'schema'=>'AUTO_IA_S2S_V1','issuer'=>'imagine-crm',
       'audience'=>'imagine-ai-agents','product'=>'imagine-crm',
       'operation'=>$operation,'tenant_ref'=>'SYNTHETIC_TENANT_A',
       'event_ref'=>'SYNTHETIC_EVENT_A','nonce_ref'=>'SYNTHETIC_NONCE_A',
       'request_ref'=>'SYNTHETIC_REQUEST_A','issued_at'=>$now-3,
       'expires_at'=>$now+60,
       'payload_hash'=>hash('sha256','SYNTHETIC_TEST_ONLY_NO_DATA'),
       'key_id'=>'SYNTHETIC_KEY_A','signature'=>'',
    ];
    foreach($other as $key=>$value)$source[$key]=$value;
    $pieces=[];
    foreach($source as $key=>$value)if($key!=='signature')$pieces[]=(string)$value;
    $source['signature']=hash_hmac('sha256',implode("\n",$pieces),
      'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY');
    return $source;
}
$ring=['SYNTHETIC_KEY_A'=>'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY'];
$now=time();
$companyA='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$companyB='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

// CRM actual classes: only deterministic preapproved native followup templates.
$settings=AutomationSettings::read('[]')->revise(
    0,['followup_qualified'],'SYNTHETIC_TEST_ACTOR',$now,0
);
tested($settings->version===1 && $settings->rules===['followup_qualified'],
    'crm_settings_version_and_template');
$rule=RuleDefinition::fromArray([
  'tenant'=>'t-synthetic-a','id'=>'followup_qualified','version'=>$settings->version,
  'template'=>'followup_qualified','delay_seconds'=>$settings->delaySeconds,
  'recipient'=>'p-synthetic-owner',
]);
$event=[
  'tenant'=>'t-synthetic-a','id'=>'e-synthetic-event',
  'resource'=>'r-synthetic-lead','stage'=>'qualified',
  'trigger'=>'lead.stage.changed','occurred_at'=>$now-1,
];
$command=$rule->plan('t-synthetic-a',$event,$now);
tested(is_array($command) && $command['contract']==='CRM_NATIVE_COMMAND_V1'
   && $command['action']==='task.followup.create' && $command['ai_calls']===0,
   'crm_native_no_ai_command');
tested($command['idempotency_key']===$rule->plan('t-synthetic-a',$event,$now)['idempotency_key'],
   'crm_no_duplicate_command_identity');
$rev2=$settings->revise(1,['followup_qualified'],'SYNTHETIC_TEST_ACTOR',$now,0);
$rule2=RuleDefinition::fromArray([
 'tenant'=>'t-synthetic-a','id'=>'followup_qualified','version'=>$rev2->version,
 'template'=>'followup_qualified','delay_seconds'=>0,
 'recipient'=>'p-synthetic-owner',
]);
tested($rule2->plan('t-synthetic-a',$event,$now)['idempotency_key']!==$command['idempotency_key'],
   'changed_version_changes_idempotency_key');
denied(fn()=>$rule->plan('t-synthetic-b',$event,$now),'crm_cross_tenant_plan');
denied(fn()=>RuleDefinition::fromArray([
 'tenant'=>'t-synthetic-a','id'=>'r-demo','version'=>1,
 'template'=>'user_custom_webhook','delay_seconds'=>0,'recipient'=>'p-demo',
]),'crm_custom_webhook_disallowed');
tested($rule->plan('t-synthetic-a',[...$event,'stage'=>'proposal'],$now)===null,
    'crm_wrong_stage_no_side_effect');

// Portal actual reader: producer schema is synthetic, no automatic grant or provider.
$sourceCalls=0;
$authorizations=0;
$authorized=true;
$eventLog=[['action'=>'automation.task_created','at'=>$now-1]];
$state=snapshot($companyA,$settings->version,$eventLog,$now);
$provider=function(string $company)use(&$sourceCalls,$state):array {
    $sourceCalls++;
    return $state;
};
$grant=function(int $admin,string $company)use(&$authorizations,&$authorized,$companyA):bool {
    $authorizations++;
    return $authorized && $admin===7 && $company===$companyA;
};
$reader=new AutomationStateReader($provider,$grant);
$read=$reader->read(7,$companyA);
tested($read===$state && $sourceCalls===1 && $authorizations===2,
    'portal_two_authorizations_one_scoped_read');
$authorized=false;
denied(fn()=>$reader->read(7,$companyA),'portal_revoked_grant');
tested($sourceCalls===1,'revocation_happens_before_transport');
$authorized=true;
denied(fn()=>$reader->read(7,$companyB),'portal_other_company');
tested($sourceCalls===1,'foreign_company_never_calls_transport');
$sourceCalls=0;$calls=0;
$unstableGrant=function()use(&$calls):bool {return ++$calls===1;};
$unstableReader=new AutomationStateReader($provider,$unstableGrant);
denied(fn()=>$unstableReader->read(7,$companyA),'portal_revoked_during_read');
tested($sourceCalls===1 && $calls===2,'portal_rechecks_after_transport');
foreach ([
  'forged_credits'=>array_replace($state,['ai_provider_calls'=>1]),
  'forged_prod'=>array_replace($state,['operational_authorization'=>true]),
  'wrong_company'=>array_replace($state,['company_id'=>$companyB]),
  'stale_time'=>array_replace($state,['observed_at'=>$now-61]),
  'extra_secret'=>array_replace($state,['authorization'=>'leaked']),
  'unknown_action'=>array_replace($state,['events'=>[['action'=>'billing.charge','at'=>$now-1]]]),
] as $label=>$malicious) {
  denied(fn()=>(new AutomationStateReader(fn()=>$malicious,fn()=>true))->read(7,$companyA),
    'portal_denies_'.$label);
}

// Agents actual signed NO_AI contract: does NOT allow CRM followup to be sent
// to n8n or accepted as a direct automation authority.
$accept=MockS2SEnvelopeVerifier::verify(
    fixtureEnvelope('lead.ingest',$now),$now,$ring);
tested($accept['decision']==='LAB_VERIFIED_NO_AUTHORIZATION'
   && $accept['ai_calls']===0 && $accept['service_effects']===0,
   'agents_signed_fixture_without_operational_authority');
$followup=MockS2SEnvelopeVerifier::verify(
    fixtureEnvelope('task.followup.create',$now),$now,$ring);
tested($followup['decision']==='DENY'
    && $followup['reason']==='ONLY_NO_AI_OPERATIONS',
    'native_followup_must_not_bypass_gateway_to_n8n');
$changedTenant=fixtureEnvelope('lead.ingest',$now);
$changedTenant['tenant_ref']='SYNTHETIC_TENANT_B';
tested(MockS2SEnvelopeVerifier::verify($changedTenant,$now,$ring)['decision']==='DENY',
   'signed_issuer_tenant_binding');
tested(MockS2SEnvelopeVerifier::verify(
    fixtureEnvelope('lead.ingest',$now),$now,[])['decision']==='DENY',
    'key_revocation_denies');
tested(MockS2SEnvelopeVerifier::verify(
    fixtureEnvelope('lead.ingest',$now,['audience'=>'imagine-crm']),$now,$ring)['decision']==='DENY',
    'audience_binding');
tested($command['ai_calls']===0 && $state['ai_provider_calls']===0
   && $accept['ai_calls']===0,
   'no_model_credits_through_any_component');
echo "CENTRAL_AUTO_P3_CONTRACT_PASS checks={$checks} crm=PINNED portal=PINN agents=PINN no_transport=1 no_provider=1 NO_GO\n";
