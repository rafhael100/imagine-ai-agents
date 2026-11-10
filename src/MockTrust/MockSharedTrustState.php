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
    // A failed revocation cannot be quietly forgotten by restoring availability.
    private bool $revocationOverflowed = false;

    public function __construct(private readonly int $capacity = 1024)
    {
        if ($capacity < 1 || $capacity > 4096) MockError::fail('TRUST_STATE_SCHEMA_DENIED');
    }

    public function setAvailable(bool $available): void { $this->available = $available; }

    public function revokeDigest(string $digest): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $digest)) {
            MockError::fail('TRUST_STATE_SCHEMA_DENIED');
        }
        $this->assertAvailable();
        // Repeat revocation remains idempotent even after capacity is reached.
        if (!isset($this->revokedDigests[$digest]) && count($this->revokedDigests) >= $this->capacity) {
            // Refusing a revocation yet continuing to authorize would fail open.
            // Permanently disable this volatile fixture for this process.
            $this->revocationOverflowed = true;
            MockError::fail('TRUST_STATE_CAPACITY_DENIED');
        }
        $this->revokedDigests[$digest] = true;
    }

    /**
     * Recheck revocation and store health without consuming a second nonce.
     * Only an in-process fixture; never a distributed authorization check.
     */
    public function assertLive(MockTrustEnvelope $envelope): void
    {
        $this->assertAvailable();
        if (isset($this->revokedDigests[$envelope->digest])) {
            MockError::fail('TRUST_DENIED');
        }
    }

    public function consume(MockTrustEnvelope $envelope): void
    {
        $this->assertLive($envelope);
        // Scoped to service pair; nonce reuse with changed content is rejected.
        $nonceKey = hash('sha256', json_encode([
            $envelope->issuerRef, $envelope->audienceRef, $envelope->nonceRef,
        ], JSON_THROW_ON_ERROR));
        if (isset($this->nonces[$nonceKey])) {
            MockError::fail('TRUST_REPLAY_DENIED');
        }
        // Never evict an earlier nonce: eviction could make replay possible.
        if (count($this->nonces) >= $this->capacity) MockError::fail('TRUST_STATE_CAPACITY_DENIED');
        $this->nonces[$nonceKey] = true;
    }

    public function countConsumed(): int { return count($this->nonces); }
    public function countRevoked(): int { return count($this->revokedDigests); }

    private function assertAvailable(): void
    {
        if (!$this->available || $this->revocationOverflowed) MockError::fail('TRUST_STATE_UNAVAILABLE');
    }
}
