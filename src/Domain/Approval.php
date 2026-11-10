<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Explicit clock and exact immutable intent binding; synthetic authority only. */
final class Approval
{
    private const MAX_REF_BYTES = 128;
    private string $state;
    private readonly string $signature;
    public function __construct(
        public readonly string $id,
        Intent $intent,
        public readonly string $approver,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        string $state = 'PENDENTE',
    ) {
        foreach ([$id, $approver] as $ref) {
            if (!str_starts_with($ref, 'SYNTHETIC_')
                || strlen($ref) <= strlen('SYNTHETIC_')
                || strlen($ref) > self::MAX_REF_BYTES) {
                MockError::fail('INVALID_SYNTHETIC_REF');
            }
        }
        if (!in_array($state, ['PENDENTE', 'APROVADA', 'RECUSADA', 'EXPIRADA', 'REVOGADA'], true)
            || $expiresAt <= $issuedAt) MockError::fail('VALIDATION_FAILED');
        $this->state = $state;
        $this->signature = $intent->signature();
    }
    public function revoke(): void { $this->state = 'REVOGADA'; }
    public function assertValid(Intent $intent, int $now): void {
        if ($this->state !== 'APROVADA' || $now < $this->issuedAt || $now >= $this->expiresAt
            || $this->signature !== $intent->signature()) MockError::fail('APPROVAL_REQUIRED');
    }
}
