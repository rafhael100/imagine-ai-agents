<?php
declare(strict_types=1);
require_once __DIR__ . '/labs/MockPolicyBoundIngress.php';

use ImagineAI\Convergence\MockPolicyBoundIngress;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;

$checks = 0;
$now = 1700000000;
$key = 'SYNTHETIC_OFFLINE_P15_TEST_SIGNING_KEY_123';
$ring = ['SYNTHETIC_KEY_A' => $key];
$policy = [
    'service' => 'imagine-ai-agents',
    'tenant_ref' => 'SYNTHETIC_TENANT_A',
    'operations' => ['run.status.project', 'usage.quota.evaluate'],
    'active' => true,
];

function p15Check(bool $condition, string $case): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('P15_FAIL_' . $case);
}
function p15Fixture(string $kind, int $now): string {
    $value = MockN8nOutboxContract::encode([
        'contract' => 'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref' => 'SYNTHETIC_P15_EVENT_01',
        'tenant_ref' => 'SYNTHETIC_TENANT_A',
        'run_ref' => 'SYNTHETIC_RUN_A',
        'kind' => $kind,
        'reference_ref' => 'SYNTHETIC_P15_REFERENCE',
        'occurred_at' => $now - 5,
    ]);
    if ($value === null) throw new RuntimeException('INVALID_P15_P7_FIXTURE');
    return $value;
}
function p15Sign(string $p7, string $tenant, int $now, string $key): array {
    $result = MockP7ToAutoIaAdapter::propose($p7, $tenant, $now);
    if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED') {
        throw new RuntimeException('PROPOSAL_NOT_AVAILABLE');
    }
    $p = $result['proposal'];
    $signed = [
        'schema' => 'AUTO_IA_S2S_V1',
        'issuer' => $p['issuer'],
        'audience' => $p['audience'],
        'product' => $p['product'],
        'operation' => $p['operation'],
        'tenant_ref' => $p['tenant_ref'],
        'event_ref' => $p['event_ref'],
        'nonce_ref' => $p['nonce_ref'],
        'request_ref' => $p['request_ref'],
        'issued_at' => $now - 5,
        'expires_at' => $now + 60,
        'payload_hash' => $p['payload_hash'],
        'key_id' => 'SYNTHETIC_KEY_A',
        'signature' => '',
    ];
    $fields = [];
    foreach ($signed as $field => $value) {
        if ($field !== 'signature') $fields[] = (string)$value;
    }
    $signed['signature'] = hash_hmac('sha256', implode("\n", $fields), $key);
    return $signed;
}
function p15Run(string $p7, array $signed, array $policy, int $now, array $keys, array &$seen): array {
    $r = MockPolicyBoundIngress::inspect($p7, $signed, $policy, $now, $keys, $seen);
    p15Check($r['ai_calls'] === 0 && $r['service_effects'] === 0, 'no_effects');
    return $r;
}
foreach ([
    'mock.run.completed' => 'run.status.project',
    'mock.quota.threshold' => 'usage.quota.evaluate',
] as $kind => $operation) {
    $p7 = p15Fixture($kind, $now);
    $signed = p15Sign($p7, 'SYNTHETIC_TENANT_A', $now, $key);
    $seen = [];
    $r = p15Run($p7, $signed, $policy, $now, $ring, $seen);
    p15Check($r['decision'] === 'LAB_POLICY_MATCH_NO_EFFECT', 'policy_match_'.$kind);
    p15Check(count($seen) === 1, 'nonce_recorded_'.$kind);
    p15Check(p15Run($p7, $signed, $policy, $now, $ring, $seen)['reason'] ===
        'PROCESS_LOCAL_REPLAY_DENIED', 'nonce_replay_'.$kind);
}
$p7 = p15Fixture('mock.run.completed', $now);
$signed = p15Sign($p7, 'SYNTHETIC_TENANT_A', $now, $key);

$seen = [];
$otherTenant = $policy;
$otherTenant['tenant_ref'] = 'SYNTHETIC_TENANT_B';
p15Check(p15Run($p7, $signed, $otherTenant, $now, $ring, $seen)['decision'] === 'DENY', 'cross_tenant');
p15Check($seen === [], 'no_nonce_on_denial');

$revoked = $policy;
$revoked['active'] = false;
p15Check(p15Run($p7, $signed, $revoked, $now, $ring, $seen)['decision'] === 'DENY', 'policy_revoked');

$noOperation = $policy;
$noOperation['operations'] = ['usage.quota.evaluate'];
p15Check(p15Run($p7, $signed, $noOperation, $now, $ring, $seen)['reason'] ===
    'OPERATION_NOT_GRANTED_BY_TRUSTED_POLICY', 'operation_not_granted');

$wildcard = $policy;
$wildcard['operations'] = ['*'];
p15Check(p15Run($p7, $signed, $wildcard, $now, $ring, $seen)['decision'] === 'DENY', 'wildcard');

$duplicate = $policy;
$duplicate['operations'] = ['run.status.project', 'run.status.project'];
p15Check(p15Run($p7, $signed, $duplicate, $now, $ring, $seen)['decision'] === 'DENY', 'duplicate_grant');

$tampered = $signed;
$tampered['payload_hash'] = str_repeat('a', 64);
p15Check(p15Run($p7, $tampered, $policy, $now, $ring, $seen)['reason'] ===
    'SIGNED_ENVELOPE_P7_BINDING_MISMATCH', 'payload_binding');

$tampered = $signed;
$tampered['signature'] = str_repeat('0', 64);
p15Check(p15Run($p7, $tampered, $policy, $now, $ring, $seen)['reason'] ===
    'SYNTHETIC_SIGNATURE_OR_SCOPE_DENIED', 'tampered_signature');

p15Check(p15Run($p7, $signed, $policy, $now + 100, $ring, $seen)['decision'] === 'DENY', 'expired');
p15Check(p15Run($p7, $signed, $policy, $now, [], $seen)['decision'] === 'DENY', 'no_trusted_key');
p15Check(p15Run(p15Fixture('mock.approval.requested', $now), $signed, $policy, $now, $ring, $seen)['decision'] === 'DENY', 'approval_not_mapped');

$maxSeen = [];
for ($i = 0; $i < 128; $i++) $maxSeen['SYNTHETIC_SAT_'.$i] = 'occupied';
p15Check(p15Run($p7, $signed, $policy, $now, $ring, $maxSeen)['reason'] ===
    'PROCESS_LOCAL_NONCE_CAPACITY_EXCEEDED', 'bounded_nonce_memory');

p15Check($seen === [], 'failed_events_never_write');
echo "AIA_P15_POLICY_BOUND_PASS checks={$checks} ai_calls=0 external_calls=0 real_iam=false NO_GO\n";
