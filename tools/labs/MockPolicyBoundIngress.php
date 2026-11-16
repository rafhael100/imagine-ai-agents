<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__ . '/../../_auto_ia_pinned_source/tools/auto_ia/MockS2SEnvelopeVerifier.php';
require_once __DIR__ . '/../../_auto_ia_pinned_source/tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php';
require_once __DIR__ . '/../../src/MockOrchestration/MockN8nOutboxContract.php';

use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\AutomationFirst\MockS2SEnvelopeVerifier;

/**
 * P15: composed POLICY CHECK over two independent pinned mock contracts.
 * This is intentionally NOT IAM, a real signer, an inbox, or a transport.
 * All refs and keys are synthetic and all returned outcomes have zero effects.
 */
final class MockPolicyBoundIngress
{
    private const OPERATIONS = ['run.status.project', 'usage.quota.evaluate'];
    private const POLICY_KEYS = ['service', 'tenant_ref', 'operations', 'active'];
    private const MAX_SEEN = 128;

    /**
     * @param array<string,mixed> $envelope Untrusted signed laboratory fixture
     * @param array<string,mixed> $trustedPolicy Independently injected trusted fixture
     * @param array<string,string> $trustedKeys Independently injected synthetic key ring
     * @param array<string,string> $seen Process-local only; NOT durable protection
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public static function inspect(
        string $canonicalP7Event,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $trustedKeys,
        array &$seen
    ): array {
        if (array_keys($trustedPolicy) !== self::POLICY_KEYS
            || $trustedPolicy['service'] !== 'imagine-ai-agents'
            || !is_string($trustedPolicy['tenant_ref'])
            || preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D', $trustedPolicy['tenant_ref']) !== 1
            || $trustedPolicy['active'] !== true
            || !is_array($trustedPolicy['operations'])
            || !array_is_list($trustedPolicy['operations'])
            || $trustedPolicy['operations'] === []
            || count($trustedPolicy['operations']) > count(self::OPERATIONS)
            || count($trustedPolicy['operations']) !== count(array_unique($trustedPolicy['operations']))) {
            return self::deny('TRUSTED_POLICY_INVALID_OR_REVOKED');
        }
        foreach ($trustedPolicy['operations'] as $operation) {
            if (!is_string($operation) || !in_array($operation, self::OPERATIONS, true)) {
                return self::deny('TRUSTED_POLICY_OPERATION_UNRECOGNIZED');
            }
        }

        $result = MockP7ToAutoIaAdapter::propose(
            $canonicalP7Event, $trustedPolicy['tenant_ref'], $now
        );
        if ($result['decision'] !== 'LAB_NO_AI_PROPOSAL_NOT_SIGNED'
            || !is_array($result['proposal'])) {
            return self::deny('P7_PROPOSAL_DENIED');
        }
        $proposal = $result['proposal'];
        if (!in_array($proposal['operation'], $trustedPolicy['operations'], true)) {
            return self::deny('OPERATION_NOT_GRANTED_BY_TRUSTED_POLICY');
        }

        // The envelope MUST reproduce exactly every binding of the P7 proposal.
        // Matching a signature alone is insufficient, even with an injected key.
        foreach ($proposal as $field => $value) {
            if (!is_string($value)
                || !array_key_exists($field, $envelope)
                || !is_string($envelope[$field])
                || !hash_equals($value, $envelope[$field])) {
                return self::deny('SIGNED_ENVELOPE_P7_BINDING_MISMATCH');
            }
        }
        $verified = MockS2SEnvelopeVerifier::verify($envelope, $now, $trustedKeys);
        if ($verified['decision'] !== 'LAB_VERIFIED_NO_AUTHORIZATION') {
            return self::deny('SYNTHETIC_SIGNATURE_OR_SCOPE_DENIED');
        }

        $nonce = $proposal['nonce_ref'];
        if (array_key_exists($nonce, $seen)) {
            return self::deny('PROCESS_LOCAL_REPLAY_DENIED');
        }
        if (count($seen) >= self::MAX_SEEN) {
            return self::deny('PROCESS_LOCAL_NONCE_CAPACITY_EXCEEDED');
        }
        // Merely a process-local laboratory marker. NOT an authorization or ACK.
        $seen[$nonce] = hash('sha256', $canonicalP7Event);
        return [
            'decision' => 'LAB_POLICY_MATCH_NO_EFFECT',
            'reason' => 'REQUIRES_REAL_IAM_DURABLE_REPLAY_AND_DELIVERY',
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
