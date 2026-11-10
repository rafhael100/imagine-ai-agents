<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockTrust\{MockSharedTrustState, MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\MockBridge\{MockCrmReadBoundary, MockCrmReadRequest, MockCrmOwnerPolicy};
use ImagineAI\MockAudit\{MockCrmReadAuditTrail, MockCrmReadAuditedBoundary};
use ImagineAI\Mocks\MockCrmReader;

function f114Fixture(MockSharedTrustState $state, ?TenantContext $ctx = null,
    string $nonce = 'SYNTHETIC_NONCE_A', string $issuer = 'SYNTHETIC_AGENT_SERVICE'): array
{
    $ctx ??= context();
    $envelope = new MockTrustEnvelope('SYNTHETIC_F114_ENV', $issuer,
        'SYNTHETIC_CRM_API', $ctx, $nonce, ['SYNTHETIC_LEAD_READ'], 10, 40);
    $owner = new MockOwnerScopeEvidence('SYNTHETIC_F114_OWNER', $envelope->digest,
        $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
    $gateway = new MockTrustGateway($issuer, 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ'], $state);
    return [$gateway, $envelope, $owner, $ctx];
}
function f114ReadBoundary(MockTrustGateway $gateway, TenantContext $ctx): MockCrmReadBoundary
{
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref', 'stage']);
    return new MockCrmReadBoundary($gateway, new MockCrmReader(), $policy);
}

return [
    'U14-01 first synthetic read consumes one shared nonce' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $out = $gateway->authorize($envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        check($out['real_authorization'] === false && $out['external_calls'] === 0);
        check($store->countConsumed() === 1);
    },
    'U14-02 second gateway sharing the object rejects replay' => function (): void {
        $store = new MockSharedTrustState();
        [$first, $envelope, $owner, $ctx] = f114Fixture($store);
        [$second] = f114Fixture($store);
        $first->authorize($envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        denied('TRUST_REPLAY_DENIED', fn () => $second->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 1);
    },
    'U14-03 changed envelope digest cannot reuse a consumed nonce' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $gateway->authorize($envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $altered = new MockTrustEnvelope('SYNTHETIC_ALTERED', $envelope->issuerRef,
            $envelope->audienceRef, $ctx, $envelope->nonceRef, ['SYNTHETIC_LEAD_READ'], 10, 40);
        $proof = new MockOwnerScopeEvidence('SYNTHETIC_ALTERED_OWNER', $altered->digest,
            $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        denied('TRUST_REPLAY_DENIED', fn () => $gateway->authorize(
            $altered, $proof, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U14-04 new nonce is allowed by second simulated gateway' => function (): void {
        $store = new MockSharedTrustState();
        [$g1, $e1, $o1, $ctx] = f114Fixture($store);
        [$g2, $e2, $o2] = f114Fixture($store, $ctx, 'SYNTHETIC_NONCE_B');
        $g1->authorize($e1, $o1, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $g2->authorize($e2, $o2, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        check($store->countConsumed() === 2);
    },
    'U14-05 shared revocation stops a previously issued fake envelope' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $store->revokeDigest($envelope->digest);
        denied('TRUST_DENIED', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 0);
    },
    'U14-06 unavailable shared state fails closed before authorizing' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 0);
    },
    'U14-07 recovery does not forget previously consumed nonce' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $gateway->authorize($envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        $store->setAvailable(true);
        denied('TRUST_REPLAY_DENIED', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U14-08 invalid service issuer denied before consuming shared nonce' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store, null,
            'SYNTHETIC_NONCE_A', 'SYNTHETIC_FOREIGN_SERVICE');
        $expected = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
            'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ'], $store);
        denied('TRUST_DENIED', fn () => $expected->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 0);
    },
    'U14-09 revoked owner proof never reserves nonce' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $owner->revoke();
        denied('OWNER_DENIED', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 0);
    },
    'U14-10 same nonce across tenants cannot bypass shared replay' => function (): void {
        $store = new MockSharedTrustState();
        [$ga, $ea, $oa, $ca] = f114Fixture($store);
        [$gb, $eb, $ob, $cb] = f114Fixture($store, context('SYNTHETIC_B'));
        $ga->authorize($ea, $oa, $ca, 'SYNTHETIC_LEAD_READ', 15);
        denied('TRUST_REPLAY_DENIED', fn () => $gb->authorize(
            $eb, $ob, $cb, 'SYNTHETIC_LEAD_READ', 15));
        [$fresh, $ef, $of] = f114Fixture($store, $cb, 'SYNTHETIC_NONCE_B');
        $fresh->authorize($ef, $of, $cb, 'SYNTHETIC_LEAD_READ', 15);
        check($store->countConsumed() === 2);
    },
    'U14-11 malformed digest and revocation outage fail closed' => function (): void {
        $store = new MockSharedTrustState();
        denied('TRUST_STATE_SCHEMA_DENIED', fn () => $store->revokeDigest('invalid'));
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $store->revokeDigest($envelope->digest));
        $store->setAvailable(true);
        $store->revokeDigest($envelope->digest);
        $store->revokeDigest($envelope->digest); // idempotent in volatile fixture
        denied('TRUST_DENIED', fn () => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U14-12 F1-13 audit classifies shared outage without leaking details' => function (): void {
        $store = new MockSharedTrustState();
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        $journal = new MockCrmReadAuditTrail();
        $bridge = new MockCrmReadAuditedBoundary(
            f114ReadBoundary($gateway, $ctx), $journal);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $envelope, $owner, $ctx, 15));
        check($journal->count() === 1 && $journal->entries()[0]['reason'] === 'TRUST');
        check($journal->entries()[0]['real_authorization'] === false
            && $journal->entries()[0]['external_calls'] === 0);
    },

    'U28-01 shared nonce capacity refuses new identities without evicting replay history' => function (): void {
        $store = new MockSharedTrustState(2);
        [$g1, $e1, $o1, $ctx] = f114Fixture($store, null, 'SYNTHETIC_NONCE_1');
        [$g2, $e2, $o2] = f114Fixture($store, $ctx, 'SYNTHETIC_NONCE_2');
        [$g3, $e3, $o3] = f114Fixture($store, $ctx, 'SYNTHETIC_NONCE_3');
        $g1->authorize($e1, $o1, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $g2->authorize($e2, $o2, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $g3->authorize(
            $e3, $o3, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        denied('TRUST_REPLAY_DENIED', fn() => $g1->authorize(
            $e1, $o1, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 2);
    },
    'U28-02 revocation store capacity is fail closed and duplicate revocation remains safe' => function (): void {
        $store = new MockSharedTrustState(1);
        $first = hash('sha256', 'SYNTHETIC_REVOCATION_A');
        $second = hash('sha256', 'SYNTHETIC_REVOCATION_B');
        $store->revokeDigest($first);
        $store->revokeDigest($first);
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $store->revokeDigest($second));
        check($store->countRevoked() === 1);
    },
    'U28-03 invalid synthetic memory budgets fail before allocating state' => function (): void {
        foreach ([0, -1, 4097] as $capacity) {
            denied('TRUST_STATE_SCHEMA_DENIED', fn() => new MockSharedTrustState($capacity));
        }
        check((new MockSharedTrustState(4096))->countConsumed() === 0);
    },
    'U28-04 trust outage and recovery cannot reset a full nonce registry' => function (): void {
        $store = new MockSharedTrustState(1);
        [$g1, $e1, $o1, $ctx] = f114Fixture($store, null, 'SYNTHETIC_NONCE_RECOVERY_A');
        [$g2, $e2, $o2] = f114Fixture($store, $ctx, 'SYNTHETIC_NONCE_RECOVERY_B');
        $g1->authorize($e1, $o1, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn() => $g2->authorize(
            $e2, $o2, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        $store->setAvailable(true);
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $g2->authorize(
            $e2, $o2, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        denied('TRUST_REPLAY_DENIED', fn() => $g1->authorize(
            $e1, $o1, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 1);
    },
    'U28-05 local gateway nonce cache also denies capacity without a replay bypass' => function (): void {
        $ctx = context();
        $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']);
        $firstEnvelope = null;
        $firstOwner = null;
        for ($i = 0; $i <= 1024; ++$i) {
            $env = new MockTrustEnvelope('SYNTHETIC_ENV_CAP_' . $i,
                'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $ctx,
                'SYNTHETIC_NONCE_CAP_' . $i, ['SYNTHETIC_LEAD_READ'], 10, 40);
            $owner = new MockOwnerScopeEvidence('SYNTHETIC_OWNER_CAP_' . $i,
                $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
            if ($i === 0) { $firstEnvelope = $env; $firstOwner = $owner; }
            if ($i < 1024) $gateway->authorize($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
            else denied('TRUST_STATE_CAPACITY_DENIED', fn() => $gateway->authorize(
                $env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        }
        denied('TRUST_REPLAY_DENIED', fn() => $gateway->authorize(
            $firstEnvelope, $firstOwner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
    },
    'U28-06 audit categorizes capacity denial as trust without leaking store internals' => function (): void {
        $store = new MockSharedTrustState(1);
        [$gateway, $first, $firstOwner, $ctx] = f114Fixture(
            $store, null, 'SYNTHETIC_NONCE_AUDIT_A');
        [, $second, $secondOwner] = f114Fixture(
            $store, $ctx, 'SYNTHETIC_NONCE_AUDIT_B');
        $trail = new MockCrmReadAuditTrail();
        $boundary = new MockCrmReadAuditedBoundary(
            f114ReadBoundary($gateway, $ctx), $trail);
        $request = new MockCrmReadRequest(f112Wire($ctx));
        check($boundary->evaluate($request, $first, $firstOwner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $boundary->evaluate(
            $request, $second, $secondOwner, $ctx, 15));
        check($trail->count() === 2
            && $trail->entries()[1]['reason'] === 'TRUST');
        check(!str_contains(json_encode($trail->entries(), JSON_THROW_ON_ERROR),
            'TRUST_STATE_CAPACITY_DENIED'));
    },

    'U29-01 failed revocation disables subsequent authorization instead of failing open' => function (): void {
        $store = new MockSharedTrustState(1);
        $store->revokeDigest(hash('sha256', 'SYNTHETIC_REVOCATION_FIRST'));
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $store->revokeDigest(
            hash('sha256', 'SYNTHETIC_REVOCATION_SECOND')));
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        denied('TRUST_STATE_UNAVAILABLE', fn() => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 0 && $store->countRevoked() === 1);
    },
    'U29-02 toggling availability cannot clear sticky revocation overflow' => function (): void {
        $store = new MockSharedTrustState(1);
        $store->revokeDigest(hash('sha256', 'SYNTHETIC_REVOCATION_FIRST'));
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $store->revokeDigest(
            hash('sha256', 'SYNTHETIC_REVOCATION_SECOND')));
        $store->setAvailable(false);
        $store->setAvailable(true);
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        denied('TRUST_STATE_UNAVAILABLE', fn() => $gateway->authorize(
            $envelope, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        denied('TRUST_STATE_UNAVAILABLE', fn() => $store->revokeDigest(
            hash('sha256', 'SYNTHETIC_REVOCATION_THIRD')));
    },
    'U29-03 a full revocation registry alone does not deny unrelated valid reads' => function (): void {
        $store = new MockSharedTrustState(1);
        $store->revokeDigest(hash('sha256', 'SYNTHETIC_REVOCATION_UNRELATED'));
        $store->revokeDigest(hash('sha256', 'SYNTHETIC_REVOCATION_UNRELATED'));
        [$gateway, $envelope, $owner, $ctx] = f114Fixture($store);
        check($gateway->authorize($envelope, $owner, $ctx,
            'SYNTHETIC_LEAD_READ', 15)['state'] === 'SYNTHETIC_READ_ONLY');
        check($store->countConsumed() === 1);
    },
    'U29-04 nonce capacity alone does not masquerade as lost revocation integrity' => function (): void {
        $store = new MockSharedTrustState(1);
        [$ga, $ea, $oa, $ctx] = f114Fixture($store, null, 'SYNTHETIC_NONCE_LOCK_A');
        [$gb, $eb, $ob] = f114Fixture($store, $ctx, 'SYNTHETIC_NONCE_LOCK_B');
        $ga->authorize($ea, $oa, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        denied('TRUST_STATE_CAPACITY_DENIED', fn() => $gb->authorize(
            $eb, $ob, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        $store->setAvailable(true);
        denied('TRUST_REPLAY_DENIED', fn() => $ga->authorize(
            $ea, $oa, $ctx, 'SYNTHETIC_LEAD_READ', 15));
        check($store->countConsumed() === 1 && $store->countRevoked() === 0);
    },
];
