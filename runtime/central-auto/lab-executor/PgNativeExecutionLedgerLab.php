<?php
declare(strict_types=1);
namespace ImagineAI\NativeLab;

require_once __DIR__.'/../../../tools/labs/PgOwnerBoundSignedDispatchLab.php';
require_once __DIR__.'/../../../tools/labs/LabAutomationTaskContract.php';

use ImagineAI\Convergence\PgSyntheticReplayGate;
use ImagineAI\Convergence\PgCompanyDispatchGuardLab;
use ImagineAI\Convergence\PgOwnerExecutionGuardLab;
use ImagineAI\Convergence\PgOwnerBoundSignedDispatchLab;
use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\LabAutomationTaskContract;
use PDO;
use Throwable;

/** CI-only reconciliation around the original P45 domain, not a new CRM policy.
 * The trusted runner observes the native CLI result. A synthetic V2 signer is
 * not a production n8n attestation or an operational service identity.
 */
final class PgNativeExecutionLedgerLab
{
    public const TEMPLATE_SHA256='85311c9621967bd3b31a0935a358bf9b61d51cd2266954293ef8c124096e41c8';
    public const LEAD_TEMPLATE_SHA256='8d3ee7e2abe2f852212ee10904fa20032265578358992e98abc99835fd5833a4';
    private const FIELDS=['company_id','tenant_ref','event_ref','worker_ref','attempt',
        'authority_revision','mapping_revision','executor_key_id','canonical_event',
        'payload_hash','template_sha256','workflow_id','workflow_sha256'];
    private const NODES=['Disparo manual','Referencias tecnicas','Projetar estado da execucao'];
    private const LEAD_NODES=['Disparo manual','Referencias do lead','Preparar lead recebido'];
    private const JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;

    public function __construct(private PDO $db) { new PgSyntheticReplayGate($db); }

    private static function require(bool $condition,string $code):void {
        if(!$condition)throw new \DomainException($code);
    }
    private static function json(mixed $value):string {return json_encode($value,self::JSON);}
    private static function ref(mixed $v):bool {
        return is_string($v)&&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;
    }
    private static function digest(mixed $v):bool {
        return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/D',$v)===1;
    }
    private static function same(array $wanted,mixed $actual):bool {
        if(!is_array($actual))return false;
        ksort($wanted);ksort($actual);return $wanted===$actual;
    }
    private static function output(string $decision,string $reason,string $state=''):array {
        return ['decision'=>$decision,'reason'=>$reason,'state'=>$state,'execute_again'=>false];
    }

    /** The queue contract selects one exact graph, never a caller-supplied path
     * or a generic NO_AI label. Lead preparation retains its own source hash.
     */
    private static function profile(array $c):array {
        $event=LabAutomationTaskContract::decode($c['canonical_event']);
        self::require(is_array($event)
            &&$event['tenant_ref']===$c['tenant_ref']&&$event['event_ref']===$c['event_ref'],
            'P26_CANONICAL_SCOPE');
        if($event['contract']==='SYNTHETIC_AIA_AUTOMATION_V1'
            &&$event['kind']==='mock.run.completed'){
            return ['event'=>$event,'sha'=>self::TEMPLATE_SHA256,'nodes'=>self::NODES,
                'workflow_ref'=>'SYNTHETIC_P26_RUN_STATUS_V1','prefix'=>'p26',
                'file'=>'native-run-status-noai.workflow.json'];
        }
        self::require($event['contract']==='SYNTHETIC_CRM_LEAD_INGEST_V1'
            &&$event['kind']==='crm.lead.ingest'
            &&$event['company_id']===($c['company_id']??null),'LEAD_CANONICAL_SCOPE');
        return ['event'=>$event,'sha'=>self::LEAD_TEMPLATE_SHA256,'nodes'=>self::LEAD_NODES,
            'workflow_ref'=>'SYNTHETIC_CRM_LEAD_INGEST_V1','prefix'=>'lead',
            'file'=>'native-crm-lead-noai.workflow.json'];
    }

