<?php
declare(strict_types=1);

namespace ImagineAI\MockUsage;

use ImagineAI\Domain\{Attempt, MockError};

/** Explicit fake provider evidence: not a receipt, invoice, or external confirmation. */
final class MockUsageProof
{
    public function __construct(
        public readonly string $id,
        public readonly Attempt $attempt,
        public readonly int $units,
        public readonly string $source = 'SYNTHETIC_MOCK_PROVIDER',
    ) {
        if (!str_starts_with($id, 'SYNTHETIC_PROOF_')
            || strlen($id) <= strlen('SYNTHETIC_PROOF_')
            || strlen($id) > 128
            || $units < 0 || $units > 1_000_000
            || $source !== 'SYNTHETIC_MOCK_PROVIDER') MockError::fail('USAGE_PROOF_INVALID');
    }
}
