<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSignedEvidenceAuditLab.php';
require_once __DIR__.'/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\PgAtomicSignedEvidenceAckLab;
use ImagineAI\Convergence\PgSignedEvidenceAuditLab;
use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P39_CI_ONLY');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cycle=new PgRevocationBoundCycleLab($db);
$cycle->createTables();
$db->exec('TRUNCATE TABLE aia_p34_lab.tenant_policy');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$keys=new PgSyntheticSignerRegistryLab($db);
$keys->createTables();
$db->exec('TRUNCATE TABLE aia_p37_lab.executor_keys');
$evidence=new PgSyntheticAckEvidenceLab($db);
$evidence->createTables();
$ack=new PgAtomicSignedEvidenceAckLab($db);
$auditor=new PgSignedEvidenceAuditLab($db);
$pair=sodium_crypto_sign_keypair();
$secret=sodium_crypto_sign_secretkey($pair);
$public=sodium_crypto_sign_publickey($pair);
$now=P18Fixture::NOW;
$checks=0;
function p39Check(bool $ok,string $label):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P39_FAIL_'.$label);
}
function p39Stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):void {
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$cycle->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
    p39Check($r['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','stage_'.$event);
}
function p39Hash(PDO $db,string $tenant,string $event):string {
    $s=$db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay
      WHERE tenant_ref=:t AND event_ref=:e');
    $s->execute([':t'=>$tenant,':e'=>$event]);
    $r=$s->fetchColumn();
    if(!is_string($r))throw new RuntimeException('P39_DIGEST_MISSING');
    return $r;
}
function p39Receipt(string $secret,string $tenant,string $event,
    string $worker,int $revision,string $digest,int $now):array {
    $v=[
        'schema'=>'AIA_N8N_ACK_RECEIPT_V2',
        'issuer'=>'SYNTHETIC_N8N_EXECUTOR','audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$worker,
        'attempt'=>1,'authority_revision'=>$revision,
        'payload_hash'=>$digest,'issued_at'=>$now,'expires_at'=>$now+60,
        'result'=>'NO_AI_COMPLETED_LAB',
    ];
    $raw=json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|
        JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
function p39Reason(array $result,string $reason):bool {
    return in_array($reason,array_column($result['issues'],'reason'),true);
}
try{
    $a='SYNTHETIC_TENANT_A';$b='SYNTHETIC_TENANT_B';
    $key='SYNTHETIC_P39_KEY';
    p39Check($cycle->authority()->seedActive($a),'seed_A_authority');
    p39Check($cycle->authority()->seedActive($b),'seed_B_authority');
    p39Check($keys->registerActive($a,$key,$public),'seed_A_key');
    p39Check($keys->registerActive($b,$key,$public),'seed_B_key');
    foreach([$a,$b] as $tenant) {
        $event=$tenant===$a?'SYNTHETIC_P39_EVENT_A':'SYNTHETIC_P39_EVENT_B';
        $worker=$tenant===$a?'SYNTHETIC_P39_WORKER_A':'SYNTHETIC_P39_WORKER_B';
        p39Stage($cycle,$tenant,$event);
        p39Check($cycle->reserve($tenant,$worker,$now,1)['attempt']===1,
            'reserve_'.$tenant);
        [$raw,$sig]=p39Receipt($secret,$tenant,$event,$worker,1,
            p39Hash($db,$tenant,$event),$now);
        $result=$ack->acknowledgeWithAtomicEvidence(
            $raw,$sig,$tenant,$event,$worker,1,$now+1,1,$key
        );
        p39Check($result['decision']==='LAB_ATOMIC_SIGNED_EVIDENCE_ACK',
            'signed_ack_'.$tenant);
    }

    $baseline=$auditor->scan();
    p39Check($baseline['decision']==='LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO'
        &&$baseline['checked']===2,'baseline_historical_consistent');
    p39Check($baseline['ai_calls']===0 &&$baseline['external_calls']===0,
        'no_api_activity');

    // A signer revoked AFTER ACK remains a valid historical signer.
    p39Check($keys->revoke($a,$key)===2,'revoke_A_after_ack');
    p39Check($auditor->scan()['decision']
        ==='LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO',
        'historical_receipt_survives_later_revocation');

    $stored=$db->query("SELECT signature_b64,receipt_sha256,key_fingerprint
        FROM aia_p38_lab.signed_ack_evidence
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'")->fetch(PDO::FETCH_ASSOC);
    p39Check(is_array($stored),'baseline_metadata_read');

    $db->exec("UPDATE aia_p38_lab.signed_ack_evidence
        SET signature_b64='".str_repeat('A',88)."'
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'");
    p39Check(p39Reason($auditor->scan(),'SIGNATURE_RECEIPT_INVALID'),
        'tampered_signature_detected');
    $db->prepare("UPDATE aia_p38_lab.signed_ack_evidence SET signature_b64=:sig
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'")->execute([
        ':sig'=>$stored['signature_b64']
    ]);

    $db->exec("UPDATE aia_p38_lab.signed_ack_evidence
        SET receipt_sha256='".str_repeat('a',64)."'
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'");
    p39Check(p39Reason($auditor->scan(),'SIGNED_RECEIPT_DIGEST_TAMPERED'),
        'receipt_digest_tamper_detected');
    $db->prepare("UPDATE aia_p38_lab.signed_ack_evidence SET receipt_sha256=:hash
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'")->execute([
        ':hash'=>$stored['receipt_sha256']
    ]);

    $db->exec("UPDATE aia_p38_lab.signed_ack_evidence
        SET key_fingerprint='".str_repeat('b',64)."'
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'");
    p39Check(p39Reason($auditor->scan(),'HISTORICAL_SIGNER_KEY_NOT_VERIFIABLE'),
        'changed_signer_fingerprint_detected');
    $db->prepare("UPDATE aia_p38_lab.signed_ack_evidence SET key_fingerprint=:hash
        WHERE event_ref='SYNTHETIC_P39_EVENT_A'")->execute([
        ':hash'=>$stored['key_fingerprint']
    ]);

    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6
        SET state='PENDING' WHERE event_ref='SYNTHETIC_P39_EVENT_A'");
    p39Check(p39Reason($auditor->scan(),'EVIDENCE_FOR_UNACKED_EVENT'),
        'evidence_wrong_state_detected');
    $db->exec("UPDATE public.aia_lab_n8n_outbox_p6
        SET state='ACKED' WHERE event_ref='SYNTHETIC_P39_EVENT_A'");
    p39Check($auditor->scan()['decision']
        ==='LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO',
        'restored_before_missing_evidence');

    $db->exec("DELETE FROM aia_p38_lab.signed_ack_evidence
        WHERE event_ref='SYNTHETIC_P39_EVENT_B'");
    $missing=$auditor->scan();
    p39Check(p39Reason($missing,'ACKED_WITHOUT_SIGNED_EVIDENCE'),
        'acked_without_evidence_detected');
    $printed=json_encode($missing,JSON_THROW_ON_ERROR);
    p39Check(!str_contains($printed,$stored['signature_b64'])
        &&!str_contains($printed,'canonical_receipt')
        &&!str_contains($printed,'public_key_b64'),'findings_do_not_expose_signatures');

    $evidence->dropTables();
    p39Check($auditor->scan()['decision']==='INDETERMINATE',
        'missing_table_never_returns_clean');

    echo "AIA_P39_HISTORICAL_ACK_AUDIT_PASS checks={$checks} tampering_detected=1 revoked_history_allowed=1 api_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $evidence->dropTables();
    $outbox->dropTables();
}
