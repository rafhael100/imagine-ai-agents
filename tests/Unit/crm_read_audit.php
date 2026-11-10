<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\{MockCrmReadAuditedBoundary, MockCrmReadAuditTrail};
use ImagineAI\MockBridge\{MockCrmReadRequest, MockCrmReadBoundary, MockCrmReadPublicFacade, MockCrmOwnerPolicy};
use ImagineAI\Contracts\CrmReader;
use ImagineAI\MockBridge\MockReadProjectionInvariant;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockSharedTrustState};

function f113Fixture(?TenantContext $ctx = null, ?MockCrmReadAuditTrail $trail = null): array
{
    [$boundary, $env, $owner, $context] = f112Fixture($ctx);
    $trail ??= new MockCrmReadAuditTrail();
    return [new MockCrmReadAuditedBoundary($boundary, $trail), $trail, $env, $owner, $context];
}


/** Tests an untrusted mock reader; never a real CRM adapter. */
final class F141FaultyReader implements CrmReader
{
    public function __construct(private readonly string $mode) {}

    public function lead(TenantContext $context, array $fields): array
    {
        $row = ['ref' => $context->resource, 'stage' => 'SYNTHETIC_NEW'];
        if ($this->mode === 'wrong_ref') $row['ref'] = 'SYNTHETIC_B_LEAD';
        if ($this->mode === 'private_ref') $row['ref'] = 'private@example.invalid';
        if ($this->mode === 'wrong_stage') $row['stage'] = 'SYNTHETIC_ADMIN';
        if ($this->mode === 'non_string') $row['ref'] = 123;
        if ($this->mode === 'huge_ref') $row['ref'] = 'SYNTHETIC_' . str_repeat('X', 1024 * 1024);
        if ($this->mode === 'huge_stage') $row['stage'] = 'SYNTHETIC_' . str_repeat('Y', 1024 * 1024);
        if ($this->mode === 'missing') return [];
        $out = array_intersect_key($row, array_flip($fields));
        if ($this->mode === 'extra') $out['secret'] = 'private@example.invalid';
        return $out;
    }

    public function attendance(TenantContext $context, array $fields): array
    {
        return [];
    }
}

function f141Fixture(string $mode): array
{
    [, $env, $owner, $ctx] = f112Fixture();
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref', 'stage']);
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ']);
    $trail = new MockCrmReadAuditTrail();
    $reader = new F141FaultyReader($mode);
    $boundary = new MockCrmReadAuditedBoundary(
        new MockCrmReadBoundary($gateway, $reader, $policy), $trail);
    return [$boundary, $trail, $env, $owner, $ctx];
}

/**
 * Malicious or re-entrant local adapter: mutate a trust/owner fixture after
 * observing a lead but before its result reaches the success-audit boundary.
 * No real concurrent service, network or CRM database is represented.
 */
final class F165MutatingReader implements CrmReader
{
    public int $calls = 0;

    public function __construct(private readonly \Closure $duringRead) {}

    public function lead(TenantContext $context, array $fields): array
    {
        ++$this->calls;
        $row = ['ref' => $context->resource, 'stage' => 'SYNTHETIC_NEW'];
        $result = array_intersect_key($row, array_flip($fields));
        ($this->duringRead)();
        return $result;
    }

    public function attendance(TenantContext $context, array $fields): array
    {
        return [];
    }
}

/** @return array{0:MockCrmReadAuditedBoundary,1:MockCrmReadAuditTrail,2:MockTrustEnvelope,3:MockOwnerScopeEvidence,4:TenantContext,5:MockCrmOwnerPolicy,6:F165MutatingReader,7:MockTrustGateway} */
function f165Fixture(\Closure $duringRead, ?MockSharedTrustState $state = null): array
{
    [, $envelope, $owner, $ctx] = f112Fixture();
    $policy = new MockCrmOwnerPolicy();
    $policy->grantFixture($ctx, ['ref', 'stage']);
    $reader = new F165MutatingReader(function () use (
        $duringRead, $policy, $envelope, $owner, $ctx, $state
    ): void {
        $duringRead($policy, $envelope, $owner, $ctx, $state);
    });
    $gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE',
        'SYNTHETIC_CRM_API', ['SYNTHETIC_LEAD_READ'], $state);
    $trail = new MockCrmReadAuditTrail();
    $boundary = new MockCrmReadAuditedBoundary(
        new MockCrmReadBoundary($gateway, $reader, $policy), $trail);
    return [$boundary, $trail, $envelope, $owner, $ctx, $policy, $reader, $gateway];
}

