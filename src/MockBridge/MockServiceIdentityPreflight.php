<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F231 -- offline service-identity preflight candidate.
 *
 * Verifies a detached Ed25519 signature with an INJECTED trusted public key.
 * A successful result is signature evidence ONLY. It is not IAM, a user login,
 * CRM membership, resource ACL, an API endpoint, or an operational ALLOW.
 *
 * Only SYNTHETIC claims are accepted. No key generation, secrets, network,
 * database, transport, token minting, or production authorization here.
 */
final class MockServiceIdentityPreflight
{
    private const DOMAIN = "SYNTHETIC_AIA_SERVICE_ED25519_V1\n";
    private const CONTRACT = 'SYNTHETIC_AIA_SERVICE_IDENTITY_V1';
    private const ISSUER = 'SYNTHETIC_AGENT_SERVICE';
    private const AUDIENCE = 'SYNTHETIC_CRM_API';
    private const KEYS = [
        'contract', 'issuer', 'audience', 'key_id', 'subject',
        'tenant_ref', 'operation', 'nonce', 'issued_at', 'expires_at',
        'request_sha256',
    ];

    /** @var array<string,true> Bounded instance-local, NOT distributed replay state. */
    private array $consumed = [];
    private bool $available = true;

    public function __construct(private readonly int $capacity = 64)
    {
        if ($capacity < 1 || $capacity > 512) {
            throw new \InvalidArgumentException('Invalid synthetic verification capacity');
        }
    }

    /**
     * @param array<string,array{public_key:string,enabled:bool}> $trustedKeys
     *   Public verification keys come from trusted configuration, never claims.
     *
     * @return bool True means signature and *synthetic* request binding
     *              verified once; it grants no permissions.
     */
    public function verifyOnce(
        string $requestJson,
        string $claimsJson,
        string $signatureBase64,
        array $trustedKeys,
        string $expectedTenant,
        int $now,
    ): bool {
        try {
            if (!$this->available || !function_exists('sodium_crypto_sign_verify_detached')
                || $now < 0 || $now > 4102444800
                || $requestJson === '' || strlen($requestJson) > 1024
                || $claimsJson === '' || strlen($claimsJson) > 1024
                || strlen($signatureBase64) !== 88
                || count($trustedKeys) < 1 || count($trustedKeys) > 8
                || !self::syntheticRef($expectedTenant, 1)) {
                return false;
            }

            $request = MockCrmReadRequest::decodeCanonicalJson($requestJson);
            $claims = json_decode($claimsJson, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($claims) || array_keys($claims) !== self::KEYS
                || $claims['contract'] !== self::CONTRACT
                || $claims['issuer'] !== self::ISSUER
                || $claims['audience'] !== self::AUDIENCE
                || $claims['subject'] !== 'SYNTHETIC_AGENTS_READ_ONLY'
                || $claims['operation'] !== 'crm.lead.read_minimal'
                || !is_string($claims['key_id'])
                || !self::syntheticRef($claims['key_id'], 3)
                || !is_string($claims['nonce'])
                || !self::syntheticRef($claims['nonce'], 4)
                || !is_string($claims['tenant_ref'])
                || $claims['tenant_ref'] !== $expectedTenant
                || $request['tenant_ref'] !== $expectedTenant
                || !is_int($claims['issued_at'])
                || !is_int($claims['expires_at'])
                || $claims['issued_at'] < 0
                || $claims['expires_at'] <= $claims['issued_at']
                || $claims['expires_at'] > 4102444800
                || $claims['expires_at'] - $claims['issued_at'] > 30
                || $now < $claims['issued_at']
                || $now >= $claims['expires_at']
                || !is_string($claims['request_sha256'])
                || preg_match('/\A[0-9a-f]{64}\z/D', $claims['request_sha256']) !== 1
                || !hash_equals(hash('sha256', $requestJson), $claims['request_sha256'])) {
                return false;
            }

            $encoded = json_encode(
                $claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            if (!hash_equals($encoded, $claimsJson)) {
                return false; // duplicate properties, escapes, extra whitespace, noncanonical order
            }

            $selected = $trustedKeys[$claims['key_id']] ?? null;
            if (!is_array($selected)
                || array_keys($selected) !== ['public_key', 'enabled']
                || ($selected['enabled'] ?? null) !== true
                || !is_string($selected['public_key'])
                || strlen($selected['public_key']) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return false;
            }
            $signature = base64_decode($signatureBase64, true);
            if (!is_string($signature)
                || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
                || base64_encode($signature) !== $signatureBase64
                || !sodium_crypto_sign_verify_detached(
                    $signature, self::DOMAIN . $claimsJson, $selected['public_key']
                )) {
                return false;
            }

            // Local fail closed; the nonce is issuer-wide (not tenant-scoped)
            // so a cross-tenant replay does not regain admission.
            $fingerprint = hash('sha256', $claims['issuer'] . "\n" . $claims['nonce']);
            if (isset($this->consumed[$fingerprint])
                || count($this->consumed) >= $this->capacity) {
                return false;
            }
            $this->consumed[$fingerprint] = true;
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function countConsumed(): int
    {
        return count($this->consumed);
    }

    private static function syntheticRef(string $value, int $minBody): bool
    {
        return strlen($value) <= 90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{' . $minBody . ',80}\z/D', $value) === 1;
    }
}
