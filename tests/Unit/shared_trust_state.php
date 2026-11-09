<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockTrust\{MockSharedTrustState, MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};
use ImagineAI\MockBridge\{MockCrmReadBoundary, MockCrmReadRequest};
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
            new MockCrmReadBoundary($gateway, new MockCrmReader()), $journal);
        $store->setAvailable(false);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $envelope, $owner, $ctx, 15));
        check($journal->count() === 1 && $journal->entries()[0]['reason'] === 'TRUST');
        check($journal->entries()[0]['real_authorization'] === false
            && $journal->entries()[0]['external_calls'] === 0);
    },
];
