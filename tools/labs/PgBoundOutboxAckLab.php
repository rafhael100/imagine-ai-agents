<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgBoundOutboxLeaseLab.php';
require_once __DIR__.'/PgCompanyDispatchGuardLab.php';
require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';

use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use PDO;
use Throwable;

/**
 * P30: independently validated, transactional mock ACK.
 * This opt-in lab class does not replace legacy P6 ACK and does not send to n8n.
 * NEVER interpret success as real transport confirmation.
 */
final class PgBoundOutboxAckLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db); // P22 disposable DB identity guard
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    public function acknowledge(string $tenant,string $eventRef,string $worker,
        int $attempt,int $now,?int $trustedRevision=null,
        ?string $expectedSignedPayloadHash=null,
        ?string $trustedExecutorKeyId=null,
        ?string $expectedExecutorKeyFingerprint=null,
        ?string $canonicalSignedReceipt=null,
        ?string $receiptSignatureB64=null,
        ?string $receiptPublicKey=null,
        ?string $companyId=null,?int $companyRevision=null):array
    {
        if(!self::ref($tenant)||!self::ref($eventRef)||!self::ref($worker)
            ||$attempt<1||$attempt>3||$now<0||$now>4_102_444_800
            ||$this->db->inTransaction()
            ||!PgCompanyDispatchGuardLab::validContext($companyId,$companyRevision)) {
            return self::deny('UNTRUSTED_REFERENCE_OR_NESTED_TX');
        }
        if($expectedSignedPayloadHash!==null
            && (preg_match('/\A[a-f0-9]{64}\z/D',$expectedSignedPayloadHash)!==1)){
            return self::deny('SIGNED_PAYLOAD_HASH_INVALID');
        }
        if(($trustedExecutorKeyId===null)!==($expectedExecutorKeyFingerprint===null)
            ||($trustedExecutorKeyId!==null
                && (!self::ref($trustedExecutorKeyId)
                    ||preg_match('/\A[a-f0-9]{64}\z/D',
                        $expectedExecutorKeyFingerprint)!==1))){
            return self::deny('SIGNER_KEY_ARGUMENTS_INVALID');
        }
        $signedEvidenceRequested=$canonicalSignedReceipt!==null
            ||$receiptSignatureB64!==null||$receiptPublicKey!==null;
        if($signedEvidenceRequested
            && ($canonicalSignedReceipt===null||$receiptSignatureB64===null
                ||$receiptPublicKey===null
                ||$trustedRevision===null||$expectedSignedPayloadHash===null
                ||$trustedExecutorKeyId===null
                ||$expectedExecutorKeyFingerprint===null
                ||strlen($receiptPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ||!hash_equals(
                    hash('sha256',$receiptPublicKey),
                    $expectedExecutorKeyFingerprint
                ))){
            return self::deny('SIGNED_EVIDENCE_ARGUMENTS_INVALID');
        }
        try{
            $this->db->beginTransaction();
            // P34: hold shared authority lock through ACK commit. Revocation
            // waits; after it commits the stale revision cannot acknowledge.
            if($trustedRevision!==null){
                if($trustedRevision<1 || !$this->authorityCurrent($tenant,$trustedRevision)){
                    $this->db->rollBack();
                    return self::deny('TENANT_AUTHORITY_REVOKED_OR_STALE');
                }
            }
            // P43: protect lease-time mapping from becoming stale before ACK.
            if($companyId!==null && !(new PgCompanyDispatchGuardLab($this->db))
                ->mappingCurrent($companyId,$tenant,$companyRevision)){
                $this->db->rollBack();
                return self::deny('COMPANY_MAPPING_REVOKED_OR_STALE');
            }
            // P37: an external key lookup is not sufficient. Lock the
            // exact tenant-scoped signer key and compare its fingerprint
            // INSIDE the ACK transaction, serializing concurrent revocation.
            if($trustedExecutorKeyId!==null){
                if(!$this->signerKeyCurrent(
                    $tenant,$trustedExecutorKeyId,$expectedExecutorKeyFingerprint)){
                    $this->db->rollBack();
                    return self::deny('EXECUTOR_SIGNER_REVOKED_OR_MISMATCH');
                }
            }
            $select=$this->db->prepare(<<<'SQL'
SELECT state,attempts,lease_owner,lease_expires,canonical_event,event_hash
 FROM public.aia_lab_n8n_outbox_p6
 WHERE tenant_ref=:tenant AND event_ref=:event
 FOR UPDATE
SQL);
            $select->execute([':tenant'=>$tenant,':event'=>$eventRef]);
            $row=$select->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||$row['state']!=='LEASED'
                ||$row['lease_owner']!==$worker
                ||(int)$row['attempts']!==$attempt
                ||$row['lease_expires']===null
                ||(int)$row['lease_expires']<=$now) {
                $this->db->rollBack();
                return self::deny('LEASE_OR_FENCING_DENIED');
            }
            $proof=$this->db->prepare(<<<'SQL'
SELECT payload_hash,request_ref,nonce_ref
 FROM aia_p16_lab.event_replay
 WHERE tenant_ref=:tenant AND event_ref=:event
 FOR SHARE
SQL);
            $proof->execute([':tenant'=>$tenant,':event'=>$eventRef]);
            $ledger=$proof->fetch(PDO::FETCH_ASSOC);
            $canonical=$row['canonical_event'];
            $decoded=is_string($canonical)
                ? MockN8nOutboxContract::decode($canonical)
                : null;
            if(!is_array($ledger)||!is_array($decoded)
                ||!is_string($row['event_hash'])
                ||$decoded['tenant_ref']!==$tenant
                ||$decoded['event_ref']!==$eventRef
                ||!hash_equals($row['event_hash'],hash('sha256',$canonical))
                ||!hash_equals($row['event_hash'],$ledger['payload_hash'])) {
                $this->db->rollBack();
                return self::deny('CANONICAL_OR_LEDGER_MISMATCH');
            }

            // P43: only P42-originated events with one matching consumed
            // mTLS transport proof can receive company-bound ACK.
            if($companyId!==null && !(new PgCompanyDispatchGuardLab($this->db))
                ->eventMatches($companyId,$tenant,$eventRef,$row['event_hash'])){
                $this->db->rollBack();
                return self::deny('COMPANY_EVENT_TRANSPORT_PROOF_INVALID');
            }

            // P36: signed receipt's exact event digest is compared under
            // the SAME locks and transaction as P30/P34's guarded ACK.
            // This prevents a TOCTOU gap between signature check and commit.
            if($expectedSignedPayloadHash!==null
                && !hash_equals($expectedSignedPayloadHash,$row['event_hash'])){
                $this->db->rollBack();
                return self::deny('SIGNED_RECEIPT_PAYLOAD_HASH_MISMATCH');
            }

            // Recorded timestamp is only for reconstruction of synthetic refs.
            // No historical reconstruction grants live service access.
            $replayed=MockP7ToAutoIaAdapter::propose(
                $canonical,$tenant,(int)$decoded['occurred_at']+5
            );
            $proposal=$replayed['proposal']??null;
            if($replayed['decision']!=='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
                ||!is_array($proposal)
                ||!hash_equals($proposal['request_ref'],$ledger['request_ref'])
                ||!hash_equals($proposal['nonce_ref'],$ledger['nonce_ref'])
                ||!hash_equals($proposal['payload_hash'],$ledger['payload_hash'])) {
                $this->db->rollBack();
                return self::deny('REQUEST_OR_NONCE_MISMATCH');
            }
            // P38: re-verify the Ed25519 V2 signature *inside* the DB
            // transaction after tenant, signer and outbox locks are held.
            // Neither a caller-supplied verified flag nor a standalone
            // precheck may authorize inserting evidence or marking ACKED.
            if($signedEvidenceRequested){
                if(!class_exists(MockContentBoundExecutorReceiptV2::class)){
                    $this->db->rollBack();
                    return self::deny('SIGNED_RECEIPT_VERIFIER_UNAVAILABLE');
                }
                $checked=MockContentBoundExecutorReceiptV2::verify(
                    $canonicalSignedReceipt,$receiptSignatureB64,$receiptPublicKey,
                    $tenant,$eventRef,$worker,$attempt,$trustedRevision,$now
                );
                if($checked['decision']!=='LAB_SIGNED_CONTENT_BOUND_RECEIPT'
                    ||!hash_equals(
                        $checked['payload_hash']??'',$row['event_hash']
                    )){
                    $this->db->rollBack();
                    return self::deny('SIGNED_RECEIPT_VERIFICATION_DENIED');
                }
            }
            $mark=$this->db->prepare(<<<'SQL'
UPDATE public.aia_lab_n8n_outbox_p6
 SET state='ACKED',lease_owner=NULL,lease_expires=NULL
 WHERE tenant_ref=:tenant AND event_ref=:event
   AND state='LEASED' AND lease_owner=:worker
   AND attempts=:attempt AND lease_expires>:now
SQL);
            $mark->execute([
                ':tenant'=>$tenant,':event'=>$eventRef,':worker'=>$worker,
                ':attempt'=>$attempt,':now'=>$now,
            ]);
            if($mark->rowCount()!==1){
                $this->db->rollBack();
                return self::deny('ACK_CONCURRENCY_DENIED');
            }
            // The ACK and its full synthetic signature evidence commit
            // together, or PostgreSQL rolls both back. No repair side effect.
            if($signedEvidenceRequested){
                $e=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p38_lab.signed_ack_evidence
 (tenant_ref,event_ref,worker_ref,attempt,authority_revision,
  signer_key_id,key_fingerprint,payload_hash,canonical_receipt,signature_b64,
  receipt_sha256)
 VALUES (:tenant,:event,:worker,:attempt,:revision,
         :key,:fingerprint,:hash,:receipt,:sig,:receipt_hash)
SQL);
                $e->execute([
                    ':tenant'=>$tenant,':event'=>$eventRef,':worker'=>$worker,
                    ':attempt'=>$attempt,':revision'=>$trustedRevision,
                    ':key'=>$trustedExecutorKeyId,
                    ':fingerprint'=>$expectedExecutorKeyFingerprint,
                    ':hash'=>$row['event_hash'],
                    ':receipt'=>$canonicalSignedReceipt,
                    ':sig'=>$receiptSignatureB64,
                    ':receipt_hash'=>hash('sha256',$canonicalSignedReceipt),
                ]);
                if($e->rowCount()!==1){
                    $this->db->rollBack();
                    return self::deny('ACK_EVIDENCE_NOT_INSERTED');
                }
            }
            $this->db->commit();
            return [
                'decision'=>'LAB_ACKED_NO_DELIVERY_PROOF',
                'reason'=>'REQUIRES_REAL_SIGNED_N8N_RECEIPT_AND_IAM',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('DB_UNAVAILABLE_OR_TRANSACTION_FAILED');
        }
    }

    private function signerKeyCurrent(
        string $tenant,string $keyId,string $fingerprint
    ):bool {
        $stmt=$this->db->prepare(<<<'SQL'
SELECT active,key_fingerprint
 FROM aia_p37_lab.executor_keys
 WHERE tenant_ref=:tenant AND key_id=:key
 FOR SHARE
SQL);
        $stmt->execute([':tenant'=>$tenant,':key'=>$keyId]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($r)
            && in_array($r['active'],[true,1,'1','t'],true)
            && is_string($r['key_fingerprint'])
            && hash_equals($fingerprint,$r['key_fingerprint']);
    }

    private function authorityCurrent(string $tenant,int $revision):bool
    {
        $stmt=$this->db->prepare(<<<'SQL'
SELECT active,revision
 FROM aia_p34_lab.tenant_policy
 WHERE tenant_ref=:tenant FOR SHARE
SQL);
        $stmt->execute([':tenant'=>$tenant]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && in_array($row['active'],[true,1,'1','t'],true)
            && (int)$row['revision']===$revision;
    }

    private static function ref(string $value):bool
    {
        return strlen($value)<=64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$value)===1;
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,
            'ai_calls'=>0,'service_effects'=>0];
    }
}
