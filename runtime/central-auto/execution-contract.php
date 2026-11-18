<?php
declare(strict_types=1);
namespace ImagineAI\ExecutionStatus;

require_once __DIR__.'/strict-json.php';
require_once __DIR__.'/../../src/MockOrchestration/CapturedLeadTaskContract.php';

use ImagineAI\MockOrchestration\CapturedLeadTaskContract;

/** Pure observation of durable evidence. Never calls reconcile, signs executor
 * receipts, changes a queue, or treats current read authority as execution authority.
 */
final class ExecutionStatusContract
{
    public const QUERY_FIELDS=['schema','issuer','audience','product','operation','company_id',
        'tenant_ref','event_ref','payload_hash','request_ref','nonce_ref','issued_at','expires_at','key_id','signature'];
    public const RESPONSE_FIELDS=['schema','issuer','audience','company_id','tenant_ref','event_ref',
        'payload_hash','lead_id','task_payload_hash','execution_state','queue_state','result_code',
        'observation_hash','attempt','request_ref','nonce_ref','issued_at','expires_at','key_id','signature'];
    public const OBSERVATION_FIELDS=['company_id','tenant_ref','event_ref','payload_hash','lead_id',
        'task_payload_hash','execution_state','queue_state','result_code','attempt'];
    public const QUERY_PREFIX='IMAGINE_AGENTS_EXECUTION_QUERY_V1';
    public const RESPONSE_PREFIX='IMAGINE_AGENTS_EXECUTION_STATUS_V1';
    public const OBSERVATION_PREFIX='IMAGINE_AGENTS_EXECUTION_OBSERVATION_V1';
    public const TEMPLATE_SHA256='8d3ee7e2abe2f852212ee10904fa20032265578358992e98abc99835fd5833a4';
    private const JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
    private const CONTEXT=['company_id','tenant_ref','event_ref','worker_ref','attempt',
        'authority_revision','mapping_revision','executor_key_id','canonical_event','payload_hash',
        'template_sha256','workflow_id','workflow_sha256'];
    private const OWNER=['schema','issuer','audience','company_id','tenant_ref','event_ref',
        'grant_ref','operation','owner_revision','payload_hash','issued_at','expires_at','decision','key_id'];
    private const RECEIPT=['schema','issuer','audience','tenant_ref','event_ref','worker_ref',
        'attempt','authority_revision','payload_hash','issued_at','expires_at','result'];
    private const NODES=['Disparo manual','Referencias do lead','Preparar lead recebido'];

    private static function need(bool $ok,string $code):void
    {if(!$ok)throw new \DomainException('EXECUTION_'.$code);}
    private static function ref(mixed $v):bool
    {return is_string($v)&&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;}
    private static function hash(mixed $v):bool
    {return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/D',$v)===1;}
    private static function closed(array $v,array $fields):void
    {$keys=array_keys($v);sort($keys);sort($fields);self::need($keys===$fields,'FIELDS');}
    private static function exact(array $wanted,mixed $actual):bool
    {if(!is_array($actual))return false;ksort($wanted);ksort($actual);return $wanted===$actual;}
    private static function canonical(string $raw,array $fields,int $limit):array
    {
        $v=\agents_json_decode($raw,$limit,8);
        self::need(is_array($v)&&array_keys($v)===$fields,'CANONICAL_FIELDS');
        self::need(hash_equals($raw,json_encode($v,self::JSON)),'CANONICAL_BYTES');return $v;
    }
    private static function historicalWindow(array $v):void
    {
        self::need(is_int($v['issued_at'])&&is_int($v['expires_at'])&&$v['issued_at']>=0
            &&$v['expires_at']<=$v['issued_at']+120&&$v['expires_at']>$v['issued_at']
            &&$v['expires_at']<=4102444800,'HISTORICAL_WINDOW');
    }
    private static function publicKey(array $row,string $fingerprintField):string
    {
        $encoded=$row['public_key_b64']??null;
        self::need(is_string($encoded)&&strlen($encoded)===44,'PUBLIC_KEY');
        $pub=base64_decode($encoded,true);
        self::need(is_string($pub)&&strlen($pub)===32&&base64_encode($pub)===$encoded
            &&self::hash($row[$fingerprintField]??null)
            &&hash_equals(hash('sha256',$pub),$row[$fingerprintField]),'PUBLIC_KEY_HASH');return $pub;
    }
    private static function signature(string $raw,mixed $encoded,string $pub):void
    {
        self::need(is_string($encoded)&&strlen($encoded)===88,'SIGNATURE_FORMAT');
        $sig=base64_decode($encoded,true);
        self::need(is_string($sig)&&strlen($sig)===64&&base64_encode($sig)===$encoded
            &&sodium_crypto_sign_verify_detached($sig,$raw,$pub),'SIGNATURE_INVALID');
    }

    public static function query(string $raw):array
    {
        $q=\agents_json_decode($raw,4096,4);self::need(is_array($q),'QUERY_OBJECT');
        self::closed($q,self::QUERY_FIELDS);
        foreach(self::QUERY_FIELDS as $field)
            self::need(in_array($field,['issued_at','expires_at'],true)?is_int($q[$field]):is_string($q[$field]),'QUERY_TYPE');
        foreach(['schema'=>'AUTO_IA_EXECUTION_QUERY_V1','issuer'=>'imagine-crm',
            'audience'=>'imagine-ai-agents','product'=>'imagine-crm','operation'=>'event.execution.read'] as $k=>$v)
            self::need($q[$k]===$v,'QUERY_DOMAIN');
        self::need(preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',
            $q['company_id'])===1,'QUERY_COMPANY');
        foreach(['tenant_ref','event_ref','request_ref','nonce_ref','key_id'] as $f)self::need(self::ref($q[$f]),'QUERY_REF');
        foreach(['payload_hash','signature'] as $f)self::need(self::hash($q[$f]),'QUERY_HASH');
        self::need($q['issued_at']>=0&&$q['expires_at']<=4102444800,'QUERY_TIME');return $q;
    }