return [
    'U13-01 allowed fake read emits one redacted decision event' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $result = $boundary->evaluate(new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15);
        check($result['projection'] === ['ref' => 'SYNTHETIC_A_LEAD']);
        check($trail->count() === 1);
        $event = $trail->entries()[0];
        check($event['outcome'] === 'READ_SYNTHETIC_ONLY' && $event['reason'] === 'NONE');
        check($event['real_authorization'] === false && $event['external_calls'] === 0);
    },
    'U13-02 journal stores no lead projection, tenant, principal or request ref' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $boundary->evaluate(new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15);
        $raw = json_encode($trail->entries(), JSON_THROW_ON_ERROR);
        foreach ([$ctx->tenant, $ctx->principal, $ctx->resource, 'SYNTHETIC_REQUEST_A',
            'SYNTHETIC_NEW', 'projection', 'fields', 'token'] as $sensitive) {
            check(!str_contains($raw, json_encode($sensitive, JSON_THROW_ON_ERROR)));
        }
        check(preg_match('/^[0-9a-f]{64}$/D', $trail->entries()[0]['context_fingerprint']) === 1);
    },
    'U13-03 body/context mismatch records denied without data' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $wire = f112Wire(); $wire['tenant_ref'] = 'SYNTHETIC_B';
        denied('BRIDGE_CONTEXT_DENIED', fn () => $boundary->evaluate(
            new MockCrmReadRequest($wire), $env, $owner, $ctx, 15));
        check($trail->count() === 1 && $trail->entries()[0]['reason'] === 'CONTEXT');
    },
    'U13-04 owner proof denial logged as category only' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $owner->revoke();
        denied('OWNER_DENIED', fn () => $boundary->evaluate(new MockCrmReadRequest(f112Wire()),
            $env, $owner, $ctx, 15));
        check($trail->entries()[0]['reason'] === 'OWNER');
    },
    'U13-05 missing envelope denied and safely categorized' => function (): void {
        [$boundary, $trail, , $owner, $ctx] = f113Fixture();
        denied('TRUST_DENIED', fn () => $boundary->evaluate(new MockCrmReadRequest(f112Wire()),
            null, $owner, $ctx, 15));
        check($trail->entries()[0]['reason'] === 'TRUST');
    },
    'U13-06 nonce replay logged as denial, not second success' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $req = new MockCrmReadRequest(f112Wire());
        $boundary->evaluate($req, $env, $owner, $ctx, 15);
        denied('TRUST_REPLAY_DENIED', fn () => $boundary->evaluate($req, $env, $owner, $ctx, 15));
        check(array_column($trail->entries(), 'reason') === ['NONE', 'REPLAY']);
    },
    'U13-07 exhausted audit capacity denies before second read' => function (): void {
        $trail = new MockCrmReadAuditTrail(1);
        [$boundary, , $env, $owner, $ctx] = f113Fixture(null, $trail);
        $req = new MockCrmReadRequest(f112Wire());
        $boundary->evaluate($req, $env, $owner, $ctx, 15);
        denied('AUDIT_CAPACITY_DENIED', fn () => $boundary->evaluate($req, $env, $owner, $ctx, 15));
        check($trail->count() === 1);
    },
    'U13-08 audit schema rejects forged success or arbitrary errors' => function (): void {
        $trail = new MockCrmReadAuditTrail();
        denied('AUDIT_SCHEMA_DENIED', fn () => $trail->append(context(),
            'SYNTHETIC_REQUEST_A', 'READ_SYNTHETIC_ONLY', 'OWNER'));
        denied('AUDIT_SCHEMA_DENIED', fn () => $trail->append(context(),
            'SYNTHETIC_REQUEST_A', 'DENIED', 'PRIVATE_ERROR_PAYLOAD'));
        denied('AUDIT_SCHEMA_DENIED', fn () => $trail->append(context(),
            'SYNTHETIC_REQUEST_A', 'AUTHORIZED_REAL', 'NONE'));
        check($trail->count() === 0);
    },
    'U13-09 invalid limits and non synthetic event references denied' => function (): void {
        denied('AUDIT_SCHEMA_DENIED', fn () => new MockCrmReadAuditTrail(0));
        denied('AUDIT_SCHEMA_DENIED', fn () => new MockCrmReadAuditTrail(101));
        $trail = new MockCrmReadAuditTrail();
        denied('AUDIT_SCHEMA_DENIED', fn () => $trail->append(context(), 'CRM-123', 'DENIED', 'TRUST'));
    },
    'U13-10 returned event copies cannot mutate internal log' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $boundary->evaluate(new MockCrmReadRequest(f112Wire()), $env, $owner, $ctx, 15);
        $copy = $trail->entries();
        $copy[0]['outcome'] = 'AUTHORIZED_REAL';
        check($trail->entries()[0]['outcome'] === 'READ_SYNTHETIC_ONLY');
    },
    'U13-11 distinct fictional tenant contexts get distinct anonymous fingerprints' => function (): void {
        $trail = new MockCrmReadAuditTrail();
        $trail->append(context('SYNTHETIC_A'), 'SYNTHETIC_REQUEST_A', 'DENIED', 'CONTEXT');
        $trail->append(context('SYNTHETIC_B'), 'SYNTHETIC_REQUEST_B', 'DENIED', 'CONTEXT');
        check($trail->entries()[0]['context_fingerprint'] !== $trail->entries()[1]['context_fingerprint']);
        check($trail->entries()[0]['request_fingerprint'] !== $trail->entries()[1]['request_fingerprint']);
    },
    'U13-12 audited boundary preserves F1-12 field order and read-only result' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f113Fixture();
        $wire = f112Wire(); $wire['fields'] = ['stage', 'ref'];
        $result = $boundary->evaluate(new MockCrmReadRequest($wire), $env, $owner, $ctx, 15);
        check(array_keys($result['projection']) === ['stage', 'ref']);
        check($result['real_authorization'] === false && $result['external_calls'] === 0);
        check(!array_key_exists('projection', $trail->entries()[0]));
    },

    'U41-01 foreign-looking lead ref is rejected before success audit' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f141Fixture('wrong_ref');
        denied('BRIDGE_PROJECTION_DENIED', fn() => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($trail->count() === 1);
        check($trail->entries()[0]['outcome'] === 'DENIED'
            && $trail->entries()[0]['reason'] === 'PROJECTION');
        check(!str_contains(json_encode($trail->entries(), JSON_THROW_ON_ERROR),
            'SYNTHETIC_B_LEAD'));
    },
    'U41-02 private reader payload cannot be recorded as a success or leaked' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f141Fixture('private_ref');
        denied('BRIDGE_PROJECTION_DENIED', fn() => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($trail->count() === 1 && $trail->entries()[0]['outcome'] === 'DENIED');
        check(!str_contains(json_encode($trail->entries(), JSON_THROW_ON_ERROR),
            'private@example.invalid'));
    },
    'U41-03 syntactically valid but unapproved stage is denied in the bridge' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f141Fixture('wrong_stage');
        $wire = f112Wire($ctx); $wire['fields'] = ['stage'];
        denied('BRIDGE_PROJECTION_DENIED', fn() => $boundary->evaluate(
            new MockCrmReadRequest($wire), $env, $owner, $ctx, 15));
        check($trail->entries()[0]['reason'] === 'PROJECTION'
            && $trail->entries()[0]['outcome'] === 'DENIED');
    },
    'U41-04 reader supplying an extra unrequested field is audit-denied' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f141Fixture('extra');
        denied('BRIDGE_PROJECTION_DENIED', fn() => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($trail->count() === 1 && $trail->entries()[0]['reason'] === 'PROJECTION');
        check(!str_contains(json_encode($trail->entries(), JSON_THROW_ON_ERROR),
            'private@example.invalid'));
    },
    'U41-05 missing and non-string reader values cannot forge success audit' => function (): void {
        foreach (['missing', 'non_string'] as $mode) {
            [$boundary, $trail, $env, $owner, $ctx] = f141Fixture($mode);
            denied('BRIDGE_PROJECTION_DENIED', fn() => $boundary->evaluate(
                new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
            check($trail->count() === 1 && $trail->entries()[0]['outcome'] === 'DENIED');
        }
    },
    'U41-06 valid interface reader preserves field ordering and audited success' => function (): void {
        [$boundary, $trail, $env, $owner, $ctx] = f141Fixture('valid');
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $out = $boundary->evaluate(new MockCrmReadRequest($wire),
            $env, $owner, $ctx, 15);
        check($out['projection'] === ['stage' => 'SYNTHETIC_NEW',
            'ref' => 'SYNTHETIC_A_LEAD']);
        check($trail->count() === 1
            && $trail->entries()[0]['outcome'] === 'READ_SYNTHETIC_ONLY'
            && $trail->entries()[0]['reason'] === 'NONE');
    },

    'U64-01 shared validator preserves the caller order for only authorized fields' => function (): void {
        $ctx = context();
        $readerOrder = ['ref' => 'SYNTHETIC_A_LEAD', 'stage' => 'SYNTHETIC_NEW'];
        $ordered = MockReadProjectionInvariant::canonicalize(
            $readerOrder, ['stage', 'ref'], $ctx);
        check($ordered === ['stage' => 'SYNTHETIC_NEW', 'ref' => 'SYNTHETIC_A_LEAD']);
        check(!MockReadProjectionInvariant::isCanonical(
            $readerOrder, ['stage', 'ref'], $ctx));
        check(MockReadProjectionInvariant::isCanonical(
            $ordered, ['stage', 'ref'], $ctx));
    },
    'U64-02 shared validator refuses extra missing forged and duplicate projections' => function (): void {
        $ctx = context();
        foreach ([
            ['ref' => 'SYNTHETIC_A_LEAD', 'email' => 'private@example.invalid'],
            ['ref' => 'SYNTHETIC_A_LEAD', 'stage' => 'SYNTHETIC_NEW', 'private' => true],
            ['stage' => 'SYNTHETIC_NEW'],
            ['ref' => 'SYNTHETIC_A_LEAD', 0 => 'SYNTHETIC_NEW'],
        ] as $candidate) {
            check(MockReadProjectionInvariant::canonicalize(
                $candidate, ['ref'], $ctx) === null);
        }
        check(MockReadProjectionInvariant::canonicalize(
            [], [], $ctx) === null);
        check(MockReadProjectionInvariant::canonicalize(
            ['ref' => 'SYNTHETIC_A_LEAD'], ['ref', 'ref'], $ctx) === null);
        check(MockReadProjectionInvariant::canonicalize(
            ['email' => 'SYNTHETIC_FAKE'], ['email'], $ctx) === null);
    },
    'U64-03 a 1 MiB reader ref cannot enter a projection or trace' => function (): void {
        $ctx = context();
        $huge = 'SYNTHETIC_' . str_repeat('X', 1024 * 1024);
        check(MockReadProjectionInvariant::canonicalize(
            ['ref' => $huge], ['ref'], $ctx) === null);
        check(!MockReadProjectionInvariant::isCanonical(
            ['ref' => $huge], ['ref'], $ctx));
    },
    'U64-04 huge stage and raw personal values are denied before regex' => function (): void {
        $ctx = context();
        $huge = 'SYNTHETIC_' . str_repeat('Y', 1024 * 1024);
        check(MockReadProjectionInvariant::canonicalize(
            ['stage' => $huge], ['stage'], $ctx) === null);
        foreach (['private@example.invalid', "SYNTHETIC_NEW\n", "SYNTHETIC_NEW\x00"] as $value) {
            check(MockReadProjectionInvariant::canonicalize(
                ['stage' => $value], ['stage'], $ctx) === null);
        }
    },
    'U64-05 stage and resource bind strictly to trusted fictional context' => function (): void {
        $ctx = context();
        check(MockReadProjectionInvariant::canonicalize(
            ['ref' => 'SYNTHETIC_B_LEAD'], ['ref'], $ctx) === null);
        check(MockReadProjectionInvariant::canonicalize(
            ['stage' => 'SYNTHETIC_ADMIN'], ['stage'], $ctx) === null);
        check(MockReadProjectionInvariant::canonicalize(
            ['ref' => 'SYNTHETIC_A_LEAD'], ['ref'], context('SYNTHETIC_B')) === null);
        check(MockReadProjectionInvariant::canonicalize(
            ['stage' => 'SYNTHETIC_NEW'], ['stage'], $ctx) ===
                ['stage' => 'SYNTHETIC_NEW']);
    },
    'U64-06 nonstring reader values cannot be normalized into success' => function (): void {
        $ctx = context();
        foreach ([null, false, 1, ['SYNTHETIC_A_LEAD'], new \stdClass()] as $bad) {
            check(MockReadProjectionInvariant::canonicalize(
                ['ref' => $bad], ['ref'], $ctx) === null);
        }
        check(MockReadProjectionInvariant::canonicalize(
            ['ref' => 'SYNTHETIC_A_LEAD'], ['ref'], $ctx) ===
                ['ref' => 'SYNTHETIC_A_LEAD']);
    },
    'U64-07 oversized malicious reader ref is denied before success audit and replay stays blocked' => function (): void {
        [$bridge, $trail, $env, $owner, $ctx] = f141Fixture('huge_ref');
        $request = new MockCrmReadRequest(f112Wire($ctx));
        denied('BRIDGE_PROJECTION_DENIED', fn () => $bridge->evaluate(
            $request, $env, $owner, $ctx, 15));
        check($trail->count() === 1 && $trail->entries()[0]['reason'] === 'PROJECTION'
            && $trail->entries()[0]['outcome'] === 'DENIED');
        denied('TRUST_REPLAY_DENIED', fn () => $bridge->evaluate(
            $request, $env, $owner, $ctx, 15));
        check(array_column($trail->entries(), 'reason') === ['PROJECTION', 'REPLAY']);
        $encoded = json_encode($trail->entries(), JSON_THROW_ON_ERROR);
        check(strlen($encoded) < 1100 && !str_contains($encoded, str_repeat('X', 100)));
    },
    'U64-08 oversized malicious stage receives only a redacted projection denial' => function (): void {
        [$bridge, $trail, $env, $owner, $ctx] = f141Fixture('huge_stage');
        $wire = f112Wire($ctx); $wire['fields'] = ['stage'];
        denied('BRIDGE_PROJECTION_DENIED', fn () => $bridge->evaluate(
            new MockCrmReadRequest($wire), $env, $owner, $ctx, 15));
        check($trail->count() === 1 && $trail->entries()[0]['outcome'] === 'DENIED'
            && $trail->entries()[0]['reason'] === 'PROJECTION');
        check(strlen(json_encode($trail->entries(), JSON_THROW_ON_ERROR)) < 800);
    },
    'U64-09 valid alternate-order public and telemetry responses remain unchanged' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref', 'stage']);
        $audit = new MockCrmReadAuditTrail();
        [, $env, $owner, $ctx] = f112Fixture();
        $facade = new MockCrmReadPublicFacade(
            new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
                ['SYNTHETIC_LEAD_READ']),
            new \ImagineAI\Mocks\MockCrmReader(), $policy, $audit);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $out = $facade->handle($wire, $env, $owner, $ctx, 15);
        check($out['projection'] === ['stage' => 'SYNTHETIC_NEW',
            'ref' => 'SYNTHETIC_A_LEAD']);
        check($out['real_authorization'] === false && $out['external_calls'] === 0);
        check($audit->count() === 1 && $audit->entries()[0]['reason'] === 'NONE');
    },

    'U65-01 owner grant revoked during read cannot create a success audit' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx, , $reader] = f165Fixture(
            function ($policy, $envelope, $owner, $ctx): void {
                $policy->revokeFixture($ctx);
            });
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($reader->calls === 1 && $audit->count() === 1);
        check($audit->entries()[0]['outcome'] === 'DENIED'
            && $audit->entries()[0]['reason'] === 'RESOURCE');
    },
    'U65-02 tenant suspended while reading is denied before success audit' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy, $envelope, $owner, $ctx): void {
                $policy->setTenantSuspended($ctx->tenant, true);
            });
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check(array_column($audit->entries(), 'reason') === ['RESOURCE']);
    },
    'U65-03 member suspended while reading cannot retain earlier permission' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy, $envelope, $owner, $ctx): void {
                $policy->setMemberSuspended($ctx->tenant, $ctx->principal, true);
            });
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($audit->entries()[0]['outcome'] === 'DENIED'
            && $audit->entries()[0]['reason'] === 'RESOURCE');
    },
    'U65-04 owner policy outage during reader work is fail closed' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy): void { $policy->setAvailable(false); });
        denied('OWNER_POLICY_UNAVAILABLE', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($audit->entries()[0]['reason'] === 'OWNER');
        check($audit->entries()[0]['outcome'] === 'DENIED');
    },
    'U65-05 revoked service envelope during reader work is fail closed' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy, $envelope): void { $envelope->revoke(); });
        denied('TRUST_DENIED', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($audit->entries()[0]['reason'] === 'TRUST'
            && $audit->entries()[0]['outcome'] === 'DENIED');
    },
    'U65-06 revoked owner scope during reader work is fail closed' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy, $envelope, $owner): void { $owner->revoke(); });
        denied('OWNER_DENIED', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($audit->entries()[0]['reason'] === 'OWNER'
            && $audit->entries()[0]['outcome'] === 'DENIED');
    },
    'U65-07 shared revocation arriving during read is detected without second nonce' => function (): void {
        $state = new MockSharedTrustState();
        [$boundary, $audit, $env, $owner, $ctx, , $reader] = f165Fixture(
            function ($policy, $envelope, $owner, $ctx, $state): void {
                $state->revokeDigest($envelope->digest);
            }, $state);
        denied('TRUST_DENIED', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($state->countConsumed() === 1 && $state->countRevoked() === 1);
        check($reader->calls === 1 && $audit->entries()[0]['reason'] === 'TRUST');
    },
    'U65-08 shared revocation store outage during reader work fails closed' => function (): void {
        $state = new MockSharedTrustState();
        [$boundary, $audit, $env, $owner, $ctx] = f165Fixture(
            function ($policy, $envelope, $owner, $ctx, $state): void {
                $state->setAvailable(false);
            }, $state);
        denied('TRUST_STATE_UNAVAILABLE', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($state->countConsumed() === 1);
        check($audit->entries()[0]['reason'] === 'TRUST'
            && $audit->entries()[0]['outcome'] === 'DENIED');
    },
    'U65-09 normal post-read recheck is read-only with single nonce and ordered result' => function (): void {
        $state = new MockSharedTrustState();
        [$boundary, $audit, $env, $owner, $ctx, , $reader, $gateway] = f165Fixture(
            function (): void {}, $state);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage', 'ref'];
        $request = new MockCrmReadRequest($wire);
        $result = $boundary->evaluate($request, $env, $owner, $ctx, 15);
        check($result['projection'] === ['stage' => 'SYNTHETIC_NEW',
            'ref' => 'SYNTHETIC_A_LEAD']);
        check($state->countConsumed() === 1 && $reader->calls === 1);
        $gateway->recheck($env, $owner, $ctx, 'SYNTHETIC_LEAD_READ', 15);
        check($state->countConsumed() === 1);
        denied('TRUST_REPLAY_DENIED', fn () => $boundary->evaluate(
            $request, $env, $owner, $ctx, 15));
        check(array_column($audit->entries(), 'outcome') ===
            ['READ_SYNTHETIC_ONLY', 'DENIED']);
        check(array_column($audit->entries(), 'reason') === ['NONE', 'REPLAY']);
    },
    'U65-10 public facade masks mid-read revocation without returning a projection' => function (): void {
        [, $env, $owner, $ctx] = f112Fixture();
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture($ctx, ['ref']);
        $reader = new F165MutatingReader(function () use ($policy, $ctx): void {
            $policy->revokeFixture($ctx);
        });
        $audit = new MockCrmReadAuditTrail();
        $facade = new MockCrmReadPublicFacade(new MockTrustGateway(
            'SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
            ['SYNTHETIC_LEAD_READ']), $reader, $policy, $audit);
        $reply = $facade->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        check($reply === [
            'contract' => 'SYNTHETIC_CRM_AIA_PUBLIC_V1',
            'state' => 'DENIED_OR_NOT_FOUND', 'projection' => null,
            'real_authorization' => false, 'external_calls' => 0,
        ]);
        check($reader->calls === 1 && $audit->count() === 1);
        check($audit->entries()[0]['reason'] === 'RESOURCE');
        check(!str_contains(json_encode($reply, JSON_THROW_ON_ERROR), $ctx->resource));
    },

    'U68-08 owner revoke and immediate regrant during reader is still denied' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx, $policy, $reader] =
            f165Fixture(function ($policy, $envelope, $owner, $ctx): void {
                $policy->revokeFixture($ctx);
                $policy->grantFixture($ctx, ['ref', 'stage']);
            });
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($reader->calls === 1 && $audit->count() === 1
            && $audit->entries()[0]['reason'] === 'RESOURCE'
            && $audit->entries()[0]['outcome'] === 'DENIED');
        // A fresh proof may authorize a later read in a new gateway;
        // the already consumed nonce must never be replayed.
        denied('TRUST_REPLAY_DENIED', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
    },
    'U68-09 tenant suspend and resume while reading never records success' => function (): void {
        [$boundary, $audit, $env, $owner, $ctx, $policy, $reader] =
            f165Fixture(function ($policy, $envelope, $owner, $ctx): void {
                $policy->setTenantSuspended($ctx->tenant, true);
                $policy->setTenantSuspended($ctx->tenant, false);
            });
        denied('RESOURCE_DENIED_OR_MISSING', fn () => $boundary->evaluate(
            new MockCrmReadRequest(f112Wire($ctx)), $env, $owner, $ctx, 15));
        check($reader->calls === 1 && $audit->count() === 1
            && $audit->entries()[0]['reason'] === 'RESOURCE'
            && $audit->entries()[0]['outcome'] === 'DENIED');
    },
];
