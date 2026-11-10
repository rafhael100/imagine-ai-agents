<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockBridge\{MockCrmOwnerPolicy, MockCrmReadPublicFacade};
use ImagineAI\MockTrust\{MockTrustGateway, MockSharedTrustState, MockTrustEnvelope, MockOwnerScopeEvidence};
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\Mocks\MockCrmReader;

function f117Fixture(
    ?MockCrmOwnerPolicy $policy = null,
    ?MockCrmReadAuditTrail $audit = null,
    ?TenantContext $context = null,
): array {
    $policy ??= new MockCrmOwnerPolicy();
    $audit ??= new MockCrmReadAuditTrail();
    [, $envelope, $owner, $ctx] = f112Fixture($context);
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
        ['SYNTHETIC_LEAD_READ']);
    $facade = new MockCrmReadPublicFacade($gateway, new MockCrmReader(), $policy, $audit);
    return [$facade, $policy, $audit, $envelope, $owner, $ctx];
}

function f117GenericDenial(): array
{
    return [
        'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
        'state' => 'DENIED_OR_NOT_FOUND',
        'projection' => null,
        'real_authorization' => false,
        'external_calls' => 0,
    ];
}

return [
    'U17-01 permitted fake minimal read gets fixed public response' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $out = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        check($out === [
            'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            'state' => 'READ_SYNTHETIC_ONLY',
            'projection' => ['ref' => 'SYNTHETIC_A_LEAD'],
            'real_authorization' => false,
            'external_calls' => 0,
        ]);
        check($audit->count() === 1);
    },
    'U17-02 explicit two-field projection preserves order and no extra fields' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref', 'stage']);
        [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $out = $facade->handle($wire, $env, $owner, $ctx, 15);
        check($out['state'] === 'READ_SYNTHETIC_ONLY');
        check(array_keys($out['projection']) === ['stage', 'ref']);
        check($out['projection'] === ['stage' => 'SYNTHETIC_NEW', 'ref' => 'SYNTHETIC_A_LEAD']);
    },
    'U17-03 absent grant and foreign tenant resource share identical public denial' => function (): void {
        [$facade, , , $env, $owner, $ctx] = f117Fixture();
        $notGranted = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        $cross = context('SYNTHETIC_A', 'SYNTHETIC_B_LEAD');
        [$otherFacade, , , $otherEnv, $otherOwner, $otherCtx] = f117Fixture(null, null, $cross);
        $foreign = $otherFacade->handle(f112Wire($otherCtx), $otherEnv, $otherOwner, $otherCtx, 15);
        check($notGranted === $foreign && $foreign === f117GenericDenial());
    },
    'U17-04 revoked, tenant suspended and missing field look identical publicly' => function (): void {
        $outcomes = [];
        for ($i = 0; $i < 3; $i++) {
            $p = new MockCrmOwnerPolicy();
            $p->grantFixture(context(), ['ref']);
            [$facade, , , $env, $owner, $ctx] = f117Fixture($p);
            if ($i === 0) $p->revokeFixture($ctx);
            if ($i === 1) $p->setTenantSuspended('SYNTHETIC_A', true);
            $wire = f112Wire($ctx);
            if ($i === 2) $wire['fields'] = ['stage'];
            $outcomes[] = $facade->handle($wire, $env, $owner, $ctx, 15);
        }
        check($outcomes[0] === $outcomes[1] && $outcomes[1] === $outcomes[2]);
        check($outcomes[0] === f117GenericDenial());
    },
    'U17-05 missing trust envelope and owner proof share public denial' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
        $missingEnv = $facade->handle(f112Wire($ctx), null, $owner, $ctx, 15);
        $missingOwner = $facade->handle(f112Wire($ctx), $env, null, $ctx, 15);
        check($missingEnv === $missingOwner && $missingEnv === f117GenericDenial());
    },
    'U17-06 expired evidence and replay never disclose differing internal errors' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
        $approved = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        $replayed = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        [$otherFacade, , , $lateEnv, $lateOwner, $lateCtx] = f117Fixture($policy);
        $expired = $otherFacade->handle(f112Wire($lateCtx), $lateEnv, $lateOwner, $lateCtx, 40);
        check($approved['state'] === 'READ_SYNTHETIC_ONLY');
        check($replayed === $expired && $replayed === f117GenericDenial());
    },
    'U17-07 body tenant substitution denies without leaking tenant or principal' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx); $wire['tenant_ref'] = 'SYNTHETIC_B';
        $out = $facade->handle($wire, $env, $owner, $ctx, 15);
        check($out === f117GenericDenial());
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'CONTEXT');
        check(!str_contains(json_encode($out, JSON_THROW_ON_ERROR), 'SYNTHETIC_B'));
    },
    'U17-08 unknown contract and mutation operations get identical denial' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $outcomes = [];
        foreach ([['contract' => 'SYNTHETIC_V2'], ['operation' => 'crm.task.create']] as $patch) {
            [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
            $outcomes[] = $facade->handle(array_replace(f112Wire($ctx), $patch), $env, $owner, $ctx, 15);
        }
        check($outcomes[0] === $outcomes[1] && $outcomes[0] === f117GenericDenial());
    },
    'U17-09 injected authority fields are rejected before any read' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        foreach ([['role' => 'SUPERUSER'], ['is_admin' => true], ['authorization' => 'SIGNED']] as $patch) {
            [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
            check($facade->handle(array_replace(f112Wire($ctx), $patch), $env, $owner,
                $ctx, 15) === f117GenericDenial());
            check($audit->count() === 0); // Parser rejects before F1-13 decorator.
        }
    },
    'U17-10 hidden personal and unknown fields cannot enter public projection' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref', 'stage']);
        foreach (['email', 'phone', 'notes', 'permissions'] as $field) {
            [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
            $wire = f112Wire($ctx); $wire['fields'] = [$field];
            check($facade->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        }
    },
    'U17-11 full audit capacity denies rather than returning unlogged data' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $audit = new MockCrmReadAuditTrail(1);
        [$facade, , , $env, $owner, $ctx] = f117Fixture($policy, $audit);
        check($facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15)
            === f117GenericDenial());
        check($audit->count() === 1);
    },
    'U17-12 owner policy outage is not exposed to API consumer' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $policy->setAvailable(false);
        check($facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15)
            === f117GenericDenial());
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'OWNER');
    },
    'U17-13 unavailable shared nonce state gets generic public denial' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $shared = new MockSharedTrustState(); $shared->setAvailable(false);
        [, $env, $owner, $ctx] = f112Fixture();
        $gate = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ'], $shared);
        $facade = new MockCrmReadPublicFacade($gate, new MockCrmReader(), $policy,
            new MockCrmReadAuditTrail());
        check($facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15)
            === f117GenericDenial());
    },
    'U17-14 internal audit reason stays internal across denied public response' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $policy->revokeFixture($ctx);
        $out = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        check($out === f117GenericDenial());
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'RESOURCE');
        $raw = json_encode($out, JSON_THROW_ON_ERROR);
        foreach (['RESOURCE', 'OWNER', 'TRUST', 'REPLAY', 'BRIDGE_', 'SYNTHETIC_A_LEAD'] as $internal) {
            check(!str_contains($raw, $internal));
        }
    },
    'U17-15 malformed request array never yields raw exception text' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        foreach ([['fields' => ['ref', 'ref']], ['fields' => []],
                  ['fields' => [['ref']]], ['tenant_ref' => 42]] as $patch) {
            [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
            check($facade->handle(array_replace(f112Wire($ctx), $patch),
                $env, $owner, $ctx, 15) === f117GenericDenial());
        }
    },
    'U17-16 public response schema is identical and metadata-minimized for both states' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , , $env, $owner, $ctx] = f117Fixture($policy);
        $success = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        $denial = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        $keys = ['contract', 'state', 'projection', 'real_authorization', 'external_calls'];
        check(array_keys($success) === $keys && array_keys($denial) === $keys);
        check($denial === f117GenericDenial());
        foreach ([$success, $denial] as $out) {
            foreach (['request_ref', 'tenant_ref', 'principal_ref', 'reason', 'error',
                'message', 'role', 'token', 'effect_ref'] as $private) {
                check(!array_key_exists($private, $out));
            }
            check($out['real_authorization'] === false && $out['external_calls'] === 0);
        }
    },

    'U68-01 a fabricated recheck without a completed read is denied' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx);
        check($facade->recheck($wire, $env, $owner, $ctx, 15) === false);
        check($audit->count() === 0);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 15) === true);
        check($audit->count() === 1);
    },
    'U68-02 a denied read creates no recheck receipt, even after policy recovery' => function (): void {
        [$facade, $policy, $audit, $env, $owner, $ctx] = f117Fixture();
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        $policy->grantFixture($ctx, ['ref']);
        check($facade->recheck($wire, $env, $owner, $ctx, 15) === false);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 15) === true);
        check($audit->count() === 2);
    },
    'U68-03 replayed or malformed read replaces positive receipt with denial' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 15));
        check($facade->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        $wire['authorization'] = 'Bearer SYNTHETIC_PRIVATE';
        check($facade->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        check($audit->count() === 2);
    },
    'U68-04 changed request or different owner proof cannot borrow a read receipt' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref', 'stage']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        $changed = $wire; $changed['request_ref'] = 'SYNTHETIC_NEW_REQUEST';
        check(!$facade->recheck($changed, $env, $owner, $ctx, 15));
        $changed = $wire; $changed['fields'] = ['stage'];
        check(!$facade->recheck($changed, $env, $owner, $ctx, 15));
        $copy = new MockOwnerScopeEvidence('SYNTHETIC_COPY_OWNER',
            $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        check(!$facade->recheck($wire, $env, $copy, $ctx, 15));
        check($facade->recheck($wire, $env, $owner, $ctx, 15));
        check($audit->count() === 1);
    },
    'U68-05 owner revoke then regrant cannot resurrect the old authorization' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        $revision = $policy->revision();
        $policy->revokeFixture($ctx);
        $policy->grantFixture($ctx, ['ref']);
        check($policy->revision() === $revision + 2);
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        $fresh = new MockTrustEnvelope('SYNTHETIC_SEC46_FRESH_ENV',
            'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API', $ctx,
            'SYNTHETIC_SEC46_FRESH_NONCE', ['SYNTHETIC_LEAD_READ'], 10, 40);
        $freshOwner = new MockOwnerScopeEvidence('SYNTHETIC_SEC46_FRESH_OWNER',
            $fresh->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 40, true);
        check($facade->handle($wire, $fresh, $freshOwner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $fresh, $freshOwner, $ctx, 15));
        check($audit->count() === 2);
    },
    'U68-06 suspended and resumed tenant or member invalidates old receipt' => function (): void {
        foreach (['TENANT', 'MEMBER'] as $kind) {
            $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
            [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
            $wire = f112Wire($ctx);
            check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
                === 'READ_SYNTHETIC_ONLY');
            if ($kind === 'TENANT') {
                $policy->setTenantSuspended($ctx->tenant, true);
                $policy->setTenantSuspended($ctx->tenant, false);
            } else {
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, true);
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, false);
            }
            check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
            check($audit->count() === 1);
        }
    },
    'U68-07 owner outage and recovery does not restore a stale read receipt' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($policy);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        $policy->setAvailable(false);
        $policy->setAvailable(true);
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        check($audit->count() === 1);
    },

    'U69-01 backward recheck time invalidates the current read receipt' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check(!$facade->recheck($wire, $env, $owner, $ctx, 14));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        check($audit->count() === 1);
    },
    'U69-02 equal and forward check times keep one receipt and no extra audit' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        foreach ([15, 16, 20, 39] as $now) {
            check($facade->recheck($wire, $env, $owner, $ctx, $now));
        }
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'NONE');
    },
    'U69-03 expiry at exact boundary cannot be undone by rewinding the clock' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 39));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 40));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 39));
        check($audit->count() === 1);
    },
    'U69-04 shorter owner-proof expiry is sticky even with envelope still live' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, , $ctx] = f117Fixture($p);
        $owner = new MockOwnerScopeEvidence('SYNTHETIC_SEC47_SHORT_OWNER',
            $env->digest, $ctx, 'SYNTHETIC_LEAD_READ', 10, 20, true);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 19));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 20));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 19));
        check($audit->count() === 1);
    },
    'U69-05 invalid negative time cannot later reuse the same read receipt' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check(!$facade->recheck($wire, $env, $owner, $ctx, -1));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        check($audit->count() === 1);
    },
    'U69-06 mismatched wire cannot advance a valid receipt clock' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref', 'stage']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        $other = $wire; $other['fields'] = ['stage'];
        check(!$facade->recheck($other, $env, $owner, $ctx, 39));
        check($facade->recheck($wire, $env, $owner, $ctx, 16));
        check($audit->count() === 1);
    },
    'U69-07 malformed recheck invalidates receipt without another read' => function (): void {
        $p = new MockCrmOwnerPolicy(); $p->grantFixture(context(), ['ref']);
        [$facade, , $audit, $env, $owner, $ctx] = f117Fixture($p);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        $bad = $wire; $bad['fields'] = [['ref']];
        check(!$facade->recheck($bad, $env, $owner, $ctx, 16));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 16));
        check($audit->count() === 1);
    },
    'U69-08 shared proof receipt never consumes a second nonce after expiry' => function (): void {
        $ctx = context();
        $p = new MockCrmOwnerPolicy(); $p->grantFixture($ctx, ['ref']);
        [, $env, $owner] = f112Fixture();
        $state = new MockSharedTrustState();
        $audit = new MockCrmReadAuditTrail();
        $facade = new MockCrmReadPublicFacade(
            new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
                ['SYNTHETIC_LEAD_READ'], $state),
            new MockCrmReader(), $p, $audit);
        $wire = f112Wire($ctx);
        check($facade->handle($wire, $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($facade->recheck($wire, $env, $owner, $ctx, 20));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 40));
        check(!$facade->recheck($wire, $env, $owner, $ctx, 15));
        check($state->countConsumed() === 1 && $audit->count() === 1);
    },
];
