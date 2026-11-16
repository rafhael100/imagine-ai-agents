<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';

use PDO;
use Throwable;

/**
 * P44 CI-only, policy-owner decision distinct from transport authentication.
 *
 * The original CENTRAL-AUTO P7 mTLS request grants read-only access to a
 * synthetic CRM state, NEVER permission to stage an AI Agents operation.
 * A separate, signed, revocable owner decision is mandatory through the P44
 * facade and is verified/consumed in the SAME P42 stage transaction.
 */
final class PgOwnerDecisionLab
{
    private const FIELDS=[
        'schema','issuer','audience','company_id','tenant_ref','event_ref',
        'grant_ref','operation','owner_revision','payload_hash','issued_at',
        'expires_at','decision','key_id'
    ];

    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p44_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p44_lab.owner_keys(
 company_id UUID NOT NULL,
 key_id VARCHAR(64) NOT NULL
   CHECK(key_id ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 public_key_b64 VARCHAR(44) NOT NULL,
 fingerprint CHAR(64) NOT NULL CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 active BOOLEAN NOT NULL,
 revision INTEGER NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 PRIMARY KEY(company_id,key_id),
 UNIQUE(company_id,fingerprint)
)
SQL);
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p44_lab.consumed_owner_grants(
 company_id UUID NOT NULL,
 grant_ref VARCHAR(64) NOT NULL
   CHECK(grant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 tenant_ref VARCHAR(64) NOT NULL
   CHECK(tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 event_ref VARCHAR(64) NOT NULL
   CHECK(event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 payload_hash CHAR(64) NOT NULL CHECK(payload_hash ~ '^[a-f0-9]{64}$'),
 signed_decision_sha256 CHAR(64) NOT NULL
   CHECK(signed_decision_sha256 ~ '^[a-f0-9]{64}$'),
 signer_key_id VARCHAR(64) NOT NULL,
 owner_revision INTEGER NOT NULL CHECK(owner_revision BETWEEN 1 AND 1000000),
 canonical_decision VARCHAR(1024) NOT NULL,
 signature_b64 VARCHAR(88) NOT NULL CHECK(length(signature_b64)=88),
 observed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY(company_id,grant_ref),
 UNIQUE(tenant_ref,event_ref)
)
SQL);
    }

    public function registerKey(string $company,string $keyId,string $pub):bool
    {
        if(!self::company($company)||!self::ref($keyId)
            ||strlen($pub)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return false;
        try{
            $q=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p44_lab.owner_keys
(company_id,key_id,public_key_b64,fingerprint,active,revision)
VALUES(CAST(:company AS uuid),:key,:pub,:fp,true,1)
ON CONFLICT DO NOTHING
SQL);
            $q->execute([
                ':company'=>$company,':key'=>$keyId,
                ':pub'=>base64_encode($pub),':fp'=>hash('sha256',$pub)
            ]);
            return $q->rowCount()===1;
        }catch(Throwable){return false;}
    }

    public function revokeKey(string $company,string $keyId):?int
    {
        if(!self::company($company)||!self::ref($keyId))return null;
        try{
            $q=$this->db->prepare(<<<'SQL'
UPDATE aia_p44_lab.owner_keys
SET active=false,revision=revision+1
WHERE company_id=CAST(:company AS uuid) AND key_id=:key AND active=true
  AND revision<1000000
RETURNING revision
SQL);
            $q->execute([':company'=>$company,':key'=>$keyId]);
            $v=$q->fetchColumn();
            return $v===false?null:(int)$v;
        }catch(Throwable){return null;}
    }

    /**
     * Must be called from the exact transaction which also consumes mTLS
     * proof and writes P16 replay + P6 outbox. Returns a LAB marker only.
     */
    public function verifyAndConsumeInsideTransaction(
        string $company,string $tenant,string $event,string $canonicalEvent,
        string $decisionCanonical,string $signatureB64,string $trustedKeyId,
        int $now
    ):string {
        if(!$this->db->inTransaction()
            ||!self::company($company)||!self::ref($tenant)
            ||!self::ref($event)||!self::ref($trustedKeyId)
            ||$now<0||$now>4102444800
            ||strlen($decisionCanonical)<150
            ||strlen($decisionCanonical)>1024
            ||strlen($signatureB64)!==88) return 'DENY_OWNER_ARGUMENTS';
        try{
            $d=json_decode($decisionCanonical,true,5,JSON_THROW_ON_ERROR);
            if(!is_array($d)||array_keys($d)!==self::FIELDS)return 'DENY_OWNER_SCHEMA';
            $encoded=json_encode($d,JSON_THROW_ON_ERROR
                |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(!hash_equals($decisionCanonical,$encoded))return 'DENY_OWNER_CANONICAL';
            foreach(['schema','issuer','audience','company_id','tenant_ref',
                     'event_ref','grant_ref','operation','payload_hash',
                     'decision','key_id'] as $name){
                if(!is_string($d[$name]))return 'DENY_OWNER_TYPE';
            }
            foreach(['owner_revision','issued_at','expires_at'] as $name){
                if(!is_int($d[$name]))return 'DENY_OWNER_TYPE';
            }
            if($d['schema']!=='AIA_OWNER_DECISION_V1'
                ||$d['issuer']!=='SYNTHETIC_CRM_POLICY_OWNER'
                ||$d['audience']!=='imagine-ai-agents'
                ||$d['operation']!=='run.status.project'
                ||$d['decision']!=='ALLOW_NO_AI_LAB'
                ||$d['company_id']!==$company
                ||$d['tenant_ref']!==$tenant
                ||$d['event_ref']!==$event
                ||$d['key_id']!==$trustedKeyId
                ||!self::ref($d['grant_ref'])
                ||preg_match('/\A[a-f0-9]{64}\z/D',$d['payload_hash'])!==1
                ||!hash_equals(hash('sha256',$canonicalEvent),$d['payload_hash'])){
                return 'DENY_OWNER_SCOPE_OR_HASH';
            }
            if($d['owner_revision']<1
                ||$d['issued_at']>$now+5
                ||$d['issued_at']<$now-60
                ||$d['expires_at']<=$now
                ||$d['expires_at']<=$d['issued_at']
                ||$d['expires_at']-$d['issued_at']>120){
                return 'DENY_OWNER_EXPIRED';
            }
            $sig=base64_decode($signatureB64,true);
            if(!is_string($sig)||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($sig)!==$signatureB64){
                return 'DENY_OWNER_SIGNATURE';
            }
            // Lock owner key for the remainder of the outer P42 transaction:
            // a revocation UPDATE cannot commit between grant check and stage.
            $q=$this->db->prepare(<<<'SQL'
SELECT public_key_b64,fingerprint,active,revision
FROM aia_p44_lab.owner_keys
WHERE company_id=CAST(:company AS uuid) AND key_id=:key FOR SHARE
SQL);
            $q->execute([':company'=>$company,':key'=>$trustedKeyId]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)
                ||!in_array($row['active'],[true,1,'1','t'],true)
                ||(int)$row['revision']!==$d['owner_revision']){
                return 'DENY_OWNER_REVOKED_OR_STALE';
            }
            $pub=base64_decode($row['public_key_b64'],true);
            if(!is_string($pub)
                ||strlen($pub)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ||!hash_equals(hash('sha256',$pub),$row['fingerprint'])
                ||!sodium_crypto_sign_verify_detached($sig,$decisionCanonical,$pub)){
                return 'DENY_OWNER_SIGNATURE';
            }
            $insert=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p44_lab.consumed_owner_grants
(company_id,grant_ref,tenant_ref,event_ref,payload_hash,
 signed_decision_sha256,signer_key_id,owner_revision,canonical_decision,signature_b64)
VALUES(CAST(:company AS uuid),:grant,:tenant,:event,:hash,:decision_hash,:key,:rev,:raw,:sig)
ON CONFLICT DO NOTHING RETURNING grant_ref
SQL);
            $insert->execute([
                ':company'=>$company,':grant'=>$d['grant_ref'],
                ':tenant'=>$tenant,':event'=>$event,
                ':hash'=>$d['payload_hash'],
                ':decision_hash'=>hash('sha256',$decisionCanonical),
                ':key'=>$trustedKeyId,
                ':rev'=>$d['owner_revision'],
                ':raw'=>$decisionCanonical,
                ':sig'=>$signatureB64
            ]);
            return $insert->fetchColumn()===false
                ?'DENY_OWNER_GRANT_ALREADY_CONSUMED'
                :'LAB_OWNER_DECISION_UNCOMMITTED';
        }catch(Throwable){
            return 'DENY_OWNER_VERIFY_OR_DB_ERROR';
        }
    }

