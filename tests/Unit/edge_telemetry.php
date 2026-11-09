<?php
declare(strict_types=1);

use ImagineAI\MockBridge\MockCrmOwnerPolicy;
use ImagineAI\MockAudit\MockCrmReadAuditTrail;
use ImagineAI\MockTelemetry\{MockCrmReadEdgeBoundary, MockCrmReadEdgeCounters};

function f118Fixture(
    ?MockCrmOwnerPolicy $policy = null,
    ?MockCrmReadAuditTrail $audit = null,
    ?MockCrmReadEdgeCounters $counters = null,
): array {
    [$facade, $policy, $audit, $envelope, $owner, $ctx] = f117Fixture($policy, $audit);
    $counters ??= new MockCrmReadEdgeCounters();
    return [new MockCrmReadEdgeBoundary($facade, $counters),
        $counters, $policy, $audit, $envelope, $owner, $ctx];
}

return [
    'U18-01 accepted fictitious read increments only aggregate success' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture($policy);
        $out = $edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15);
        check($out['state'] === 'READ_SYNTHETIC_ONLY');
        check($counters->snapshot()['outcomes']['READ_SYNTHETIC_ONLY'] === 1);
        check($counters->snapshot()['attempts'] === 1 && $audit->count() === 1);
    },
    'U18-02 schema failure counted before internal audit sees any request' => function (): void {
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture();
        $wire = f112Wire($ctx); $wire['is_admin'] = true;
        check($edge->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        check($counters->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);
        check($audit->count() === 0);
    },
    'U18-03 raw sensitive-looking injected values never appear in counters or reply' => function (): void {
        [$edge, $counters, , , $env, $owner, $ctx] = f118Fixture();
        $wire = f112Wire($ctx); $wire['authorization'] = 'Bearer secret-123@hidden.example';
        $out = $edge->handle($wire, $env, $owner, $ctx, 15);
        $visible = json_encode([$out, $counters->snapshot()], JSON_THROW_ON_ERROR);
        check($out === f117GenericDenial());
        foreach (['hidden.example', 'Bearer', 'secret-123', 'SYNTHETIC_A_LEAD', 'SYNTHETIC_REQUEST_A'] as $secret) {
            check(!str_contains($visible, $secret));
        }
    },
    'U18-04 malformed types and projections counted without business identifiers' => function (): void {
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture();
        foreach ([['tenant_ref' => 42], ['fields' => [['ref']]], ['fields' => []],
            ['fields' => ['ref', 'ref']], ['contract' => 'UNSUPPORTED']] as $patch) {
            check($edge->handle(array_replace(f112Wire($ctx), $patch),
                $env, $owner, $ctx, 15) === f117GenericDenial());
        }
        check($counters->snapshot()['attempts'] === 5);
        check($counters->snapshot()['outcomes']['SCHEMA_DENIED'] === 5 && $audit->count() === 0);
    },
    'U18-05 absent trust evidence increments generic policy counter only' => function (): void {
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture();
        check($edge->handle(f112Wire($ctx), null, $owner, $ctx, 15)
            === f117GenericDenial());
        check($counters->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
        check($audit->count() === 1);
    },
    'U18-06 unavailable owner policy is indistinguishable in public output' => function (): void {
        $policy = new MockCrmOwnerPolicy();
        $policy->grantFixture(context(), ['ref']);
        $policy->setAvailable(false);
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture($policy);
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15)
            === f117GenericDenial());
        check($counters->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
        check($audit->entries()[0]['reason'] === 'OWNER');
    },
    'U18-07 context mismatch gets generic rejection, never a context label' => function (): void {
        [$edge, $counters, , , $env, $owner, $ctx] = f118Fixture();
        $wire = f112Wire($ctx); $wire['tenant_ref'] = 'SYNTHETIC_B';
        check($edge->handle($wire, $env, $owner, $ctx, 15) === f117GenericDenial());
        check($counters->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
        check(!str_contains(json_encode($counters->snapshot()), 'SYNTHETIC_B'));
    },
    'U18-08 bounded counters deny subsequent reads before owner or audit' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
        $audit = new MockCrmReadAuditTrail();
        [$edge, $counters, , , $env, $owner, $ctx] = f118Fixture($policy, $audit,
            new MockCrmReadEdgeCounters(1));
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15)['state']
            === 'READ_SYNTHETIC_ONLY');
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f117GenericDenial());
        check($counters->snapshot()['attempts'] === 1 && $audit->count() === 1);
    },
    'U18-09 malformed attempt uses last recording slot and future reads fail closed' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref']);
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture($policy, null,
            new MockCrmReadEdgeCounters(1));
        $bad = f112Wire($ctx); unset($bad['purpose_ref']);
        check($edge->handle($bad, $env, $owner, $ctx, 15) === f117GenericDenial());
        check($edge->handle(f112Wire($ctx), $env, $owner, $ctx, 15) === f117GenericDenial());
        check($counters->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);
        check($audit->count() === 0);
    },
    'U18-10 unknown ticket and outcome cannot forge counter entries' => function (): void {
        $counters = new MockCrmReadEdgeCounters();
        denied('EDGE_SCHEMA_DENIED', fn () => $counters->finish(999, 'READ_SYNTHETIC_ONLY'));
        $ticket = $counters->begin();
        denied('EDGE_SCHEMA_DENIED', fn () => $counters->finish($ticket, 'ROOT_ACCESS'));
        check($counters->snapshot()['pending'] === 1);
        $counters->finish($ticket, 'SCHEMA_DENIED');
        check($counters->snapshot()['outcomes']['SCHEMA_DENIED'] === 1);
    },
    'U18-11 ticket cannot be completed twice or with a negative index' => function (): void {
        $counters = new MockCrmReadEdgeCounters();
        $ticket = $counters->begin();
        $counters->finish($ticket, 'DENIED_OR_NOT_FOUND');
        denied('EDGE_SCHEMA_DENIED', fn () => $counters->finish($ticket, 'READ_SYNTHETIC_ONLY'));
        denied('EDGE_SCHEMA_DENIED', fn () => $counters->finish(-1, 'SCHEMA_DENIED'));
        check($counters->snapshot()['outcomes']['DENIED_OR_NOT_FOUND'] === 1);
    },
    'U18-12 counter snapshot is a value copy with fixed cardinality' => function (): void {
        $counters = new MockCrmReadEdgeCounters();
        $ticket = $counters->begin(); $counters->finish($ticket, 'SCHEMA_DENIED');
        $copy = $counters->snapshot();
        $copy['outcomes']['SCHEMA_DENIED'] = 900;
        $copy['tenant'] = 'SECRET';
        $live = $counters->snapshot();
        check(array_keys($live) === ['contract','attempts','pending','outcomes',
            'real_authorization','external_calls']);
        check($live['outcomes']['SCHEMA_DENIED'] === 1 && !isset($live['tenant']));
    },
    'U18-13 invalid capacity limits are rejected without allocation' => function (): void {
        foreach ([0, -1, 257] as $capacity) {
            denied('EDGE_SCHEMA_DENIED', fn () => new MockCrmReadEdgeCounters($capacity));
        }
        check((new MockCrmReadEdgeCounters(256))->snapshot()['attempts'] === 0);
    },
    'U18-14 replay and success have same public schema but separate aggregate counts' => function (): void {
        $policy = new MockCrmOwnerPolicy(); $policy->grantFixture(context(), ['ref','stage']);
        [$edge, $counters, , $audit, $env, $owner, $ctx] = f118Fixture($policy);
        $wire = f112Wire($ctx); $wire['fields'] = ['stage','ref'];
        $success = $edge->handle($wire, $env, $owner, $ctx, 15);
        $replay = $edge->handle($wire, $env, $owner, $ctx, 15);
        check($success['projection'] === ['stage'=>'SYNTHETIC_NEW',
            'ref'=>'SYNTHETIC_A_LEAD']);
        check($replay === f117GenericDenial());
        check(array_keys($success) === array_keys($replay));
        $counts = $counters->snapshot()['outcomes'];
        check($counts['READ_SYNTHETIC_ONLY'] === 1
            && $counts['DENIED_OR_NOT_FOUND'] === 1 && $audit->count() === 2);
    },
];
