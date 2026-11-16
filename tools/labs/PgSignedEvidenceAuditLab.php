<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgAtomicStagingAuditLab.php';
require_once __DIR__.'/PgSyntheticAckEvidenceLab.php';

use PDO;
use Throwable;

/**
 * P39 read-only consistent-snapshot forensic scan of synthetic signed ACKs.
 * Historical verification allows a signer revoked AFTER a legitimate ACK;
 * it never reauthorizes a signer or external delivery.
 */
final class PgSignedEvidenceAuditLab
{
    private PgAtomicStagingAuditLab $base;

    public function __construct(private PDO $db)
    {
        $this->base=new PgAtomicStagingAuditLab($db);
    }

    /** @return array{decision:string,checked:int,issues:array,ai_calls:int,external_calls:int} */
    public function scan():array
    {
        if($this->db->inTransaction())return self::output('INDETERMINATE',0,[
            ['reason'=>'REQUIRE_OWN_READONLY_SNAPSHOT']
        ]);
        try{
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $base=$this->base->scan();
            if($base['decision']!=='LAB_CONSISTENT_NO_AUTHORIZATION'){
                $status=$base['decision']==='INDETERMINATE'
                    ?'INDETERMINATE':'DENY_INCONSISTENT';
                $this->db->commit();
                return self::output($status,0,[['reason'=>'BASE_LEDGER_OUTBOX_INTEGRITY_DENIED']]);
            }
            $rows=$this->db->query(<<<'SQL'
SELECT b.tenant_ref AS out_tenant,b.event_ref AS out_event,
 b.state AS out_state,b.attempts AS out_attempt,
 b.canonical_event AS out_canonical,b.event_hash AS out_hash,
 e.tenant_ref AS signed_tenant,e.event_ref AS signed_event,
 e.worker_ref AS signed_worker,e.attempt AS signed_attempt,
 e.authority_revision AS signed_revision,e.signer_key_id,
 e.key_fingerprint,e.payload_hash AS signed_hash,
 e.canonical_receipt,e.signature_b64,e.receipt_sha256
FROM public.aia_lab_n8n_outbox_p6 b
FULL OUTER JOIN aia_p38_lab.signed_ack_evidence e
 ON b.tenant_ref=e.tenant_ref AND b.event_ref=e.event_ref
ORDER BY COALESCE(b.tenant_ref,e.tenant_ref),
 COALESCE(b.event_ref,e.event_ref)
SQL)->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>2000){
                $this->db->commit();
                return self::output('INDETERMINATE',0,[
                    ['reason'=>'AUDIT_CAPACITY_LIMIT']
                ]);
            }
            $issues=[];
            $keyQuery=$this->db->prepare(<<<'SQL'
SELECT public_key_b64,key_fingerprint
 FROM aia_p37_lab.executor_keys
 WHERE tenant_ref=:tenant AND key_id=:key
SQL);
            foreach($rows as $row){
                $tenant=$row['out_tenant']??$row['signed_tenant'];
                $event=$row['out_event']??$row['signed_event'];
                $reason=null;
                if($row['out_tenant']===null){
                    $reason='ORPHAN_SIGNED_EVIDENCE';
                }elseif($row['out_state']==='ACKED' && $row['signed_tenant']===null){
                    $reason='ACKED_WITHOUT_SIGNED_EVIDENCE';
                }elseif($row['signed_tenant']!==null && $row['out_state']!=='ACKED'){
                    $reason='EVIDENCE_FOR_UNACKED_EVENT';
                }elseif($row['signed_tenant']!==null){
                    $raw=$row['canonical_receipt'];
                    if(!is_string($raw)
                        ||!is_string($row['signature_b64'])
                        ||!is_string($row['receipt_sha256'])
                        ||!hash_equals(hash('sha256',$raw),$row['receipt_sha256'])){
                        $reason='SIGNED_RECEIPT_DIGEST_TAMPERED';
                    }elseif(!hash_equals($row['signed_hash'],$row['out_hash'])
                        ||(int)$row['signed_attempt']!==(int)$row['out_attempt']){
                        $reason='SIGNED_ACK_STATE_MISMATCH';
                    }else{
                        $keyQuery->execute([
                            ':tenant'=>$row['signed_tenant'],
                            ':key'=>$row['signer_key_id']
                        ]);
                        $key=$keyQuery->fetch(PDO::FETCH_ASSOC);
                        $pub=is_array($key)
                            ?base64_decode($key['public_key_b64'],true)
                            :false;
                        if(!is_string($pub)
                            ||strlen($pub)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                            ||!hash_equals(hash('sha256',$pub),$row['key_fingerprint'])
                            ||!hash_equals($row['key_fingerprint'],$key['key_fingerprint'])){
                            $reason='HISTORICAL_SIGNER_KEY_NOT_VERIFIABLE';
                        }else{
                            $json=json_decode($raw,true,5);
                            $issued=is_array($json)?($json['issued_at']??null):null;
                            if(!is_int($issued)){
                                $reason='SIGNATURE_RECEIPT_INVALID';
                            }else{
                                $proof=MockContentBoundExecutorReceiptV2::verify(
                                    $raw,$row['signature_b64'],$pub,
                                    $row['signed_tenant'],$row['signed_event'],
                                    $row['signed_worker'],(int)$row['signed_attempt'],
                                    (int)$row['signed_revision'],$issued+1
                                );
                                if($proof['decision']!=='LAB_SIGNED_CONTENT_BOUND_RECEIPT'
                                    ||!hash_equals(
                                        $row['signed_hash'],$proof['payload_hash']??''
                                    )){
                                    $reason='SIGNATURE_RECEIPT_INVALID';
                                }
                            }
                        }
                    }
                }
                if($reason!==null){
                    // Only IDs and diagnostic categories; never emit raw receipt,
                    // signature, public/private key or canonical event bytes.
                    $issues[]=[
                        'tenant_ref'=>$tenant,
                        'event_ref'=>$event,
                        'reason'=>$reason,
                    ];
                }
            }
            $this->db->commit();
            return self::output($issues?'DENY_INCONSISTENT':
                'LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO',count($rows),$issues);
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::output('INDETERMINATE',0,[
                ['reason'=>'DB_UNAVAILABLE_OR_AUDIT_QUERY_FAILED']
            ]);
        }
    }

    /** @return array{decision:string,checked:int,issues:array,ai_calls:int,external_calls:int} */
    private static function output(string $decision,int $checked,array $issues):array
    {
        return [
            'decision'=>$decision,'checked'=>$checked,'issues'=>$issues,
            'ai_calls'=>0,'external_calls'=>0,
        ];
    }
}
