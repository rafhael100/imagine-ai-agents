<?php
declare(strict_types=1);

namespace ImagineAI\MockAudit;

use ImagineAI\Domain\{MockError, TenantContext};

/** Volatile, privacy-minimized laboratory journal. Not a production audit log. */
final class MockCrmReadAuditTrail
{
    private array $events = [];

    public function __construct(private readonly int $capacity = 32)
    {
        if ($capacity < 1 || $capacity > 100) MockError::fail('AUDIT_SCHEMA_DENIED');
    }

    public function assertRoom(): void
    {
        if (count($this->events) >= $this->capacity) MockError::fail('AUDIT_CAPACITY_DENIED');
    }

    public function append(TenantContext $context, string $requestRef, string $outcome, string $reason): void
    {
        $this->assertRoom();
        if ($context->product !== 'SYNTHETIC_CRM'
            || !preg_match('/^SYNTHETIC_[A-Z0-9_]{1,80}$/D', $requestRef)
            || !in_array($outcome, ['READ_SYNTHETIC_ONLY', 'DENIED'], true)
            || !in_array($reason, ['NONE', 'CONTEXT', 'TRUST', 'OWNER', 'REPLAY', 'RESOURCE', 'PROJECTION', 'OTHER'], true)
            || (($outcome === 'READ_SYNTHETIC_ONLY') !== ($reason === 'NONE'))) {
            MockError::fail('AUDIT_SCHEMA_DENIED');
        }
        $this->events[] = [
            'contract' => 'SYNTHETIC_AUDIT_V1',
            'operation' => 'crm.lead.read_minimal',
            'context_fingerprint' => hash('sha256', "SYNTHETIC_AUDIT_V1\0" . $context->key()),
            'request_fingerprint' => hash('sha256', "SYNTHETIC_AUDIT_V1\0" . $requestRef),
            'outcome' => $outcome,
            'reason' => $reason,
            'real_authorization' => false,
            'external_calls' => 0,
        ];
    }

    /** Returns a value copy; no mutable handle to the internal events. */
    public function entries(): array { return $this->events; }
    public function count(): int { return count($this->events); }
}
