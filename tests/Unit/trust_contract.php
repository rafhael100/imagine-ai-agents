<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};

function f111Fixture(?TenantContext $ctx = null, ?string $issuer = null,
    ?string $audience = null, ?string $nonce = null, ?array $scopes = null): array
{
    $context = $ctx ?? context();
    $envelope = new MockTrustEnvelope('SYNTHETIC_CONTEXT_A',
        $issuer ?? 'SYNTHETIC_AGENT_SERVICE', $audience ?? 'SYNTHETIC_CRM_API',
        $context, $nonce ?? 'SYNTHETIC_NONCE_A', $scopes ?? ['SYNTHETIC_LEAD_READ'], 10, 40);
    $owner = new MockOwnerScopeEvidence('SYNTHETIC_OWNER_PROOF_A', $envelope->digest,
        $context, 'SYNTHETIC_LEAD_READ', 10, 40, true);
    $gate = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ']);
    return [$envelope, $owner, $gate, $context];
}
return [
    'U11-01 exact fake issuer audience scope context owner grants read-only simulation' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        $result = $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        check($result['state'] === 'SYNTHETIC_READ_ONLY' && $result['mode'] === 'SYNTHETIC_ONLY');
        check($result['real_authorization'] === false && $result['external_calls'] === 0);
        check($result['digest'] === $env->digest);
    },
    'U11-02 missing envelope and missing owner evidence deny by default' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        denied('TRUST_DENIED', fn () => $gate->authorize(null, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        denied('TRUST_DENIED', fn () => $gate->authorize($env, null, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-03 issuer mismatch never accepted from untrusted context' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture(null, 'SYNTHETIC_OTHER_ISSUER');
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-04 audience confusion is denied even when issuer matches' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture(null, null, 'SYNTHETIC_PERFORMANCE_API');
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-05 tenant substitution cannot authorize another company resource' => function (): void {
        [$env, $owner, $gate] = f111Fixture();
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner,
            context('SYNTHETIC_B'), 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-06 user purpose and resource substitution all deny' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        foreach ([
            new TenantContext($ctx->product, $ctx->tenant, 'SYNTHETIC_OTHER_USER', $ctx->purpose, $ctx->resource),
            new TenantContext($ctx->product, $ctx->tenant, $ctx->principal, 'SYNTHETIC_OTHER_PURPOSE', $ctx->resource),
            new TenantContext($ctx->product, $ctx->tenant, $ctx->principal, $ctx->purpose, 'SYNTHETIC_OTHER_RESOURCE'),
        ] as $other) {
            denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $other, 'SYNTHETIC_LEAD_READ', 15));
        }
    },
    'U11-07 scopes not covered by gateway or envelope are denied' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_SUMMARY_DRAFT', 15));
        $gate2 = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_SUMMARY_DRAFT']);
        denied('TRUST_DENIED', fn () => $gate2->authorize($env, $owner, $ctx, 'SYNTHETIC_SUMMARY_DRAFT', 15));
    },
    'U11-08 write and mutation scopes are unsupported by schema' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_TASK_CREATE', 15));
        denied('TRUST_SCHEMA_DENIED', fn () => new MockTrustEnvelope('SYNTHETIC_BAD',
            'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $ctx, 'SYNTHETIC_NONCE_B',
            ['SYNTHETIC_TASK_CREATE'], 10, 40));
    },
    'U11-09 expired and not-yet-valid envelope or proof are denied' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        foreach ([9, 40] as $time) {
            denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', $time));
        }
        $limited = new MockOwnerScopeEvidence('SYNTHETIC_SHORT', $env->digest,
            $ctx, 'SYNTHETIC_LEAD_READ', 10, 16, true);
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $limited, $ctx, 'SYNTHETIC_LEAD_READ', 20));
    },
    'U11-10 envelope revocation prevents authorization after creation' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        $env->revoke();
        denied('TRUST_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-11 owner evidence revocation or refusal prevents authorization' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        $owner->revoke();
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        $deniedProof = new MockOwnerScopeEvidence('SYNTHETIC_REFUSAL', $env->digest,
            $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, false);
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $deniedProof, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-12 owner evidence from another envelope cannot authorize' => function (): void {
        [$env, , $gate, $ctx] = f111Fixture();
        $foreign = new MockOwnerScopeEvidence('SYNTHETIC_FOREIGN', str_repeat('a', 64),
            $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $foreign, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-13 reusing a consumed nonce is denied even for identical read' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        denied('TRUST_REPLAY_DENIED', fn () => $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-14 nonce reuse after changing request body is still denied' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        $gate->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $changed = new MockTrustEnvelope('SYNTHETIC_CONTEXT_B', $env->issuerRef,
            $env->audienceRef, $ctx, $env->nonceRef, ['SYNTHETIC_LEAD_READ'], 10, 40);
        $proof = new MockOwnerScopeEvidence('SYNTHETIC_OWNER_B', $changed->digest,
            $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        denied('TRUST_REPLAY_DENIED', fn () => $gate->authorize($changed, $proof, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U11-15 malformed schema duplicate scopes and excessive TTL denied' => function (): void {
        [$env, $owner, $gate, $ctx] = f111Fixture();
        denied('TRUST_SCHEMA_DENIED', fn () => new MockTrustEnvelope('SYNTHETIC_BAD',
            $env->issuerRef, $env->audienceRef, $ctx, 'SYNTHETIC_NONCE_C',
            ['SYNTHETIC_LEAD_READ', 'SYNTHETIC_LEAD_READ'], 10, 40));
        denied('TRUST_SCHEMA_DENIED', fn () => new MockTrustEnvelope('SYNTHETIC_BAD',
            $env->issuerRef, $env->audienceRef, $ctx, 'SYNTHETIC_NONCE_C',
            ['SYNTHETIC_LEAD_READ'], 10, 131));
        denied('OWNER_SCHEMA_DENIED', fn () => new MockOwnerScopeEvidence('SYNTHETIC_BAD',
            'invalid-digest', $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true));
    },
    'U11-16 owner context and scope mismatch are denied by owner boundary' => function (): void {
        [$env, , $gate, $ctx] = f111Fixture();
        $otherContext = context('SYNTHETIC_B');
        $foreign = new MockOwnerScopeEvidence('SYNTHETIC_OTHER_OWNER', $env->digest,
            $otherContext, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $foreign, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        $wrongScope = new MockOwnerScopeEvidence('SYNTHETIC_SCOPE_OTHER', $env->digest,
            $ctx, 'SYNTHETIC_SUMMARY_DRAFT', 10, 40, true);
        denied('OWNER_DENIED', fn () => $gate->authorize($env, $wrongScope, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
];
