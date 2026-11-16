<?php
declare(strict_types=1);

require_once __DIR__ . '/AutoIaRouter.php';

use ImagineAI\AutomationFirst\AutoIaRouter;

$checks = 0;
function assertRoute(bool $ok, string $case): void
{
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException('AUTO_IA_01_FAIL ' . $case);
}
function fixture(string $product, string $operation, string $reason = 'NONE'): array
{
    return [
        'schema' => 'AUTO_IA_EVENT_V1',
        'product' => $product,
        'operation' => $operation,
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'event_ref' => 'SYNTHETIC_EVENT_001',
        'semantic_reason' => $reason,
    ];
}

$catalog = AutoIaRouter::matrix();
assertRoute(count($catalog) === 8, 'all eight Imagine projects');
$nonAi = 0;
$potentialAi = 0;
foreach ($catalog as $product => $operations) {
    assertRoute(count($operations) >= 3, 'operations ' . $product);
    foreach ($operations as $operation => $kind) {
        $event = fixture($product, $operation, $kind === 'NO_AI' ? 'NONE' : $kind);
        $result = AutoIaRouter::classify($event);
        assertRoute(array_keys($result) === ['decision', 'reason', 'provider_calls', 'model_tokens'], 'closed result');
        assertRoute($result['provider_calls'] === 0 && $result['model_tokens'] === 0, 'no provider/token cost');
        assertRoute($result === AutoIaRouter::classify($event), 'deterministic replay');
        if ($kind === 'NO_AI') {
            $nonAi++;
            assertRoute($result['decision'] === 'NO_AI', 'deterministic operation ' . $operation);
            $event['semantic_reason'] = 'CREATIVE_DRAFT';
            assertRoute(AutoIaRouter::classify($event)['decision'] === 'NO_AI', 'untrusted AI escalation blocked');
        } else {
            $potentialAi++;
            assertRoute($result['decision'] === 'AI_REVIEW_REQUIRED', 'requires external gateway ' . $operation);
            assertRoute($result['reason'] === 'GATEWAY_AUTHORIZATION_NOT_IMPLEMENTED', 'never a grant');
            $event['semantic_reason'] = 'NONE';
            assertRoute(AutoIaRouter::classify($event)['decision'] === 'DENY', 'missing justification');
        }
    }
}
assertRoute($nonAi === 16 && $potentialAi === 8, 'full expected matrix');
$ok = fixture('imagine-crm', 'lead.ingest');
$invalid = [];
$invalid[] = ['schema' => 'AUTO_IA_EVENT_V2'] + array_diff_key($ok, ['schema' => true]);
$invalid[] = array_merge($ok, ['provider' => 'external.example']);
$invalid[] = array_merge($ok, ['tenant_ref' => 'TENANT_A']);
$invalid[] = array_merge($ok, ['event_ref' => 'SYNTHETIC_../SECRET']);
$invalid[] = array_merge($ok, ['product' => 'imagine-unknown']);
$invalid[] = array_merge($ok, ['operation' => 'billing.charge']);
$invalid[] = array_merge($ok, ['semantic_reason' => ['NONE']]);
$invalid[] = array_merge($ok, ['event_ref' => '']);
$invalid[] = array_merge($ok, ['tenant_ref' => 'SYNTHETIC_' . str_repeat('A', 60)]);
$invalid[] = array_diff_key($ok, ['semantic_reason' => true]);
$invalid[] = array_merge($ok, ['api_key' => 'SYNTHETIC_SECRET_NEVER_ACCEPTED']);
foreach ($invalid as $idx => $event) {
    assertRoute(AutoIaRouter::classify($event)['decision'] === 'DENY', 'fail-closed invalid ' . $idx);
}
echo 'AUTO_IA_01_PASS checks=' . $checks . ' no_network=1 no_provider=1 no_billing=1' . PHP_EOL;
