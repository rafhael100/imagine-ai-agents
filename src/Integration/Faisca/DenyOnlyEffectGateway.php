<?php
declare(strict_types=1);

namespace ImagineAI\Integration\Faisca;

use ImagineAI\Contracts\CrmEffectGateway;
use ImagineAI\Domain\{Approval, Effect, Intent, MockError, TenantContext};

/** No CRM adapter, reader, transport or mutation exists behind this boundary. */
final class DenyOnlyEffectGateway implements CrmEffectGateway
{
    private int $attempts = 0;

    public function submit(Intent $intent, ?Approval $approval, int $now): Effect
    {
        ++$this->attempts;
        MockError::fail('FAISCA_EFFECTS_DISABLED');
    }

    public function status(TenantContext $context, Intent $intent): Effect
    {
        ++$this->attempts;
        MockError::fail('FAISCA_EFFECTS_DISABLED');
    }

    public function attemptCount(): int { return $this->attempts; }
}
