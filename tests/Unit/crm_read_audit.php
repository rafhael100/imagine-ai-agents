<?php
declare(strict_types=1);

use ImagineAI\Domain\TenantContext;
use ImagineAI\MockAudit\{MockCrmReadAuditedBoundary, MockCrmReadAuditTrail};
use ImagineAI\MockBridge\MockCrmReadRequest;
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence};

function f113Fixture(?TenantContext $ctx = null, ?MockCrmReadAuditTrail $trail = null): array
{
    [$boundary, $env, $owner, $context] = f112Fixture($ctx);
    $trail ??= new MockCrmReadAuditTrail();
    return [new MockCrmReadAuditedBoundary($boundary, $trail), $trail, $env, $owner, $context];
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
];
