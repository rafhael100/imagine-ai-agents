<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/MockContentBoundExecutorReceiptV2.php';

use PDO;
use Throwable;

/** PostgreSQL16 disposable signer registry, never an IAM or key management service. */
final class PgSyntheticSignerRegistryLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p37_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p37_lab.executor_keys (
 tenant_ref VARCHAR(64) NOT NULL
   CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 key_id VARCHAR(64) NOT NULL
   CHECK (key_id ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 public_key_b64 VARCHAR(44) NOT NULL,
 key_fingerprint CHAR(64) NOT NULL
   CHECK (key_fingerprint ~ '^[a-f0-9]{64}$'),
 active BOOLEAN NOT NULL,
 revision INTEGER NOT NULL CHECK (revision BETWEEN 1 AND 1000000),
 PRIMARY KEY (tenant_ref,key_id),
 UNIQUE (tenant_ref,key_fingerprint)
)
SQL);
    }

    public function registerActive(
        string $tenant,string $keyId,string $publicKey
    ):bool {
        if(!self::ref($tenant)||!self::ref($keyId)
            ||strlen($publicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) return false;
        try{
            $stmt=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p37_lab.executor_keys
 (tenant_ref,key_id,public_key_b64,key_fingerprint,active,revision)
 VALUES (:t,:k,:p,:f,true,1) ON CONFLICT DO NOTHING
SQL);
            $stmt->execute([
                ':t'=>$tenant,':k'=>$keyId,
                ':p'=>base64_encode($publicKey),
                ':f'=>hash('sha256',$publicKey),
            ]);
            return $stmt->rowCount()===1;
        }catch(Throwable){return false;}
    }

    /** Atomic replacement; old id never reactivated or silently reused. */
    public function rotate(
        string $tenant,string $oldId,string $newId,string $newPublicKey
    ):bool {
        if(!self::ref($tenant)||!self::ref($oldId)||!self::ref($newId)
            ||$oldId===$newId
            ||strlen($newPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            ||$this->db->inTransaction())return false;
        try{
            $this->db->beginTransaction();
            $stmt=$this->db->prepare(<<<'SQL'
UPDATE aia_p37_lab.executor_keys
 SET active=false,revision=revision+1
 WHERE tenant_ref=:t AND key_id=:old AND active=true AND revision<1000000
SQL);
            $stmt->execute([':t'=>$tenant,':old'=>$oldId]);
            if($stmt->rowCount()!==1){
                $this->db->rollBack();
                return false;
            }
            $insert=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p37_lab.executor_keys
 (tenant_ref,key_id,public_key_b64,key_fingerprint,active,revision)
 VALUES (:t,:new,:pub,:fp,true,1) ON CONFLICT DO NOTHING
SQL);
            $insert->execute([
                ':t'=>$tenant,':new'=>$newId,
                ':pub'=>base64_encode($newPublicKey),
                ':fp'=>hash('sha256',$newPublicKey),
            ]);
            if($insert->rowCount()!==1){
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return false;
        }
    }

    public function revoke(string $tenant,string $keyId):?int
    {
        if(!self::ref($tenant)||!self::ref($keyId))return null;
        try{
            $stmt=$this->db->prepare(<<<'SQL'
UPDATE aia_p37_lab.executor_keys
 SET active=false,revision=revision+1
 WHERE tenant_ref=:t AND key_id=:k AND active=true AND revision<1000000
 RETURNING revision
SQL);
            $stmt->execute([':t'=>$tenant,':k'=>$keyId]);
            $value=$stmt->fetchColumn();
            return $value===false?null:(int)$value;
        }catch(Throwable){return null;}
    }

    /**
     * Preliminary lookup only. The exact fingerprint and active state MUST
     * be rechecked with a P30 FOR SHARE lock inside the ACK transaction.
     */
    public function resolveActive(string $tenant,string $keyId):?string
    {
        if(!self::ref($tenant)||!self::ref($keyId))return null;
        try{
            $stmt=$this->db->prepare(<<<'SQL'
SELECT public_key_b64,key_fingerprint,active
 FROM aia_p37_lab.executor_keys
 WHERE tenant_ref=:t AND key_id=:k
SQL);
            $stmt->execute([':t'=>$tenant,':k'=>$keyId]);
            $r=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)||!in_array($r['active'],[true,1,'1','t'],true))
                return null;
            $public=base64_decode($r['public_key_b64'],true);
            if(!is_string($public)||strlen($public)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ||base64_encode($public)!==$r['public_key_b64']
                ||!hash_equals(hash('sha256',$public),$r['key_fingerprint'])){
                return null;
            }
            return $public;
        }catch(Throwable){return null;}
    }

    /** @return ?array{active:bool,revision:int,key_fingerprint:string} */
    public function snapshot(string $tenant,string $keyId):?array
    {
        if(!self::ref($tenant)||!self::ref($keyId))return null;
        try{
            $stmt=$this->db->prepare('SELECT active,revision,key_fingerprint
              FROM aia_p37_lab.executor_keys WHERE tenant_ref=:t AND key_id=:k');
            $stmt->execute([':t'=>$tenant,':k'=>$keyId]);
            $r=$stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($r)?[
                'active'=>in_array($r['active'],[true,1,'1','t'],true),
                'revision'=>(int)$r['revision'],
                'key_fingerprint'=>$r['key_fingerprint'],
            ]:null;
        }catch(Throwable){return null;}
    }

    private static function ref(string $s):bool
    {
        return strlen($s)<=64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
}

/**
 * Signed V2 receipt + tenant key registry + tenant authority revocation.
 * No production executor identity; NO real n8n delivery is inferred.
 */
final class PgRegistryBoundExecutorAckLab
{
    private PgSyntheticSignerRegistryLab $keys;
    private PgBoundOutboxAckLab $ack;

    public function __construct(PDO $db)
    {
        $this->keys=new PgSyntheticSignerRegistryLab($db);
        $this->ack=new PgBoundOutboxAckLab($db);
    }

    /** @return array<string,mixed> */
    public function acknowledgeWithRegisteredSigner(
        string $receipt,string $signatureB64,string $tenant,
        string $event,string $worker,int $attempt,int $now,
        int $authorityRevision,string $trustedKeyId
    ):array {
        $key=$this->keys->resolveActive($tenant,$trustedKeyId);
        if($key===null){
            return self::deny('SIGNER_NOT_ACTIVE_OR_TRUSTED');
        }
        $verified=MockContentBoundExecutorReceiptV2::verify(
            $receipt,$signatureB64,$key,$tenant,$event,$worker,
            $attempt,$authorityRevision,$now
        );
        if($verified['decision']!=='LAB_SIGNED_CONTENT_BOUND_RECEIPT'){
            return $verified;
        }
        $r=$this->ack->acknowledge(
            $tenant,$event,$worker,$attempt,$now,
            $authorityRevision,$verified['payload_hash'],
            $trustedKeyId,hash('sha256',$key)
        );
        if($r['decision']!=='LAB_ACKED_NO_DELIVERY_PROOF')return $r;
        return [
            'decision'=>'LAB_TRUSTED_SIGNER_ACK_NO_DELIVERY',
            'reason'=>'SIGNER_IS_SYNTHETIC_NO_REAL_IAM',
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,'ai_calls'=>0,'service_effects'=>0];
    }
}
