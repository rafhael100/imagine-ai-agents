<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/LabAutomationTaskContract.php';

use ImagineAI\MockOrchestration\CapturedLeadTaskContract;
use PDO;
use Throwable;

/**
 * P45: read-only owner-service authorization recheck before *every*
 * execution lease and signed ACK in an existing PostgreSQL transaction.
 *
 * This is not an operational CRM identity, consent service or permission
 * authority. Keys, grants and timestamps are all CI fixtures.
 */
final class PgOwnerExecutionGuardLab
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

    public function grantCurrent(string $company,string $tenant,string $event,
        string $payloadHash,int $now):bool
    {
        if(!$this->db->inTransaction()
            ||preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$company)!==1
            ||!self::ref($tenant)||!self::ref($event)
            ||preg_match('/\A[a-f0-9]{64}\z/D',$payloadHash)!==1
            ||$now<0||$now>4102444800) return false;
        try{
            // Derive the operation from the protected persisted job, never
            // from a caller's choice or the owner decision being verified.
            $job=$this->db->prepare('SELECT canonical_event,event_hash FROM public.aia_lab_n8n_outbox_p6 '
                .'WHERE tenant_ref=:tenant AND event_ref=:event FOR SHARE');
            $job->execute([':tenant'=>$tenant,':event'=>$event]);$row=$job->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||!hash_equals($row['event_hash'],$payloadHash)
                ||!hash_equals(hash('sha256',$row['canonical_event']),$payloadHash))return false;
            $task=LabAutomationTaskContract::decode($row['canonical_event']);
            $operation=LabAutomationTaskContract::operation($row['canonical_event']);
            if($task===null||$task['tenant_ref']!==$tenant||$task['event_ref']!==$event
                ||!in_array($operation,['run.status.project','lead.ingest'],true)
                ||($operation==='lead.ingest'&&$task['company_id']!==$company))return false;
            if($operation==='lead.ingest')$now=time();
            // Both rows stay locked until the caller's P28/P30 commit.
            $q=$this->db->prepare(<<<'SQL'
SELECT grant_ref,payload_hash,signed_decision_sha256,
 signer_key_id,owner_revision,canonical_decision,signature_b64
FROM aia_p44_lab.consumed_owner_grants
WHERE company_id=CAST(:company AS uuid)
  AND tenant_ref=:tenant AND event_ref=:event FOR SHARE
SQL);
            $q->execute([':company'=>$company,':tenant'=>$tenant,':event'=>$event]);
            $grant=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($grant)
                ||!hash_equals($payloadHash,$grant['payload_hash'])
                ||!is_string($grant['canonical_decision'])
                ||!is_string($grant['signature_b64'])
                ||!hash_equals(hash('sha256',$grant['canonical_decision']),
                    $grant['signed_decision_sha256']))return false;
            $d=json_decode($grant['canonical_decision'],true,5,JSON_THROW_ON_ERROR);
            if(!is_array($d)||array_keys($d)!==self::FIELDS)return false;
            if(!hash_equals($grant['canonical_decision'],json_encode($d,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)))
                return false;
            foreach(['schema','issuer','audience','company_id','tenant_ref','event_ref',
                'grant_ref','operation','payload_hash','decision','key_id'] as $field)
                if(!is_string($d[$field]))return false;
            foreach(['owner_revision','issued_at','expires_at'] as $field)
                if(!is_int($d[$field]))return false;
            if($d['schema']!=='AIA_OWNER_DECISION_V1'
                ||$d['issuer']!=='SYNTHETIC_CRM_POLICY_OWNER'
                ||$d['audience']!=='imagine-ai-agents'
                ||$d['operation']!==$operation
                ||$d['decision']!=='ALLOW_NO_AI_LAB'
                ||$d['company_id']!==$company
                ||$d['tenant_ref']!==$tenant
                ||$d['event_ref']!==$event
                ||$d['grant_ref']!==$grant['grant_ref']
                ||$d['key_id']!==$grant['signer_key_id']
                ||$d['owner_revision']!==(int)$grant['owner_revision']
                ||!hash_equals($d['payload_hash'],$payloadHash)
                ||$d['issued_at']>$now+5
                ||$d['issued_at']<$now-60
                ||$d['expires_at']<=$now
                ||$d['expires_at']<=$d['issued_at']
                ||$d['expires_at']-$d['issued_at']>120)return false;
            $k=$this->db->prepare(<<<'SQL'
SELECT public_key_b64,fingerprint,active,revision
FROM aia_p44_lab.owner_keys
WHERE company_id=CAST(:company AS uuid) AND key_id=:key FOR SHARE
SQL);
            $k->execute([':company'=>$company,':key'=>$grant['signer_key_id']]);
            $signer=$k->fetch(PDO::FETCH_ASSOC);
            if(!is_array($signer)
                ||!in_array($signer['active'],[true,1,'1','t'],true)
                ||(int)$signer['revision']!==$d['owner_revision'])return false;
            $pub=base64_decode($signer['public_key_b64'],true);
            $sig=base64_decode($grant['signature_b64'],true);
            if($operation==='lead.ingest'){
                $current=time();
                if(!CapturedLeadTaskContract::fresh($task,$current)
                    ||$d['issued_at']>$current+5||$d['issued_at']<$current-60
                    ||$d['expires_at']<=$current)return false;
            }
            return is_string($pub)
                &&strlen($pub)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                &&hash_equals(hash('sha256',$pub),$signer['fingerprint'])
                &&is_string($sig)
                &&strlen($sig)===SODIUM_CRYPTO_SIGN_BYTES
                &&base64_encode($sig)===$grant['signature_b64']
                &&sodium_crypto_sign_verify_detached(
                    $sig,$grant['canonical_decision'],$pub
                );
        }catch(Throwable){return false;}
    }

    private static function ref(string $s):bool {
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
}
