<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/PgCapturedLeadProofLab.php';
require_once __DIR__.'/PgN8nOutboxLab.php';
require_once __DIR__.'/PgOwnerDecisionLab.php';
require_once __DIR__.'/PgOwnerExecutionGuardLab.php';
require_once __DIR__.'/PgCompanyDispatchGuardLab.php';
require_once __DIR__.'/PgSyntheticCompanyTenantBridgeLab.php';
require_once __DIR__.'/PgTenantAuthorityLab.php';

use ImagineAI\Labs\PgN8nOutboxLab;
use ImagineAI\MockOrchestration\CapturedLeadTaskContract;
use PDO;
use Throwable;

/** One CI transaction joins authenticated capture to the existing P16/P6.
 * This does not renew transport admission, create a queue, or call n8n/CRM.
 */
final class PgCapturedLeadStageLab
{
    public function __construct(private PDO $db){new PgSyntheticReplayGate($db);}

    public function createTables():void
    {
        if($this->db->inTransaction())throw new \RuntimeException('LEAD_SCHEMA_NESTED_TX');
        if(!is_string($this->db->query("SELECT to_regclass('auto_ia_lab.lead_ingest_payloads')")
            ->fetchColumn()))throw new \RuntimeException('LEAD_IMMUTABLE_CAPTURE_REQUIRED');
        (new PgSyntheticReplayGate($this->db))->createSchema();
        if(!is_string($this->db->query("SELECT to_regclass('public.aia_lab_n8n_outbox_p6')")
            ->fetchColumn()))(new PgN8nOutboxLab($this->db))->createTables();
        (new PgSyntheticCompanyTenantBridgeLab($this->db))->createTable();
        (new PgOwnerDecisionLab($this->db))->createTables();
        (new PgTenantAuthorityLab($this->db))->createTables();
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_lead_ingest_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_lead_ingest_lab.captured_job_sources(
 tenant_ref text NOT NULL CHECK(tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 event_ref text NOT NULL CHECK(event_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 company_id uuid NOT NULL,
 source_product text NOT NULL CHECK(source_product='imagine-crm'),
 source_payload_hash text NOT NULL CHECK(source_payload_hash ~ '^[a-f0-9]{64}$'),
 task_payload_hash text NOT NULL CHECK(task_payload_hash ~ '^[a-f0-9]{64}$'),
 staged_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 PRIMARY KEY(tenant_ref,event_ref),
 FOREIGN KEY(tenant_ref,source_product,event_ref)
  REFERENCES auto_ia_lab.lead_ingest_payloads(tenant,product,event_ref),
 FOREIGN KEY(tenant_ref,event_ref)
  REFERENCES public.aia_lab_n8n_outbox_p6(tenant_ref,event_ref)
)
SQL);
        $this->db->exec('REVOKE ALL ON SCHEMA aia_lead_ingest_lab FROM PUBLIC');
        $this->db->exec('REVOKE ALL ON aia_lead_ingest_lab.captured_job_sources FROM PUBLIC');
    }

    public function stage(string $company,string $tenant,int $mappingRevision,
        string $canonicalTask,string $ownerDecision,string $ownerSignatureB64,
        string $ownerKeyId,int $now):array
    {
        $task=CapturedLeadTaskContract::decode($canonicalTask);
        if($this->db->inTransaction()||$task===null||$task['company_id']!==$company
            ||$task['tenant_ref']!==$tenant||$mappingRevision<1
            ||$ownerDecision===''||$ownerSignatureB64===''||$ownerKeyId===''||$now<0)
            return self::deny('LEAD_STAGE_ARGUMENTS_OR_OWNER_REQUIRED');
        $proof=new PgCapturedLeadProofLab($this->db);
        $companyGuard=new PgCompanyDispatchGuardLab($this->db);
        try{
            $this->db->beginTransaction();
            $authority=$this->tenantRevision($tenant);
            if($authority===null||!$companyGuard->mappingCurrent($company,$tenant,$mappingRevision))
                return $this->rollback('LEAD_AUTHORITY_OR_MAPPING_DENIED');
            if(!$proof->sourceMatches($company,$tenant,$canonicalTask)
                ||$proof->hasLegacyProof($tenant,$task['event_ref']))
                return $this->rollback('LEAD_CAPTURE_SOURCE_DENIED');
            $checkedNow=time();
            if(!CapturedLeadTaskContract::fresh($task,$checkedNow))
                return $this->rollback('LEAD_TASK_NOT_CURRENT');
            $decision=(new PgOwnerDecisionLab($this->db))->verifyAndConsumeInsideTransaction(
                $company,$tenant,$task['event_ref'],$canonicalTask,$ownerDecision,
                $ownerSignatureB64,$ownerKeyId,$checkedNow);
            if($decision!=='LAB_OWNER_DECISION_UNCOMMITTED')
                return $this->rollback('LEAD_OWNER_DECISION_DENIED');
            // The original P15/P16 adapter remains unchanged. This private
            // claim is reached only after capture, mapping and owner checks.
            if(!$this->claimReplay($canonicalTask,$tenant,$checkedNow))
                return $this->rollback('LEAD_REPLAY_CONFLICT');
            if((new PgN8nOutboxLab($this->db))->enqueueWithinTransaction($canonicalTask)!=='CREATED')
                return $this->rollback('LEAD_OUTBOX_CONFLICT');
            $q=$this->db->prepare(<<<'SQL'
INSERT INTO aia_lead_ingest_lab.captured_job_sources
 (tenant_ref,event_ref,company_id,source_product,source_payload_hash,task_payload_hash)
VALUES(:tenant,:event,CAST(:company AS uuid),'imagine-crm',:source,:task)
ON CONFLICT DO NOTHING RETURNING event_ref
SQL);
            $q->execute([':tenant'=>$tenant,':event'=>$task['event_ref'],':company'=>$company,
                ':source'=>$task['source_payload_hash'],':task'=>hash('sha256',$canonicalTask)]);
            if($q->fetchColumn()===false)return $this->rollback('LEAD_PROVENANCE_CONFLICT');
            // INSERT/constraint/trigger waits may cross the task or owner
            // deadline. All changes above remain tentative until this fence.
            if($this->tenantRevision($tenant)!==$authority
                ||!$companyGuard->mappingCurrent($company,$tenant,$mappingRevision)
                ||!$proof->stagedMatches($company,$tenant,$canonicalTask,hash('sha256',$canonicalTask)))
                return $this->rollback('LEAD_POST_WAIT_AUTHORITY_DENIED');
            $checkedNow=time();
            if(!CapturedLeadTaskContract::fresh($task,$checkedNow)
                ||!(new PgOwnerExecutionGuardLab($this->db))->grantCurrent(
                    $company,$tenant,$task['event_ref'],hash('sha256',$canonicalTask),$checkedNow))
                return $this->rollback('LEAD_POST_WAIT_OWNER_OR_TASK_EXPIRED');
            $this->db->commit();
            return ['decision'=>'LAB_CAPTURED_LEAD_STAGED_NO_DELIVERY',
                'reason'=>'CAPTURE_P16_P6_OWNER_SINGLE_CI_COMMIT',
                'ai_calls'=>0,'service_effects'=>0];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('LEAD_STAGE_DATABASE_ERROR_ROLLED_BACK');
        }
    }

    private function tenantRevision(string $tenant):?int
    {
        $q=$this->db->prepare('SELECT active,revision FROM aia_p34_lab.tenant_policy '
            .'WHERE tenant_ref=:tenant FOR SHARE');
        $q->execute([':tenant'=>$tenant]);$r=$q->fetch(PDO::FETCH_ASSOC);
        return is_array($r)&&in_array($r['active'],[true,1,'1','t'],true)
            &&(int)$r['revision']>0?(int)$r['revision']:null;
    }

    private function claimReplay(string $canonical,string $tenant,int $now):bool
    {
        if(!$this->db->inTransaction())return false;
        $p=CapturedLeadTaskContract::propose($canonical,$tenant,$now)['proposal']??null;
        if(!is_array($p))return false;
        $q=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p16_lab.event_replay(tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
VALUES(:tenant,:event,:request,:nonce,:hash)
ON CONFLICT DO NOTHING RETURNING event_ref
SQL);
        $q->execute([':tenant'=>$tenant,':event'=>$p['event_ref'],':request'=>$p['request_ref'],
            ':nonce'=>$p['nonce_ref'],':hash'=>$p['payload_hash']]);
        return $q->fetchColumn()!==false;
    }

    private function rollback(string $reason):array
    {
        $this->db->rollBack();return self::deny($reason);
    }
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,'ai_calls'=>0,'service_effects'=>0];
    }
}