    private static function company(string $v):bool {
        return preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$v
        )===1;
    }
    private static function ref(string $v):bool {
        return strlen($v)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;
    }
}

/** No optional owner-grant arguments: absence is always DENY. */
final class PgOwnerDecisionBoundStageLab
{
    private PgAtomicCompanyTenantStageLab $atomic;
    public function __construct(PDO $db) {
        $this->atomic=new PgAtomicCompanyTenantStageLab($db);
    }
    /** @return array<string,mixed> */
    public function stage(
        string $company,string $tenant,string $request,string $nonce,
        string $transportDigest,int $mappingRevision,int $transportNow,
        string $canonicalEvent,array $envelope,array $trustedPolicy,
        int $eventNow,array $trustedKeys,array &$seen,
        string $ownerDecision,string $ownerSignatureB64,string $ownerKeyId
    ):array {
        if($ownerDecision===''||$ownerSignatureB64===''||$ownerKeyId===''){
            return ['decision'=>'DENY','reason'=>'OWNER_DECISION_REQUIRED',
                'ai_calls'=>0,'service_effects'=>0];
        }
        return $this->atomic->stageFromAdmittedTransport(
            $company,$tenant,$request,$nonce,$transportDigest,
            $mappingRevision,$transportNow,$canonicalEvent,
            $envelope,$trustedPolicy,$eventNow,$trustedKeys,$seen,
            $ownerDecision,$ownerSignatureB64,$ownerKeyId
        );
    }
    public static function operationalReadiness():array {
        return [
            'decision'=>'NO_GO',
            'reason'=>'SYNTHETIC_OWNER_KEY_NOT_REAL_CRM_IAM',
        ];
    }
}
