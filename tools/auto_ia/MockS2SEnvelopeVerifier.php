<?php
declare(strict_types=1);

namespace ImagineAI\AutomationFirst;

require_once __DIR__ . '/AutoIaRouter.php';

/**
 * AUTO-IA-09 synthetic signed envelope verifier (no network, dispatch, permissions or IAM).
 * The injected key ring is a closed test fixture, NEVER a production secret store.
 */
final class MockS2SEnvelopeVerifier
{
    private const FIELDS = [
        'schema', 'issuer', 'audience', 'product', 'operation', 'tenant_ref',
        'event_ref', 'nonce_ref', 'request_ref', 'issued_at', 'expires_at',
        'payload_hash', 'key_id', 'signature',
    ];
    private const REF_PATTERN = '/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D';
    private const AUDIENCE = 'imagine-ai-agents';

    /**
     * @param array<string,mixed> $event An untrusted, data-only envelope.
     * @param array<string,string> $keyRing Synthetic keys supplied by caller, not user payload.
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public static function verify(array $event, int $serverNow, array $keyRing): array
    {
        $keys = array_keys($event);
        $expected = self::FIELDS;
        sort($keys);
        sort($expected);
        if ($keys !== $expected) return self::deny('CLOSED_SCHEMA_REQUIRED');

        foreach (self::FIELDS as $field) {
            if (in_array($field, ['issued_at', 'expires_at'], true)) {
                if (!is_int($event[$field])) return self::deny('INVALID_FIELD_TYPE');
            } elseif (!is_string($event[$field])) {
                return self::deny('INVALID_FIELD_TYPE');
            }
        }

        if ($event['schema'] !== 'AUTO_IA_S2S_V1') return self::deny('SCHEMA_MISMATCH');
        if ($event['audience'] !== self::AUDIENCE) return self::deny('AUDIENCE_MISMATCH');
        if (!array_key_exists($event['product'], AutoIaRouter::matrix())
            || $event['issuer'] !== $event['product']) {
            return self::deny('ISSUER_PRODUCT_MISMATCH');
        }
        foreach (['tenant_ref','event_ref','nonce_ref','request_ref','key_id'] as $key) {
            if (!self::isRef($event[$key])) return self::deny('INVALID_SYNTHETIC_REFERENCE');
        }
        if (preg_match('/\A[a-z][a-z0-9_.]{0,79}\z/D', $event['operation']) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/D', $event['payload_hash']) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/D', $event['signature']) !== 1) {
            return self::deny('INVALID_DIGEST_OR_OPERATION');
        }
        if ($serverNow < 0 || $serverNow > 10_000_000_000
            || $event['issued_at'] < 0
            || $event['expires_at'] <= $event['issued_at']
            || $event['expires_at'] - $event['issued_at'] > 300
            || $serverNow + 5 < $event['issued_at']
            || $serverNow >= $event['expires_at']) {
            return self::deny('EXPIRED_OR_INVALID_WINDOW');
        }
        $route = AutoIaRouter::classify([
            'schema' => 'AUTO_IA_EVENT_V1',
            'product' => $event['product'],
            'operation' => $event['operation'],
            'tenant_ref' => $event['tenant_ref'],
            'event_ref' => $event['event_ref'],
            'semantic_reason' => 'NONE',
        ]);
        if ($route['decision'] !== 'NO_AI') return self::deny('ONLY_NO_AI_OPERATIONS');

        $id = $event['key_id'];
        // A key retired from the trusted ring causes a denial; no fallback to another key.
        if (!array_key_exists($id, $keyRing) || !is_string($keyRing[$id])
            || !str_starts_with($keyRing[$id], 'SYNTHETIC_')
            || strlen($keyRing[$id]) < 32 || strlen($keyRing[$id]) > 128) {
            return self::deny('SYNTHETIC_KEY_UNAVAILABLE');
        }

        $toSign = [];
        foreach (self::FIELDS as $field) {
            if ($field !== 'signature') $toSign[] = (string)$event[$field];
        }
        // Fields are restricted to ASCII-safe references, catalog entries,
        // fixed labels and decimal timestamps. Newline is an unambiguous
        // delimiter within this strictly closed schema.
        $canonical = implode("\n", $toSign);
        $expectedMac = hash_hmac('sha256', $canonical, $keyRing[$id]);
        if (!hash_equals($expectedMac, $event['signature'])) {
            return self::deny('SIGNATURE_MISMATCH');
        }

        // Cryptographic fixture validation is NOT authorization to run a task,
        // reserve credit, trust a caller's tenant or contact n8n.
        return [
            'decision' => 'LAB_VERIFIED_NO_AUTHORIZATION',
            'reason' => 'DURABLE_NONCE_AND_REAL_IAM_NOT_IMPLEMENTED',
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }

    private static function isRef(string $value): bool
    {
        return strlen($value) <= 64
            && preg_match(self::REF_PATTERN, $value) === 1;
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
