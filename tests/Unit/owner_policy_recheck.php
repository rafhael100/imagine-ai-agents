<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmOwnerPolicy, MockCrmReadBoundary, MockCrmReadRequest};
use ImagineAI\MockTrust\{MockTrustGateway, MockSharedTrustState};
use ImagineAI\MockAudit\{MockCrmReadAuditTrail, MockCrmReadAuditedBoundary};
use ImagineAI\Mocks\MockCrmReader;

/** One fake provider policy for this fixture, never a CRM backend or real grant. */
function f116Fixture(MockCrmOwnerPolicy $policy, ?TenantContext $context = null): array
{
    [, $env, $owner, $ctx] = f112Fixture($context);
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ']);
    $boundary = new MockCrmReadBoundary($gateway, new MockCrmReader(), $policy);
    return [$boundary, $env, $owner, $ctx];
}

/** Uses a shared synthetic nonce store so failed attempts are observable. */
function f134Fixture(?MockCrmOwnerPolicy $policy = null): array
{
    [, $env, $owner, $ctx] = f112Fixture();
    $store = new MockSharedTrustState();
    $policy ??= new MockCrmOwnerPolicy();
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ'], $store);
    return [new MockCrmReadBoundary($gateway, new MockCrmReader(), $policy),
        $store, $policy, $env, $owner, $ctx];
}

