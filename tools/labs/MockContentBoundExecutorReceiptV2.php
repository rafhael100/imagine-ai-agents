<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgBoundOutboxAckLab.php';

use PDO;
use Throwable;

/**
 * P36 CI-only synthetic V2 executor receipt:
 * Ed25519 signs exact P7 payload digest AND trust revision.
 * The same hash is checked inside the P30/34 ACK transaction.
 * No real n8n identity, callback, billable API or customer data.
 */
final class MockContentBoundExecutorReceiptV2
{
    private const FIELDS=[
        'schema','issuer','audience','tenant_ref','event_ref','worker_ref',
        'attempt','authority_revision','payload_hash','issued_at','expires_at',
        'result'
    ];

    /**
     * @return array{decision:string,reason:string,payload_hash:?string,ai_calls:int,service_effects:int}
     */
    public static function verify(string $canonical,string $signatureB64,
        string $trustedPublicKey,string $tenant,string $event,string $worker,
        int $attempt,int $revision,int $now):array
    {
        if(strlen($canonical)<120 || strlen($canonical)>1024
            ||strlen($trustedPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            ||$attempt<1 ||$attempt>3||$revision<1
            ||$now<0 ||$now>4_102_444_800
            ||!self::ref($tenant)||!self::ref($event)||!self::ref($worker)){
            return self::deny('UNTRUSTED_RECEIPT_ARGUMENTS');
        }
        try{
            $r=json_decode($canonical,true,5,JSON_THROW_ON_ERROR);
            if(!is_array($r)||array_keys($r)!==self::FIELDS){
                return self::deny('RECEIPT_SCHEMA_NOT_CANONICAL');
            }
            $exact=json_encode($r,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(!hash_equals($exact,$canonical)){
                return self::deny('RECEIPT_BYTES_NOT_CANONICAL');
            }
            foreach(['schema','issuer','audience','tenant_ref','event_ref',
                'worker_ref','payload_hash','result'] as $field){
                if(!is_string($r[$field]))return self::deny('RECEIPT_FIELD_TYPE');
            }
            foreach(['attempt','authority_revision','issued_at','expires_at'] as $field){
                if(!is_int($r[$field]))return self::deny('RECEIPT_FIELD_TYPE');
            }
            if($r['schema']!=='AIA_N8N_ACK_RECEIPT_V2'
                ||$r['issuer']!=='SYNTHETIC_N8N_EXECUTOR'
                ||$r['audience']!=='imagine-ai-agents'
                ||$r['result']!=='NO_AI_COMPLETED_LAB'){
                return self::deny('RECEIPT_SCHEMA_OR_RESULT_DENIED');
            }
            if(!self::ref($r['tenant_ref'])||!self::ref($r['event_ref'])
                ||!self::ref($r['worker_ref'])
                ||preg_match('/\A[a-f0-9]{64}\z/D',$r['payload_hash'])!==1){
                return self::deny('RECEIPT_INVALID_DIGEST_OR_SCOPE');
            }
            if(!hash_equals($tenant,$r['tenant_ref'])
                ||!hash_equals($event,$r['event_ref'])
                ||!hash_equals($worker,$r['worker_ref'])
                ||$r['attempt']!==$attempt
                ||$r['authority_revision']!==$revision){
                return self::deny('RECEIPT_TRUSTED_BINDING_MISMATCH');
            }
            if($r['issued_at']<0 ||$r['expires_at']<=$r['issued_at']
                ||$r['expires_at']-$r['issued_at']>120
                ||$r['issued_at']>$now+5||$now>=$r['expires_at']){
                return self::deny('RECEIPT_INVALID_WINDOW');
            }
            $sig=base64_decode($signatureB64,true);
            if(!is_string($sig)||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($sig)!==$signatureB64
                ||!sodium_crypto_sign_verify_detached(
                    $sig,$canonical,$trustedPublicKey)){
                return self::deny('RECEIPT_SIGNATURE_NOT_TRUSTED');
            }
            return [
                'decision'=>'LAB_SIGNED_CONTENT_BOUND_RECEIPT',
                'reason'=>'V2_SYNTHETIC_ONLY',
                'payload_hash'=>$r['payload_hash'],
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            return self::deny('RECEIPT_INVALID_OR_VERIFICATION_ERROR');
        }
    }

    private static function ref(string $v):bool
    {
        return strlen($v)<=64&&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;
    }
    /** @return array{decision:string,reason:string,payload_hash:?string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,'payload_hash'=>null,
            'ai_calls'=>0,'service_effects'=>0];
    }
}

/**
 * Requires verified V2 bytes, test pubkey supplied separately, and exact
 * event hash verified under P30's row lock before ACK commit.
 */
final class PgContentBoundExecutorAckLab
{
    private PgBoundOutboxAckLab $ack;
    public function __construct(PDO $db)
    {
        $this->ack=new PgBoundOutboxAckLab($db);
    }

    /** @return array<string,mixed> */
    public function acknowledgeSignedV2(
        string $receipt,string $signatureB64,string $trustedPublicKey,
        string $tenant,string $event,string $worker,int $attempt,
        int $now,int $trustedRevision
    ):array {
        $verified=MockContentBoundExecutorReceiptV2::verify(
            $receipt,$signatureB64,$trustedPublicKey,$tenant,$event,$worker,
            $attempt,$trustedRevision,$now
        );
        if($verified['decision']!=='LAB_SIGNED_CONTENT_BOUND_RECEIPT'){
            return $verified;
        }
        $result=$this->ack->acknowledge(
            $tenant,$event,$worker,$attempt,$now,$trustedRevision,
            $verified['payload_hash']
        );
        if($result['decision']!=='LAB_ACKED_NO_DELIVERY_PROOF'){
            return $result;
        }
        return [
            'decision'=>'LAB_V2_ACKED_NO_EXTERNAL_DELIVERY_PROOF',
            'reason'=>'REAL_N8N_EXECUTOR_NOT_CONNECTED',
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }
}
