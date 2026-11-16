<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F235: SYNTHETIC evidence-only composition of F231 + F232 + F233.
 * No successful result ever authorizes a CRM read, HTTP request, user
 * impersonation, tenant membership, or any production operation.
 */
final class MockSyntheticEvidenceGate
{
    public function __construct(
        private readonly MockServiceIdentityPreflight $identity,
        private readonly MockSharedNonceLedger $replay
    ) {}

    /**
     * Scope fixture must be injected by a trusted TEST harness, never copied
     * from the request. Return true means all mock proofs matched once.
     * @param array<string,mixed> $trustedKeys
     * @param array<string,mixed> $expectedScope
     */
    public function checkEvidenceOnce(
        string $canonicalRequest,
        string $canonicalClaims,
        string $signatureBase64,
        array $trustedKeys,
        array $expectedScope,
        string $tenant,
        int $now
    ): bool {
        try {
            // Scope BEFORE cryptographic admission: invalid tenant/lead/fields
            // must not consume a local F231 nonce or shared F233 record.
            if (!MockSyntheticScopePreflight::matches($canonicalRequest, $expectedScope)
                || !is_string($expectedScope['tenant_ref'] ?? null)
                || $expectedScope['tenant_ref'] !== $tenant) {
                return false;
            }
            if (strlen($canonicalClaims) > 1024 || $canonicalClaims === '') {
                return false;
            }
            $claims = json_decode($canonicalClaims, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($claims)
                || !is_string($claims['issuer'] ?? null)
                || !is_string($claims['nonce'] ?? null)
                || !is_int($claims['expires_at'] ?? null)) {
                return false;
            }
            // F231 validates the entire signed claims contract and the
            // byte-exact request before this synthetic replay reservation.
            if (!$this->identity->verifyOnce(
                $canonicalRequest, $canonicalClaims, $signatureBase64,
                $trustedKeys, $tenant, $now
            )) {
                return false;
            }
            return $this->replay->consumeOnce(
                $claims['issuer'], $claims['nonce'], $now, $claims['expires_at']
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
