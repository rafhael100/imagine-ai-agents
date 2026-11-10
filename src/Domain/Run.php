<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Minimal mock lifecycle. Completing a run never confirms an effect. */
final class Run
{
    private const MAX_REF_BYTES = 80;
    private string $state = 'RECEBIDA';
    public function __construct(public readonly string $id, public readonly TenantContext $context) {
        // Enforce the same bound even for standalone legacy domain instances.
        // Coordinator-only checks would leave this construction path unbounded.
        if (!str_starts_with($id, 'SYNTHETIC_')
            || strlen($id) <= strlen('SYNTHETIC_')
            || strlen($id) > self::MAX_REF_BYTES) {
            MockError::fail('INVALID_SYNTHETIC_REF');
        }
    }
    public function state(): string { return $this->state; }
    public function transition(string $next): void
    {
        $edges = [
            'RECEBIDA' => ['EXECUTANDO', 'CANCELADA'],
            'EXECUTANDO' => ['AGUARDANDO', 'CONCLUIDA', 'FALHOU', 'CANCELADA'],
            'AGUARDANDO' => ['EXECUTANDO', 'FALHOU', 'CANCELADA'],
        ];
        if (!in_array($next, $edges[$this->state] ?? [], true)) MockError::fail('STATE_CONFLICT');
        $this->state = $next;
    }
    public function assertActive(): void
    {
        if ($this->state !== 'EXECUTANDO') MockError::fail('RUN_NOT_ACTIVE');
    }
    public function step(string $id): Step { $this->assertActive(); return new Step($id, $this); }
}
