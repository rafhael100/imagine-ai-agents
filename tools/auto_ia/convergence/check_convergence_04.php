<?php
declare(strict_types=1);

/**
 * CONV-04: test-only P12 outbox -> AUTO-IA signed inbox -> fenced ACK.
 * Single ephemeral PG16 CI database; no n8n, HTTP, API, AI, real IAM or costs.
 */
require_once __DIR__ . '/../../../_p12_pinned_source/src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__ . '/../../../_p12_pinned_source/tools/labs/PgN8nOutboxLab.php';
require_once __DIR__ . '/../MockS2SEnvelopeVerifier.php';
require_once __DIR__ . '/MockP7ToAutoIaAdapter.php';

use ImagineAI\Labs\PgN8nOutboxLab;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\AutomationFirst\MockS2SEnvelopeVerifier;

$checks = 0;
function ck(bool $pass, string $name): void {
    global $checks;
    ++$checks;
    if (!$pass) throw new RuntimeException('AUTO_IA_CONV04_FAILED_' . $name);
}
function db(): PDO {
    return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres', 'ci-only-password', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
function canonicalEvent(string $tenant,string $event,string $kind,int $at): string {
    $obj=[
       'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
       'event_ref'=>$event,
       'tenant_ref'=>$tenant,
       'run_ref'=>'SYNTHETIC_RUN_01',
       'kind'=>$kind,
       'reference_ref'=>'SYNTHETIC_REF_01',
       'occurred_at'=>$at
    ];
    $c=MockN8nOutboxContract::encode($obj);
    ck(is_string($c), 'p12_canonical');
    return $c;
}
/** Only in this CI script: fake key is trusted by ephemeral SQL and verifier. */
function fakeSignedProposal(array $proposal, int $now): array {
    $envelope=[
        'schema'=>'AUTO_IA_S2S_V1',
        'issuer'=>$proposal['issuer'],
        'audience'=>$proposal['audience'],
        'product'=>$proposal['product'],
        'operation'=>$proposal['operation'],
        'tenant_ref'=>$proposal['tenant_ref'],
        'event_ref'=>$proposal['event_ref'],
        'nonce_ref'=>$proposal['nonce_ref'],
        'request_ref'=>$proposal['request_ref'],
        'issued_at'=>$now-5,
        'expires_at'=>$now+120,
        'payload_hash'=>$proposal['payload_hash'],
        'key_id'=>'SYNTHETIC_KEY_A',
        'signature'=>'',
    ];
    $key='SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY';
    $parts=[];
    foreach ($envelope as $k=>$v) if($k!=='signature') $parts[]=(string)$v;
    $envelope['signature']=hash_hmac('sha256',implode("\n",$parts),$key);
    $verification=MockS2SEnvelopeVerifier::verify(
        $envelope,$now,['SYNTHETIC_KEY_A'=>$key]);
    ck($verification['decision']==='LAB_VERIFIED_NO_AUTHORIZATION',
       'php_hmac_fixture_valid');
    return $envelope;
}
function signedInbox(PDO $connection,string $tenantRole,array $e): string {
    ck(in_array($tenantRole,['aia_lab_tenant_a','aia_lab_tenant_b'],true),
        'closed_role');
    $connection->exec('SET ROLE '.$tenantRole);
    try {
        $slots=implode(',',array_fill(0,14,'?'));
        $stmt=$connection->prepare('SELECT auto_ia_lab.receive_signed_no_ai('.
            $slots.')');
        ck(count($e)===14,'exact_envelope_fields');
        // This order is schema-controlled and identical to AUTO-IA-09.
        $r=[
          $e['schema'],$e['issuer'],$e['audience'],$e['product'],
          $e['operation'],$e['tenant_ref'],$e['event_ref'],$e['nonce_ref'],
          $e['request_ref'],$e['issued_at'],$e['expires_at'],
          $e['payload_hash'],$e['key_id'],$e['signature'],
        ];
        $stmt->execute($r);
        return (string)$stmt->fetchColumn();
    } finally {
        $connection->exec('RESET ROLE');
    }
}
function propose(string $canonical,string $tenant,int $now): array {
    $r=MockP7ToAutoIaAdapter::propose($canonical,$tenant,$now);
    ck(($r['ai_calls']??-1)===0 && ($r['service_effects']??-1)===0,
       'adapter_never_spends_or_mutates');
    return $r;
}
$required=[
 'GITHUB_ACTIONS'=>'true',
 'GITHUB_REPOSITORY'=>'rafhael100/imagine-ai-agents',
 'AIA_P6_PG_LAB'=>'true',
 'DB_DATABASE'=>'imagine_agents_ci',
 'DB_HOST'=>'127.0.0.1',
 'DB_USERNAME'=>'postgres',
];
foreach($required as $key=>$value) {
    ck(getenv($key)===$value,'strict_ci_environment_'.$key);
}
$now=time();
$pdo=db();
$out=new PgN8nOutboxLab($pdo);
$incoming=db();
$out->createTables();
try {
    $a=canonicalEvent('SYNTHETIC_TENANT_A','SYNTHETIC_CONV_EVENT_A',
        'mock.run.completed',$now-5);
    ck($out->enqueue($a)==='CREATED','outbox_created');
    ck($out->enqueue($a)==='REPLAY','outbox_duplicate_not_added');
    $first=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now);
    ck(is_array($first) && $first['attempt']===1
       && $first['canonical_event']===$a,'first_claim');
    $firstProposal=propose($first['canonical_event'],'SYNTHETIC_TENANT_A',$now);
    ck($firstProposal['decision']==='LAB_NO_AI_PROPOSAL_NOT_SIGNED',
       'p12_to_auto_ia_mapping');
    $signed=fakeSignedProposal($firstProposal['proposal'],$now);
    $tampered=$signed;
    $tampered['payload_hash']=str_repeat('0',64);
    ck(signedInbox($incoming,'aia_lab_tenant_a',$tampered)==='LAB_DENY_MAC',
       'signed_payload_tamper_denied');
    ck($out->stats('SYNTHETIC_TENANT_A')['leased']===1,
       'tampered_does_not_ack');
    ck(signedInbox($incoming,'aia_lab_tenant_a',$signed)
       ==='LAB_RECEIVED_NO_DELIVERY','first_durable_signed_inbox');
    ck($out->stats('SYNTHETIC_TENANT_A')['leased']===1,
       'durable_inbox_is_not_ack');

    // Simulate an ACK lost after a successful DB write, without real time/network.
    // A retry must be idempotent and a stale generation ACK must be rejected.
    $next=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now+60);
    ck(is_array($next) && $next['attempt']===2
       && $next['event_ref']===$first['event_ref'],'lease_retried');
    ck(signedInbox($incoming,'aia_lab_tenant_a',$signed)
       ==='LAB_DUPLICATE_NO_DELIVERY','retry_does_not_duplicate_inbox');
    ck(!$out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_CONV_EVENT_A',
       'SYNTHETIC_WORKER_A',1,$now+61),'stale_same_worker_ack_denied');
    ck($out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_CONV_EVENT_A',
       'SYNTHETIC_WORKER_A',2,$now+61),'fenced_ack_after_signed_persist');
    ck(!$out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_CONV_EVENT_A',
       'SYNTHETIC_WORKER_A',2,$now+61),'duplicate_ack_denied');

    $b=canonicalEvent('SYNTHETIC_TENANT_B','SYNTHETIC_CONV_EVENT_B',
        'mock.quota.threshold',$now-5);
    ck($out->enqueue($b)==='CREATED','second_tenant_outbox');
    $claimB=$out->claim('SYNTHETIC_TENANT_B','SYNTHETIC_WORKER_B',$now);
    ck(is_array($claimB) && $claimB['attempt']===1,'tenant_b_claim');
    $pB=propose($claimB['canonical_event'],'SYNTHETIC_TENANT_B',$now);
    ck($pB['decision']==='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
       && $pB['proposal']['operation']==='usage.quota.evaluate',
       'tenant_b_quota_event_mapping');
    $signedB=fakeSignedProposal($pB['proposal'],$now);
    ck(signedInbox($incoming,'aia_lab_tenant_a',$signedB)
       ==='LAB_DENY_SERVICE_TENANT','cross_tenant_signed_event_denied');
    ck(signedInbox($incoming,'aia_lab_tenant_b',$signedB)
       ==='LAB_RECEIVED_NO_DELIVERY','right_tenant_accepts');
    ck($out->acknowledge('SYNTHETIC_TENANT_B','SYNTHETIC_CONV_EVENT_B',
       'SYNTHETIC_WORKER_B',1,$now+1),'tenant_b_ack');

    // Approval notifications have no safe mapping in current vocabulary:
    // the event remains in the original outbox and must not be ACKed.
    $approval=canonicalEvent('SYNTHETIC_TENANT_A','SYNTHETIC_CONV_APPROVAL',
       'mock.approval.requested',$now-5);
    ck($out->enqueue($approval)==='CREATED','pending_approval_outbox');
    $ap=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now);
    ck(is_array($ap) && $ap['attempt']===1,'approval_claim');
    $reject=propose($ap['canonical_event'],'SYNTHETIC_TENANT_A',$now);
    ck($reject['decision']==='DENY'
       && $reject['reason']==='P7_KIND_UNMAPPED_REQUIRES_REVIEW'
       && $reject['proposal']===null,'approval_mapping_denied');
    ck($out->stats('SYNTHETIC_TENANT_A')['leased']===1,
       'unmapped_approval_not_acknowledged');
    ck(($out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now+60)['attempt']??null)===2,
       'approval_retry_2');
    ck(($out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now+120)['attempt']??null)===3,
       'approval_retry_3');
    ck($out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_A',$now+180)===null,
       'approval_deadletter_after_limit');
    ck($out->stats('SYNTHETIC_TENANT_A')===[
        'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>1
    ],'approval_deadletter_preserved');

    $incoming->exec('SET ROLE aia_lab_tenant_a');
    ck((int)$incoming->query("SELECT COUNT(*) FROM auto_ia_lab.event_inbox
      WHERE event_ref LIKE 'SYNTHETIC_CONV_EVENT_%'")->fetchColumn()===1,
      'tenant_a_inbox_sees_only_own_event');
    $incoming->exec('SET ROLE aia_lab_tenant_b');
    ck((int)$incoming->query("SELECT COUNT(*) FROM auto_ia_lab.event_inbox
      WHERE event_ref LIKE 'SYNTHETIC_CONV_EVENT_%'")->fetchColumn()===1,
      'tenant_b_inbox_sees_only_own_event');
    $incoming->exec('RESET ROLE');
    ck((int)$incoming->query("SELECT COUNT(*) FROM auto_ia_lab.event_inbox
      WHERE event_ref LIKE 'SYNTHETIC_CONV_EVENT_%'")->fetchColumn()===2,
      'two_company_total_not_duplicates');
    ck($out->stats('SYNTHETIC_TENANT_B')===[
        'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>0
    ],'tenant_b_outbox_independent');
    echo "AIA_CONV_04_PASS checks={$checks} source=P12_PINNED pg16=1 no_n8n=1 no_ai=1 no_billing=1 NO_GO\n";
} finally {
    if ($incoming->inTransaction()) $incoming->rollBack();
    $incoming->exec('RESET ROLE');
    if ($pdo->inTransaction()) $pdo->rollBack();
    $out->dropTables();
}
