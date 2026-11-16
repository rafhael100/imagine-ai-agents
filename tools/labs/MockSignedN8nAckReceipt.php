<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgBoundOutboxAckLab.php';

use PDO;
use Throwable;

/**
 * P35: Ed25519 signature verifier for a STRICTLY SYNTHETIC n8n receipt.
 * Test public key is provided out-of-band; never trust key material or
 * authorization claims carried inside the receipt itself.
 *
 * This is NOT a real n8n identity, webhook or external acknowledgement.
 */
final class MockSignedN8nAckReceipt
{
    private const FIELDS=[
        'schema','issuer','audience','tenant_ref','event_ref',
        'worker_ref','attempt','issued_at','expires_at','result'
    ];

    /**
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public static function verify(string $canonical,string $signatureB64,
        string $trustedPublicKey,string $expectedTenant,string $expectedEvent,
        string $expectedWorker,int $expectedAttempt,int $now):array
    {
        if(strlen($canonical)<50 || strlen($canonical)>1024
            ||strlen($trustedPublicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            ||$expectedAttempt<1 || $expectedAttempt>3
            ||$now<0 || $now>4_102_444_800
            ||!self::ref($expectedTenant)||!self::ref($expectedEvent)
            ||!self::ref($expectedWorker)){
            return self::deny('UNTRUSTED_RECEIPT_ARGUMENTS');
        }
        try{
            $record=json_decode($canonical,true,5,JSON_THROW_ON_ERROR);
            if(!is_array($record) || array_keys($record)!==self::FIELDS){
                return self::deny('RECEIPT_SCHEMA_NOT_CANONICAL');
            }
            $reencoded=json_encode($record,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(!hash_equals($reencoded,$canonical)){
                return self::deny('RECEIPT_BYTES_NOT_CANONICAL');
            }
            foreach(['schema','issuer','audience','tenant_ref','event_ref',
                     'worker_ref','result'] as $field){
                if(!is_string($record[$field])){
                    return self::deny('RECEIPT_FIELD_TYPE');
                }
            }
            foreach(['attempt','issued_at','expires_at'] as $field){
                if(!is_int($record[$field]))return self::deny('RECEIPT_FIELD_TYPE');
            }
            if($record['schema']!=='AIA_N8N_ACK_RECEIPT_V1'
                ||$record['issuer']!=='SYNTHETIC_N8N_EXECUTOR'
                ||$record['audience']!=='imagine-ai-agents'
                ||$record['result']!=='NO_AI_COMPLETED_LAB'){
                return self::deny('RECEIPT_SCHEMA_OR_RESULT');
            }
            foreach(['tenant_ref','event_ref','worker_ref'] as $field){
                if(!self::ref($record[$field]))return self::deny('RECEIPT_SCOPE_INVALID');
            }
            if(!hash_equals($expectedTenant,$record['tenant_ref'])
                ||!hash_equals($expectedEvent,$record['event_ref'])
                ||!hash_equals($expectedWorker,$record['worker_ref'])
                ||$record['attempt']!==$expectedAttempt){
                return self::deny('RECEIPT_TRUSTED_BINDING_MISMATCH');
            }
            if($record['issued_at']<0 ||$record['expires_at']<=$record['issued_at']
                ||$record['expires_at']-$record['issued_at']>120
                ||$record['issued_at']>$now+5||$now>=$record['expires_at']){
                return self::deny('RECEIPT_INVALID_WINDOW');
            }
            $signature=base64_decode($signatureB64,true);
            if(!is_string($signature)
                ||strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($signature)!==$signatureB64
                ||!sodium_crypto_sign_verify_detached($signature,$canonical,
                    $trustedPublicKey)){
                return self::deny('RECEIPT_SIGNATURE_NOT_TRUSTED');
            }
            return [
                'decision'=>'LAB_SIGNED_RECEIPT_NOT_OPERATIONAL',
                'reason'=>'SYNTHETIC_ED25519_IS_NOT_REAL_N8N_IAM',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }catch(Throwable){
            return self::deny('RECEIPT_INVALID_OR_VERIFICATION_ERROR');
        }
    }

    private static function ref(string $value):bool
    {
        return strlen($value)<=64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$value)===1;
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason):array
    {
        return ['decision'=>'DENY','reason'=>$reason,'ai_calls'=>0,'service_effects'=>0];
    }
}

/**
 * P35 opt-in signature-first ACK facade. In addition to Ed25519 fixture
 * verification, P30/P34 still check DB event, lease, worker, attempt and
 * authority revision under the same PostgreSQL transaction.
 */
final class PgSignedExecutorAckLab
{
    private PgBoundOutboxAckLab $boundAck;

    public function __construct(PDO $db)
    {
        $this->boundAck=new PgBoundOutboxAckLab($db);
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    public function acknowledgeSigned(string $canonical,string $signatureB64,
        string $trustedPublicKey,string $tenant,string $event,string $worker,
        int $attempt,int $now,int $trustedRevision):array
    {
        $verified=MockSignedN8nAckReceipt::verify(
            $canonical,$signatureB64,$trustedPublicKey,
            $tenant,$event,$worker,$attempt,$now
        );
        if($verified['decision']!=='LAB_SIGNED_RECEIPT_NOT_OPERATIONAL'){
            return $verified;
        }
        $result=$this->boundAck->acknowledge(
            $tenant,$event,$worker,$attempt,$now,$trustedRevision
        );
        if($result['decision']!=='LAB_ACKED_NO_DELIVERY_PROOF')return $result;
        return [
            'decision'=>'LAB_SIGNED_ACK_RECORDED_NO_N8N_DELIVERY_PROOF',
            'reason'=>'REAL_N8N_IDENTITY_AND_TRANSPORT_NOT_CONFIGURED',
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }
}
