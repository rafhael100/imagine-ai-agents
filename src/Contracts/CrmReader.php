<?php
declare(strict_types=1);

namespace ImagineAI\Contracts;

use ImagineAI\Domain\TenantContext;
/** Local mock contract, not homologated with CRM. */
interface CrmReader {
    public function lead(TenantContext $context, array $fields): array;
    public function attendance(TenantContext $context, array $fields): array;
}
