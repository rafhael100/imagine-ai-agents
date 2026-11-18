<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__.'/PgCapturedLeadProofLab.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use PDO;
use Throwable;

/**
 * P46 read-only historical lineage of P42/P44-originated CI events:
 * mTLS P7 -> explicit mapping P41 -> consumed transport P42
 * -> signed owner P44 -> replay P16 -> outbox P6.
 *
 * No grant, signer, mTLS nonce or event is authorized by this scanner.
 */
final class PgTransportOwnerLineageAuditLab
{
    private const OWNER_FIELDS=[
        'schema','issuer','audience','company_id','tenant_ref','event_ref',
        'grant_ref','operation','owner_revision','payload_hash','issued_at',
        'expires_at','decision','key_id'
    ];
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    /** @return array{decision:string,checked:int,issues:array,ai_calls:int,external_calls:int} */
    public function scan():array
    {
        if($this->db->inTransaction())return self::out('INDETERMINATE',0,[
            ['reason'=>'REQUIRES_INDEPENDENT_READONLY_SNAPSHOT']
        ]);
        try{
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            // Each source is explicit. An installation exercising only the
            // captured-lead path need not fabricate the historical P7 tables.
            $legacy=$this->exists('aia_p42_lab.consumed_transport_proofs')
                ?"SELECT 'P7'::text AS source_kind,company_id,tenant_ref,event_ref,
                    request_id::text,nonce::text,payload_hash::text
                   FROM aia_p42_lab.consumed_transport_proofs"
                :"SELECT 'P7'::text AS source_kind,NULL::uuid AS company_id,
                    NULL::text AS tenant_ref,NULL::text AS event_ref,NULL::text AS request_id,
                    NULL::text AS nonce,NULL::text AS payload_hash WHERE false";
            $captured=$this->exists('aia_lead_ingest_lab.captured_job_sources')
                ?"SELECT 'CAPTURED_LEAD'::text AS source_kind,company_id,tenant_ref,event_ref,
                    NULL::text AS request_id,NULL::text AS nonce,task_payload_hash AS payload_hash
                   FROM aia_lead_ingest_lab.captured_job_sources"
                :"SELECT 'CAPTURED_LEAD'::text AS source_kind,NULL::uuid AS company_id,
                    NULL::text AS tenant_ref,NULL::text AS event_ref,NULL::text AS request_id,
                    NULL::text AS nonce,NULL::text AS payload_hash WHERE false";
            $admitted=$this->exists('central_auto_lab.mtls_nonce_ledger')
                ?"SELECT company_id,request_id,nonce,issuer,audience FROM central_auto_lab.mtls_nonce_ledger"
                :"SELECT NULL::uuid AS company_id,NULL::text AS request_id,NULL::text AS nonce,
                    NULL::text AS issuer,NULL::text AS audience WHERE false";
            $rows=$this->db->query(<<<SQL
WITH transport AS ($legacy),captured AS ($captured),
 provenance AS (SELECT * FROM transport UNION ALL SELECT * FROM captured),
 admitted AS ($admitted)
SELECT
 t.source_kind,
 t.company_id::text AS tc,t.tenant_ref AS tt,t.event_ref AS te,
 t.request_id AS t_request,t.nonce AS t_nonce,t.payload_hash AS th,
 g.company_id::text AS gc,g.tenant_ref AS gt,g.event_ref AS ge,
 g.grant_ref,g.payload_hash AS gh,g.signed_decision_sha256 AS signed_sha,
 g.signer_key_id,g.owner_revision,g.canonical_decision,g.signature_b64,
 m.tenant_ref AS mapped_tenant,
 n.nonce AS admitted_nonce,
 l.payload_hash AS replay_hash,
 o.event_hash AS outbox_hash,o.canonical_event,o.tenant_ref AS ot,o.event_ref AS oe,
 k.public_key_b64,k.fingerprint
FROM provenance t
FULL OUTER JOIN aia_p44_lab.consumed_owner_grants g
 ON g.company_id=t.company_id AND g.tenant_ref=t.tenant_ref
 AND g.event_ref=t.event_ref
FULL OUTER JOIN public.aia_lab_n8n_outbox_p6 o
 ON o.tenant_ref=COALESCE(t.tenant_ref,g.tenant_ref)
 AND o.event_ref=COALESCE(t.event_ref,g.event_ref)
LEFT JOIN aia_p41_lab.company_tenant_links m
 ON m.company_id=COALESCE(t.company_id,g.company_id)
LEFT JOIN admitted n
 ON n.company_id=t.company_id AND n.request_id=t.request_id
 AND n.nonce=t.nonce AND n.issuer='portal-automation-admin'
 AND n.audience='crm-automation-state'
LEFT JOIN aia_p16_lab.event_replay l
 ON l.tenant_ref=COALESCE(t.tenant_ref,g.tenant_ref)
 AND l.event_ref=COALESCE(t.event_ref,g.event_ref)
LEFT JOIN aia_p44_lab.owner_keys k
 ON k.company_id=g.company_id AND k.key_id=g.signer_key_id
WHERE t.company_id IS NOT NULL OR g.company_id IS NOT NULL
 OR o.canonical_event LIKE '{"contract":"SYNTHETIC_CRM_LEAD_INGEST_V1",%'
ORDER BY COALESCE(t.tenant_ref,g.tenant_ref),
 COALESCE(t.event_ref,g.event_ref)
SQL)->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>2000){
                $this->db->commit();
                return self::out('INDETERMINATE',0,[
                    ['reason'=>'AUDIT_CAPACITY_LIMIT']
                ]);
            }
            $issues=[];
            foreach($rows as $r){
                $tenant=$r['tt']??$r['gt']??$r['ot'];
                $event=$r['te']??$r['ge']??$r['oe'];
                $why=null;
                if($r['tc']===null){
                    $why=$r['gc']!==null?'OWNER_GRANT_WITHOUT_TRANSPORT_PROOF':
                        'LEAD_JOB_WITHOUT_SOURCE_PROOF';
                }elseif($r['gc']===null){
                    $why=$r['source_kind']==='CAPTURED_LEAD'?'LEAD_JOB_WITHOUT_OWNER_GRANT':
                        'P42_EVENT_WITHOUT_OWNER_GRANT';
                }elseif($r['tc']!==$r['gc']||$r['tt']!==$r['gt']
                    ||$r['te']!==$r['ge']){
                    $why='COMPANY_TENANT_EVENT_SCOPE_MISMATCH';
                }elseif($r['mapped_tenant']!==$r['tt']){
                    $why='COMPANY_TENANT_MAPPING_DRIFT';
                }elseif($r['source_kind']==='P7'&&$r['admitted_nonce']===null){
                    $why='MISSING_ORIGINAL_MTLS_ADMISSION';
                }elseif(!is_string($r['replay_hash'])
                    ||!is_string($r['outbox_hash'])
                    ||!is_string($r['canonical_event'])){
                    $why='EVENT_LEDGER_OR_OUTBOX_MISSING';
                }elseif(!hash_equals($r['th'],$r['gh'])
                    ||!hash_equals($r['th'],$r['replay_hash'])
                    ||!hash_equals($r['th'],$r['outbox_hash'])
                    ||!hash_equals($r['th'],hash('sha256',$r['canonical_event']))
                    ||LabAutomationTaskContract::decode($r['canonical_event'])===null){
                    $why='EVENT_LINEAGE_HASH_MISMATCH';
                }elseif($r['source_kind']==='CAPTURED_LEAD'
                    &&(!LabAutomationTaskContract::isLead($r['canonical_event'])
                        ||!(new PgCapturedLeadProofLab($this->db))->stagedMatches(
                            $r['tc'],$r['tt'],$r['canonical_event'],$r['th'],false))){
                    $why='CAPTURED_LEAD_LINEAGE_INVALID';
                }elseif($r['source_kind']==='P7'
                    &&(MockN8nOutboxContract::decode($r['canonical_event'])===null
                        ||(new PgCapturedLeadProofLab($this->db))->hasCapturedProof($r['tt'],$r['te']))){
                    $why='P7_PROOF_CANNOT_AUTHORIZE_CAPTURED_LEAD';
                }else{
                    $why=self::signedOwnerReason($r);
                }
                if($why!==null){
                    $issues[]=[
                        'tenant_ref'=>$tenant,'event_ref'=>$event,
                        'reason'=>$why
                    ];
                }
            }
            $this->db->commit();
            return self::out($issues?'DENY_INCONSISTENT':
                'LAB_HISTORICAL_OWNER_LINEAGE_CONSISTENT_NO_GO',
                count($rows),$issues);
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::out('INDETERMINATE',0,[
                ['reason'=>'READONLY_QUERY_OR_SCHEMA_UNAVAILABLE']
            ]);
        }
    }

    private static function signedOwnerReason(array $r):?string
    {
        if(!is_string($r['canonical_decision'])
            ||!is_string($r['signature_b64'])
            ||!is_string($r['signed_sha'])
            ||!hash_equals(hash('sha256',$r['canonical_decision']),$r['signed_sha'])){
            return 'SIGNED_OWNER_DECISION_HASH_INVALID';
        }
        try{
            $d=json_decode($r['canonical_decision'],true,5,JSON_THROW_ON_ERROR);
            if(!is_array($d)||array_keys($d)!==self::OWNER_FIELDS)return 'OWNER_DECISION_SCHEMA_INVALID';
            $raw=json_encode($d,JSON_THROW_ON_ERROR
                |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(!hash_equals($raw,$r['canonical_decision']))return 'OWNER_DECISION_NOT_CANONICAL';
            foreach(['schema','issuer','audience','company_id','tenant_ref',
                'event_ref','grant_ref','operation','payload_hash','decision',
                'key_id'] as $key){
                if(!is_string($d[$key]))return 'OWNER_DECISION_SCHEMA_INVALID';
            }
            foreach(['owner_revision','issued_at','expires_at'] as $key){
                if(!is_int($d[$key]))return 'OWNER_DECISION_SCHEMA_INVALID';
            }
            if($d['schema']!=='AIA_OWNER_DECISION_V1'
                ||$d['issuer']!=='SYNTHETIC_CRM_POLICY_OWNER'
                ||$d['audience']!=='imagine-ai-agents'
                ||$d['operation']!==LabAutomationTaskContract::operation($r['canonical_event'])
                ||!in_array($d['operation'],['run.status.project','lead.ingest'],true)
                ||$d['decision']!=='ALLOW_NO_AI_LAB'
                ||$d['company_id']!==$r['gc']
                ||$d['tenant_ref']!==$r['gt']
                ||$d['event_ref']!==$r['ge']
                ||$d['grant_ref']!==$r['grant_ref']
                ||$d['payload_hash']!==$r['gh']
                ||$d['key_id']!==$r['signer_key_id']
                ||$d['owner_revision']!==(int)$r['owner_revision']
                ||$d['issued_at']<0
                ||$d['expires_at']<=$d['issued_at']
                ||$d['expires_at']-$d['issued_at']>120){
                return 'OWNER_DECISION_HISTORICAL_SCOPE_INVALID';
            }
            // Historical cryptographic validity does not depend on whether
            // that public key is ACTIVE NOW, since it may have been revoked
            // *after* the legitimate synthetic event was originally staged.
            $pub=is_string($r['public_key_b64'])
                ?base64_decode($r['public_key_b64'],true):false;
            $sig=base64_decode($r['signature_b64'],true);
            if(!is_string($pub)
                ||strlen($pub)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ||!is_string($r['fingerprint'])
                ||!hash_equals(hash('sha256',$pub),$r['fingerprint'])){
                return 'OWNER_HISTORICAL_KEY_MISSING_OR_CHANGED';
            }
            if(!is_string($sig)||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($sig)!==$r['signature_b64']
                ||!sodium_crypto_sign_verify_detached(
                    $sig,$r['canonical_decision'],$pub)){
                return 'OWNER_HISTORICAL_SIGNATURE_INVALID';
            }
            return null;
        }catch(Throwable){
            return 'OWNER_DECISION_SCHEMA_INVALID';
        }
    }

    private function exists(string $relation):bool
    {
        $q=$this->db->prepare('SELECT to_regclass(:relation)');
        $q->execute([':relation'=>$relation]);return is_string($q->fetchColumn());
    }

    /** @return array{decision:string,checked:int,issues:array,ai_calls:int,external_calls:int} */
    private static function out(string $decision,int $checked,array $issues):array
    {
        return [
            'decision'=>$decision,'checked'=>$checked,'issues'=>$issues,
            'ai_calls'=>0,'external_calls'=>0
        ];
    }
}
