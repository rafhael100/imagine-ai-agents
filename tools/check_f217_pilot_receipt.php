<?php
declare(strict_types=1);

/**
 * F217: use actual in-memory pilot, coordinator, edge and facade classes.
 * Not real CRM, LLM, IAM, HTTP, customer data, or operational permission.
 */
use ImagineAI\Contracts\AiSuggestionProvider;
use ImagineAI\Domain\Run;
use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockBridge\MockCrmReadPublicFacade;
use ImagineAI\MockOrchestration\SupervisedCoordinator;
use ImagineAI\MockPilot\MockReadOnlyPilot;
use ImagineAI\MockTelemetry\MockCrmReadEdgeBoundary;
use ImagineAI\MockTelemetry\MockCrmReadEdgeCounters;
use ImagineAI\MockTrust\MockOwnerScopeEvidence;
use ImagineAI\MockTrust\MockTrustEnvelope;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\Mocks\MockAiSuggestionProvider;
use ImagineAI\Mocks\MockCrmReader;
use ImagineAI\Mocks\MockSupervisedGateway;

spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function f217Check(bool $ok): void
{
    if (!$ok) throw new RuntimeException('F217_PILOT_RECEIPT_DENIED');
}
function f217Wire(TenantContext $ctx): array
{
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref' => 'SYNTHETIC_F217_REQUEST',
        'operation' => 'crm.lead.read_minimal',
        'tenant_ref' => $ctx->tenant,
        'principal_ref' => $ctx->principal,
        'resource_ref' => $ctx->resource,
        'purpose_ref' => $ctx->purpose,
        'fields' => ['ref'],
    ];
}
function f217Proof(TenantContext $ctx, string $suffix): array
{
    $env = new MockTrustEnvelope(
        'SYNTHETIC_F217_ENV_' . $suffix,
        'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        $ctx, 'SYNTHETIC_F217_NONCE_' . $suffix,
        ['SYNTHETIC_LEAD_READ'], 10, 40
    );
    $owner = new MockOwnerScopeEvidence(
        'SYNTHETIC_F217_OWNER_' . $suffix,
        $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true
    );
    return [$env, $owner];
}
function f217Fixture(string $suffix, ?AiSuggestionProvider $provider = null): array
{
    $ctx = new TenantContext(
        'SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
        'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'
    );
    [$env, $owner] = f217Proof($ctx, $suffix);
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref']);
    $audit = new MockCrmReadAuditTrail();
    $counter = new MockCrmReadEdgeCounters();
    $facade = new MockCrmReadPublicFacade(
        new MockTrustGateway(
            'SYNTHETIC_AGENT_SERVICE',
            'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']
        ),
        new MockCrmReader(), $policy, $audit
    );
    $edge = new MockCrmReadEdgeBoundary($facade, $counter);
    $mutations = new MockSupervisedGateway();
    $coord = new SupervisedCoordinator(
        $ctx, $mutations, $provider ?? new MockAiSuggestionProvider()
    );
    $pilot = new MockReadOnlyPilot($ctx, $edge, $coord);
    return [$pilot, $edge, $counter, $audit, $coord,
            $mutations, $env, $owner, $ctx];
}
function f217Denial(): array
{
    return [
        'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'summary' => null,
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}
function f217Success(): array
{
    return [
        'contract' => 'SYNTHETIC_PILOT_READ_ONLY_V1',
        'state' => 'SUGGESTION_SYNTHETIC_ONLY',
        'summary' => 'SYNTHETIC summary proposal only',
        'real_authorization' => false,
        'external_calls' => 0,
        'write_calls' => 0,
    ];
}

final class F217UnsafeSuggestionProvider implements AiSuggestionProvider
{
    public function __construct(private readonly string $mode) {}
    public function suggest(Run $run, array $fields): array
    {
        if ($this->mode === 'THROW') {
            throw new RuntimeException('SYNTHETIC_PRIVATE_PROVIDER_ERROR');
        }
        return [
            'kind' => 'SYNTHETIC_SUGGESTION',
            'version' => 'mock-v1',
            'run' => $run->id,
            'source' => $run->context->resource,
            'text' => 'SYNTHETIC_FORGED_UNTRUSTED',
        ];
    }
}

try {
    // Positive path: preserve the ability to recheck the SAME successful
    // pilot read without a new nonce, counter slot, or CRM write.
    [$pilot, $edge, $counter, $audit, $coord, $mut,
        $env, $owner, $ctx] = f217Fixture('NORMAL');
    $wire = f217Wire($ctx);
    f217Check($pilot->suggest(
        $wire, $env, $owner, $ctx, 15, 'SYNTHETIC_F217_RUN_OK'
    ) === f217Success());
    f217Check($edge->recheck($wire, $env, $owner, $ctx, 16));
    f217Check($counter->snapshot()['attempts'] === 1
        && $audit->count() === 1 && $mut->submitCount() === 0);

    // A second pilot invocation that fails Run preflight must invalidate
    // the previous receipt WITHOUT using a new edge ticket or read.
    f217Check($pilot->suggest(
        $wire, $env, $owner, $ctx, 17, 'RUN_INVALID'
    ) === f217Denial());
    f217Check(!$edge->recheck($wire, $env, $owner, $ctx, 18));
    f217Check($counter->snapshot()['attempts'] === 1
        && $audit->count() === 1);
    [$freshEnv, $freshOwner] = f217Proof($ctx, 'RECOVERY');
    f217Check($pilot->suggest(
        $wire, $freshEnv, $freshOwner, $ctx, 19,
        'SYNTHETIC_F217_RUN_RECOVERED'
    ) === f217Success());
    f217Check($edge->recheck($wire, $freshEnv, $freshOwner, $ctx, 20));
    f217Check($counter->snapshot()['attempts'] === 2
        && $audit->count() === 2 && $mut->submitCount() === 0);

    // The synthetic read completed but a provider threw. The overall pilot
    // denies and the post-read success cannot be rechecked as valid.
    [$pilot, $edge, $counter, $audit, $coord, $mut,
        $env, $owner, $ctx] = f217Fixture(
            'PROVIDER_THROW', new F217UnsafeSuggestionProvider('THROW'));
    $wire = f217Wire($ctx);
    f217Check($pilot->suggest(
        $wire, $env, $owner, $ctx, 15, 'SYNTHETIC_F217_RUN_THROW'
    ) === f217Denial());
    f217Check(!$edge->recheck($wire, $env, $owner, $ctx, 16));
    f217Check($counter->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1
        && $audit->count() === 1 && $mut->submitCount() === 0);

    // Malicious but schema-shaped suggestion text is rejected and cannot
    // leave a valid proof after the pilot returned a generic denial.
    [$pilot, $edge, $counter, $audit, $coord, $mut,
        $env, $owner, $ctx] = f217Fixture(
            'PROVIDER_FORGE', new F217UnsafeSuggestionProvider('FORGE'));
    $wire = f217Wire($ctx);
    f217Check($pilot->suggest(
        $wire, $env, $owner, $ctx, 15, 'SYNTHETIC_F217_RUN_FORGE'
    ) === f217Denial());
    f217Check(!$edge->recheck($wire, $env, $owner, $ctx, 16));
    f217Check($counter->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1
        && $audit->count() === 1 && $mut->submitCount() === 0);

    echo 'F217_PILOT_READ_RECEIPT_PASS checks=4 '
        . 'operational_decision=NO_GO real_authorization=false '
        . 'external_calls=0 write_calls=0' . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'F217_PILOT_READ_RECEIPT_DENIED operational_decision=NO_GO'
        . PHP_EOL);
    exit(1);
}
