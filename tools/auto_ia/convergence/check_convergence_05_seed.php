<?php
declare(strict_types=1);
/**
 * CONV-05: seed one disposable PostgreSQL with pinned P12 outbox states
 * and signed AUTO-IA inbox, then let CI pg_dump/pg_restore test persistence.
 * No provider, customer, IAM, n8n transport or billing.
 */
require_once __DIR__.'/../../../_p12_pinned_source/src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__.'/../../../_p12_pinned_source/tools/labs/PgN8nOutboxLab.php';
require_once __DIR__.'/../MockS2SEnvelopeVerifier.php';
require_once __DIR__.'/MockP7ToAutoIaAdapter.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract as P12Contract;
use ImagineAI\Labs\PgN8nOutboxLab as P12Outbox;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter as Adapter;

foreach([
 'AIA_P6_PG_LAB'=>'true','GITHUB_ACTIONS'=>'true',
 'GITHUB_REPOSITORY'=>'rafhael100/imagine-ai-agents',
 'DB_DATABASE'=>'imagine_agents_ci','DB_HOST'=>'127.0.0.1',
 'DB_USERNAME'=>'postgres'
] as $key=>$value) {
 if(getenv($key)!==$value) throw new RuntimeException('RECOVERY_CI_ONLY_'.$key);
}
function demand(bool $yes,string $label): void {
 if (!$yes) throw new RuntimeException('CONV05_SEED_FAIL_'.$label);
}
function connect(): PDO {
 return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
 'postgres','ci-only-password',
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
function canonical(string $tenant,string $event,string $kind,int $at): string {
 $result=P12Contract::encode([
  'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
  'event_ref'=>$event,'tenant_ref'=>$tenant,'run_ref'=>'SYNTHETIC_RUN_05',
  'kind'=>$kind,'reference_ref'=>'SYNTHETIC_RESOURCE_05','occurred_at'=>$at,
 ]);
 demand(is_string($result),'canonical');
 return $result;
}
function signedForInbox(array $p,int $now): array {
 $e=[
  'AUTO_IA_S2S_V1',
  $p['issuer'],$p['audience'],$p['product'],$p['operation'],
  $p['tenant_ref'],$p['event_ref'],$p['nonce_ref'],$p['request_ref'],
  $now-5,$now+270,$p['payload_hash'],'SYNTHETIC_KEY_A',
 ];
 $parts=array_map('strval',$e);
 $e[]=hash_hmac('sha256',implode("\n",$parts),
  'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY');
 return $e;
}
function deliver(PDO $db,string $role,array $envelope): string {
 demand(in_array($role,['aia_lab_tenant_a','aia_lab_tenant_b'],true),'role');
 $db->exec('SET ROLE '.$role);
 try {
   $q=$db->prepare('SELECT auto_ia_lab.receive_signed_no_ai('.
      implode(',',array_fill(0,14,'?')).')');
   $q->execute($envelope);
   return (string)$q->fetchColumn();
 } finally {
   $db->exec('RESET ROLE');
 }
}
$now=time();
$db=connect();
$incoming=connect();
$out=new P12Outbox($db);
$out->createTables();
foreach([
 ['SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_A','mock.run.completed',
  'SYNTHETIC_RECOVERY_WORKER_A','aia_lab_tenant_a'],
 ['SYNTHETIC_TENANT_B','SYNTHETIC_RECOVERY_B','mock.quota.threshold',
  'SYNTHETIC_RECOVERY_WORKER_B','aia_lab_tenant_b'],
] as [$tenant,$id,$kind,$worker,$role]) {
 $body=canonical($tenant,$id,$kind,$now-5);
 demand($out->enqueue($body)==='CREATED','outbox_create_'.$id);
 $claim=$out->claim($tenant,$worker,$now);
 demand(is_array($claim) && $claim['attempt']===1,'claim_'.$id);
 $route=Adapter::propose($claim['canonical_event'],$tenant,$now);
 demand($route['decision']==='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
   && $route['ai_calls']===0,'adapter_'.$id);
 $envelope=signedForInbox($route['proposal'],$now);
 demand(deliver($incoming,$role,$envelope)==='LAB_RECEIVED_NO_DELIVERY',
   'signed_inbox_'.$id);
 demand($out->acknowledge($tenant,$id,$worker,1,$now+1),'fenced_ack_'.$id);
}
$approval=canonical('SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_APPROVAL',
  'mock.approval.requested',$now-5);
demand($out->enqueue($approval)==='CREATED','outbox_approval');
$first=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_WORKER_A',$now);
demand(is_array($first) && $first['attempt']===1,'approval_claim');
demand(Adapter::propose($first['canonical_event'],'SYNTHETIC_TENANT_A',$now)['decision']
==='DENY','approval_denied');
for($attempt=2;$attempt<=3;$attempt++) {
 $c=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_WORKER_A',
  $now+($attempt-1)*60);
 demand(is_array($c) && $c['attempt']===$attempt,'approval_retry_'.$attempt);
}
demand($out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_RECOVERY_WORKER_A',$now+180)
===null,'dead_letter_retained');
demand($out->stats('SYNTHETIC_TENANT_A')===[
 'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>1
],'expected_A_outbox_state');
demand($out->stats('SYNTHETIC_TENANT_B')===[
 'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>0
],'expected_B_outbox_state');
demand((int)$db->query('SELECT COUNT(*) FROM auto_ia_lab.event_inbox')
->fetchColumn()===2,'exactly_two_signed_inbox_rows');
echo "AIA_CONV05_SEED_PASS outbox=3 inbox=2 acked=2 dead=1 no_provider=1\n";
// Intentionally do NOT drop tables: the next CI step dumps/restores this
// ephemeral database into a second ephemeral database for recovery proof.
