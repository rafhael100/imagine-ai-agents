<?php
declare(strict_types=1);
namespace ImagineAI\MockOrchestration;

/** Closed CI task derived from an immutable CRM admission, never a Run event.
 * source_payload_hash identifies received bytes; SHA(task) identifies the job.
 * The task alone grants neither staging nor execution authority.
 */
final class CapturedLeadTaskContract
{
    private const KEYS=['contract','event_ref','tenant_ref','company_id','lead_id',
        'source_payload_hash','kind','occurred_at'];

    public static function valid(array $task):bool
    {
        if(array_keys($task)!==self::KEYS
            ||$task['contract']!=='SYNTHETIC_CRM_LEAD_INGEST_V1'
            ||$task['kind']!=='crm.lead.ingest'
            ||!is_int($task['occurred_at'])||$task['occurred_at']<0
            ||$task['occurred_at']>4102444800)return false;
        foreach(['event_ref','tenant_ref'] as $key)
            if(!is_string($task[$key])||preg_match(
                '/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$task[$key])!==1)return false;
        if(!is_string($task['company_id'])||preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',
            $task['company_id'])!==1)return false;
        if(!is_string($task['lead_id'])||preg_match(
            '/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D',
            $task['lead_id'])!==1)return false;
        return is_string($task['source_payload_hash'])&&preg_match(
            '/\A[a-f0-9]{64}\z/D',$task['source_payload_hash'])===1;
    }

    public static function encode(array $task):?string
    {
        if(!self::valid($task))return null;
        try{
            $bytes=json_encode($task,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
                |JSON_UNESCAPED_UNICODE);
            return strlen($bytes)<=512?$bytes:null;
        }catch(\Throwable){return null;}
    }

    public static function decode(string $bytes):?array
    {
        if($bytes===''||strlen($bytes)>512)return null;
        try{
            $task=json_decode($bytes,true,4,JSON_THROW_ON_ERROR);
            if(!is_array($task))return null;
            $canonical=self::encode($task);
            // Exact re-encoding also rejects duplicate/escaped duplicate keys,
            // alternate key order, whitespace, floats and Unicode aliases.
            return $canonical!==null&&hash_equals($canonical,$bytes)?$task:null;
        }catch(\Throwable){return null;}
    }

    public static function fresh(array $task,int $now):bool
    {
        return self::valid($task)&&$now>=0&&$now<=4102444800
            &&$task['occurred_at']<=$now+5&&$task['occurred_at']+60>$now;
    }

    /** A deterministic proposal only; source proof and owner grant are separate. */
    public static function propose(string $bytes,string $tenant,int $now):array
    {
        $task=self::decode($bytes);
        if($task===null||$task['tenant_ref']!==$tenant||!self::fresh($task,$now))
            return ['decision'=>'DENY','reason'=>'CAPTURED_LEAD_TASK_INVALID_OR_STALE',
                'proposal'=>null,'ai_calls'=>0,'service_effects'=>0];
        $hash=hash('sha256',$bytes);
        return ['decision'=>'LAB_NO_AI_PROPOSAL_NOT_SIGNED',
            'reason'=>'REQUIRES_IMMUTABLE_CAPTURE_AND_SEPARATE_OWNER_DECISION',
            'proposal'=>['issuer'=>'imagine-crm','audience'=>'imagine-ai-agents',
                'product'=>'imagine-crm','operation'=>'lead.ingest',
                'tenant_ref'=>$tenant,'event_ref'=>$task['event_ref'],
                'nonce_ref'=>'SYNTHETIC_LEAD_NONCE_'.strtoupper(substr($hash,0,24)),
                'request_ref'=>'SYNTHETIC_LEAD_REQUEST_'.strtoupper(substr($hash,0,24)),
                'payload_hash'=>$hash],
            'ai_calls'=>0,'service_effects'=>0];
    }
}