    /** The only accepted input is the authenticated SQL function's snapshot. */
    public static function project(array $s,array $q):array
    {
        self::closed($s,['source','inbox','provenance','replay','outbox','owner_grant','owner_key',
            'execution','executor_key','ack_evidence','audit_received','legacy_proof','auth_expires_at']);
        self::need($s['audit_received']===true&&$s['legacy_proof']===false,'ORIGIN_ADMISSION');
        $source=$s['source'];$inbox=$s['inbox'];
        self::need(is_array($source)&&is_array($inbox),'ORIGIN_ROWS');
        self::need($source['company_id']===$q['company_id']&&$source['tenant']===$q['tenant_ref']
            &&$source['event_ref']===$q['event_ref']&&$source['payload_hash']===$q['payload_hash']
            &&$source['product']==='imagine-crm'&&$source['operation']==='lead.ingest'
            &&$source['state']==='CAPTURED_NOT_DISPATCHED','ORIGIN_SCOPE');
        foreach(['tenant','product','operation','event_ref','nonce_ref','payload_hash','issued_epoch','expires_epoch'] as $f)
            self::need(array_key_exists($f,$inbox)&&$inbox[$f]===$source[$f],'INBOX_BINDING');
        $hex=$source['payload_bytes']??null;
        self::need(is_string($hex)&&str_starts_with($hex,'\\x'),'SOURCE_BYTES');$hex=substr($hex,2);
        self::need(strlen($hex)>=4&&strlen($hex)<=4096&&strlen($hex)%2===0
            &&preg_match('/\A[a-f0-9]+\z/D',$hex)===1,'SOURCE_HEX');
        $raw=hex2bin($hex);
        self::need(is_string($raw)&&hash_equals(hash('sha256',$raw),$q['payload_hash']),'SOURCE_HASH');
        $payload=\agents_json_decode($raw,2048,4);self::need(is_array($payload),'SOURCE_JSON');
        self::closed($payload,['schema','action','company_id','event_id','lead_id']);
        foreach($payload as $value)self::need(is_string($value),'SOURCE_TYPE');
        self::need($payload['schema']==='CRM_LEAD_EVENT_V1'&&$payload['action']==='lead.created'
            &&$payload['company_id']===$q['company_id']&&strtolower($payload['event_id'])===$source['event_id']
            &&strtolower($payload['lead_id'])===$source['lead_id']
            &&preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D',$source['lead_id'])===1
            &&$q['event_ref']==='SYNTHETIC_'.strtoupper(str_replace('-','',$source['event_id'])),'SOURCE_IDENTITIES');
        $facts=['company_id'=>$q['company_id'],'tenant_ref'=>$q['tenant_ref'],'event_ref'=>$q['event_ref'],
            'payload_hash'=>$q['payload_hash'],'lead_id'=>$source['lead_id'],'task_payload_hash'=>'',
            'execution_state'=>'NOT_STAGED','queue_state'=>'NONE','result_code'=>'NONE','attempt'=>0];
        $o=$s['outbox'];
        if($o===null){
            foreach(['provenance','replay','owner_grant','owner_key','execution','executor_key','ack_evidence'] as $f)
                self::need($s[$f]===null,'ORPHAN_WITHOUT_QUEUE');
            return $facts;
        }
        self::need(is_array($o)&&is_array($s['provenance'])&&is_array($s['replay']),'STAGED_ROWS');
        $task=CapturedLeadTaskContract::decode($o['canonical_event']);self::need($task!==null,'TASK_CONTRACT');
        $taskHash=hash('sha256',$o['canonical_event']);
        self::need($task['company_id']===$q['company_id']&&$task['tenant_ref']===$q['tenant_ref']
            &&$task['event_ref']===$q['event_ref']&&$task['lead_id']===$source['lead_id']
            &&$task['source_payload_hash']===$q['payload_hash']&&$o['tenant_ref']===$q['tenant_ref']
            &&$o['event_ref']===$q['event_ref']&&$o['event_hash']===$taskHash,'TASK_SOURCE_BINDING');
        $p=$s['provenance'];$r=$s['replay'];
        self::need($p['company_id']===$q['company_id']&&$p['tenant_ref']===$q['tenant_ref']
            &&$p['event_ref']===$q['event_ref']&&$p['source_product']==='imagine-crm'
            &&$p['source_payload_hash']===$q['payload_hash']&&$p['task_payload_hash']===$taskHash,'PROVENANCE');
        $derived=CapturedLeadTaskContract::propose($o['canonical_event'],$q['tenant_ref'],$task['occurred_at'])['proposal'];
        self::need(is_array($derived)&&$r['tenant_ref']===$q['tenant_ref']&&$r['event_ref']===$q['event_ref']
            &&$r['payload_hash']===$taskHash&&$r['nonce_ref']===$derived['nonce_ref']
            &&$r['request_ref']===$derived['request_ref'],'REPLAY_BINDING');
        self::owner($s,$q,$taskHash);
        self::need(in_array($o['state'],['PENDING','LEASED','ACKED','DEAD'],true)
            &&is_int($o['attempts'])&&$o['attempts']>=0&&$o['attempts']<=3,'QUEUE_STATE');
        self::need(array_key_exists('lease_owner',$o)&&array_key_exists('lease_expires',$o),'QUEUE_LEASE_FIELDS');
        if($o['state']==='LEASED')self::need($o['attempts']>=1&&self::ref($o['lease_owner'])
            &&is_int($o['lease_expires'])&&$o['lease_expires']>0,'QUEUE_LEASE');
        else self::need($o['lease_owner']===null&&$o['lease_expires']===null
            &&($o['state']!=='PENDING'||$o['attempts']===0)
            &&($o['state']!=='ACKED'||$o['attempts']>=1)
            &&($o['state']!=='DEAD'||$o['attempts']===3),'QUEUE_TERMINAL_SHAPE');
        $facts['task_payload_hash']=$taskHash;$facts['queue_state']=$o['state'];
        $x=$s['execution'];
        if($x===null){
            self::need($s['executor_key']===null&&$s['ack_evidence']===null&&$o['state']!=='ACKED','ORPHAN_ACK');
            $facts['execution_state']='NOT_STARTED';return $facts;
        }
        self::need(is_array($x),'INTENT_ROW');
        $c=self::context($x,$o,$q,$task);
        self::need(in_array($x['state'],['STARTED','RESULT','ACKED','UNKNOWN'],true)
            &&in_array($o['state'],['LEASED','DEAD','ACKED'],true)
            &&$o['attempts']>=$c['attempt'],'INTENT_QUEUE_STATE');
        if($o['state']==='LEASED'&&$o['attempts']===$c['attempt'])
            self::need($o['lease_owner']===$c['worker_ref'],'INTENT_LEASE_WORKER');
        $facts['attempt']=$c['attempt'];$facts['execution_state']=$x['state'];
        if(in_array($x['state'],['STARTED','UNKNOWN'],true)){
            foreach(['result_json','result_sha256','canonical_receipt','signature_b64'] as $f)
                self::need($x[$f]===null,'UNFINISHED_RESULT_FIELDS');
            self::need($s['ack_evidence']===null&&$o['state']!=='ACKED','UNFINISHED_ACK');return $facts;
        }
        self::result($x,$c,$task,$s['executor_key']);
        $facts['result_code']='PREPARED';$ack=$s['ack_evidence'];
        if($o['state']==='ACKED'){
            self::need(is_array($ack)&&$o['attempts']===$c['attempt'],'ACK_ROWS');
            foreach(['tenant_ref','event_ref','worker_ref','attempt','authority_revision','payload_hash'] as $f)
                self::need($ack[$f]===$c[$f],'ACK_CONTEXT');
            self::need($ack['signer_key_id']===$c['executor_key_id']
                &&$ack['key_fingerprint']===$s['executor_key']['key_fingerprint']
                &&$ack['canonical_receipt']===$x['canonical_receipt']&&$ack['signature_b64']===$x['signature_b64']
                &&$ack['receipt_sha256']===hash('sha256',$x['canonical_receipt']),'ACK_EVIDENCE');
            // The domain ACK may commit before P26 mirrors ACKED. Observation
            // recognizes that durable fact without mutating the lagging ledger.
            $facts['execution_state']='ACKED';
        }else self::need($x['state']==='RESULT'&&$ack===null,'ACK_EVIDENCE_MISSING');
        return $facts;
    }

