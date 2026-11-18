<?php
declare(strict_types=1);
namespace ImagineAI\Labs;

/**
 * P11 strict Ed25519 verifier of synthetic-only model usage receipts.
 * No I/O, no provider API and no key discovery from untrusted payloads.
 *
 * The trusted public key MUST be supplied by the CI harness, never by the
 * receipt itself. The test key does NOT authenticate a real provider.
 */
final class SyntheticUsageReceipt
{
    private const KEYS=[
        'contract','issuer','proof_ref','tenant_ref',
        'request_ref','observed','issued_at','expires_at',
    ];

    /**
     * @return ?array<string,mixed> A strictly canonical verified fixture.
     */
    public static function verify(
        string $bytes,string $signatureB64,string $trustedPublicKey,int $now
    ): ?array {
        if ($bytes==='' || strlen($bytes)>512
            || strlen($trustedPublicKey)!==32
            || $now<0 || $now>4102444800
            || !function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }
        try {
            $proof=json_decode($bytes,true,4,JSON_THROW_ON_ERROR);
            if (!is_array($proof) || array_keys($proof)!==self::KEYS
                || $proof['contract']!=='SYNTHETIC_AIA_USAGE_RECEIPT_V1'
                || $proof['issuer']!=='SYNTHETIC_PROVIDER_FIXTURE'
                || !self::ref($proof['proof_ref'])
                || !self::ref($proof['tenant_ref'])
                || !self::ref($proof['request_ref'])
                || !is_int($proof['observed'])
                || $proof['observed']<0 || $proof['observed']>5000
                || !is_int($proof['issued_at'])
                || !is_int($proof['expires_at'])
                || $proof['issued_at']<0
                || $proof['expires_at']<=$proof['issued_at']
                || $proof['expires_at']-$proof['issued_at']>60
                || $now<$proof['issued_at'] || $now>$proof['expires_at']
            ) {
                return null;
            }
            $canonical=json_encode(
                $proof,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
            );
            if (!is_string($canonical) || !hash_equals($canonical,$bytes)) {
                return null;
            }
            $sig=base64_decode($signatureB64,true);
            if (!is_string($sig) || strlen($sig)!==64) return null;
            if (!sodium_crypto_sign_verify_detached($sig,$bytes,$trustedPublicKey)) {
                return null;
            }
            return $proof;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Fixed-format synthetic reference; never an IAM tenant/user. */
    private static function ref(mixed $value): bool
    {
        return is_string($value) && strlen($value)<=90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$value)===1;
    }
}
