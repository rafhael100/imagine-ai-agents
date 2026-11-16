<?php
declare(strict_types=1);

namespace ImagineAI\AutomationFirst;

use ImagineAI\MockOrchestration\MockN8nOutboxContract;

require_once __DIR__ . '/../AutoIaRouter.php';

/**
 * AIA-CONV-02: read-only adapter for *one pinned P7* synthetic outbox contract.
 * It proposes NO_AI routing; it does not sign, send, execute, charge or authorize.
 *
 * The class MockN8nOutboxContract is supplied from an independently checked-out
 * P7 source SHA during CI. No real service/cross-repo network integration.
 */
final class MockP7ToAutoIaAdapter
{
    private const TARGETS = [
        'mock.run.completed' => 'run.status.project',
        'mock.quota.threshold' => 'usage.quota.evaluate',
    ];

    /** @return array{decision:string,reason:string,proposal:?array,ai_calls:int,service_effects:int} */
    public static function propose(string $canonicalP7Event, string $trustedTenantFixture, int $serverNow): array
    {
        if (!class_exists(MockN8nOutboxContract::class)
            || strlen($canonicalP7Event) > 512) {
            return self::deny('P7_CONTRACT_UNAVAILABLE_OR_TOO_LONG');
        }
        $source = MockN8nOutboxContract::decode($canonicalP7Event);
        if (!is_array($source)) return self::deny('P7_CANONICAL_CONTRACT_DENIED');
        if (!self::ref($trustedTenantFixture)
            || !self::ref($source['tenant_ref'])
            || !hash_equals($trustedTenantFixture, $source['tenant_ref'])) {
            return self::deny('SOURCE_TENANT_MISMATCH');
        }
        if (!self::ref($source['event_ref'])
            || !self::ref($source['run_ref'])
            || !self::ref($source['reference_ref'])) {
            return self::deny('AUTO_IA_REF_INCOMPATIBLE');
        }
        if ($serverNow < 0 || $serverNow > 4_102_444_800
            || $source['occurred_at'] > $serverNow + 5
            || $source['occurred_at'] + 60 <= $serverNow) {
            return self::deny('EVENT_WINDOW_NOT_CURRENT');
        }
        $operation = self::TARGETS[$source['kind']] ?? null;
        if ($operation === null) {
            // P7 approval.requested is NOT equivalent to granting access.
            // Deliberately deny until a reviewed business mapping exists.
            return self::deny('P7_KIND_UNMAPPED_REQUIRES_REVIEW');
        }
        $product = 'imagine-ai-agents';
        $route = AutoIaRouter::classify([
            'schema' => 'AUTO_IA_EVENT_V1',
            'product' => $product,
            'operation' => $operation,
            'tenant_ref' => $source['tenant_ref'],
            'event_ref' => $source['event_ref'],
            'semantic_reason' => 'NONE',
        ]);
        if ($route['decision'] !== 'NO_AI') return self::deny('AUTO_IA_ROUTER_DENIED');
        $sha = hash('sha256', $canonicalP7Event);
        return [
            'decision' => 'LAB_NO_AI_PROPOSAL_NOT_SIGNED',
            'reason' => 'REQUIRES_SERVER_IAM_SIGNING_AND_PERSISTENT_REPLAY_CHECK',
            'proposal' => [
                'issuer' => $product,
                'audience' => 'imagine-ai-agents',
                'product' => $product,
                'operation' => $operation,
                'tenant_ref' => $source['tenant_ref'],
                'event_ref' => $source['event_ref'],
                'nonce_ref' => 'SYNTHETIC_NONCE_'.strtoupper(substr($sha, 0, 24)),
                'request_ref' => 'SYNTHETIC_REQUEST_'.strtoupper(substr($sha, 0, 24)),
                'payload_hash' => $sha,
            ],
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }

    private static function ref(string $value): bool
    {
        return strlen($value) <= 64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D', $value) === 1;
    }

    /** @return array{decision:string,reason:string,proposal:?array,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'proposal' => null,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