    private static function owner(array $s,array $q,string $hash):void
    {
        $g=$s['owner_grant'];$key=$s['owner_key'];self::need(is_array($g)&&is_array($key),'OWNER_ROWS');
        $d=self::canonical($g['canonical_decision'],self::OWNER,1024);self::historicalWindow($d);
        foreach(['company_id','tenant_ref','event_ref'] as $f)
            self::need($g[$f]===$q[$f]&&$d[$f]===$q[$f],'OWNER_SCOPE');
        self::need($d['schema']==='AIA_OWNER_DECISION_V1'&&$d['issuer']==='SYNTHETIC_CRM_POLICY_OWNER'
            &&$d['audience']==='imagine-ai-agents'&&$d['operation']==='lead.ingest'
            &&$d['decision']==='ALLOW_NO_AI_LAB'&&$d['payload_hash']===$hash&&$g['payload_hash']===$hash
            &&$d['grant_ref']===$g['grant_ref']&&self::ref($d['grant_ref'])
            &&$d['key_id']===$g['signer_key_id']&&self::ref($d['key_id'])
            &&is_int($d['owner_revision'])&&$d['owner_revision']>=1&&$d['owner_revision']===$g['owner_revision']
            &&$key['company_id']===$q['company_id']&&$key['key_id']===$d['key_id']
            &&$key['revision']>=$d['owner_revision']
            &&$g['signed_decision_sha256']===hash('sha256',$g['canonical_decision']),'OWNER_BINDING');
        self::signature($g['canonical_decision'],$g['signature_b64'],self::publicKey($key,'fingerprint'));
    }

