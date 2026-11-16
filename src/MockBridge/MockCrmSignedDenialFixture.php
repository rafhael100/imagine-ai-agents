<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

use ImagineAI\Domain\MockError;

/**
 * F230: OFFLINE, PUBLIC-KEY-OF-TEST-ONLY proof for a CRM denial response.
 *
 * This is not a CRM server, credential exchange, authentication or IAM.
 * No network, SQL, client data, shared replay store or authorization.
 * The fixture can seal only a canonical, uniform DENIAL and can never
 * turn a denial or a synthetic positive read into a production grant.
 */
final class MockCrmSignedDenialFixture
{
    private const DOMAIN = 'SYNTHETIC_CRM_AIA_RESPONSE_HMAC_V1';
    private const CONTRACT = 'SYNTHETIC_CRM_AIA_RESPONSE_PROOF_V1';
    private const ISSUER = 'SYNTHETIC_CRM_TEST_SERVICE';
    private const AUDIENCE = 'SYNTHETIC_AGENT_TEST_SERVICE';
    private const PREFIX = 'SYNTHETIC_PUBLIC_TEST_ONLY_';
    private const KEYS = [
        'contract', 'issuer', 'audience', 'tenant_ref', 'nonce',
        'issued_at', 'expires_at', 'request_sha256', 'body_sha256', 'signature',
    ];

    /** @var array<string,true> */
    private array $seen = [];
    private bool $available = true;

    public function __construct(private readonly int $capacity = 64)
    {
        if ($capacity < 1 || $capacity > 512) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
    }

    /**
     * @param array<string,mixed> $crmDenial
     * @return array{body:string,proof:array<string,mixed>}
     */
    public static function issue(
        string $requestBody,
        array $crmDenial,
        string $nonce,
        int $issuedAt,
        int $expiresAt,
        string $publicFixtureKey,
    ): array {
        $request = MockCrmReadRequest::decodeCanonicalJson($requestBody);
        if ($crmDenial !== MockCrmPublicResponseContract::denial()
            || !self::allowsKey($publicFixtureKey)
            || !self::validNonce($nonce)
            || !self::validClock($issuedAt, $expiresAt)) {
            MockError::fail('BRIDGE_SCHEMA_DENIED');
        }
        $responseBody = self::canonicalDenial();
        $proof = [
            'contract' => self::CONTRACT,
            'issuer' => self::ISSUER,
            'audience' => self::AUDIENCE,
            'tenant_ref' => $request['tenant_ref'],
            'nonce' => $nonce,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'request_sha256' => hash('sha256', $requestBody),
            'body_sha256' => hash('sha256', $responseBody),
        ];
        $proof['signature'] = hash_hmac(
            'sha256', self::message($proof), $publicFixtureKey
        );
        return ['body' => $responseBody, 'proof' => $proof];
    }

    /**
     * A valid test MAC is NOT authorization. Even successful verification
     * only acknowledges a DENIAL. Replay state is in this PHP object alone.
     *
     * @param array<mixed> $proof
     */
    public function verifyOnce(
        string $requestBody,
        string $responseBody,
        array $proof,
        string $expectedTenant,
        string $expectedNonce,
        int $now,
        string $publicFixtureKey,
    ): bool {
        try {
            if (!$this->available
                || strlen($requestBody) > 1024
                || strlen($responseBody) > 512
                || array_keys($proof) !== self::KEYS
                || !self::allowsKey($publicFixtureKey)
                || !self::validNonce($expectedNonce)
                || !self::validTenant($expectedTenant)
                || !is_string($proof['nonce'])
                || $proof['nonce'] !== $expectedNonce
                || !is_string($proof['tenant_ref'])
                || $proof['tenant_ref'] !== $expectedTenant
                || $proof['contract'] !== self::CONTRACT
                || $proof['issuer'] !== self::ISSUER
                || $proof['audience'] !== self::AUDIENCE
                || !is_int($proof['issued_at'])
                || !is_int($proof['expires_at'])
                || !self::validClock($proof['issued_at'], $proof['expires_at'])
                || $now < $proof['issued_at']
                || $now >= $proof['expires_at']
                || !is_string($proof['request_sha256'])
                || !is_string($proof['body_sha256'])
                || !is_string($proof['signature'])) {
                return false;
            }
            $request = MockCrmReadRequest::decodeCanonicalJson($requestBody);
            if ($request['tenant_ref'] !== $expectedTenant
                || !hash_equals(self::canonicalDenial(), $responseBody)
                || !self::isDigest($proof['request_sha256'])
                || !self::isDigest($proof['body_sha256'])
                || !self::isDigest($proof['signature'])
                || !hash_equals(hash('sha256', $requestBody), $proof['request_sha256'])
                || !hash_equals(hash('sha256', $responseBody), $proof['body_sha256'])
                || !hash_equals(
                    hash_hmac(
                        'sha256',
                        self::message(array_slice($proof, 0, -1)),
                        $publicFixtureKey
                    ),
                    $proof['signature']
                )
                || isset($this->seen[$expectedNonce])
                || count($this->seen) >= $this->capacity) {
                return false;
            }

            $this->seen[$expectedNonce] = true;
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
        return count($this->seen);
    }

    private static function canonicalDenial(): string
    {
        return json_encode(
            MockCrmPublicResponseContract::denial(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    private static function allowsKey(string $key): bool
    {
        return strlen($key) >= 48 && strlen($key) <= 128
            && str_starts_with($key, self::PREFIX);
    }

    private static function validNonce(string $nonce): bool
    {
        return strlen($nonce) <= 90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{4,80}\z/D', $nonce) === 1;
    }

    private static function validTenant(string $tenant): bool
    {
        return strlen($tenant) <= 90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D', $tenant) === 1;
    }

    private static function validClock(int $issued, int $expires): bool
    {
        return $issued >= 0 && $expires > $issued
            && $expires <= 4102444800 && $expires - $issued <= 30;
    }

    private static function isDigest(string $digest): bool
    {
        return strlen($digest) === 64
            && preg_match('/\A[0-9a-f]{64}\z/D', $digest) === 1;
    }

    /** @param array<string,mixed> $claims */
    private static function message(array $claims): string
    {
        return implode("\n", [self::DOMAIN, ...array_values($claims)]);
    }
}
