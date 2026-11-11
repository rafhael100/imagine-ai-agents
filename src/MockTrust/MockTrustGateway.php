<?php
declare(strict_types=1);

namespace ImagineAI\MockTrust;

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockCatalog\MockAgentVersionSnapshot;

/** Read-only mock trust boundary. No networking, issuer verification or real IAM. */
final class MockTrustGateway
{
    private const MAX_LOCAL_NONCES = 1024;
    private array $seenNonces = [];
    private readonly array $allowedScopes;

    /** @param list<string> $allowedScopes */
    public function __construct(
        public readonly string $issuerRef,
        public readonly string $audienceRef,
        array $allowedScopes,
        private readonly ?MockSharedTrustState $sharedState = null,
    ) {
        MockAgentVersionSnapshot::syntheticRef($issuerRef);
        MockAgentVersionSnapshot::syntheticRef($audienceRef);
        if (!array_is_list($allowedScopes) || count($allowedScopes) === 0
            || count($allowedScopes) > 2) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        foreach ($allowedScopes as $scope) {
            if (!is_string($scope) || !in_array($scope, [
                'SYNTHETIC_LEAD_READ', 'SYNTHETIC_SUMMARY_DRAFT',
            ], true)) MockError::fail('TRUST_SCHEMA_DENIED');
        }
        if (count(array_unique($allowedScopes)) !== count($allowedScopes)) {
            MockError::fail('TRUST_SCHEMA_DENIED');
        }
        $this->allowedScopes = $allowedScopes;
    }

    /** Monotonic revision of the optional, in-process shared state. */
    public function trustRevision(): int
    {
        return $this->sharedState?->revision() ?? 0;
    }

    public function assertTrustRevision(int $expected): void
    {
        if ($this->sharedState === null) {
            if ($expected !== 0) MockError::fail('TRUST_STATE_UNAVAILABLE');
            return;
        }
        $this->sharedState->assertRevision($expected);
    }

    /**
     * Read-only, non-consuming recheck after an untrusted reader returns.
     * This deliberately does not inspect the already-used local nonce.
     */
    public function recheck(?MockTrustEnvelope $envelope, ?MockOwnerScopeEvidence $owner,
        TenantContext $context, string $scope, int $now): void
    {
        if ($envelope === null || $owner === null) MockError::fail('TRUST_DENIED');
        $envelope->assertValid($context, $now);
        if ($envelope->issuerRef !== $this->issuerRef || $envelope->audienceRef !== $this->audienceRef
            || !in_array($scope, $this->allowedScopes, true)
            || !in_array($scope, $envelope->scopes, true)) {
            MockError::fail('TRUST_DENIED');
        }
        $owner->assertValid($envelope, $context, $scope, $now);
        if ($this->sharedState !== null) $this->sharedState->assertLive($envelope);
    }

    public function authorize(?MockTrustEnvelope $envelope, ?MockOwnerScopeEvidence $owner,
        TenantContext $context, string $scope, int $now): array
    {
        $this->recheck($envelope, $owner, $context, $scope, $now);
        if ($envelope === null) MockError::fail('TRUST_DENIED');
        if ($this->sharedState !== null) {
            $this->sharedState->consume($envelope);
        } else {
            // Historical single-gateway mock: no cross-instance durability.
            if (isset($this->seenNonces[$envelope->nonceRef])) MockError::fail('TRUST_REPLAY_DENIED');
            // Never evict old nonce records; exceeding the mock's memory budget
            // denies fresh requests rather than permitting replay.
            if (count($this->seenNonces) >= self::MAX_LOCAL_NONCES) {
                MockError::fail('TRUST_STATE_CAPACITY_DENIED');
            }
            $this->seenNonces[$envelope->nonceRef] = $envelope->digest;
        }
        return [
            'mode' => 'SYNTHETIC_ONLY', 'state' => 'SYNTHETIC_READ_ONLY',
            'digest' => $envelope->digest, 'scope' => $scope,
            'real_authorization' => false, 'external_calls' => 0,
        ];
    }
}
