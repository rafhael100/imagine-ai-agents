<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockCatalog\{MockAgentVersionSnapshot, MockAgentTemplateCatalog, MockVersionReview, MockPromotionGate};

function f106Manifest(array $patch = []): array
{
    return array_replace([
        'instruction_ref' => 'SYNTHETIC_INSTRUCTIONS_V1',
        'tools' => ['SYNTHETIC_TOOL_READ'],
        'policy_ref' => 'SYNTHETIC_POLICY_V1',
        'risk' => 'LOW',
        'schema_version' => 'synthetic-catalog-v1',
    ], $patch);
}

function f106Snapshot(TenantContext $ctx, string $ref = 'SYNTHETIC_SNAPSHOT_V1',
    string $version = 'SYNTHETIC_VERSION_V1', array $patch = []): MockAgentVersionSnapshot
{
    return new MockAgentVersionSnapshot($ref, 'SYNTHETIC_AGENT', $version, $ctx, f106Manifest($patch));
}

function f106Review(MockAgentVersionSnapshot $snapshot, array $patch = []): MockVersionReview
{
    $values = array_replace([
        'ref' => 'SYNTHETIC_REVIEW_' . $snapshot->version,
        'snapshotRef' => $snapshot->ref,
        'digest' => $snapshot->digest,
        'context' => $snapshot->context,
        'datasetVersion' => 'SYNTHETIC_DATASET_V1',
        'ruleVersion' => 'SYNTHETIC_RULE_V1',
        'reviewerRef' => 'SYNTHETIC_REVIEWER',
        'issuedAt' => 10,
        'expiresAt' => 30,
        'passed' => true,
    ], $patch);
    return new MockVersionReview(...$values);
}

function f106Fixture(): array
{
    $ctx = context();
    $catalog = new MockAgentTemplateCatalog();
    $catalog->addTemplate('SYNTHETIC_AGENT', $ctx, ['SYNTHETIC_TOOL_READ', 'SYNTHETIC_TOOL_EFFECT']);
    $snapshot = f106Snapshot($ctx);
    $catalog->addSnapshot($snapshot);
    $review = f106Review($snapshot);
    $gate = new MockPromotionGate($catalog, $ctx, ['SYNTHETIC_TOOL_READ', 'SYNTHETIC_TOOL_EFFECT'],
        'SYNTHETIC_DATASET_V1', 'SYNTHETIC_RULE_V1');
    return [$ctx, $catalog, $snapshot, $review, $gate];
}

