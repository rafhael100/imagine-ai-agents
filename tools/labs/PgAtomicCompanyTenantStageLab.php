<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticCompanyTenantBridgeLab.php';
require_once __DIR__.'/PgSyntheticAtomicStager.php';
require_once __DIR__.'/PgOwnerDecisionLab.php';
require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use PDO;
use Throwable;

/**
 * P42: consumes ONE P7 synthetic mTLS admission and creates ONE protected
 * P16 replay + P6 outbox event atomically, on a single disposable PG16 DB.
 *
 * Does NOT accept a live mTLS connection, grant real owner authorization,
 * call n8n, expose real customer identities or consume AI credits.
 */
final class PgAtomicCompanyTenantStageLab
{
    private PgSyntheticCompanyTenantBridgeLab $bridge;
    private PgSyntheticAtomicStager $stager;

    public function __construct(private PDO $db)
    {
        $this->bridge=new PgSyntheticCompanyTenantBridgeLab($db);
        $this->stager=new PgSyntheticAtomicStager($db);
    }

    public function createTables():void
    {
        $this->bridge->createTable();
        $this->stager->createTables();
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p42_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p42_lab.consumed_transport_proofs (
 company_id UUID NOT NULL,
 request_id VARCHAR(50) NOT NULL
  CHECK (request_id ~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'),
 nonce VARCHAR(50) NOT NULL
  CHECK (nonce ~ '^SYNTHETIC_[A-Z0-9_]{1,40}$'),
 tenant_ref VARCHAR(64) NOT NULL
  CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 event_ref VARCHAR(64) NOT NULL
  CHECK (event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 payload_hash CHAR(64) NOT NULL
  CHECK (payload_hash ~ '^[a-f0-9]{64}$'),
 observed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY(company_id,nonce),
 UNIQUE(company_id,request_id),
 UNIQUE(tenant_ref,event_ref)
)
SQL);
    }

    public function bridge():PgSyntheticCompanyTenantBridgeLab
    {
        return $this->bridge;
    }

    /**
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $trustedKeys
     * @param array<string,string> $seen
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function stageFromAdmittedTransport(
        string $company,string $tenant,string $request,string $nonce,
        string $transportDigest,int $mappingRevision,int $transportNow,
        string $canonicalEvent,array $envelope,array $trustedPolicy,
        int $eventNow,array $trustedKeys,array &$seen,
        ?string $ownerDecision=null,?string $ownerSignatureB64=null,
        ?string $ownerTrustedKeyId=null
    ):array {
        if($this->db->inTransaction()
            ||($trustedPolicy['tenant_ref']??null)!==$tenant){
            return self::deny('NESTED_TX_OR_TENANT_POLICY_MISMATCH');
        }
        $event=MockN8nOutboxContract::decode($canonicalEvent);
        if(!is_array($event)||$event['tenant_ref']!==$tenant){
            return self::deny('CANONICAL_EVENT_WRONG_TENANT');
        }
        if(($ownerDecision!==null ||$ownerSignatureB64!==null
                ||$ownerTrustedKeyId!==null)
            &&($ownerDecision===null||$ownerSignatureB64===null
                ||$ownerTrustedKeyId===null)){
            return self::deny('INCOMPLETE_OWNER_SERVICE_DECISION');
        }
        $tempSeen=$seen;
        try{
            $this->db->beginTransaction();
            $boundary=$this->bridge->inspectWithinTransaction(
                $company,$tenant,$request,$nonce,$transportDigest,
                $mappingRevision,$transportNow
            );
            if($boundary['decision']!=='LAB_BOUND_UNCOMMITTED_NO_EFFECT'){
                $this->db->rollBack();
                return self::deny('MTLS_COMPANY_TENANT_PROOF_DENIED');
            }
            // P44 owner decision is distinct from read-only mTLS admission.
            // It is checked and consumed under the SAME transaction as P42,
            // so revocation can neither race past nor be silently reused.
            if($ownerDecision!==null){
                $granted=(new PgOwnerDecisionLab($this->db))
                    ->verifyAndConsumeInsideTransaction(
                        $company,$tenant,$event['event_ref'],
                        $canonicalEvent,$ownerDecision,$ownerSignatureB64,
                        $ownerTrustedKeyId,$eventNow
                    );
                if($granted!=='LAB_OWNER_DECISION_UNCOMMITTED'){
                    $this->db->rollBack();
                    return self::deny('OWNER_SERVICE_DECISION_DENIED');
                }
            }
            // An admitted transport proof can be consumed once, regardless
            // of how many different event IDs a caller tries afterwards.
            $consume=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p42_lab.consumed_transport_proofs
(company_id,request_id,nonce,tenant_ref,event_ref,payload_hash)
VALUES(CAST(:company AS uuid),:request,:nonce,:tenant,:event,:hash)
ON CONFLICT DO NOTHING RETURNING event_ref
SQL);
            $consume->execute([
                ':company'=>$company,':request'=>$request,':nonce'=>$nonce,
                ':tenant'=>$tenant,':event'=>$event['event_ref'],
                ':hash'=>hash('sha256',$canonicalEvent)
            ]);
            if($consume->fetchColumn()===false){
                $this->db->rollBack();
                return self::deny('TRANSPORT_PROOF_ALREADY_CONSUMED');
            }
            $staged=$this->stager->stageWithinTransaction(
                $canonicalEvent,$envelope,$trustedPolicy,$eventNow,
                $trustedKeys,$tempSeen
            );
            if($staged['decision']!=='LAB_STAGED_UNCOMMITTED_NO_DELIVERY'){
                $this->db->rollBack();
                return self::deny('EVENT_OR_OUTBOX_DENIED_ROLLED_BACK');
            }
            $this->db->commit();
            $seen=$tempSeen;
            return [
                'decision'=>'LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY',
                'reason'=>'P7_PROOF_P16_REPLAY_P6_OUTBOX_SINGLE_CI_COMMIT',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('ATOMIC_BRIDGE_DATABASE_ERROR');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,
            'ai_calls'=>0,'service_effects'=>0];
    }
}
