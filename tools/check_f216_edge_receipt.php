<?php
declare(strict_types=1);

/**
 * F216 — executable PHP regression against actual Agents facade and edge.
 * Not an HTTP endpoint, real CRM authorization or operational staging test.
 */
use ImagineAI\Contracts\CrmReader;
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockBridge\MockCrmReadPublicFacade;
use ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary;
use ImagineAI\MockTelemetry\MockCrmReadEdgeCounters;
use ImagineAI\MockTrust\MockTrustEnvelope;
use ImagineAI\MockTrust\MockOwnerScopeEvidence;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\Mocks\MockCrmReader;

spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f216Check(bool $condition): void
{
    if (!$condition) throw new RuntimeException('F216_DENIED');
}
function f216Denied(): array
{
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'projection' => null,
        'real_authorization' => false,
        'external_calls' => 0,
    ];
}
function f216Wire(TenantContext $ctx): array
{
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F216_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $ctx->tenant,
        'principal_ref' => $ctx->principal,
        'resource_ref' => $ctx->resource,
        'purpose_ref' => $ctx->purpose,
        'fields' => ['ref'],
    ];
}
function f216Proof(TenantContext $ctx, string $suffix): array
{
    $env = new MockTrustEnvelope(
        'SYNTHETIC_F216_ENV_' . $suffix,
        'SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API',
        $ctx,
        'SYNTHETIC_F216_NONCE_' . $suffix,
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_F216_OWNER_' . $suffix,
        $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true
    );
    return [$env, $owner];
}
function f216Fixture(
    string $suffix,
    ?MockCrmReadEdgeCounters $counters = null,
    ?CrmReader $reader = null
): array {
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A',
        'SYNTHETIC_PRINCIPAL', 'SYNTHETIC_SUMMARY',
        'SYNTHETIC_A_LEAD'
    );
    [$env, $owner] = f216Proof($ctx, $suffix);
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref']);
    $audit = new MockCrmReadAuditTrail();
    $counter = $counters ?? new MockCrmReadEdgeCounters();
    $facade = new MockCrmReadPublicFacade(
        new MockTrustGateway(
            'SYNTHETIC_AGENT_SERVICE',
            'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ']
        ),
        $reader ?? new MockCrmReader(),
        $policy,
        $audit
    );
    return [
        new MockCrmReadEdgeBoundary($facade, $counter),
        $counter, $audit, $env, $owner, $ctx
    ];
}

/** Untrusted adapter capable of prematurely completing a local counter. */
final class F216CounterTamperingReader implements CrmReader
{
    public function __construct(
        private readonly MockCrmReadEdgeCounters $counter
    ) {}
    public function lead(TenantContext $context, array $fields): array
    {
        // Simulated telemetry corruption from a malicious in-process adapter.
        $this->counter->finish(1, 'SCHEMA_DENIED');
        return ['ref' => $context->resource];
    }
    public function attendance(TenantContext $context, array $fields): array
    {
        return [];
    }
}

/** Untrusted adapter attempting an in-process nested edge submission. */
final class F216NestedReader implements CrmReader
{
    public function __construct(private readonly Closure $duringRead) {}
    public function lead(TenantContext $context, array $fields): array
    {
        ($this->duringRead)();
        return ['ref' => $context->resource];
    }
    public function attendance(TenantContext $context, array $fields): array
    {
        return [];
    }
}

try {
    // Pre-parser denial after a successful read MUST invalidate its receipt.
    [$edge, $counter, $audit, $env, $owner, $ctx] =
        f216Fixture('SCHEMA');
    $wire = f216Wire($ctx);
    f216Check($edge->handle($wire, $env, $owner, $ctx, 15)['state']
        === 'READ_SYNTHETIC_ONLY');
    f216Check($edge->recheck($wire, $env, $owner, $ctx, 16));
    $untrusted = $wire;
    $untrusted['role'] = 'SYNTHETIC_ADMIN';
    f216Check($edge->handle($untrusted, $env, $owner, $ctx, 16)
        === f216Denied());
    f216Check(!$edge->recheck($wire, $env, $owner, $ctx, 17));
    f216Check($audit->count() === 1);
    f216Check($counter->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);
    [$env2, $owner2] = f216Proof($ctx, 'SCHEMA_FRESH');
    f216Check($edge->handle($wire, $env2, $owner2, $ctx, 17)['state']
        === 'READ_SYNTHETIC_ONLY');
    f216Check($edge->recheck($wire, $env2, $owner2, $ctx, 18));
    f216Check($audit->count() === 2);

    // A full edge counter denies a fresh attempt without reusing the old one.
    [$edge, $counter, $audit, $env, $owner, $ctx] =
        f216Fixture('CAPACITY', new MockCrmReadEdgeCounters(1));
    $wire = f216Wire($ctx);
    f216Check($edge->handle($wire, $env, $owner, $ctx, 15)['state']
        === 'READ_SYNTHETIC_ONLY');
    f216Check($edge->recheck($wire, $env, $owner, $ctx, 16));
    f216Check($edge->handle($wire, $env, $owner, $ctx, 17)
        === f216Denied());
    f216Check(!$edge->recheck($wire, $env, $owner, $ctx, 18));
    f216Check($counter->snapshot()['attempts'] === 1
        && $audit->count() === 1);

    // A facade success that is denied later by telemetry MUST NOT leave a
    // usable read receipt, even if the audit already recorded a fake success.
    $counter = new MockCrmReadEdgeCounters();
    [$edge, $counter, $audit, $env, $owner, $ctx] =
        f216Fixture('TELEMETRY', $counter,
            new F216CounterTamperingReader($counter));
    $wire = f216Wire($ctx);
    $denied = $edge->handle($wire, $env, $owner, $ctx, 15);
    f216Check($denied === f216Denied());
    f216Check(!$edge->recheck($wire, $env, $owner, $ctx, 16));
    f216Check($audit->count() === 1
        && $counter->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);

    // A nested edge attempt rejected by the parser still taints the outer
    // in-flight facade read. No reader/counter/audit can produce a success.
    $nestedEdge = null;
    $nestedEnv = null;
    $nestedOwner = null;
    $nestedContext = null;
    $reader = new F216NestedReader(static function () use (
        &$nestedEdge, &$nestedEnv, &$nestedOwner, &$nestedContext
    ): void {
        $bad = f216Wire($nestedContext);
        $bad['is_admin'] = true;
        f216Check($nestedEdge->handle(
            $bad, $nestedEnv, $nestedOwner, $nestedContext, 15
        ) === f216Denied());
    });
    [$nestedEdge, $counter, $audit,
        $nestedEnv, $nestedOwner, $nestedContext] =
        f216Fixture('REENTRY', null, $reader);
    $wire = f216Wire($nestedContext);
    f216Check($nestedEdge->handle(
        $wire, $nestedEnv, $nestedOwner, $nestedContext, 15
    ) === f216Denied());
    f216Check(!$nestedEdge->recheck(
        $wire, $nestedEnv, $nestedOwner, $nestedContext, 16
    ));
    f216Check($audit->count() === 1);
    f216Check($counter->snapshot()['outcomes']['SCHEMA_DENIED'] === 1
        && $counter->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);

    echo 'F216_EDGE_RECEIPT_FAIL_CLOSED_PASS checks=4 '
        . 'real_authorization=false external_calls=0 operational_decision=NO_GO'
        . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,
        "F216_EDGE_RECEIPT_FAIL_CLOSED_DENIED operational_decision=NO_GO\n");
    exit(1);
}
