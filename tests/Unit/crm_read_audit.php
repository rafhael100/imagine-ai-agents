<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\{MockCrmReadAuditedBoundary, MockCrmReadAuditTrail};
use ImagineAI\MockBridge\{MockCrmReadRequest, MockCrmReadBoundary, MockCrmReadPublicFacade, MockCrmOwnerPolicy};
use ImagineAI\Contracts\CrmReader;
use ImagineAI\MockTrust\MockTrustGateway;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

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
];
