<?php
declare(strict_types=1);

// AUTO-IA-10 CI fixture. Never deliver these synthetic secrets to production.
require_once __DIR__ . '/../MockS2SEnvelopeVerifier.php';
use ImagineAI\AutomationFirst\MockS2SEnvelopeVerifier;

$secrets = [
    'SYNTHETIC_KEY_A' => 'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY',
    'SYNTHETIC_KEY_B' => 'SYNTHETIC_OFFLINE_HMAC_TEST_KEY_B_32_BYTES_ONLY',
];
$now = time();
$original = [
    'schema' => 'AUTO_IA_S2S_V1',
    'issuer' => 'imagine-crm',
    'audience' => 'imagine-ai-agents',
    'product' => 'imagine-crm',
    'operation' => 'lead.ingest',
    'tenant_ref' => 'SYNTHETIC_TENANT_A',
    'event_ref' => 'SYNTHETIC_S2S_EVENT_01',
    'nonce_ref' => 'SYNTHETIC_S2S_NONCE_01',
    'request_ref' => 'SYNTHETIC_S2S_REQUEST_01',
    'issued_at' => $now - 5,
    'expires_at' => $now + 120,
    'payload_hash' => hash('sha256','SYNTHETIC_PAYLOAD_NOT_REVEALED'),
    'key_id' => 'SYNTHETIC_KEY_A',
    'signature' => '',
];
function quoteSql(string|int $v): string {
    if (is_int($v)) return (string)$v;
    if (!preg_match('/\A[A-Za-z0-9_.-]+\z/D', $v)) {
        throw new RuntimeException('fixture contains non ASCII-safe field');
    }
    return "'".$v."'";
}
function signFixture(array $value, array $keys): array {
    $data = [];
    foreach ($value as $field=>$v) {
        if ($field!=='signature') $data[]=(string)$v;
    }
    $value['signature']=hash_hmac('sha256',implode("\n",$data),$keys[$value['key_id']]);
    return $value;
}
function emitCase(string $name, array $e, string $expected, array $secrets): void {
    $verified=MockS2SEnvelopeVerifier::verify($e,time(),$secrets);
    if ($expected==='LAB_RECEIVED_NO_DELIVERY'
       && $verified['decision']!=='LAB_VERIFIED_NO_AUTHORIZATION') {
        throw new RuntimeException('fixture verification before SQL not valid');
    }
    $args=array_map('quoteSql',array_values($e));
    $call='auto_ia_lab.receive_signed_no_ai('.implode(',',$args).')';
    echo 'DO $$' . "\n";
    echo "DECLARE result text;\nBEGIN\n";
    echo "  result:=".$call.";\n";
    echo "  IF result<>'".$expected."' THEN RAISE EXCEPTION 'AUTO_IA_10_".$name." got % expected ".$expected."',result; END IF;\n";
    echo 'END $$;' . "\n";
}
echo "\\set ON_ERROR_STOP on\nSET ROLE aia_lab_tenant_a;\n";
$good=signFixture($original,$secrets);
emitCase('ADMIT',$good,'LAB_RECEIVED_NO_DELIVERY',$secrets);
emitCase('REPLAY',$good,'LAB_DUPLICATE_NO_DELIVERY',$secrets);
$bad=$good;
$bad['signature']=str_repeat('0',64);
emitCase('BAD_MAC',$bad,'LAB_DENY_MAC',$secrets);
$bad=$good;
$bad['tenant_ref']='SYNTHETIC_TENANT_B';
$bad=signFixture($bad,$secrets);
emitCase('TENANT_FORGERY',$bad,'LAB_DENY_SERVICE_TENANT',$secrets);
$bad=$good;
$bad['key_id']='SYNTHETIC_KEY_B';
$bad=signFixture($bad,$secrets);
emitCase('DISABLED_KEY',$bad,'LAB_DENY_KEY',$secrets);
$bad=$good;
$bad['audience']='imagine-portal';
$bad=signFixture($bad,$secrets);
emitCase('BAD_AUDIENCE',$bad,'LAB_DENY_SIGNED_SCHEMA',$secrets);
$bad=$good;
$bad['event_ref']='SYNTHETIC_S2S_EVENT_02';
$bad['nonce_ref']='SYNTHETIC_S2S_NONCE_01';
$bad['request_ref']='SYNTHETIC_S2S_REQUEST_02';
$bad=signFixture($bad,$secrets);
emitCase('NONCE_CONFLICT',$bad,'LAB_DENY_REPLAY_CONFLICT',$secrets);
$bad=$good;
$bad['event_ref']='SYNTHETIC_S2S_EVENT_03';
$bad['nonce_ref']='SYNTHETIC_S2S_NONCE_03';
$bad['request_ref']='SYNTHETIC_S2S_REQUEST_03';
$bad['operation']='lead.message.interpret';
$bad=signFixture($bad,$secrets);
emitCase('AI_BYPASS',$bad,'LAB_DENY_AI_OR_UNKNOWN_OPERATION',$secrets);
echo "RESET ROLE;\n";
echo <<<'SQL'
DO $$
BEGIN
 IF has_function_privilege('aia_lab_tenant_a',
  'auto_ia_lab.receive_no_ai(text,text,text,text,text,bigint,bigint)','EXECUTE')
 THEN RAISE EXCEPTION 'UNSIGNED_INGRESS_STILL_EXPOSED'; END IF;
 IF has_table_privilege('aia_lab_tenant_a','auto_ia_lab.mock_s2s_keys','SELECT')
 THEN RAISE EXCEPTION 'MOCK_SECRET_EXPOSED'; END IF;
 IF (SELECT COUNT(*) FROM auto_ia_lab.event_inbox WHERE event_ref LIKE 'SYNTHETIC_S2S_EVENT_%')<>1
 THEN RAISE EXCEPTION 'UNEXPECTED_EVENT_COUNT'; END IF;
END $$;
\echo AUTO_IA_10_SIGNED_INBOX_PASS

SQL;
