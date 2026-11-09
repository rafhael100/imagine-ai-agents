<?php
declare(strict_types=1);
/** Local-only synthetic service trust check; php examples/f111-trust-demo.php */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (!str_starts_with($class, $prefix)) return;
    require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});
use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockTrust\{MockTrustEnvelope, MockOwnerScopeEvidence, MockTrustGateway};

$context = new TenantContext('SYNTHETIC_CRM', 'SYNTHETIC_A',
    'SYNTHETIC_OPERATOR', 'SYNTHETIC_SUMMARY', 'SYNTHETIC_LEAD_A');
$envelope = new MockTrustEnvelope('SYNTHETIC_ENVELOPE_A', 'SYNTHETIC_AGENT_SERVICE',
    'SYNTHETIC_CRM_API', $context, 'SYNTHETIC_NONCE_A', ['SYNTHETIC_LEAD_READ'], 10, 30);
$owner = new MockOwnerScopeEvidence('SYNTHETIC_OWNER_A', $envelope->digest, $context,
    'SYNTHETIC_LEAD_READ', 10, 30, true);
$gateway = new MockTrustGateway('SYNTHETIC_AGENT_SERVICE', 'SYNTHETIC_CRM_API',
    ['SYNTHETIC_LEAD_READ']);
$first = $gateway->authorize($envelope, $owner, $context, 'SYNTHETIC_LEAD_READ', 15);
$replayDenied = false;
try {
    $gateway->authorize($envelope, $owner, $context, 'SYNTHETIC_LEAD_READ', 16);
} catch (MockError $e) {
    $replayDenied = $e->getMessage() === 'TRUST_REPLAY_DENIED';
}
if ($first['state'] !== 'SYNTHETIC_READ_ONLY' || !$replayDenied
    || $first['real_authorization'] !== false || $first['external_calls'] !== 0) {
    throw new RuntimeException('Synthetic trust boundary mismatch');
}
echo json_encode([
    'mode'=>'SYNTHETIC_ONLY', 'first'=>'SYNTHETIC_READ_ONLY',
    'second'=>'TRUST_REPLAY_DENIED', 'real_authorization'=>false,
    'external_calls'=>0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
