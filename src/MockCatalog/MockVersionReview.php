<?php
declare(strict_types=1);

namespace ImagineAI\MockCatalog;

use ImagineAI\Domain\{MockError, TenantContext};

/** Fictional review; not a human identity, compliance approval or real signature. */
final class MockVersionReview
{
    private bool $revoked = false;

    public function __construct(
        public readonly string $ref,
        public readonly string $snapshotRef,
        public readonly string $digest,
        public readonly TenantContext $context,
        public readonly string $datasetVersion,
        public readonly string $ruleVersion,
        public readonly string $reviewerRef,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly bool $passed,
    ) {
        foreach ([$ref, $snapshotRef, $datasetVersion, $ruleVersion, $reviewerRef] as $item) {
            MockAgentVersionSnapshot::syntheticRef($item);
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $digest) || $issuedAt < 0 || $expiresAt <= $issuedAt) {
            MockError::fail('REVIEW_DENIED');
        }
    }

    public function revoke(): void { $this->revoked = true; }

    public function assertValid(
        MockAgentVersionSnapshot $snapshot,
        TenantContext $context,
        string $datasetVersion,
        string $ruleVersion,
        int $now,
    ): void {
        if ($this->revoked || !$this->passed || $now < $this->issuedAt || $now >= $this->expiresAt
            || $this->snapshotRef !== $snapshot->ref || $this->digest !== $snapshot->digest
            || $this->context->key() !== $context->key()
            || $this->datasetVersion !== $datasetVersion || $this->ruleVersion !== $ruleVersion) {
            MockError::fail('REVIEW_DENIED');
        }
    }
}