return [
    'U16-01 explicit fictional owner grant allows one fake field' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $out = $bridge->evaluate(new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15);
        check($out['projection'] === ['ref' => 'SYNTHETIC_A_LEAD']);
        check($out['real_authorization'] === false && $out['external_calls'] === 0);
    },
    'U16-02 missing owner grant denies despite valid old trust evidence' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-03 owner revocation after envelope issuance denies at the read' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $policy->revokeFixture($ctx);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-04 tenant suspension after envelope issuance denies at the read' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $policy->setTenantSuspended('SYNTHETIC_A', true);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-05 tenant resume is checked at the next separate fake request' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $policy->setTenantSuspended('SYNTHETIC_A', true);
        [$deniedBridge, $deniedEnv, $deniedOwner, $ctx] = f116Fixture($policy);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $deniedBridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $deniedEnv, $deniedOwner, $ctx, 15));
        $policy->setTenantSuspended('SYNTHETIC_A', false);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $out = $bridge->evaluate(new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15);
        check($out['state'] === 'READ_SYNTHETIC_ONLY');
    },
    'U16-06 revoked fictional membership denies despite owner evidence' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $policy->setMemberSuspended('SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL', true);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-07 fictional membership resumption needs a new read attempt' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $policy->setMemberSuspended('SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL', true);
        [$blocked, $env1, $owner1, $ctx] = f116Fixture($policy);
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $blocked->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env1, $owner1, $ctx, 15));
        $policy->setMemberSuspended('SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL', false);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        check($bridge->evaluate(new MockCrmReadRequest(f112Wire($ctx)), $env, $owner,
            $ctx, 15)['projection'] === ['ref' => 'SYNTHETIC_A_LEAD']);
    },
    'U16-08 allowed ref must not silently extend to fake stage field' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $wire = f112Wire($ctx); $wire['fields'] = ['ref', 'stage'];
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest($wire), $env, $owner, $ctx, 15));
    },
    'U16-09 both explicitly permitted fake fields respect request ordering' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref', 'stage']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $out = $bridge->evaluate(new MockCrmReadRequest($wire), $env, $owner, $ctx, 15);
        check(array_keys($out['projection']) === ['stage', 'ref']);
        check($out['projection'] === ['stage' => 'SYNTHETIC_NEW', 'ref' => 'SYNTHETIC_A_LEAD']);
    },
    'U16-10 owner grant for A cannot authorize B even for equivalent fields' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context('SYNTHETIC_A'), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy, context('SYNTHETIC_B'));
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-11 owner denies foreign and nonexistent resources identically' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        foreach (['SYNTHETIC_B_LEAD', 'SYNTHETIC_MISSING_LEAD'] as $resource) {
            $foreign = context('SYNTHETIC_A', $resource);
            [$bridge, $env, $owner, $ctx] = f116Fixture($policy, $foreign);
            denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
                new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        }
    },
    'U16-12 principal and purpose substitutions never inherit old grants' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $base = context(); $policy->grantFixture($base, ['ref']);
        $others = [
            new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_OTHER_USER',
                'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'),
            new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
                'SYNTHETIC_OTHER_PURPOSE', 'SYNTHETIC_A_LEAD'),
        ];
        foreach ($others as $other) {
            [$bridge, $env, $owner, $ctx] = f116Fixture($policy, $other);
            denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
                new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        }
    },
    'U16-13 owner policy outage denies despite prior grant and proof' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $policy->setAvailable(false);
        denied('OWNER_POLICY_UNAVAILABLE', fn () => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U16-14 owner policy outage audit records only a safe reason category' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $env, $owner, $ctx] = f116Fixture($policy);
        $audit = new MockCrmReadAuditTrail();
        $decorated = new MockCrmReadAuditedBoundary($bridge, $audit);
        $policy->setAvailable(false);
        denied('OWNER_POLICY_UNAVAILABLE', fn () => $decorated->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'OWNER');
        $serialized = json_encode($audit->entries(), JSON_THROW_ON_ERROR);
        check(!str_contains($serialized, 'SYNTHETIC_A_LEAD'));
        check(!str_contains($serialized, 'OWNER_POLICY_UNAVAILABLE'));
    },
    'U16-15 malformed grants cannot create synthetic privileges' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        foreach ([[], ['ref', 'ref'], ['email'], ['ref', 'email'], ['ref' => true]] as $bad) {
            denied('OWNER_POLICY_SCHEMA_DENIED', fn () => $policy->grantFixture(context(), $bad));
        }
        denied('OWNER_POLICY_SCHEMA_DENIED',
            fn () => $policy->setTenantSuspended('TENANT_REAL', true));
        denied('OWNER_POLICY_SCHEMA_DENIED',
            fn () => $policy->setMemberSuspended('SYNTHETIC_A', 'UNKNOWN_MEMBER', true));
    },
    'U16-16 owner cannot grant invalid foreign or nonfixture contexts' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $bad = [
            context('SYNTHETIC_A', 'SYNTHETIC_B_LEAD'),
            new TenantContext('SYNTHETIC_PORTAL', 'SYNTHETIC_A', 'SYNTHETIC_PRINCIPAL',
                'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'),
            new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A', 'SYNTHETIC_OTHER_USER',
                'SYNTHETIC_SUMMARY', 'SYNTHETIC_A_LEAD'),
        ];
        foreach ($bad as $ctx) {
            denied('OWNER_POLICY_SCHEMA_DENIED', fn () => $policy->grantFixture($ctx, ['ref']));
        }
    },
    'U16-17 F1-12 fixture now has explicit owner policy injection' => function (): void {
        [$bridge, $env, $owner, $ctx] = f112Fixture();
        $out = $bridge->evaluate(new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15);
        check($out['mode'] === 'SYNTHETIC_ONLY' && $out['external_calls'] === 0);
    },
    'U16-18 missing, revoked, suspended and field denial have one resource denial code' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $attempt = static function (MockCrmOwnerPolicy $state, array $fields = ['ref']): void {
            [$bridge, $env, $owner, $ctx] = f116Fixture($state);
            $wire = f112Wire($ctx); $wire['fields'] = $fields;
            denied('RESOURCE_DENIED_OR_MISSING', fn () => $bridge->evaluate(
                new MockCrmReadRequest($wire), $env, $owner, $ctx, 15));
        };
        $attempt($policy); // missing
        $policy->grantFixture(context(), ['ref']);
        $attempt($policy, ['stage']); // field denied
        $policy->revokeFixture(context());
        $attempt($policy); // revoked
        $policy->grantFixture(context(), ['ref']);
        $policy->setTenantSuspended('SYNTHETIC_A', true);
        $attempt($policy); // suspended
    },

    'U33-01 bridge constructor refuses absent policy by signature' => function (): void {
        $gate = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']);
        try {
            new MockCrmReadBoundary($gate, new MockCrmReader());
            throw new \RuntimeException('POLICY_BYPASS_ALLOWED');
        } catch (\ArgumentCountError) {
            check(true);
        }
    },
    'U33-02 explicit but ungranted owner policy denies valid trust proof' => function (): void {
        [$bridge, $env, $owner, $ctx] = f116Fixture(new MockCrmOwnerPolicy());
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $bridge->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U33-03 revocation of required policy denies next fresh trust envelope' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$first, $env, $owner, $ctx] = f116Fixture($policy);
        check($first->evaluate(new MockCrmReadRequest(f112Wire($ctx)),
            $env, $owner, $ctx, 15)['state'] === 'READ_SYNTHETIC_ONLY');
        $policy->revokeFixture($ctx);
        [$second, $newEnv, $newOwner] = f116Fixture($policy);
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $second->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $newEnv, $newOwner, $ctx, 15));
    },

    'U34-01 missing grant denies before nonce consumption, permitting authorized later attempt' => function (): void {
        [$bridge, $store, $policy, $env, $owner, $ctx] = f134Fixture();
        $req = new MockCrmReadRequest(f112Wire($ctx));
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $bridge->evaluate($req, $env, $owner, $ctx, 15));
        check($store->countConsumed() === 0);
        $policy->grantFixture($ctx, ['ref']);
        check($bridge->evaluate($req, $env, $owner, $ctx, 15)['state'] === 'READ_SYNTHETIC_ONLY');
        check($store->countConsumed() === 1);
    },
    'U34-02 revoked grant cannot burn a fresh nonce before restoration' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $store, , $env, $owner, $ctx] = f134Fixture($policy);
        $policy->revokeFixture($ctx);
        $req = new MockCrmReadRequest(f112Wire($ctx));
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $bridge->evaluate($req, $env, $owner, $ctx, 15));
        check($store->countConsumed() === 0);
        $policy->grantFixture($ctx, ['ref']);
        check($bridge->evaluate($req, $env, $owner, $ctx, 15)['state'] === 'READ_SYNTHETIC_ONLY');
    },
    'U34-03 suspended tenant cannot exhaust nonce registry via denied reads' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $store, , $env, $owner, $ctx] = f134Fixture($policy);
        $policy->setTenantSuspended('SYNTHETIC_A', true);
        $req = new MockCrmReadRequest(f112Wire($ctx));
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $bridge->evaluate($req, $env, $owner, $ctx, 15));
        check($store->countConsumed() === 0);
        $policy->setTenantSuspended('SYNTHETIC_A', false);
        check($bridge->evaluate($req, $env, $owner, $ctx, 15)['state'] === 'READ_SYNTHETIC_ONLY');
    },
    'U34-04 policy outage preserves nonce until independent owner decision succeeds' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $store, , $env, $owner, $ctx] = f134Fixture($policy);
        $policy->setAvailable(false);
        $req = new MockCrmReadRequest(f112Wire($ctx));
        denied('OWNER_POLICY_UNAVAILABLE', fn() => $bridge->evaluate($req, $env, $owner, $ctx, 15));
        check($store->countConsumed() === 0);
        $policy->setAvailable(true);
        check($bridge->evaluate($req, $env, $owner, $ctx, 15)['state'] === 'READ_SYNTHETIC_ONLY');
    },
    'U34-05 field outside owner grant never consumes proof or returns projection' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$bridge, $store, , $env, $owner, $ctx] = f134Fixture($policy);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $req = new MockCrmReadRequest($wire);
        denied('RESOURCE_DENIED_OR_MISSING', fn() => $bridge->evaluate($req, $env, $owner, $ctx, 15));
        check($store->countConsumed() === 0);
        $policy->grantFixture($ctx, ['ref', 'stage']);
        check(array_keys($bridge->evaluate($req, $env, $owner, $ctx, 15)['projection'])
            === ['stage', 'ref']);
        check($store->countConsumed() === 1);
    },
];
