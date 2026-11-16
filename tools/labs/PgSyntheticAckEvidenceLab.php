<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticSignerRegistryLab.php';

use PDO;

/**
 * P38 disposable PostgreSQL evidence table. Full receipt and signature contain
 * synthetic identifiers only; no private key, customer PII or external proof.
 */
final class PgSyntheticAckEvidenceLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p38_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p38_lab.signed_ack_evidence (
 tenant_ref VARCHAR(64) NOT NULL
   CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 event_ref VARCHAR(64) NOT NULL
   CHECK (event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 worker_ref VARCHAR(64) NOT NULL
   CHECK (worker_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 attempt INTEGER NOT NULL CHECK (attempt BETWEEN 1 AND 3),
 authority_revision INTEGER NOT NULL CHECK (authority_revision BETWEEN 1 AND 1000000),
 signer_key_id VARCHAR(64) NOT NULL
   CHECK (signer_key_id ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 key_fingerprint CHAR(64) NOT NULL CHECK (key_fingerprint ~ '^[a-f0-9]{64}$'),
 payload_hash CHAR(64) NOT NULL CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 canonical_receipt VARCHAR(1024) NOT NULL,
 signature_b64 VARCHAR(88) NOT NULL CHECK (length(signature_b64)=88),
 receipt_sha256 CHAR(64) NOT NULL CHECK (receipt_sha256 ~ '^[a-f0-9]{64}$'),
 recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY (tenant_ref,event_ref),
 FOREIGN KEY (tenant_ref,event_ref)
   REFERENCES public.aia_lab_n8n_outbox_p6 (tenant_ref,event_ref)
)
SQL);
    }

    public function dropTables():void
    {
        $this->db->exec('DROP TABLE IF EXISTS aia_p38_lab.signed_ack_evidence');
    }

    public function countEvidence():int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM aia_p38_lab.signed_ack_evidence')
            ->fetchColumn();
    }
}

/**
 * Opt-in P38 facade uses trusted, tenant-scoped registry lookup, verifies
 * Ed25519 V2, then P30 re-verifies under locks and inserts signed evidence
 * in the SAME transaction as ACK. Never sends to n8n.
 */
final class PgAtomicSignedEvidenceAckLab
{
    private PgSyntheticSignerRegistryLab $registry;
    private PgBoundOutboxAckLab $boundAck;

    public function __construct(PDO $db)
    {
        $this->registry=new PgSyntheticSignerRegistryLab($db);
        $this->boundAck=new PgBoundOutboxAckLab($db);
    }

    /** @return array<string,mixed> */
    public function acknowledgeWithAtomicEvidence(
        string $canonical,string $signatureB64,string $tenant,string $event,
        string $worker,int $attempt,int $now,int $trustedAuthorityRevision,
        string $trustedSignerKeyId
    ):array {
        $key=$this->registry->resolveActive($tenant,$trustedSignerKeyId);
        if($key===null) return self::deny('NO_ACTIVE_SIGNER_KEY');
        $v=MockContentBoundExecutorReceiptV2::verify(
            $canonical,$signatureB64,$key,$tenant,$event,$worker,
            $attempt,$trustedAuthorityRevision,$now
        );
        if($v['decision']!=='LAB_SIGNED_CONTENT_BOUND_RECEIPT')return $v;
        // This first validation is only an early rejection optimization.
        // P30 verifies the same signed bytes again with the key/tenant/outbox
        // rows locked until ACK and evidence commit together.
        $r=$this->boundAck->acknowledge(
            $tenant,$event,$worker,$attempt,$now,$trustedAuthorityRevision,
            $v['payload_hash'],$trustedSignerKeyId,hash('sha256',$key),
            $canonical,$signatureB64,$key
        );
        if($r['decision']!=='LAB_ACKED_NO_DELIVERY_PROOF')return $r;
        return [
            'decision'=>'LAB_ATOMIC_SIGNED_EVIDENCE_ACK',
            'reason'=>'NO_REAL_N8N_EXECUTION_OR_IAM',
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,'ai_calls'=>0,'service_effects'=>0];
    }
}
