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

/** Fill 127 reviewed local decisions; slot 128 is reserved for suspension. */
function f151FillPromotionGate(MockPromotionGate $gate, MockAgentVersionSnapshot $snapshot,
    MockVersionReview $review, TenantContext $ctx, int $n = 127): void
{
    for ($i = 0; $i < $n; ++$i) {
        check($gate->promote('SYNTHETIC_SEC29_PROMOTE_' . $i,
            $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
    }
}

/** Fill a purely local catalog; no registry, deployment or real agent. */
function f152CatalogSnapshots(int $count = 256): array
{
    $ctx = context();
    $catalog = new MockAgentTemplateCatalog();
    $catalog->addTemplate('SYNTHETIC_AGENT', $ctx, ['SYNTHETIC_TOOL_READ']);
    for ($i = 0; $i < $count; ++$i) {
        $catalog->addSnapshot(f106Snapshot($ctx,
            'SYNTHETIC_SNAPSHOT_BOUND_' . $i, 'SYNTHETIC_VERSION_BOUND_' . $i));
    }
    return [$ctx, $catalog];
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
        $catalog = new MockAgentTemplateCatalog();
        denied('CATALOG_SCHEMA_DENIED', fn () => $catalog->addTemplate('SYNTHETIC_BAD', $ctx, [['SYNTHETIC_TOOL_READ']]));
        denied('CATALOG_SCHEMA_DENIED', fn () => new MockPromotionGate($catalog, $ctx, [['SYNTHETIC_TOOL_READ']],
            'SYNTHETIC_DATASET_V1', 'SYNTHETIC_RULE_V1'));
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
    'U06-C01 disabling a selected snapshot cannot block its explicit suspension' => function (): void {
        [$ctx, $catalog, $v1, $r1, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_C01_PROMOTE', $v1, $r1, $ctx, 15);
        $catalog->disable($v1->ref, $ctx);
        check($gate->current() === null); // Do not expose a disabled version as eligible.
        check($gate->suspend('SYNTHETIC_C01_SUSPEND', $ctx) === 'SUSPENSO');
        check($gate->current() === null);
        check($gate->suspend('SYNTHETIC_C01_SUSPEND', $ctx) === 'SUSPENSO');
        check(count($gate->events()) === 2 && $gate->events()[1]['code'] === 'SYNTHETIC_SUSPENDED');
        denied('SNAPSHOT_DENIED', fn () => $gate->rollback(
            'SYNTHETIC_C01_ROLLBACK', $v1, $r1, $ctx, 15));
    },
    'U06-C02 reusing a suspension reference cannot falsely suspend a later version' => function (): void {
        [$ctx, $catalog, $v1, $r1, $gate] = f106Fixture();
        $v2 = f106Snapshot($ctx, 'SYNTHETIC_C02_SNAPSHOT_V2', 'SYNTHETIC_C02_VERSION_V2');
        $catalog->addSnapshot($v2);
        $r2 = f106Review($v2);
        $gate->promote('SYNTHETIC_C02_PROMOTE_ONE', $v1, $r1, $ctx, 15);
        check($gate->suspend('SYNTHETIC_C02_STOP_ONE', $ctx) === 'SUSPENSO');
        check($gate->suspend('SYNTHETIC_C02_STOP_ONE', $ctx) === 'SUSPENSO');
        $gate->promote('SYNTHETIC_C02_PROMOTE_TWO', $v2, $r2, $ctx, 15);
        denied('PROMOTION_CONFLICT', fn () => $gate->suspend('SYNTHETIC_C02_STOP_ONE', $ctx));
        check($gate->current() === $v2->ref && count($gate->events()) === 3);
        check($gate->suspend('SYNTHETIC_C02_STOP_TWO', $ctx) === 'SUSPENSO');
        check($gate->current() === null && count($gate->events()) === 4);
    },
    'U06-C03 capability loss suspends only an affected active mock selection' => function (): void {
        [$ctx, , $v1, $r1, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_C03_PROMOTE', $v1, $r1, $ctx, 15);
        $gate->revokeTool('SYNTHETIC_TOOL_EFFECT'); // Not used by this snapshot.
        check($gate->current() === $v1->ref && count($gate->events()) === 1);
        $gate->revokeTool('SYNTHETIC_TOOL_READ');
        check($gate->current() === null && count($gate->events()) === 2);
        check($gate->events()[1]['code'] === 'SYNTHETIC_SUSPENDED_TOOL_REVOKED');
        $gate->revokeTool('SYNTHETIC_TOOL_READ'); // No duplicate suspension event.
        check(count($gate->events()) === 2);
        denied('RELEASE_DENIED', fn () => $gate->promote(
            'SYNTHETIC_C03_NEW_PROMOTION', $v1, $r1, $ctx, 15));
        denied('RELEASE_DENIED', fn () => $gate->rollback(
            'SYNTHETIC_C03_ROLLBACK', $v1, $r1, $ctx, 15));
        $trace = json_encode($gate->events(), JSON_THROW_ON_ERROR);
        check(!str_contains($trace, 'SYNTHETIC_TOOL_READ'));
    },

    'U51-01 127 promotions reserve final decision for safe suspension' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        $events = $gate->events();
        check(count($events) === 127 && $gate->current() === $snapshot->ref);
        denied('PROMOTION_CAPACITY_DENIED', fn() => $gate->promote(
            'SYNTHETIC_SEC29_OVERFLOW', $snapshot, $review, $ctx, 15));
        check($gate->events() === $events && $gate->current() === $snapshot->ref);
    },
    'U51-02 final reserved decision permits suspension and records outcome once' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        check($gate->suspend('SYNTHETIC_SEC29_EMERGENCY', $ctx) === 'SUSPENSO');
        check($gate->current() === null && count($gate->events()) === 128
            && $gate->events()[127]['code'] === 'SYNTHETIC_SUSPENDED');
        check($gate->suspend('SYNTHETIC_SEC29_EMERGENCY', $ctx) === 'SUSPENSO');
        check(count($gate->events()) === 128);
    },
    'U51-03 exact repeated decision remains idempotent after full decision cap' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        $gate->suspend('SYNTHETIC_SEC29_EMERGENCY', $ctx);
        $events = $gate->events();
        // Historical API may repeat its original response, but never reactivates.
        check($gate->promote('SYNTHETIC_SEC29_PROMOTE_0',
            $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current() === null && $gate->events() === $events);
        denied('PROMOTION_CAPACITY_DENIED', fn() => $gate->promote(
            'SYNTHETIC_SEC29_NEW', $snapshot, $review, $ctx, 15));
    },
    'U51-04 new rollback cannot bypass a saturated decision register' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        $events = $gate->events();
        denied('PROMOTION_CAPACITY_DENIED', fn() => $gate->rollback(
            'SYNTHETIC_SEC29_ROLLBACK', $snapshot, $review, $ctx, 15));
        check($gate->current() === $snapshot->ref && $gate->events() === $events);
        check($gate->suspend('SYNTHETIC_SEC29_SUSPEND', $ctx) === 'SUSPENSO');
        check($gate->current() === null);
    },
    'U51-05 rejected eligibility check leaves final available slot untouched' => function (): void {
        [$ctx, $catalog, $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx, 126);
        $bad = f106Snapshot($ctx, 'SYNTHETIC_SEC29_BAD_REF',
            'SYNTHETIC_SEC29_BAD_VERSION', ['risk' => 'HIGH']);
        $catalog->addSnapshot($bad);
        $badReview = f106Review($bad);
        denied('RELEASE_DENIED', fn() => $gate->promote(
            'SYNTHETIC_SEC29_INVALID', $bad, $badReview, $ctx, 15));
        check(count($gate->events()) === 126);
        check($gate->promote('SYNTHETIC_SEC29_LAST_ALLOWED',
            $snapshot, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->suspend('SYNTHETIC_SEC29_LAST_STOP', $ctx) === 'SUSPENSO');
        check(count($gate->events()) === 128);
    },
    'U51-06 capability revocation is still audited and disables a selected version' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        $gate->revokeTool('SYNTHETIC_TOOL_READ');
        check($gate->current() === null && count($gate->events()) === 128);
        check($gate->events()[127]['code'] === 'SYNTHETIC_SUSPENDED_TOOL_REVOKED');
        $before = $gate->events();
        $gate->revokeTool('SYNTHETIC_TOOL_READ');
        check($gate->events() === $before);
        denied('RELEASE_DENIED', fn() => $gate->rollback(
            'SYNTHETIC_SEC29_ILLEGAL_ROLLBACK', $snapshot, $review, $ctx, 15));
    },

    'U52-01 catalog accepts 64 templates, then denies 65th before insertion' => function (): void {
        $ctx = context();
        $catalog = new MockAgentTemplateCatalog();
        for ($i = 0; $i < 64; ++$i) {
            $catalog->addTemplate('SYNTHETIC_TEMPLATE_CAP_' . $i,
                $ctx, ['SYNTHETIC_TOOL_READ']);
        }
        denied('CATALOG_CAPACITY_DENIED', fn() => $catalog->addTemplate(
            'SYNTHETIC_TEMPLATE_CAP_64', $ctx, ['SYNTHETIC_TOOL_READ']));
        denied('TEMPLATE_CONFLICT', fn() => $catalog->addTemplate(
            'SYNTHETIC_TEMPLATE_CAP_0', $ctx, ['SYNTHETIC_TOOL_READ']));
    },
    'U52-02 rejected schema does not consume the last template slot' => function (): void {
        $ctx = context();
        $catalog = new MockAgentTemplateCatalog();
        for ($i = 0; $i < 63; ++$i) {
            $catalog->addTemplate('SYNTHETIC_TEMPLATE_CAP_' . $i,
                $ctx, ['SYNTHETIC_TOOL_READ']);
        }
        denied('CATALOG_SCHEMA_DENIED', fn() => $catalog->addTemplate(
            'SYNTHETIC_TEMPLATE_INVALID', $ctx,
            ['SYNTHETIC_TOOL_READ', 'SYNTHETIC_TOOL_READ']));
        $catalog->addTemplate('SYNTHETIC_TEMPLATE_CAP_63', $ctx,
            ['SYNTHETIC_TOOL_READ']);
        denied('CATALOG_CAPACITY_DENIED', fn() => $catalog->addTemplate(
            'SYNTHETIC_TEMPLATE_AFTER_CAP', $ctx, []));
    },
    'U52-03 256 snapshots fit, but 257th cannot enter the catalog' => function (): void {
        [$ctx, $catalog] = f152CatalogSnapshots();
        $last = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BOUND_255',
            'SYNTHETIC_VERSION_BOUND_255');
        check($catalog->get($last->ref, $ctx)->digest === $last->digest);
        $next = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BOUND_256',
            'SYNTHETIC_VERSION_BOUND_256');
        denied('CATALOG_CAPACITY_DENIED', fn() => $catalog->addSnapshot($next));
        denied('SNAPSHOT_DENIED', fn() => $catalog->get($next->ref, $ctx));
        check($catalog->get($last->ref, $ctx)->digest === $last->digest);
    },
    'U52-04 exact duplicate snapshot remains idempotent at full capacity' => function (): void {
        [$ctx, $catalog] = f152CatalogSnapshots();
        $same = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BOUND_0',
            'SYNTHETIC_VERSION_BOUND_0');
        $catalog->addSnapshot($same);
        $catalog->addSnapshot($same);
        check($catalog->get($same->ref, $ctx)->digest === $same->digest);
        $extra = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BOUND_EXTRA',
            'SYNTHETIC_VERSION_BOUND_EXTRA');
        denied('CATALOG_CAPACITY_DENIED', fn() => $catalog->addSnapshot($extra));
    },
    'U52-05 same ref with modified digest remains conflict after capacity' => function (): void {
        [$ctx, $catalog] = f152CatalogSnapshots();
        $old = f106Snapshot($ctx, 'SYNTHETIC_SNAPSHOT_BOUND_0',
            'SYNTHETIC_VERSION_BOUND_0');
        $changed = f106Snapshot($ctx, $old->ref, $old->version,
            ['instruction_ref' => 'SYNTHETIC_INSTRUCTIONS_CHANGED']);
        denied('SNAPSHOT_CONFLICT', fn() => $catalog->addSnapshot($changed));
        check($catalog->get($old->ref, $ctx)->digest === $old->digest);
    },
    'U52-06 same template version with another ref remains conflict at capacity' => function (): void {
        [$ctx, $catalog] = f152CatalogSnapshots();
        $duplicateVersion = f106Snapshot($ctx,
            'SYNTHETIC_SNAPSHOT_ALT_REF', 'SYNTHETIC_VERSION_BOUND_0');
        denied('SNAPSHOT_CONFLICT', fn() => $catalog->addSnapshot($duplicateVersion));
        denied('SNAPSHOT_DENIED', fn() => $catalog->get($duplicateVersion->ref, $ctx));
    },
    'U52-07 capacity cannot block revocation or existing reviewed selection' => function (): void {
        [$ctx, $catalog] = f152CatalogSnapshots();
        $selected = $catalog->get('SYNTHETIC_SNAPSHOT_BOUND_255', $ctx);
        $review = f106Review($selected);
        $gate = new MockPromotionGate($catalog, $ctx, ['SYNTHETIC_TOOL_READ'],
            'SYNTHETIC_DATASET_V1', 'SYNTHETIC_RULE_V1');
        check($gate->promote('SYNTHETIC_SEC30_SELECT',
            $selected, $review, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current() === $selected->ref);
        $catalog->disable($selected->ref, $ctx);
        denied('SNAPSHOT_DENIED', fn() => $catalog->get($selected->ref, $ctx));
        check($gate->current() === null);
        check($gate->suspend('SYNTHETIC_SEC30_STOP', $ctx) === 'SUSPENSO');
        check($gate->current() === null && count($gate->events()) === 2);
    },

    'U56-01 revoked selected review hides active snapshot and keeps safe suspend available' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_SEC34_PROMOTE', $snapshot, $review, $ctx, 15);
        check($gate->current() === $snapshot->ref && $gate->current(15) === $snapshot->ref);
        $review->revoke();
        $before = $gate->events();
        check($gate->current() === null && $gate->current(15) === null);
        check($gate->events() === $before);
        check($gate->suspend('SYNTHETIC_SEC34_SUSPEND', $ctx) === 'SUSPENSO');
        check($gate->current() === null && count($gate->events()) === 2);
    },
    'U56-02 explicit synthetic clock denies expired selected review without mutation' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_SEC34_PROMOTE', $snapshot, $review, $ctx, 15);
        $before = $gate->events();
        check($gate->current(29) === $snapshot->ref);
        check($gate->current(30) === null && $gate->current(100) === null);
        // Clockless legacy queries cannot establish wall-clock expiration.
        check($gate->current() === $snapshot->ref);
        check($gate->events() === $before);
        check($gate->suspend('SYNTHETIC_SEC34_EXPIRED_STOP', $ctx) === 'SUSPENSO');
    },
    'U56-03 explicit clock earlier than issuance fails closed and does not consume history' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        check($gate->current(15) === null);
        $gate->promote('SYNTHETIC_SEC34_PROMOTE', $snapshot, $review, $ctx, 15);
        $before = $gate->events();
        check($gate->current(9) === null && $gate->current(-1) === null);
        check($gate->current(10) === $snapshot->ref);
        check($gate->events() === $before);
    },
    'U56-04 new reviewed selection is independent from revoked historical review' => function (): void {
        [$ctx, , $snapshot, $oldReview, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_SEC34_OLD_SELECT', $snapshot, $oldReview, $ctx, 15);
        $oldReview->revoke();
        check($gate->current() === null);
        $newReview = f106Review($snapshot, ['ref' => 'SYNTHETIC_SEC34_RENEWED_REVIEW']);
        $gate->promote('SYNTHETIC_SEC34_NEW_SELECT', $snapshot, $newReview, $ctx, 15);
        check($gate->current(15) === $snapshot->ref);
        denied('REVIEW_DENIED', fn () => $gate->promote(
            'SYNTHETIC_SEC34_OLD_REPLAY', $snapshot, $oldReview, $ctx, 15));
        check($gate->current() === $snapshot->ref && count($gate->events()) === 2);
        $newReview->revoke();
        check($gate->current() === null);
    },
    'U56-05 rollback binds visibility to its own review and not prior selection' => function (): void {
        [$ctx, $catalog, $v1, $r1, $gate] = f106Fixture();
        $v2 = f106Snapshot($ctx, 'SYNTHETIC_SEC34_V2', 'SYNTHETIC_SEC34_VERSION_V2');
        $catalog->addSnapshot($v2);
        $r2 = f106Review($v2);
        $gate->promote('SYNTHETIC_SEC34_V1_ACTIVE', $v1, $r1, $ctx, 15);
        $gate->promote('SYNTHETIC_SEC34_V2_ACTIVE', $v2, $r2, $ctx, 15);
        $r1->revoke();
        check($gate->current(15) === $v2->ref);
        $r1new = f106Review($v1, ['ref' => 'SYNTHETIC_SEC34_REVIEW_NEW']);
        check($gate->rollback('SYNTHETIC_SEC34_GO_BACK', $v1, $r1new, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current(15) === $v1->ref);
        $r2->revoke();
        check($gate->current(15) === $v1->ref);
        $r1new->revoke();
        check($gate->current() === null);
    },
    'U56-06 historical decision replay does not swap or revive a revoked review' => function (): void {
        [$ctx, , $snapshot, $oldReview, $gate] = f106Fixture();
        $gate->promote('SYNTHETIC_SEC34_ORIGINAL', $snapshot, $oldReview, $ctx, 15);
        $gate->suspend('SYNTHETIC_SEC34_STOP', $ctx);
        $newReview = f106Review($snapshot, ['ref' => 'SYNTHETIC_SEC34_CURRENT_REVIEW']);
        $gate->promote('SYNTHETIC_SEC34_RESELECT', $snapshot, $newReview, $ctx, 15);
        $before = $gate->events();
        check($gate->promote('SYNTHETIC_SEC34_ORIGINAL',
            $snapshot, $oldReview, $ctx, 15) === 'ATIVO_SIMULADO');
        check($gate->current(15) === $snapshot->ref && $gate->events() === $before);
        $oldReview->revoke();
        denied('REVIEW_DENIED', fn () => $gate->promote(
            'SYNTHETIC_SEC34_ORIGINAL', $snapshot, $oldReview, $ctx, 15));
        check($gate->current(15) === $snapshot->ref);
    },
    'U56-07 full decision budget still reserves suspension after review revocation' => function (): void {
        [$ctx, , $snapshot, $review, $gate] = f106Fixture();
        f151FillPromotionGate($gate, $snapshot, $review, $ctx);
        $review->revoke();
        check($gate->current() === null && count($gate->events()) === 127);
        denied('REVIEW_DENIED', fn () => $gate->promote(
            'SYNTHETIC_SEC34_UNREVIEWED', $snapshot, $review, $ctx, 15));
        check($gate->suspend('SYNTHETIC_SEC34_RESERVED_STOP', $ctx) === 'SUSPENSO');
        check($gate->current() === null && count($gate->events()) === 128);
        check($gate->events()[127]['code'] === 'SYNTHETIC_SUSPENDED');
    },
];