    private static function context(array $x,array $o,array $q,array $task):array
    {
        $c=self::canonical($x['context_json'],self::CONTEXT,4096);
        self::need($x['context_sha256']===hash('sha256',$x['context_json']),'CONTEXT_HASH');
        foreach(['company_id','tenant_ref','event_ref'] as $f)
            self::need($c[$f]===$q[$f]&&$x[$f]===$q[$f],'CONTEXT_SCOPE');
        foreach(['worker_ref','attempt','payload_hash','template_sha256','workflow_sha256','workflow_id'] as $f)
            self::need($x[$f]===$c[$f],'CONTEXT_ROW');
        self::need($c['canonical_event']===$o['canonical_event']&&$c['payload_hash']===$o['event_hash']
            &&self::ref($c['worker_ref'])&&self::ref($c['executor_key_id'])
            &&is_int($c['attempt'])&&$c['attempt']>=1&&$c['attempt']<=3
            &&is_int($c['authority_revision'])&&$c['authority_revision']>=1
            &&is_int($c['mapping_revision'])&&$c['mapping_revision']>=1
            &&$c['template_sha256']===self::TEMPLATE_SHA256
            &&$c['workflow_id']==='lead'.substr($c['payload_hash'],0,20),'CONTEXT_TASK');
        $path=__DIR__.'/../../infra/n8n-private/workflows/native-crm-lead-noai.workflow.json';
        self::need(is_file($path)&&!is_link($path)&&hash_file('sha256',$path)===self::TEMPLATE_SHA256,'TEMPLATE_PIN');
        $flow=json_decode(file_get_contents($path),false,32,JSON_THROW_ON_ERROR);$input=self::inputs($c,$task);
        foreach($flow->nodes[1]->parameters->assignments->assignments as $assignment)
            $assignment->value=$input[$assignment->name];
        $flow->id=$c['workflow_id'];
        self::need(hash('sha256',json_encode($flow,self::JSON))===$c['workflow_sha256'],'MATERIALIZED_GRAPH');return $c;
    }
    private static function inputs(array $c,array $task):array
    {
        return ['company_id'=>$c['company_id'],'tenant_ref'=>$c['tenant_ref'],'event_ref'=>$c['event_ref'],
            'lead_id'=>$task['lead_id'],'source_payload_hash'=>$task['source_payload_hash'],
            'payload_hash'=>$c['payload_hash'],'worker_ref'=>$c['worker_ref'],'attempt'=>$c['attempt'],
            'template_sha256'=>self::TEMPLATE_SHA256,'workflow_ref'=>'SYNTHETIC_CRM_LEAD_INGEST_V1'];
    }
    private static function result(array $x,array $c,array $task,mixed $key):void
    {
        self::need(is_string($x['result_json'])&&$x['result_sha256']===hash('sha256',$x['result_json'])
            &&is_array($key)&&$key['tenant_ref']===$c['tenant_ref']&&$key['key_id']===$c['executor_key_id'],'RESULT_HASH');
        $payload=\agents_json_decode($x['result_json'],262144,32);
        $result=$payload['data']['resultData']??null;self::need(is_array($result),'RESULT_DATA');
        self::need(($result['error']??null)===null&&($result['lastNodeExecuted']??null)===self::NODES[2],'RESULT_SUCCESS');
        $runs=$result['runData']??null;self::need(is_array($runs),'RESULT_RUNS');self::closed($runs,self::NODES);
        foreach(self::NODES as $node)
            self::need(is_array($runs[$node])&&count($runs[$node])===1
                &&($runs[$node][0]['executionStatus']??null)==='success','RESULT_NODE');
        $input=self::inputs($c,$task);
        $output=['schema'=>'AIA_NATIVE_LEAD_INGEST_V1']+$input+['operation'=>'lead.ingest',
            'lead_status'=>'PREPARED','crm_mutated'=>false,'ai_api_calls'=>0,'operational_authorization'=>false];
        foreach([1=>$input,2=>$output] as $index=>$wanted){
            $items=$runs[self::NODES[$index]][0]['data']['main']??null;
            self::need(is_array($items)&&count($items)===1&&is_array($items[0])&&count($items[0])===1
                &&self::exact($wanted,$items[0][0]['json']??null),'RESULT_BINDINGS');
        }
        $receipt=self::canonical($x['canonical_receipt'],self::RECEIPT,1024);self::historicalWindow($receipt);
        foreach(['tenant_ref','event_ref','worker_ref','attempt','authority_revision','payload_hash'] as $f)
            self::need($receipt[$f]===$c[$f],'RECEIPT_BINDING');
        self::need($receipt['schema']==='AIA_N8N_ACK_RECEIPT_V2'&&$receipt['issuer']==='SYNTHETIC_N8N_EXECUTOR'
            &&$receipt['audience']==='imagine-ai-agents'&&$receipt['result']==='NO_AI_COMPLETED_LAB','RECEIPT_DOMAIN');
        // Revoked current execution keys retain historical verification value.
        self::signature($x['canonical_receipt'],$x['signature_b64'],self::publicKey($key,'key_fingerprint'));
    }

