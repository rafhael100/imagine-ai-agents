<?php
declare(strict_types=1);

// Dependency-free local runner. No assertions disabled by PHP configuration.
spl_autoload_register(function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use ImagineAI\Domain\{TenantContext, Run, Intent, Approval, Attempt, Effect, Usage, MockError};
use ImagineAI\Mocks\{MockCrmReader, MockCrmEffectGateway, MockAiSuggestionProvider};

function check(bool $condition): void {
    if (!$condition) throw new RuntimeException('Unexpected result');
}
function denied(string $code, callable $action): void {
    try { $action(); } catch (MockError $error) { check($error->getMessage() === $code); return; }
    throw new RuntimeException('Expected rejection: ' . $code);
}
function context(string $tenant = 'SYNTHETIC_A', ?string $resource = null): TenantContext {
    return new TenantContext('SYNTHETIC_CRM', $tenant, 'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', $resource ?? $tenant . '_LEAD');
}
function fixture(?TenantContext $context = null, string $material = 'SYNTHETIC_TASK'): array {
    $run = new Run('SYNTHETIC_RUN', $context ?? context());
    $run->transition('EXECUTANDO');
    $intent = new Intent('SYNTHETIC_INTENT', $run->step('SYNTHETIC_STEP'), 'SYNTHETIC_KEY', 'mock.task.create', $material);
    $approval = new Approval('SYNTHETIC_APPROVAL', $intent, 'SYNTHETIC_REVIEWER', 10, 20, 'APROVADA');
    return [$run, $intent, $approval];
}
$tests = [];
$tests['tenant and missing resource have same denial'] = function (): void {
    $reader = new MockCrmReader();
    foreach (['SYNTHETIC_B_LEAD', 'SYNTHETIC_MISSING'] as $ref) {
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $reader->lead(context('SYNTHETIC_A', $ref), ['ref']));
    }
    check($reader->lead(context(), ['ref']) === ['ref' => 'SYNTHETIC_A_LEAD']);
    denied('FIELD_DENIED', fn() => $reader->lead(context(), ['private']));
};
$tests['suggestion deterministic, no writes, untrusted text grants nothing'] = function (): void {
    [$run, $intent] = fixture();
    $gateway = new MockCrmEffectGateway();
    $input = (new MockCrmReader())->attendance(context(), ['text']);
    $provider = new MockAiSuggestionProvider();
    check($provider->suggest($run, $input) === $provider->suggest($run, $input));
    check($gateway->count() === 0);
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, null, 15));
    check($gateway->status(context(), $intent)->state === 'NAO_SOLICITADO');
};
$tests['completed run does not confirm indeterminate effect'] = function (): void {
    [$run, $intent, $approval] = fixture();
    $effect = (new MockCrmEffectGateway('INDETERMINADO'))->submit($intent, $approval, 15);
    $run->transition('CONCLUIDA');
    check($run->state() === 'CONCLUIDA' && !$effect->isConfirmed());
    denied('PROOF_REQUIRED', fn() => new Effect($intent, 'CONFIRMADO'));
};
$tests['same intent returns identical effect; altered material conflicts'] = function (): void {
    [, $intent, $approval] = fixture();
    $gateway = new MockCrmEffectGateway();
    $first = $gateway->submit($intent, $approval, 15);
    check($first === $gateway->submit($intent, $approval, 15));
    [, $other, $otherApproval] = fixture(null, 'SYNTHETIC_CHANGED');
    denied('IDEMPOTENCY_CONFLICT', fn() => $gateway->submit($other, $otherApproval, 15));
    check($gateway->count() === 1);
};
$tests['missing revoked expired pending and mismatched approvals block writes'] = function (): void {
    [, $intent, $approval] = fixture();
    $gateway = new MockCrmEffectGateway();
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, null, 15));
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, $approval, 20));
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, $approval, 9));
    $approval->revoke();
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, $approval, 15));
    $pending = new Approval('SYNTHETIC_PENDING', $intent, 'SYNTHETIC_REVIEWER', 10, 20);
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, $pending, 15));
    [, $other, $otherApproval] = fixture(null, 'SYNTHETIC_OTHER');
    denied('APPROVAL_REQUIRED', fn() => $gateway->submit($intent, $otherApproval, 15));
    check($gateway->count() === 0);
};
$tests['cancel blocks new and existing steps without erasing effect'] = function (): void {
    [$run, $intent, $approval] = fixture();
    $gateway = new MockCrmEffectGateway();
    $effect = $gateway->submit($intent, $approval, 15);
    $run->transition('CANCELADA');
    denied('RUN_NOT_ACTIVE', fn() => $run->step('SYNTHETIC_NEXT'));
    denied('RUN_NOT_ACTIVE', fn() => $gateway->submit($intent, $approval, 15));
    denied('STATE_CONFLICT', fn() => $run->transition('EXECUTANDO'));
    check($gateway->status(context(), $intent) === $effect && $effect->isConfirmed());
};
$tests['unknown logical version rejected'] = function (): void {
    denied('VERSION_UNSUPPORTED', fn() => new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD', 'mock-v2'));
};
$tests['technical usage carries attempt and context without business writes'] = function (): void {
    [, $intent] = fixture();
    $attempt = new Attempt('SYNTHETIC_ATTEMPT', $intent, 'TIMEOUT');
    $usage = new Usage('SYNTHETIC_USAGE', $attempt, 3);
    $gateway = new MockCrmEffectGateway();
    check($usage->attempt === $attempt && $usage->context === $intent->context && $usage->units === 3);
    check($gateway->count() === 0); // No credit/billing surface exists in this model.
    denied('VALIDATION_FAILED', fn() => new Usage('SYNTHETIC_USAGE', $attempt, -1));
};
$tests['effect status cannot cross tenant and tenant keys stay separate'] = function (): void {
    [, $a, $aa] = fixture();
    [, $b, $ba] = fixture(context('SYNTHETIC_B'));
    $gateway = new MockCrmEffectGateway();
    $ea = $gateway->submit($a, $aa, 15);
    $eb = $gateway->submit($b, $ba, 15);
    check($ea->id !== $eb->id && $gateway->count() === 2);
    denied('CONTEXT_DENIED', fn() => $gateway->status(context('SYNTHETIC_B'), $a));
};
$tests['all outcomes retain their state and timeout never resubmits'] = function (): void {
    foreach (['CONFIRMADO', 'NEGADO', 'PENDENTE', 'INDETERMINADO'] as $state) {
        [, $intent, $approval] = fixture();
        $gateway = new MockCrmEffectGateway($state);
        $effect = $gateway->submit($intent, $approval, 15);
        new Attempt('SYNTHETIC_TIMEOUT', $intent, 'TIMEOUT');
        check($gateway->status(context(), $intent) === $effect);
        check($gateway->submit($intent, $approval, 15) === $effect);
        check($gateway->count() === 1 && $effect->state === $state);
    }
};
$tests = array_merge($tests, require __DIR__ . '/supervised.php', require __DIR__ . '/resilience.php', require __DIR__ . '/tool_dispatch.php', require __DIR__ . '/usage_quota.php', require __DIR__ . '/catalog_release.php', require __DIR__ . '/knowledge_context.php', require __DIR__ . '/evaluation_compare.php', require __DIR__ . '/review_handoff.php', require __DIR__ . '/trust_contract.php', require __DIR__ . '/crm_read_contract.php', require __DIR__ . '/crm_read_audit.php');
$failed = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo "PASS $name\n"; }
    catch (Throwable $error) { ++$failed; fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n"); }
}
echo count($tests) . " cases, $failed failures\n";
exit($failed === 0 ? 0 : 1);
