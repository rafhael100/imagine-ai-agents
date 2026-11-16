<?php
declare(strict_types=1);

require_once __DIR__ . '/MockN8nEnvelope.php';

use ImagineAI\AutomationFirst\{AutoIaRouter, MockN8nEnvelope};

$checks = 0;
$key = 'SYNTHETIC_LOCAL_TEST_SIGNING_KEY_NOT_FOR_PRODUCTION';

function testAssert(bool $ok, string $message): void
{
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException('AUTO_IA_05_FAIL '.$message);
}

function eventFixture(string $product = 'imagine-crm', string $operation = 'lead.ingest',
                      string $nonce = 'SYNTHETIC_NONCE_01', string $tenant = 'SYNTHETIC_TENANT_A',
                      int $issued = 100, int $expires = 200): array
{
    global $key;
    $event = [
        'schema' => 'AUTO_IA_N8N_EVENT_V1',
        'product' => $product,
        'operation' => $operation,
        'tenant_ref' => $tenant,
        'event_ref' => 'SYNTHETIC_EVENT_001',
        'nonce_ref' => $nonce,
        'request_ref' => 'SYNTHETIC_REQUEST_001',
        'issued_at' => $issued,
        'expires_at' => $expires,
        'payload_sha256' => hash('sha256', 'SYNTHETIC_FAKE_EVENT_PAYLOAD'),
        'signature' => '',
    ];
    $event['signature'] = MockN8nEnvelope::unsafeFixtureSignature($event, $key);
    return $event;
}
function deniedEnvelope(array $event, int $now, string $code): void
{
    global $checks, $key;
    $seen = [];
    $res = MockN8nEnvelope::inspect($event, $now, $key, $seen);
    testAssert($res['decision'] === 'DENY', 'deny '.$code);
    testAssert($res['reason'] === $code, 'reason '.$code.' actual='.$res['reason']);
    testAssert($res['service_effects'] === 0 && $res['ai_tokens'] === 0
        && $res['external_calls'] === 0 && $seen === [], 'no side effect or replay mutation');
}

$seen = [];
$valid = eventFixture();
$pass = MockN8nEnvelope::inspect($valid, 150, $key, $seen);
testAssert($pass['decision'] === 'LAB_ENVELOPE_ACCEPTED_NO_ACTION'
    && $pass['reason'] === 'SERVICE_AUTHORIZATION_AND_TRANSPORT_NOT_IMPLEMENTED',
    'lab acceptance without authority');
testAssert($pass['external_calls'] === 0 && $pass['service_effects'] === 0
    && $pass['ai_tokens'] === 0, 'no calls');
$again = MockN8nEnvelope::inspect($valid, 150, $key, $seen);
testAssert($again['decision'] === 'DENY' && $again['reason'] === 'NONCE_REPLAY',
    'duplicate nonce denied within local process');
testAssert(count($seen) === 1, 'duplicate has no mutation');

$nonceI = 2;
foreach (AutoIaRouter::matrix() as $product => $operations) {
    foreach ($operations as $operation => $kind) {
        $event = eventFixture($product, $operation,
            'SYNTHETIC_NONCE_'.str_pad((string)$nonceI++, 3, '0', STR_PAD_LEFT));
        $local = [];
        $res = MockN8nEnvelope::inspect($event, 150, $key, $local);
        testAssert($res['decision'] === ($kind === 'NO_AI'
            ? 'LAB_ENVELOPE_ACCEPTED_NO_ACTION' : 'DENY'), 'catalog '.$product.':'.$operation);
        testAssert($res['service_effects'] === 0 && $res['external_calls'] === 0
            && $res['ai_tokens'] === 0, 'never call system');
        if ($kind !== 'NO_AI') {
            testAssert($res['reason'] === 'ONLY_DETERMINISTIC_OPERATIONS',
                'AI candidate cannot bypass gateway');
        }
    }
}

$bad = $valid;
$bad['signature'] = str_repeat('0',64);
deniedEnvelope($bad,150,'INVALID_SYNTHETIC_SIGNATURE');
$bad = $valid;
$bad['url'] = 'https://external.example/endpoint';
deniedEnvelope($bad,150,'INVALID_ENVELOPE_SCHEMA');
$bad = $valid;
$bad['api_key'] = 'SYNTHETIC_SECRET_SHOULD_NOT_EXIST';
deniedEnvelope($bad,150,'INVALID_ENVELOPE_SCHEMA');
$bad = $valid;
$bad['payload_sha256'] = str_repeat('0',64);
deniedEnvelope($bad,150,'INVALID_SYNTHETIC_SIGNATURE');
$bad = $valid;
$bad['nonce_ref'] = 'RAW_UNSCOPED_NONCE';
deniedEnvelope($bad,150,'INVALID_SYNTHETIC_SCOPE');
$bad = $valid;
$bad['expires_at'] = 99;
deniedEnvelope($bad,150,'EXPIRED_OR_INVALID_WINDOW');
$bad = $valid;
$bad['issued_at'] = 160;
deniedEnvelope($bad,150,'EXPIRED_OR_INVALID_WINDOW');
$bad = $valid;
$bad['expires_at'] = 999;
deniedEnvelope($bad,150,'EXPIRED_OR_INVALID_WINDOW');
$bad = $valid;
unset($bad['tenant_ref']);
deniedEnvelope($bad,150,'INVALID_ENVELOPE_SCHEMA');
$bad = $valid;
$bad['issued_at'] = '100';
deniedEnvelope($bad,150,'INVALID_ENVELOPE_FIELD');
$bad = $valid;
$bad['schema'] = 'AUTO_IA_N8N_EVENT_V2';
deniedEnvelope($bad,150,'EXPIRED_OR_INVALID_WINDOW');
$bad = $valid;
$bad['operation'] = 'billing.charge';
$bad['signature'] = MockN8nEnvelope::unsafeFixtureSignature($bad,$key);
deniedEnvelope($bad,150,'ONLY_DETERMINISTIC_OPERATIONS');
$bad = eventFixture('imagine-crm','lead.ingest','SYNTHETIC_NONCE_02','SYNTHETIC_TENANT_B');
$seen = ['SYNTHETIC_NONCE_02' => 'hash-from-other-tenant'];
$res = MockN8nEnvelope::inspect($bad, 150, $key, $seen);
testAssert($res['decision'] === 'DENY' && $res['reason'] === 'NONCE_REPLAY',
    'synthetic cross tenant nonce replay denied');
$seen = [];
for ($i=0;$i<128;$i++) {
    $event = eventFixture('imagine-crm','lead.ingest',
        'SYNTHETIC_NONCE_'.str_pad((string)($i+1000), 4,'0',STR_PAD_LEFT));
    $res = MockN8nEnvelope::inspect($event,150,$key,$seen);
    testAssert($res['decision'] === 'LAB_ENVELOPE_ACCEPTED_NO_ACTION','bounded nonce memory');
}
$overflow = eventFixture('imagine-crm','lead.ingest','SYNTHETIC_NONCE_EXTRA');
$res = MockN8nEnvelope::inspect($overflow,150,$key,$seen);
testAssert($res['decision'] === 'DENY' && $res['reason'] === 'NONCE_CAPACITY_EXHAUSTED'
    && count($seen)===128, 'fail closed on capacity');
echo "AUTO_IA_05_PASS checks={$checks} no_n8n=1 no_ai_api=1 no_external_network=1\n";
