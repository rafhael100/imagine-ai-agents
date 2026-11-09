<?php
declare(strict_types=1);

namespace ImagineAI\MockTrust;

use ImagineAI\Domain\MockError;

/**
 * Shared object within ONE PHP process. Models two gateways observing a single
 * volatile nonce/revocation store; NEVER a distributed cache, IAM or DB.
 */
final class MockSharedTrustState
{
    private array $nonces = [];
    private array $revokedDigests = [];
    private bool $available = true;

    public function setAvailable(bool $available): void { $this->available = $available; }

    public function revokeDigest(string $digest): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $digest)) {
            MockError::fail('TRUST_STATE_SCHEMA_DENIED');
        }
        $this->assertAvailable();
        $this->revokedDigests[$digest] = true;
    }

    public function consume(MockTrustEnvelope $envelope): void
    {
        $this->assertAvailable();
        if (isset($this->revokedDigests[$envelope->digest])) {
            MockError::fail('TRUST_DENIED');
        }
        // Scoped to service pair; nonce reuse with changed content is rejected.
        $nonceKey = hash('sha256', json_encode([
            $envelope->issuerRef, $envelope->audienceRef, $envelope->nonceRef,
        ], JSON_THROW_ON_ERROR));
        if (isset($this->nonces[$nonceKey])) {
            MockError::fail('TRUST_REPLAY_DENIED');
        }
        $this->nonces[$nonceKey] = true;
    }

    public function countConsumed(): int { return count($this->nonces); }

    private function assertAvailable(): void
    {
        if (!$this->available) MockError::fail('TRUST_STATE_UNAVAILABLE');
    }
}