    public static function observationHash(array $facts):string
    {
        $values=[];foreach(self::OBSERVATION_FIELDS as $field)$values[]=$facts[$field];
        return hash('sha256',self::OBSERVATION_PREFIX."\n".implode("\n",$values));
    }
    public static function reply(array $facts,array $q,string $keyId,string $key,int $now,int $authExpires):array
    {
        $expires=min($now+30,$q['expires_at'],$authExpires);self::need($expires>$now,'REPLY_EXPIRED');
        $r=['schema'=>'AUTO_IA_EXECUTION_STATUS_V1','issuer'=>'imagine-ai-agents','audience'=>'imagine-crm',
            'company_id'=>$facts['company_id'],'tenant_ref'=>$facts['tenant_ref'],'event_ref'=>$facts['event_ref'],
            'payload_hash'=>$facts['payload_hash'],'lead_id'=>$facts['lead_id'],'task_payload_hash'=>$facts['task_payload_hash'],
            'execution_state'=>$facts['execution_state'],'queue_state'=>$facts['queue_state'],'result_code'=>$facts['result_code'],
            'observation_hash'=>self::observationHash($facts),'attempt'=>$facts['attempt'],
            'request_ref'=>$q['request_ref'],'nonce_ref'=>$q['nonce_ref'],'issued_at'=>$now,'expires_at'=>$expires,'key_id'=>$keyId];
        $r['signature']=hash_hmac('sha256',self::RESPONSE_PREFIX."\n".implode("\n",$r),$key);return $r;
    }
}