    public static function inputs(array $c):array {
        $profile=self::profile($c);$event=$profile['event'];
        if($event['contract']==='SYNTHETIC_CRM_LEAD_INGEST_V1'){
            return ['company_id'=>$event['company_id'],'tenant_ref'=>$c['tenant_ref'],
                'event_ref'=>$c['event_ref'],'lead_id'=>$event['lead_id'],
                'source_payload_hash'=>$event['source_payload_hash'],'payload_hash'=>$c['payload_hash'],
                'worker_ref'=>$c['worker_ref'],'attempt'=>$c['attempt'],
                'template_sha256'=>$profile['sha'],'workflow_ref'=>$profile['workflow_ref']];
        }
        return ['tenant_ref'=>$c['tenant_ref'],'event_ref'=>$c['event_ref'],
            'run_ref'=>$event['run_ref'],'reference_ref'=>$event['reference_ref'],
            'payload_hash'=>$c['payload_hash'],'worker_ref'=>$c['worker_ref'],
            'attempt'=>$c['attempt'],'template_sha256'=>self::TEMPLATE_SHA256,
            'workflow_ref'=>'SYNTHETIC_P26_RUN_STATUS_V1'];
    }

    /** Canonical context includes the exact source event and materialized graph.
     * Reconstruct the graph here as well as in Python; no caller-supplied hash
     * or verified flag can substitute a different executable workflow.
     */
    private static function context(array $c):string {
        $keys=array_keys($c);sort($keys);$expected=self::FIELDS;sort($expected);
        self::require($keys===$expected,'P26_CONTEXT_FIELDS');
        foreach(['tenant_ref','event_ref','worker_ref','executor_key_id'] as $field)
            self::require(self::ref($c[$field]),'P26_CONTEXT_REFERENCE');
        self::require(is_string($c['company_id'])&&preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',
            $c['company_id'])===1,'P26_COMPANY');
        foreach(['attempt','authority_revision','mapping_revision'] as $field)
            self::require(is_int($c[$field])&&$c[$field]>=1,'P26_CONTEXT_REVISION');
        self::require($c['attempt']<=3,'P26_CONTEXT_ATTEMPT');
        foreach(['payload_hash','template_sha256','workflow_sha256'] as $field)
            self::require(self::digest($c[$field]),'P26_CONTEXT_DIGEST');
        self::require(is_string($c['canonical_event'])&&strlen($c['canonical_event'])<=512
            &&hash_equals(hash('sha256',$c['canonical_event']),$c['payload_hash']),
            'P26_CONTEXT_EVENT_HASH');
        $profile=self::profile($c);
        self::require($c['template_sha256']===$profile['sha']
            &&$c['workflow_id']===$profile['prefix'].substr($c['payload_hash'],0,20),'P26_WORKFLOW_ID');
        $path=__DIR__.'/../../../infra/n8n-private/workflows/'.$profile['file'];
        self::require(is_file($path)&&!is_link($path)
            &&hash_file('sha256',$path)===$profile['sha'],'P26_TEMPLATE_PIN');
        $flow=json_decode(file_get_contents($path),false,32,JSON_THROW_ON_ERROR);
        $input=self::inputs($c);
        foreach($flow->nodes[1]->parameters->assignments->assignments as $assignment)
            $assignment->value=$input[$assignment->name];
        $flow->id=$c['workflow_id'];
        self::require(hash_equals(hash('sha256',self::json($flow)),$c['workflow_sha256']),
            'P26_MATERIALIZED_GRAPH_HASH');
        $ordered=[];foreach(self::FIELDS as $key)$ordered[$key]=$c[$key];
        return self::json($ordered);
    }

    private function row(array $c,bool $lock=false):?array {
        $q=$this->db->prepare('SELECT * FROM aia_p26_native.executions '
            .'WHERE tenant_ref=:tenant AND event_ref=:event'.($lock?' FOR UPDATE':''));
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
        $r=$q->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;
    }
    private static function matches(array $row,string $context):bool {
        return hash_equals(hash('sha256',$context),$row['context_sha256'])
            &&hash_equals($context,$row['context_json']);
    }

    /** Explicit post-lease recheck. Locks protect intent admission only; they
     * cannot atomically cancel a process after this transaction commits.
     */
    private function current(array $c,int $now):bool {
        $q=$this->db->prepare('SELECT active,revision FROM aia_p34_lab.tenant_policy '
            .'WHERE tenant_ref=:tenant FOR SHARE');
        $q->execute([':tenant'=>$c['tenant_ref']]);$tenant=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($tenant)||!in_array($tenant['active'],[true,1,'1','t'],true)
            ||(int)$tenant['revision']!==$c['authority_revision'])return false;
        $company=new PgCompanyDispatchGuardLab($this->db);
        if(!$company->mappingCurrent($c['company_id'],$c['tenant_ref'],$c['mapping_revision']))return false;
        $q=$this->db->prepare('SELECT state,attempts,lease_owner,lease_expires,canonical_event,event_hash '
            .'FROM public.aia_lab_n8n_outbox_p6 WHERE tenant_ref=:tenant AND event_ref=:event FOR UPDATE');
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['state']!=='LEASED'||$r['lease_owner']!==$c['worker_ref']
            ||(int)$r['attempts']!==$c['attempt']||(int)$r['lease_expires']<=$now
            ||!hash_equals($r['event_hash'],$c['payload_hash'])
            ||!hash_equals($r['canonical_event'],$c['canonical_event']))return false;
        $q=$this->db->prepare('SELECT payload_hash FROM aia_p16_lab.event_replay '
            .'WHERE tenant_ref=:tenant AND event_ref=:event FOR SHARE');
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);$hash=$q->fetchColumn();
        return is_string($hash)&&hash_equals($hash,$c['payload_hash'])
            &&$company->eventMatches($c['company_id'],$c['tenant_ref'],$c['event_ref'],$hash)
            &&(new PgOwnerExecutionGuardLab($this->db))->grantCurrent(
                $c['company_id'],$c['tenant_ref'],$c['event_ref'],$hash,$now);
    }

    public function begin(array $c,int $now):array {
        $context=self::context($c);
        $this->db->beginTransaction();
        try{
            if(!$this->current($c,$now)){
                $this->db->rollBack();return self::output('DENY','P26_CURRENT_SCOPE_OR_LEASE_DENIED');
            }
            $row=$this->row($c,true);
            if($row!==null){
                $this->db->rollBack();return self::output('DENY','P26_INTENT_ALREADY_EXISTS',$row['state']);
            }
            // The first pass can wait for authority/outbox locks. Never grant
            // STARTED using the clock captured before that wait. All relevant
            // locks are held now; verify the current lease/grant window again.
            if(!$this->current($c,time())){
                $this->db->rollBack();return self::output('DENY','P26_EXPIRED_AFTER_LOCK_WAIT');
            }
            $q=$this->db->prepare("INSERT INTO aia_p26_native.executions
                (tenant_ref,event_ref,company_id,worker_ref,attempt,payload_hash,template_sha256,
                 workflow_sha256,workflow_id,context_json,context_sha256,state,reason)
                VALUES (:tenant,:event,CAST(:company AS uuid),:worker,:attempt,:hash,:template,
                 :workflow,:id,:context,:digest,'STARTED','P26_INTENT_COMMITTED_BEFORE_CALL')");
            $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref'],':company'=>$c['company_id'],
                ':worker'=>$c['worker_ref'],':attempt'=>$c['attempt'],':hash'=>$c['payload_hash'],
                ':template'=>$c['template_sha256'],':workflow'=>$c['workflow_sha256'],
                ':id'=>$c['workflow_id'],':context'=>$context,':digest'=>hash('sha256',$context)]);
            $this->db->commit();
            return ['decision'=>'P26_CALL_ONCE','state'=>'STARTED','execute_again'=>false];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private static function validResult(array $c,mixed $payload):bool {
        try{
            if(!is_array($payload)||strlen(self::json($payload))>262144)return false;
            $profile=self::profile($c);$nodes=$profile['nodes'];
            $result=$payload['data']['resultData']??null;
            if(!is_array($result)||($result['error']??null)!==null
                ||($result['lastNodeExecuted']??null)!==$nodes[2])return false;
            $runs=$result['runData']??null;
            if(!is_array($runs))return false;
            $keys=array_keys($runs);sort($keys);$expected=$nodes;sort($expected);
            if($keys!==$expected)return false;
            foreach($nodes as $node)
                if(!is_array($runs[$node])||count($runs[$node])!==1
                    ||($runs[$node][0]['executionStatus']??null)!=='success')return false;
            $input=self::inputs($c);
            if($profile['event']['contract']==='SYNTHETIC_CRM_LEAD_INGEST_V1'){
                $output=['schema'=>'AIA_NATIVE_LEAD_INGEST_V1']+$input+[
                    'operation'=>'lead.ingest','lead_status'=>'PREPARED','crm_mutated'=>false,
                    'ai_api_calls'=>0,'operational_authorization'=>false];
            }else{
                $output=['schema'=>'AIA_NATIVE_RUN_STATUS_V1']+$input+[
                    'operation'=>'run.status.project','run_status'=>'COMPLETED',
                    'ai_api_calls'=>0,'operational_authorization'=>false];
            }
            foreach([1=>$input,2=>$output] as $index=>$wanted){
                $items=$runs[$nodes[$index]][0]['data']['main']??null;
                if(!is_array($items)||count($items)!==1||!is_array($items[0])
                    ||count($items[0])!==1||!self::same($wanted,$items[0][0]['json']??null))return false;
            }
            return true;
        }catch(Throwable){return false;}
    }

    /** Persist verified observation AND its synthetic receipt before any ACK.
     * A crash after this commit needs no second n8n invocation or private key.
     */
    public function record(array $c,mixed $payload,string $secret,int $now):array {
        $context=self::context($c);
        $this->db->beginTransaction();
        try{
            $r=$this->row($c,true);
            if($r===null||!self::matches($r,$context)||$r['state']!=='STARTED'){
                $this->db->rollBack();return self::output('DENY','P26_RESULT_FENCING_DENIED');
            }
            if(!self::validResult($c,$payload)){
                $this->unknown($c,'P26_MALFORMED_OBSERVED_RESULT');$this->db->commit();
                return self::output('DENY','P26_MALFORMED_OBSERVED_RESULT','UNKNOWN');
            }
            $pub=sodium_crypto_sign_publickey_from_secretkey($secret);
            $registered=(new PgSyntheticSignerRegistryLab($this->db))->resolveActive(
                $c['tenant_ref'],$c['executor_key_id']);
            self::require(is_string($registered)&&hash_equals($pub,$registered),'P26_SYNTHETIC_SIGNER');
            $receipt=self::json(['schema'=>'AIA_N8N_ACK_RECEIPT_V2',
                'issuer'=>'SYNTHETIC_N8N_EXECUTOR','audience'=>'imagine-ai-agents',
                'tenant_ref'=>$c['tenant_ref'],'event_ref'=>$c['event_ref'],'worker_ref'=>$c['worker_ref'],
                'attempt'=>$c['attempt'],'authority_revision'=>$c['authority_revision'],
                'payload_hash'=>$c['payload_hash'],'issued_at'=>$now,'expires_at'=>$now+60,
                'result'=>'NO_AI_COMPLETED_LAB']);
            $observed=self::json($payload);
            $q=$this->db->prepare("UPDATE aia_p26_native.executions SET state='RESULT',
                reason='P26_OBSERVATION_PERSISTED_BEFORE_ACK',result_json=:result,result_sha256=:hash,
                canonical_receipt=:receipt,signature_b64=:signature,
                observed_at=clock_timestamp(),updated_at=clock_timestamp()
                WHERE tenant_ref=:tenant AND event_ref=:event AND state='STARTED'");
            $q->execute([':result'=>$observed,':hash'=>hash('sha256',$observed),':receipt'=>$receipt,
                ':signature'=>base64_encode(sodium_crypto_sign_detached($receipt,$secret)),
                ':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
            $this->db->commit();return self::output('P26_RESULT_DURABLE','P26_ACK_NOT_YET_ATTEMPTED','RESULT');
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function unknown(array $c,string $reason):void {
        $q=$this->db->prepare("UPDATE aia_p26_native.executions SET state='UNKNOWN',
            reason=:reason,updated_at=clock_timestamp() WHERE tenant_ref=:tenant AND event_ref=:event
            AND state='STARTED'");
        $q->execute([':reason'=>$reason,':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
    }

    private function evidenceMatches(array $c,array $r):bool {
        $q=$this->db->prepare("SELECT e.* FROM aia_p38_lab.signed_ack_evidence e
            JOIN public.aia_lab_n8n_outbox_p6 o USING(tenant_ref,event_ref)
            WHERE e.tenant_ref=:tenant AND e.event_ref=:event AND o.state='ACKED'
              AND o.event_hash=e.payload_hash AND o.attempts=e.attempt");
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);$e=$q->fetch(PDO::FETCH_ASSOC);
        return is_array($e)&&$e['worker_ref']===$c['worker_ref']&&(int)$e['attempt']===$c['attempt']
            &&(int)$e['authority_revision']===$c['authority_revision']
            &&$e['signer_key_id']===$c['executor_key_id']&&$e['payload_hash']===$c['payload_hash']
            &&hash_equals($r['canonical_receipt'],$e['canonical_receipt'])
            &&hash_equals($r['signature_b64'],$e['signature_b64'])
            &&hash_equals(hash('sha256',$r['canonical_receipt']),$e['receipt_sha256']);
    }

    public function reconcile(array $c,int $now):array {
        $context=self::context($c);
        $this->db->beginTransaction();
        $r=$this->row($c,true);
        if($r===null||!self::matches($r,$context)){
            $this->db->rollBack();return self::output('DENY','P26_RECONCILIATION_SCOPE_DENIED');
        }
        if($r['state']==='STARTED'){
            $this->unknown($c,'P26_CRASH_WINDOW_OUTCOME_UNKNOWN');$this->db->commit();
            return self::output('P26_MANUAL_RECONCILIATION_REQUIRED','P26_NO_AUTOMATIC_RETRY','UNKNOWN');
        }
        $this->db->commit();
        if($r['state']==='UNKNOWN')return self::output('P26_MANUAL_RECONCILIATION_REQUIRED',
            'P26_NO_AUTOMATIC_RETRY','UNKNOWN');
        if(!hash_equals(hash('sha256',$r['result_json']),$r['result_sha256'])
            ||!self::validResult($c,json_decode($r['result_json'],true,64,JSON_THROW_ON_ERROR)))
            return self::output('DENY','P26_STORED_RESULT_INTEGRITY_DENIED',$r['state']);
        // Original ACK and evidence commit together. If the process died before
        // updating this auxiliary ledger, reconcile from that exact evidence.
        if(!$this->evidenceMatches($c,$r)){
            if($r['state']==='ACKED')return self::output('DENY','P26_ACK_EVIDENCE_MISSING','ACKED');
            $ack=(new PgOwnerBoundSignedDispatchLab($this->db))->acknowledgeSigned(
                $r['canonical_receipt'],$r['signature_b64'],$c['company_id'],$c['tenant_ref'],
                $c['event_ref'],$c['worker_ref'],$c['attempt'],time(),$c['authority_revision'],
                $c['mapping_revision'],$c['executor_key_id']);
            if($ack['decision']!=='LAB_ATOMIC_SIGNED_EVIDENCE_ACK'
                &&!$this->evidenceMatches($c,$r))
                return self::output('DENY',$ack['reason'],'RESULT');
        }
        self::require($this->evidenceMatches($c,$r),'P26_ATOMIC_ACK_EVIDENCE_REQUIRED');
        $q=$this->db->prepare("UPDATE aia_p26_native.executions SET state='ACKED',
            reason='P26_EXISTING_DOMAIN_ACK_EVIDENCE_CONFIRMED',updated_at=clock_timestamp()
            WHERE tenant_ref=:tenant AND event_ref=:event AND context_sha256=:hash
              AND state IN ('RESULT','ACKED')");
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref'],':hash'=>hash('sha256',$context)]);
        return self::output('P26_ACK_EVIDENCE_CONFIRMED','P26_NO_SECOND_EXECUTION','ACKED');
    }
}
