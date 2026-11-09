<?php
declare(strict_types=1);

/**
 * Offline-only F1-07 example; uses invented fixtures and has no external side effects.
 * Usage: php examples/knowledge_context_demo.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ImagineAI\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    require $path;
});

use ImagineAI\Domain\{MockError, TenantContext};
use ImagineAI\MockKnowledge\{
    MockContextEnvelope, MockKnowledgeSource, MockKnowledgeCatalog,
    MockKnowledgeRetriever, MockKnowledgeTrace, MockRetrievalGrant,
};

$context = new TenantContext(
    'SYNTHETIC_CRM', 'SYNTHETIC_ACME', 'SYNTHETIC_OPERATOR',
    'SYNTHETIC_ASSIST', 'SYNTHETIC_CASE_001',
);
$source = new MockKnowledgeSource(
    ref: 'SYNTHETIC_FAQ_A', version: 'SYNTHETIC_V1', context: $context,
    baseRef: 'SYNTHETIC_KB', sectionRef: 'SYNTHETIC_SECTION_01',
    ownerRef: 'SYNTHETIC_OWNER', factKey: 'SYNTHETIC_POLICY',
    factValue: 'SYNTHETIC_ALLOWED', text: 'SYNTHETIC_EXAMPLE_INFORMATION_ONLY',
    issuedAt: 10, expiresAt: 100,
);
$catalog = new MockKnowledgeCatalog();
$catalog->add($source);
$grant = new MockRetrievalGrant(
    'SYNTHETIC_GRANT', $context, 'SYNTHETIC_KB',
    'SYNTHETIC_V1', [$source->ref], 10, 100,
);
$trace = new MockKnowledgeTrace();
$retriever = new MockKnowledgeRetriever($catalog, $trace);

$first = $retriever->select($grant, $context, [$source->ref], 20);
$result = $first->deliver($context, 20);
if ($result['status'] !== 'READY' || $result['passages'][0]['trust'] !== 'UNTRUSTED_DATA') {
    throw new RuntimeException('Synthetic scope or trust failure');
}

// The second pending envelope must fail closed if the grant changes before delivery.
$pending = $retriever->select($grant, $context, [$source->ref], 21);
$grant->revoke();
$denied = false;
try {
    $pending->deliver($context, 22);
} catch (MockError $error) {
    $denied = $error->getMessage() === 'KNOWLEDGE_DENIED';
}
if (!$denied) throw new RuntimeException('Revocation was not enforced');

// A different tenant may not reuse this source or its original grant.
$otherTenant = new TenantContext(
    'SYNTHETIC_CRM', 'SYNTHETIC_OTHER', 'SYNTHETIC_OPERATOR',
    'SYNTHETIC_ASSIST', 'SYNTHETIC_CASE_001',
);
$crossTenantDenied = false;
try {
    $retriever->select($grant, $otherTenant, [$source->ref], 23);
} catch (MockError $error) {
    $crossTenantDenied = $error->getMessage() === 'KNOWLEDGE_DENIED';
}
if (!$crossTenantDenied) throw new RuntimeException('Cross-tenant isolation failed');

echo json_encode([
    'mode' => 'SYNTHETIC_OFFLINE',
    'delivery' => $result['status'],
    'trust' => $result['trust'],
    'passage_count' => count($result['passages']),
    'pending_after_revocation' => 'DENIED',
    'cross_tenant_access' => 'DENIED',
    'trace_codes' => array_column($trace->events(), 'code'),
    'external_calls' => 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
