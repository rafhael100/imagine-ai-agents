<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F233 — one shared in-process synthetic nonce ledger.
 *
 * Demonstrates cross-object replay rejection only. It does NOT implement
 * distributed atomicity, persistent storage, IAM, authorization or CRM IO.
 * State is intentionally bounded and NEVER evicted on capacity exhaustion.
 */
final class MockSharedNonceLedger
{
    /** @var array<string, int> */
    private array $consumed = [];
    private bool $available = true;

    public function __construct(private readonly int $capacity = 64)
    {
        if ($capacity < 1 || $capacity > 512) {
            throw new \InvalidArgumentException('Synthetic capacity invalid');
        }
    }

    /**
     * @return bool True = one synthetic nonce was recorded, not CRM permission.
     */
    public function consumeOnce(string $issuer, string $nonce, int $now, int $expiresAt): bool
    {
        if (!$this->available
            || !self::synthetic($issuer) || !self::synthetic($nonce)
            || $now < 0 || $now > 4102444800
            || $expiresAt <= $now || $expiresAt > 4102444800
            || $expiresAt - $now > 30) {
            return false;
        }

        $key = hash('sha256', strlen($issuer) . ':' . $issuer . strlen($nonce) . ':' . $nonce);
        if (isset($this->consumed[$key]) || count($this->consumed) >= $this->capacity) {
            return false;
        }

        // A single PHP process executes this assignment sequentially. This
        // is not a compare-and-set in any external or concurrent data store.
        $this->consumed[$key] = $expiresAt;
        return true;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function consumedCount(): int
    {
        return count($this->consumed);
    }

    private static function synthetic(string $value): bool
    {
        return strlen($value) <= 90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D', $value) === 1;
    }
}
