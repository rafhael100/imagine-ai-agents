<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/LabAutomationTaskContract.php';

use ImagineAI\MockOrchestration\CapturedLeadTaskContract;
use PDO;
use Throwable;

/** Read-only source proof. A current ingress link is distinct from owner authority.
 * Captured fingerprint/key/revisions remain historical provenance; neither the
 * original short-lived envelope nor an old transport key is renewed here.
 */
final class PgCapturedLeadProofLab
{
    public function __construct(private PDO $db){new PgSyntheticReplayGate($db);}

    public function sourceMatches(string $company,string $tenant,string $canonical,
        bool $current=true):bool
    {
        $task=CapturedLeadTaskContract::decode($canonical);
        if(!$this->db->inTransaction()||$task===null
            ||$task['company_id']!==$company||$task['tenant_ref']!==$tenant)return false;
        try{
            $sql=<<<'SQL'
SELECT c.company_id::text,c.event_id::text,c.lead_id::text,c.payload_hash,
 convert_from(c.payload_bytes,'UTF8') AS payload,
 c.operation,c.state,l.active,l.revision,
 (SELECT count(*)=5 AND count(DISTINCT j.key)=5
    AND array_agg(j.key ORDER BY j.key)=ARRAY['action','company_id','event_id','lead_id','schema']
    AND bool_and(json_typeof(j.value)='string')
  FROM json_each(convert_from(c.payload_bytes,'UTF8')::json) j) AS payload_closed
FROM auto_ia_lab.lead_ingest_payloads c
JOIN auto_ia_lab.event_inbox i
 ON i.tenant=c.tenant AND i.product=c.product AND i.event_ref=c.event_ref
 AND i.operation=c.operation AND i.nonce_ref=c.nonce_ref
 AND i.payload_hash=c.payload_hash AND i.issued_epoch=c.issued_epoch
 AND i.expires_epoch=c.expires_epoch
JOIN auto_ia_lab.company_links l ON l.company_id=c.company_id AND l.tenant=c.tenant
WHERE c.company_id=CAST(:company AS uuid) AND c.tenant=:tenant
 AND c.product='imagine-crm' AND c.event_ref=:event
 AND EXISTS(SELECT 1 FROM auto_ia_lab.service_event_audit a
  WHERE a.company_id=c.company_id AND a.event_ref=c.event_ref
   AND a.outcome='LAB_RECEIVED_NO_DELIVERY' AND a.grant_revision=c.grant_revision)
SQL;
            $q=$this->db->prepare($sql.($current?' FOR SHARE OF i,l':''));
            $q->execute([':company'=>$company,':tenant'=>$tenant,':event'=>$task['event_ref']]);
            $r=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)||$r['company_id']!==$company||$r['lead_id']!==$task['lead_id']
                ||$r['operation']!=='lead.ingest'||$r['state']!=='CAPTURED_NOT_DISPATCHED'
                ||!self::truth($r['payload_closed'])
                ||($current&&(!self::truth($r['active'])||(int)$r['revision']<1))
                ||!is_string($r['payload'])||strlen($r['payload'])>2048
                ||!hash_equals(hash('sha256',$r['payload']),$task['source_payload_hash'])
                ||!hash_equals($r['payload_hash'],$task['source_payload_hash'])
                ||$task['event_ref']!=='SYNTHETIC_'.strtoupper(str_replace('-','',$r['event_id'])))
                return false;
            $p=json_decode($r['payload'],true,4,JSON_THROW_ON_ERROR);
            return is_array($p)&&($p['schema']??null)==='CRM_LEAD_EVENT_V1'
                &&($p['action']??null)==='lead.created'&&($p['company_id']??null)===$company
                &&is_string($p['event_id']??null)&&strtolower($p['event_id'])===$r['event_id']
                &&is_string($p['lead_id']??null)&&strtolower($p['lead_id'])===$r['lead_id'];
        }catch(Throwable){return false;}
    }

    public function stagedMatches(string $company,string $tenant,string $canonical,
        string $taskHash,bool $current=true):bool
    {
        $task=CapturedLeadTaskContract::decode($canonical);
        if($task===null||!hash_equals(hash('sha256',$canonical),$taskHash)
            ||!$this->sourceMatches($company,$tenant,$canonical,$current))return false;
        try{
            $q=$this->db->prepare('SELECT company_id::text,source_product,source_payload_hash,'
                .'task_payload_hash FROM aia_lead_ingest_lab.captured_job_sources '
                .'WHERE tenant_ref=:tenant AND event_ref=:event'.($current?' FOR SHARE':''));
            $q->execute([':tenant'=>$tenant,':event'=>$task['event_ref']]);
            $r=$q->fetch(PDO::FETCH_ASSOC);
            return is_array($r)&&$r['company_id']===$company&&$r['source_product']==='imagine-crm'
                &&hash_equals($r['source_payload_hash'],$task['source_payload_hash'])
                &&hash_equals($r['task_payload_hash'],$taskHash)
                &&!$this->hasLegacyProof($tenant,$task['event_ref']);
        }catch(Throwable){return false;}
    }

    public function hasLegacyProof(string $tenant,string $event):bool
    {
        if(!is_string($this->db->query("SELECT to_regclass('aia_p42_lab.consumed_transport_proofs')")
            ->fetchColumn()))return false;
        $q=$this->db->prepare('SELECT 1 FROM aia_p42_lab.consumed_transport_proofs '
            .'WHERE tenant_ref=:tenant AND event_ref=:event');
        $q->execute([':tenant'=>$tenant,':event'=>$event]);return $q->fetchColumn()!==false;
    }

    public function hasCapturedProof(string $tenant,string $event):bool
    {
        if(!is_string($this->db->query("SELECT to_regclass('aia_lead_ingest_lab.captured_job_sources')")
            ->fetchColumn()))return false;
        $q=$this->db->prepare('SELECT 1 FROM aia_lead_ingest_lab.captured_job_sources '
            .'WHERE tenant_ref=:tenant AND event_ref=:event');
        $q->execute([':tenant'=>$tenant,':event'=>$event]);return $q->fetchColumn()!==false;
    }

    private static function truth(mixed $v):bool{return in_array($v,[true,1,'1','t'],true);}
}
