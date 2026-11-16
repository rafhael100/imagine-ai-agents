<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../_p7_pinned_source/src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__ . '/../MockS2SEnvelopeVerifier.php';
require_once __DIR__ . '/MockP7ToAutoIaAdapter.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\AutomationFirst\{MockP7ToAutoIaAdapter,MockS2SEnvelopeVerifier};

$cases=0;
function checked(bool $pass, string $name): void {
    global $cases;
    $cases++;
    if (!$pass) throw new RuntimeException("AIA_CONV_02_FAIL ".$name);
}
function p7Fixture(string $kind, int $now): string {
    $envelope=[
        'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref'=>'SYNTHETIC_P7_EVENT_01',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'run_ref'=>'SYNTHETIC_RUN_A',
        'kind'=>$kind,
        'reference_ref'=>'SYNTHETIC_P7_REFERENCE',
        'occurred_at'=>$now-5,
    ];
    $value=MockN8nOutboxContract::encode($envelope);
    if ($value===null) throw new RuntimeException('P7 contract changed — DENY');
    return $value;
}
function denied(string $source,string $tenant,int $now,string $reason): void {
    $r=MockP7ToAutoIaAdapter::propose($source,$tenant,$now);
    checked($r['decision']==='DENY' && $r['reason']===$reason,'denied_'.$reason);
    checked($r['proposal']===null && $r['ai_calls']===0 && $r['service_effects']===0,
      'no authorization or effects on denied');
}
$now=1000;
foreach ([
 'mock.run.completed'=>'run.status.project',
 'mock.quota.threshold'=>'usage.quota.evaluate'
] as $kind=>$expectedOperation) {
    $p7=p7Fixture($kind,$now);
    $r=MockP7ToAutoIaAdapter::propose($p7,'SYNTHETIC_TENANT_A',$now);
    checked($r['decision']==='LAB_NO_AI_PROPOSAL_NOT_SIGNED','allowed_'.$kind);
    checked($r['proposal']['operation']===$expectedOperation
        && $r['proposal']['product']==='imagine-ai-agents','mapping_'.$kind);
    checked($r['ai_calls']===0 && $r['service_effects']===0,'zero_calls_'.$kind);
    checked(!isset($r['proposal']['signature']) && !isset($r['proposal']['key_id']),
        'no spoofed signature_'.$kind);
    checked($r===MockP7ToAutoIaAdapter::propose($p7,'SYNTHETIC_TENANT_A',$now),
        'deterministic mapping_'.$kind);

    // Test-only signing of the proposed, STILL UNAUTHORIZED event.
    $p=$r['proposal'];
    $toSign=[
        'schema'=>'AUTO_IA_S2S_V1',
        'issuer'=>$p['issuer'],
        'audience'=>$p['audience'],
        'product'=>$p['product'],
        'operation'=>$p['operation'],
        'tenant_ref'=>$p['tenant_ref'],
        'event_ref'=>$p['event_ref'],
        'nonce_ref'=>$p['nonce_ref'],
        'request_ref'=>$p['request_ref'],
        'issued_at'=>$now-5,
        'expires_at'=>$now+60,
        'payload_hash'=>$p['payload_hash'],
        'key_id'=>'SYNTHETIC_KEY_A',
        'signature'=>'',
    ];
    $key='SYNTHETIC_OFFLINE_CONVERGENCE_TEST_SIGNING_KEY';
    $canonical=[];
    foreach($toSign as $name=>$value) if($name!=='signature') $canonical[]=(string)$value;
    $toSign['signature']=hash_hmac('sha256',implode("\n",$canonical),$key);
    $v=MockS2SEnvelopeVerifier::verify($toSign,$now,['SYNTHETIC_KEY_A'=>$key]);
    checked($v['decision']==='LAB_VERIFIED_NO_AUTHORIZATION','only synthetic signature_'.$kind);
}
denied(p7Fixture('mock.approval.requested',$now),'SYNTHETIC_TENANT_A',$now,
    'P7_KIND_UNMAPPED_REQUIRES_REVIEW');
denied(p7Fixture('mock.run.completed',$now),'SYNTHETIC_TENANT_B',$now,'SOURCE_TENANT_MISMATCH');
denied(p7Fixture('mock.run.completed',$now),'SYNTHETIC_TENANT_A',$now+61,
    'EVENT_WINDOW_NOT_CURRENT');
denied(p7Fixture('mock.run.completed',$now),'SYNTHETIC_TENANT_A',$now-12,
    'EVENT_WINDOW_NOT_CURRENT');
$invalid=json_decode(p7Fixture('mock.run.completed',$now),true);
$invalid['event_ref']='SYNTHETIC_'.str_repeat('A',70);
$long=MockN8nOutboxContract::encode($invalid);
checked($long!==null,'P7 permits longer references than AUTO-IA');
denied($long,'SYNTHETIC_TENANT_A',$now,'AUTO_IA_REF_INCOMPATIBLE');
denied('{"evil":true}','SYNTHETIC_TENANT_A',$now,'P7_CANONICAL_CONTRACT_DENIED');
denied(p7Fixture('mock.run.completed',$now),'SYNTHETIC_UNTRUSTED',$now,
    'SOURCE_TENANT_MISMATCH');
echo "AIA_CONV_02_PASS checks={$cases} p7_pin_read_only=1 no_ai_api=1 no_iam=1\n";