return [
    'U06-01 known template snapshot is immutable and safe view contains no privilege' => function (): void {
        [$ctx, $catalog, $snapshot, , $gate] = f106Fixture();
        check($catalog->get($snapshot->ref, $ctx) === $snapshot);
        check(array_keys($snapshot->safeView()) === ['snapshot_ref', 'template_ref', 'version', 'digest']);
        check(!array_key_exists('tools', $snapshot->safeView()) && !array_key_exists('instruction_ref', $snapshot->safeView()));
        check(strlen($snapshot->digest) === 64 && $gate->current() === null && $gate->events() === []);
        $copy = f106Snapshot($ctx);
        check($copy->digest === $snapshot->digest);
    },
    'U06-02 material changes invalidate existing snapshot and prior review' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        $changed = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V1', 'SYNTHETIC_VERSION_V1',
            ['instruction_ref' => 'SYNTHETIC_INSTRUCTIONS_V2']);
        denied('SNAPSHOT_CONFLICT', fn () => $catalog->addSnapshot($changed));
        $next = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V2', 'SYNTHETIC_VERSION_V2',
            ['instruction_ref' => 'SYNTHETIC_INSTRUCTIONS_V2']);
        $catalog->addSnapshot($next);
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_DECISION_NEW', $next, $review, $ctx, 15));
        check($gate->current() === null && $snapshot->digest !== $next->digest);
    },
    'U06-03 unknown or deactivated version denies without fallback' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        denied('SNAPSHOT_DENIED', fn () => $catalog->get('SYNTHETIC_UNKNOWN', $ctx));
        $catalog->disable($snapshot->ref, $ctx);
        denied('SNAPSHOT_DENIED', fn () => $gate->promote('SYNTHETIC_DECISION', $snapshot, $review, $ctx, 15));
        check($gate->current() === null && $gate->events() === []);
    },
    'U06-04 extra fields bad schema types and excess tools are rejected' => function (): void {
        [$ctx] = f106Fixture();
        foreach ([
            ['extra' => 'SYNTHETIC_EXTRA'],
            ['tools' => 'SYNTHETIC_TOOL_READ'],
            ['tools' => [['SYNTHETIC_TOOL_READ']]],
            ['tools' => ['SYNTHETIC_TOOL_READ', 'SYNTHETIC_TOOL_READ']],
            ['tools' => array_fill(0, 9, 'SYNTHETIC_TOOL_READ')],
            ['instruction_ref' => str_repeat('SYNTHETIC_X', 35)],
            ['schema_version' => 'synthetic-catalog-v9'],
            ['risk' => 'ADMIN'],
            ['policy_ref' => 'unsafe'],
        ] as $patch) {
            denied('CATALOG_SCHEMA_DENIED', fn () => f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BAD', 'SYNTHETIC_BAD', $patch));
        }
    },
    'U06-05 missing revoked expired or failed review blocks publication' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        $no = f106Review($snapshot, ['passed' => false]);
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_REJECT', $snapshot, $no, $ctx, 15));
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_EXPIRED', $snapshot, $review, $ctx, 30));
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_EARLY', $snapshot, $review, $ctx, 9));
        $review->revoke();
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_REVOKED', $snapshot, $review, $ctx, 15));
        check($gate->current() === null && $gate->events() === []);
    },
    'U06-06 tenant product purpose principal and resource mismatch denied' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        $variants = [
            ['SYNTHETIC_OTHER', $ctx->tenant, $ctx->principal, $ctx->purpose, $ctx->resource],
            [$ctx->product, 'SYNTHETIC_B', $ctx->principal, $ctx->purpose, $ctx->resource],
            [$ctx->product, $ctx->tenant, 'SYNTHETIC_OTHER', $ctx->purpose, $ctx->resource],
            [$ctx->product, $ctx->tenant, $ctx->principal, 'SYNTHETIC_OTHER', $ctx->resource],
            [$ctx->product, $ctx->tenant, $ctx->principal, $ctx->purpose, 'SYNTHETIC_OTHER'],
        ];
        foreach ($variants as $args) {
            $other = new TenantContext(...$args);
            denied('SNAPSHOT_DENIED', fn () => $catalog->get($snapshot->ref, $other));
            denied('CATALOG_DENIED', fn () => $gate->promote('SYNTHETIC_WRONG', $snapshot, $review, $other, 15));
        }
        check($gate->current() === null);
    },
    'U06-07 approved mock evaluation transitions only local catalog state' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        check($gate->promote('SYNTHETIC_DECISION_A', $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current() === $snapshot->ref && count($gate->events()) === 1);
        check($gate->events()[0]['code'] === 'SYNTHETIC_PROMOTED');
        $gw = new \ImagineAI\Mocks\MockSupervisedGateway('CONFIRMADO');
        check($gw->submitCount() === 0); // No fake or real tool call was triggered.
    },
    'U06-08 revoked capability and changed policy or risk require new approval' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        $gate->revokeTool('SYNTHETIC_TOOL_READ');
        denied('RELEASE_DENIED', fn () => $gate->promote('SYNTHETIC_POLICY_CHANGED', $snapshot, $review, $ctx, 15));
        $changed = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V2', 'SYNTHETIC_VERSION_V2', ['risk' => 'HIGH']);
        $catalog->addSnapshot($changed);
        denied('REVIEW_DENIED', fn () => $gate->promote('SYNTHETIC_RISK_CHANGED', $changed, $review, $ctx, 15));
        $other = new MockPromotionGate($catalog, $ctx, ['SYNTHETIC_TOOL_READ'],
            'SYNTHETIC_DATASET_V2', 'SYNTHETIC_RULE_V1');
        denied('REVIEW_DENIED', fn () => $other->promote('SYNTHETIC_DATASET_CHANGED', $snapshot, $review, $ctx, 15));
    },
    'U06-09 exact repeated decision is idempotent even after suspension' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_ONCE', $snapshot, $review, $ctx, 15);
        check($gate->promote('SYNTHETIC_ONCE', $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check(count($gate->events()) === 1);
        check($gate->suspend('SYNTHETIC_STOP', $ctx) === 'SUSPENSO');
        check($gate->suspend('SYNTHETIC_STOP', $ctx) === 'SUSPENSO');
        check($gate->promote('SYNTHETIC_ONCE', $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current() === null && count($gate->events()) === 2);
    },
    'U06-10 idempotency identity collision with different manifest fails' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_DECISION_REF', $snapshot, $review, $ctx, 15);
        $another = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V2', 'SYNTHETIC_VERSION_V2',
            ['tools' => ['SYNTHETIC_TOOL_EFFECT']]);
        $catalog->addSnapshot($another);
        $r2 = f106Review($another);
        denied('PROMOTION_CONFLICT', fn () => $gate->promote('SYNTHETIC_DECISION_REF', $another, $r2, $ctx, 15));
        check($gate->current() === $snapshot->ref && count($gate->events()) === 1);
    },
    'U06-11 rollback only to previously reviewed and currently eligible versions' => function (): void {
        [$ctx, $catalog, $v1, $r1, $gate] = f106Fixture();
        $v2 = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V2', 'SYNTHETIC_VERSION_V2',
            ['tools' => ['SYNTHETIC_TOOL_EFFECT']]);
        $catalog->addSnapshot($v2);
        $r2 = f106Review($v2);
        $v3 = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_V3', 'SYNTHETIC_VERSION_V3');
        $catalog->addSnapshot($v3);
        $r3 = f106Review($v3);
        $gate->promote('SYNTHETIC_FIRST', $v1, $r1, $ctx, 15);
        $gate->promote('SYNTHETIC_SECOND', $v2, $r2, $ctx, 15);
        denied('ROLLBACK_DENIED', fn () => $gate->rollback('SYNTHETIC_UNSEEN', $v3, $r3, $ctx, 15));
        check($gate->rollback('SYNTHETIC_ROLLBACK', $v1, $r1, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current() === $v1->ref);
        $catalog->disable($v2->ref, $ctx);
        denied('SNAPSHOT_DENIED', fn () => $gate->rollback('SYNTHETIC_DISABLED', $v2, $r2, $ctx, 15));
        $gate->revokeTool('SYNTHETIC_TOOL_READ');
        denied('RELEASE_DENIED', fn () => $gate->rollback('SYNTHETIC_REVOKED_TOOL', $v1, $r1, $ctx, 15));
    },
    'U06-12 untrusted instruction reference never leaks in audit or confers grant' => function (): void {
        [$ctx, $catalog, , , $gate] = f106Fixture();
        $marker = 'SYNTHETIC_TOKEN_IGNORE_PREVIOUS_INSTRUCTIONS';
        $evil = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_UNTRUSTED', 'SYNTHETIC_VERSION_UNTRUSTED',
            ['instruction_ref' => $marker]);
        $catalog->addSnapshot($evil);
        $review = f106Review($evil);
        $gate->promote('SYNTHETIC_PROMOTE_UNTRUSTED', $evil, $review, $ctx, 15);
        $encoded = json_encode([$gate->events(), $evil->safeView()], JSON_THROW_ON_ERROR);
        check(!str_contains($encoded, $marker) && !str_contains($encoded, 'INSTRUCTIONS'));
        foreach ($gate->events() as $event) {
            check(array_keys($event) === ['sequence', 'template_hash', 'snapshot_hash', 'code']);
        }
        check($gate->current() === $evil->ref);
        // Catalog selection has no link to capability grants or the fake CRM effect gateway.
        check((new \ImagineAI\Mocks\MockSupervisedGateway('CONFIRMADO'))->submitCount() === 0);
    },
];
