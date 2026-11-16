<?php
declare(strict_types=1);

require_once __DIR__ . '/MockS2SEnvelopeVerifier.php';

use ImagineAI\AutomationFirst\{AutoIaRouter, MockS2SEnvelopeVerifier};

$checks = 0;
$keyRing = [
    'SYNTHETIC_KEY_A' => 'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY',
    'SYNTHETIC_KEY_B' => 'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_B_32_BYTES_ONLY',
];

function ok(bool $pass, string $name): void {
    global $checks;
    ++$checks;
    if (!$pass) throw new RuntimeException("AUTO_IA_09_FAILED $name");
}
function signedFixture(string $product = 'imagine-crm', string $operation = 'lead.ingest',
                       string $kid = 'SYNTHETIC_KEY_A'): array {
    global $keyRing;
    $e = [
        'schema' => 'AUTO_IA_S2S_V1',
        'issuer' => $product,
        'audience' => 'imagine-ai-agents',
        'product' => $product,
        'operation' => $operation,
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'event_ref' => 'SYNTHETIC_EVENT_001',
        'nonce_ref' => 'SYNTHETIC_NONCE_001',
        'request_ref' => 'SYNTHETIC_REQUEST_001',
        'issued_at' => 100,
        'expires_at' => 200,
        'payload_hash' => hash('sha256', 'SYNTHETIC_NO_PAYLOAD_EXPORTED'),
        'key_id' => $kid,
        'signature' => '',
    ];
    if (isset($keyRing[$kid])) {
        $values = [];
        foreach ($e as $field => $value) {
            if ($field !== 'signature') $values[] = (string)$value;
        }
        $e['signature'] = hash_hmac('sha256', implode("\n", $values), $keyRing[$kid]);
    } else {
        $e['signature'] = str_repeat('0', 64);
    }
    return $e;
}
function expectDeny(array $e, string $reason, int $now = 150, ?array $ring = null): void {
    global $keyRing;
    $r = MockS2SEnvelopeVerifier::verify($e, $now, $ring ?? $keyRing);
    ok($r['decision'] === 'DENY' && $r['reason'] === $reason, "deny_$reason actual_".$r['reason']);
    ok($r['ai_calls'] === 0 && $r['service_effects'] === 0, 'deny has no effects');
}

foreach (AutoIaRouter::matrix() as $product => $ops) {
    foreach ($ops as $op => $kind) {
        $r = MockS2SEnvelopeVerifier::verify(signedFixture($product, $op), 150, $keyRing);
        ok($r['decision'] === ($kind === 'NO_AI' ? 'LAB_VERIFIED_NO_AUTHORIZATION' : 'DENY'),
            "classification_{$product}_{$op}");
        ok($r['ai_calls'] === 0 && $r['service_effects'] === 0, 'all zero costs');
    }
}
ok(MockS2SEnvelopeVerifier::verify(signedFixture(), 150, $keyRing)['reason']
   === 'DURABLE_NONCE_AND_REAL_IAM_NOT_IMPLEMENTED', 'never authority');
ok(MockS2SEnvelopeVerifier::verify(signedFixture('imagine-crm','lead.ingest','SYNTHETIC_KEY_B'),
  150,$keyRing)['decision'] === 'LAB_VERIFIED_NO_AUTHORIZATION', 'two keys accepted for rotation');
expectDeny(signedFixture('imagine-crm','lead.ingest','SYNTHETIC_KEY_A'),
    'SYNTHETIC_KEY_UNAVAILABLE',150,['SYNTHETIC_KEY_B'=>$keyRing['SYNTHETIC_KEY_B']]);
expectDeny(signedFixture('imagine-crm','lead.ingest','SYNTHETIC_KEY_REMOVED'),
    'SYNTHETIC_KEY_UNAVAILABLE');
foreach ([
    ['tenant_ref','SYNTHETIC_TENANT_B','SIGNATURE_MISMATCH'],
    ['event_ref','SYNTHETIC_EVENT_002','SIGNATURE_MISMATCH'],
    ['nonce_ref','SYNTHETIC_NONCE_002','SIGNATURE_MISMATCH'],
    ['request_ref','SYNTHETIC_REQUEST_002','SIGNATURE_MISMATCH'],
    ['payload_hash',str_repeat('a',64),'SIGNATURE_MISMATCH'],
    ['key_id','SYNTHETIC_KEY_B','SIGNATURE_MISMATCH'],
    ['audience','imagine-crm','AUDIENCE_MISMATCH'],
    ['issuer','imagine-sites','ISSUER_PRODUCT_MISMATCH'],
    ['schema','AUTO_IA_S2S_V2','SCHEMA_MISMATCH'],
    ['operation','billing.charge','ONLY_NO_AI_OPERATIONS'],
    ['issued_at',201,'EXPIRED_OR_INVALID_WINDOW'],
    ['expires_at',100,'EXPIRED_OR_INVALID_WINDOW'],
    ['issued_at','100','INVALID_FIELD_TYPE'],
] as [$field,$value,$reason]) {
    $e=signedFixture(); $e[$field]=$value; expectDeny($e,$reason);
}
expectDeny(signedFixture(),'EXPIRED_OR_INVALID_WINDOW',201);
$bad=signedFixture(); $bad['api_key']='SYNTHETIC_UNTRUSTED';
expectDeny($bad,'CLOSED_SCHEMA_REQUIRED');
$bad=signedFixture(); unset($bad['nonce_ref']);
expectDeny($bad,'CLOSED_SCHEMA_REQUIRED');
$bad=signedFixture(); $bad['signature']=str_repeat('0',64);
expectDeny($bad,'SIGNATURE_MISMATCH');
$bad=signedFixture(); $bad['operation']=['lead.ingest'];
expectDeny($bad,'INVALID_FIELD_TYPE');
$bad=signedFixture(); $bad['operation']="lead.ingest\nX";
expectDeny($bad,'INVALID_DIGEST_OR_OPERATION');
echo "AUTO_IA_09_PASS checks={$checks} no_iam=1 no_n8n=1 no_provider=1\n";
