<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';

use PDO;
use Throwable;

/**
 * P41: CI-only mapping between a P7-admitted mTLS CRM/Portal company UUID
 * and a separately approved synthetic AI Agents tenant. Only a PRE-FLIGHT
 * read; it does not create an event, lease, ACK, credential or AI call.
 *
 * Never use the resulting decision as bearer authorization. Real owner-service
 * IAM and an atomic binding to the event admission transaction remain absent.
 */
final class PgSyntheticCompanyTenantBridgeLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function createTable():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p41_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p41_lab.company_tenant_links (
 company_id UUID NOT NULL PRIMARY KEY,
 tenant_ref VARCHAR(64) NOT NULL UNIQUE
   CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 source_contract VARCHAR(40) NOT NULL
   CHECK (source_contract='CENTRAL_AUTO_MTLS_REQUEST_V1'),
 provenance VARCHAR(40) NOT NULL
   CHECK (provenance='SYNTHETIC_MAPPING_APPROVED_IN_CI'),
 active BOOLEAN NOT NULL,
 revision INTEGER NOT NULL CHECK (revision BETWEEN 1 AND 1000000)
)
SQL);
    }

    public function seedLabBinding(string $company,string $tenant):bool
    {
        if(!self::company($company)||!self::ref($tenant))return false;
        try{
            $stmt=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p41_lab.company_tenant_links
(company_id,tenant_ref,source_contract,provenance,active,revision)
VALUES (CAST(:company AS uuid),:tenant,'CENTRAL_AUTO_MTLS_REQUEST_V1',
        'SYNTHETIC_MAPPING_APPROVED_IN_CI',true,1)
ON CONFLICT DO NOTHING
SQL);
            $stmt->execute([':company'=>$company,':tenant'=>$tenant]);
            return $stmt->rowCount()===1;
        }catch(Throwable){return false;}
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    public function inspect(string $company,string $tenant,string $request,
        string $nonce,string $digest,int $revision,int $now):array
    {
        if($this->db->inTransaction())return self::deny('NESTED_BRIDGE_TX_DENIED');
        try{
            $this->db->beginTransaction();
            $r=$this->inspectWithinTransaction(
                $company,$tenant,$request,$nonce,$digest,$revision,$now
            );
            if($r['decision']!=='LAB_BOUND_UNCOMMITTED_NO_EFFECT'){
                $this->db->rollBack();
                return $r;
            }
            $this->db->commit();
            return [
                'decision'=>'LAB_MATCHED_BOUNDARY_NO_EXECUTION',
                'reason'=>'NO_REAL_BRIDGE_IAM_OR_ATOMIC_STAGE',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('BRIDGE_DATABASE_UNAVAILABLE');
        }
    }

    /**
     * P42: Caller owns the transaction. FOR SHARE locks on mapping and
     * P7 mTLS nonce persist UNTIL caller commits P16+P6+proof-consumption.
     * On error, caller MUST rollback. No effect is performed here.
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function inspectWithinTransaction(string $company,string $tenant,
        string $request,string $nonce,string $digest,int $revision,int $now):array
    {
        if(!self::company($company)||!self::ref($tenant)
            ||!self::p7ref($request)||!self::p7ref($nonce)
            ||preg_match('/\A[a-f0-9]{64}\z/D',$digest)!==1
            ||$revision<1||$now<0||$now>4_102_444_800
            ||!$this->db->inTransaction()){
            return self::deny('UNTRUSTED_BRIDGE_PARAMETERS_OR_NO_TX');
        }
        try{
            $binding=$this->db->prepare(<<<'SQL'
SELECT tenant_ref,active,revision
FROM aia_p41_lab.company_tenant_links
WHERE company_id=CAST(:company AS uuid) FOR SHARE
SQL);
            $binding->execute([':company'=>$company]);
            $r=$binding->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)||$r['tenant_ref']!==$tenant
                ||!in_array($r['active'],[true,1,'1','t'],true)
                ||(int)$r['revision']!==$revision){
                return self::deny('COMPANY_TENANT_LINK_MISSING_REVOKED_OR_STALE');
            }
            $proof=$this->db->prepare(<<<'SQL'
SELECT issued_at FROM central_auto_lab.mtls_nonce_ledger
WHERE issuer='portal-automation-admin'
 AND audience='crm-automation-state'
 AND company_id=CAST(:company AS uuid)
 AND request_id=:request AND nonce=:nonce AND request_digest=:digest
FOR SHARE
SQL);
            $proof->execute([
                ':company'=>$company,':request'=>$request,
                ':nonce'=>$nonce,':digest'=>$digest
            ]);
            $issued=$proof->fetchColumn();
            if($issued===false || (int)$issued<$now-120 || (int)$issued>$now+5){
                return self::deny('MTLS_LEDGER_PROOF_MISSING_OR_STALE');
            }
            return [
                'decision'=>'LAB_BOUND_UNCOMMITTED_NO_EFFECT',
                'reason'=>'CALLER_MUST_COMMIT_ATOMIC_STAGE',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            return self::deny('BRIDGE_DATABASE_UNAVAILABLE');
        }
    }

    public function revoke(string $company):?int
    {
        if(!self::company($company))return null;
        try{
            $stmt=$this->db->prepare(<<<'SQL'
UPDATE aia_p41_lab.company_tenant_links
SET active=false,revision=revision+1
WHERE company_id=CAST(:company AS uuid) AND active=true
  AND revision<1000000
RETURNING revision
SQL);
            $stmt->execute([':company'=>$company]);
            $r=$stmt->fetchColumn();
            return $r===false?null:(int)$r;
        }catch(Throwable){return null;}
    }

    /** @return ?array{active:bool,tenant_ref:string,revision:int} */
    public function snapshot(string $company):?array
    {
        if(!self::company($company))return null;
        try{
            $s=$this->db->prepare('SELECT active,tenant_ref,revision
                FROM aia_p41_lab.company_tenant_links
                WHERE company_id=CAST(:company AS uuid)');
            $s->execute([':company'=>$company]);
            $r=$s->fetch(PDO::FETCH_ASSOC);
            return is_array($r)?[
                'active'=>in_array($r['active'],[true,1,'1','t'],true),
                'tenant_ref'=>$r['tenant_ref'],
                'revision'=>(int)$r['revision']
            ]:null;
        }catch(Throwable){return null;}
    }

    private static function company(string $s):bool
    {
        return preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$s
        )===1;
    }

    private static function ref(string $s):bool
    {
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }

    private static function p7ref(string $s):bool
    {
        return strlen($s)<=50
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,40}\z/D',$s)===1;
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,
            'ai_calls'=>0,'service_effects'=>0];
    }
}
