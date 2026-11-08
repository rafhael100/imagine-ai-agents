<?php
declare(strict_types=1);

namespace ImagineAI\MockResilience;

use ImagineAI\Contracts\CrmEffectGateway;
use ImagineAI\Domain\{Approval, Effect, Intent, MockError, TenantContext};

/** Deterministic, process-local fault injection: no network, timers or retries. */
final class MockFaultGateway implements CrmEffectGateway
{
    private int $submits = 0;
    private int $statuses = 0;

    /** @param list<bool> $loseSubmitReplies @param list<bool> $failStatusQueries */
    public function __construct(
        private readonly CrmEffectGateway $inner,
        private readonly array $loseSubmitReplies = [],
        private readonly array $failStatusQueries = [],
    ) {
        foreach ([$loseSubmitReplies, $failStatusQueries] as $plan) {
            if (!array_is_list($plan) || count($plan) > 64) MockError::fail('VALIDATION_FAILED');
            foreach ($plan as $flag) if (!is_bool($flag)) MockError::fail('VALIDATION_FAILED');
        }
    }

    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        // Always delegate validation to the existing fake. The fault loses only the REPLY.
        ++$this->submits;
        $effect = $this->inner->submit($intent, $approval, $now);
        if ($this->loseSubmitReplies[$this->submits - 1] ?? false) {
            MockError::fail('SYNTHETIC_TIMEOUT');
        }
        return $effect;
    }

    public function status(TenantContext $context, Intent $intent): Effect
    {
        // Verify the complete context even if a planned fault masks the inner response.
        $context->assertSame($intent->context);
        ++$this->statuses;
        if ($this->failStatusQueries[$this->statuses - 1] ?? false) {
            MockError::fail('SYNTHETIC_STATUS_UNAVAILABLE');
        }
        return $this->inner->status($context, $intent);
    }

    public function submitCount(): int { return $this->submits; }
    public function statusCount(): int { return $this->statuses; }
}
