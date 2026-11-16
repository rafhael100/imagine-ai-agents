<?php
declare(strict_types=1);
require_once __DIR__ . '/labs/PgSyntheticReplayGate.php';

use ImagineAI\Convergence\PgSyntheticReplayGate;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;

if (getenv('AIA_P16_PG_LAB') !== 'true') throw new RuntimeException('P16_CI_ONLY');
$tenant = $argv[1] ?? '';
$event = $argv[2] ?? '';
$kind = 'mock.run.completed';
$now = 1700000000;
$key = 'SYNTHETIC_P16_OFFLINE_SIGNER_ONLY_12345678';
$refPattern = '/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D';
if (preg_match($refPattern, $tenant) !== 1 || preg_match($refPattern, $event) !== 1) {
    throw new RuntimeException('P16_INVALID_ARGUMENT');
}
$p7 = MockN8nOutboxContract::encode([
    'contract' => 'SYNTHETIC_AIA_AUTOMATION_V1',
    'event_ref' => $event,
    'tenant_ref' => $tenant,
    'run_ref' => 'SYNTHETIC_RUN_A',
    'kind' => $kind,
    'reference_ref' => 'SYNTHETIC_P16_REF',
    'occurred_at' => $now-5,
]);
if ($p7 === null) throw new RuntimeException('P16_INVALID_SOURCE');
$p = MockP7ToAutoIaAdapter::propose($p7, $tenant, $now)['proposal'];
if (!is_array($p)) throw new RuntimeException('P16_PROPOSAL_NOT_AVAILABLE');
$envelope = [
    'schema' => 'AUTO_IA_S2S_V1',
    'issuer' => $p['issuer'],
    'audience' => $p['audience'],
    'product' => $p['product'],
    'operation' => $p['operation'],
    'tenant_ref' => $p['tenant_ref'],
    'event_ref' => $p['event_ref'],
    'nonce_ref' => $p['nonce_ref'],
    'request_ref' => $p['request_ref'],
    'issued_at' => $now-5,
    'expires_at' => $now+60,
    'payload_hash' => $p['payload_hash'],
    'key_id' => 'SYNTHETIC_KEY_A',
    'signature' => '',
];
$canonical = [];
foreach ($envelope as $field => $value) if ($field !== 'signature') $canonical[] = (string)$value;
$envelope['signature'] = hash_hmac('sha256', implode("\n", $canonical), $key);
$db = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres',
    'ci-only-password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$gate = new PgSyntheticReplayGate($db);
$seen = [];
$policy = [
    'service' => 'imagine-ai-agents',
    'tenant_ref' => $tenant,
    'operations' => ['run.status.project'],
    'active' => true,
];
$r = $gate->inspectAndClaim($p7, $envelope, $policy, $now, ['SYNTHETIC_KEY_A'=>$key], $seen);
if ($r['ai_calls'] !== 0 || $r['service_effects'] !== 0) throw new RuntimeException('P16_UNEXPECTED_EFFECT');
echo $r['decision'] . ' ' . $r['reason'] . "\n";
