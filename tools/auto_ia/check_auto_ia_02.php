<?php
declare(strict_types=1);

require_once __DIR__ . '/MockAiBudgetGate.php';

use ImagineAI\AutomationFirst\MockAiBudgetGate;

$checks = 0;
function assertBudget(bool $ok, string $name): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('AUTO_IA_02_FAIL ' . $name);
}
function eventFixture(string $operation = 'lead.message.interpret'): array
{
    return [
        'schema' => 'AUTO_IA_EVENT_V1',
        'product' => 'imagine-crm',
        'operation' => $operation,
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'event_ref' => 'SYNTHETIC_EVENT_001',
        'semantic_reason' => $operation === 'lead.ingest' ? 'NONE' : 'UNSTRUCTURED_LANGUAGE',
    ];
}
function budgetFixture(): array
{
    return [
        'schema' => 'AUTO_IA_BUDGET_FIXTURE_V1',
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'event_ref' => 'SYNTHETIC_EVENT_001',
        'product' => 'imagine-crm',
        'operation' => 'lead.message.interpret',
        'policy_ref' => 'SYNTHETIC_POLICY_01',
        'issued_at' => 100,
        'expires_at' => 200,
        'now' => 150,
        'max_model_tokens' => 1000,
        'requested_model_tokens' => 400,
        'available_model_tokens' => 900,
        'estimated_cost_minor' => 15,
        'remaining_budget_minor' => 20,
        'enabled' => true,
    ];
}
function deniedBudget(array $event, ?array $budget, string $reason): void
{
    $result = MockAiBudgetGate::evaluate($event, $budget);
    assertBudget($result['decision'] === 'DENY', 'deny ' . $reason);
    assertBudget($result['reason'] === $reason, 'reason ' . $reason);
    assertBudget($result['provider_calls'] === 0 && $result['model_tokens'] === 0 && $result['billed_minor'] === 0, 'no spend ' . $reason);
}
$event = eventFixture();
$good = budgetFixture();
$pass = MockAiBudgetGate::evaluate($event, $good);
assertBudget($pass === [
    'decision' => 'LAB_BUDGET_WITHIN_LIMIT',
    'reason' => 'EXTERNAL_AUTHORIZATION_NOT_IMPLEMENTED',
    'provider_calls' => 0,
    'model_tokens' => 0,
    'billed_minor' => 0,
], 'bounded lab is not permission or spend');
assertBudget(MockAiBudgetGate::evaluate($event, $good) === $pass, 'pure replay no reservation');
assertBudget(MockAiBudgetGate::evaluate(eventFixture('lead.ingest'), null)['decision'] === 'NO_AI',
    'no budget needed for deterministic workflow');
deniedBudget($event, null, 'BUDGET_FIXTURE_MISSING');
deniedBudget(eventFixture('lead.unknown'), $good, 'ROUTE_NOT_ELIGIBLE');
foreach ([
    ['tenant_ref', 'SYNTHETIC_TENANT_B', 'SCOPE_MISMATCH'],
    ['event_ref', 'SYNTHETIC_EVENT_002', 'SCOPE_MISMATCH'],
    ['product', 'imagine-portal', 'SCOPE_MISMATCH'],
    ['operation', 'support.message.interpret', 'SCOPE_MISMATCH'],
    ['enabled', false, 'POLICY_DISABLED'],
    ['expires_at', 149, 'POLICY_EXPIRED_OR_INVALID'],
    ['issued_at', 151, 'POLICY_EXPIRED_OR_INVALID'],
    ['expires_at', 4000, 'POLICY_EXPIRED_OR_INVALID'],
    ['max_model_tokens', 0, 'TOKEN_BUDGET_EXCEEDED'],
    ['requested_model_tokens', 1001, 'TOKEN_BUDGET_EXCEEDED'],
    ['available_model_tokens', 399, 'TOKEN_BUDGET_EXCEEDED'],
    ['estimated_cost_minor', 21, 'COST_BUDGET_EXCEEDED'],
    ['remaining_budget_minor', 14, 'COST_BUDGET_EXCEEDED'],
    ['estimated_cost_minor', 0, 'COST_BUDGET_EXCEEDED'],
    ['policy_ref', 'TOKEN_SECRET', 'INVALID_BUDGET_FIXTURE'],
    ['requested_model_tokens', -1, 'INVALID_BUDGET_FIXTURE'],
    ['remaining_budget_minor', '20', 'INVALID_BUDGET_FIXTURE'],
] as [$key, $value, $reason]) {
    $bad = $good;
    $bad[$key] = $value;
    deniedBudget($event, $bad, $reason);
}
$bad = $good;
$bad['provider_api_key'] = 'SYNTHETIC_SECRET_SHOULD_BE_DENIED';
deniedBudget($event, $bad, 'INVALID_BUDGET_FIXTURE');
$bad = $good;
unset($bad['event_ref']);
deniedBudget($event, $bad, 'INVALID_BUDGET_FIXTURE');
echo "AUTO_IA_02_PASS checks={$checks} no_network=1 no_reservation=1 no_billing=1\n";
