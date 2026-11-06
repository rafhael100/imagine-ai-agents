<?php
declare(strict_types=1);

namespace ImagineAI\Contracts;

use ImagineAI\Domain\{TenantContext, Intent, Approval, Effect};
/** In-memory simulation only. */
interface CrmEffectGateway {
    public function submit(Intent $intent, ?Approval $approval, int $now): Effect;
    public function status(TenantContext $context, Intent $intent): Effect;
}
