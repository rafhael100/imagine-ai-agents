<?php
declare(strict_types=1);

namespace ImagineAI\AutomationFirst;

require_once __DIR__ . '/AutoIaRouter.php';

/**
 * AUTO-IA-05: offline, synthetic contract for future internal n8n event relay.
 * This class validates a sealed envelope but NEVER calls a webhook, runs n8n,
 * authorizes a tenant, executes service effects or makes paid AI requests.
 */
final class MockN8nEnvelope
{
    private const KEYS = [
        'schema', 'product', 'operation', 'tenant_ref', 'event_ref',
        'nonce_ref', 'request_ref', 'issued_at', 'expires_at',
        'payload_sha256', 'signature',
    ];
    private const MAX_SEEN = 128;

    /** @param array<string,mixed> $event
     *  @param array<string,string> $seen process-local synthetic nonce memory, not durable replay protection
     *  @return array{decision:string,reason:string,service_effects:int,external_calls:int,ai_tokens:int}
     */
    public static function inspect(array $event, int $now, string $mockKey, array &$seen): array
    {
        $provided = array_keys($event);
        sort($provided);
        $expected = self::KEYS;
        sort($expected);
        if ($provided !== $expected) return self::deny('INVALID_ENVELOPE_SCHEMA');

        foreach (self::KEYS as $key) {
            if (in_array($key, ['issued_at','expires_at'], true)) {
                if (!is_int($event[$key])) return self::deny('INVALID_ENVELOPE_FIELD');
            } elseif (!is_string($event[$key])) {
                return self::deny('INVALID_ENVELOPE_FIELD');
            }
        }
        if ($event['schema'] !== 'AUTO_IA_N8N_EVENT_V1'
            || !is_int($now) || $now < 0 || $now > 1_000_000_000
            || $event['issued_at'] < 0 || $event['expires_at'] < 0
            || $event['expires_at'] <= $event['issued_at']
            || $event['expires_at'] - $event['issued_at'] > 300
            || $now < $event['issued_at'] || $now >= $event['expires_at']) {
            return self::deny('EXPIRED_OR_INVALID_WINDOW');
        }
        foreach (['tenant_ref','event_ref','nonce_ref','request_ref'] as $key) {
            if (!self::syntheticRef($event[$key])) return self::deny('INVALID_SYNTHETIC_SCOPE');
        }
        foreach (['payload_sha256','signature'] as $key) {
            if (preg_match('/\A[a-f0-9]{64}\z/D', $event[$key]) !== 1) {
                return self::deny('INVALID_DIGEST');
            }
        }
        if (!str_starts_with($mockKey, 'SYNTHETIC_')
            || strlen($mockKey) < 20 || strlen($mockKey) > 128) {
            return self::deny('OFFLINE_KEY_REQUIRED');
        }

        // Only operations the server-side catalog classifies as deterministic
        // can be represented by this n8n automation event. Neither this
        // contract nor a signed envelope is authority to change any service.
        $route = AutoIaRouter::classify([
            'schema' => 'AUTO_IA_EVENT_V1',
            'product' => $event['product'],
            'operation' => $event['operation'],
            'tenant_ref' => $event['tenant_ref'],
            'event_ref' => $event['event_ref'],
            'semantic_reason' => 'NONE',
        ]);
        if ($route['decision'] !== 'NO_AI') return self::deny('ONLY_DETERMINISTIC_OPERATIONS');
        $canonical = self::canonical($event);
        if ($canonical === null
            || !hash_equals(hash_hmac('sha256', $canonical, $mockKey), $event['signature'])) {
            return self::deny('INVALID_SYNTHETIC_SIGNATURE');
        }
        if (isset($seen[$event['nonce_ref']])) return self::deny('NONCE_REPLAY');
        if (count($seen) >= self::MAX_SEEN) return self::deny('NONCE_CAPACITY_EXHAUSTED');

        $seen[$event['nonce_ref']] = hash('sha256', $canonical);
        return [
            'decision' => 'LAB_ENVELOPE_ACCEPTED_NO_ACTION',
            'reason' => 'SERVICE_AUTHORIZATION_AND_TRANSPORT_NOT_IMPLEMENTED',
            'service_effects' => 0,
            'external_calls' => 0,
            'ai_tokens' => 0,
        ];
    }

    /** Lab-only fixture construction: NOT an operational signing API. */
    public static function unsafeFixtureSignature(array $event, string $mockKey): string
    {
        $canonical = self::canonical($event);
        if ($canonical === null) return '';
        return hash_hmac('sha256', $canonical, $mockKey);
    }

    /** @return string|null */
    private static function canonical(array $event): ?string
    {
        $unsigned = [];
        foreach (self::KEYS as $key) {
            if ($key === 'signature') continue;
            if (!array_key_exists($key, $event)) return null;
            $unsigned[$key] = $event[$key];
        }
        try {
            return json_encode($unsigned, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function syntheticRef(string $value): bool
    {
        return strlen($value) <= 64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D', $value) === 1;
    }

    /** @return array{decision:string,reason:string,service_effects:int,external_calls:int,ai_tokens:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'service_effects' => 0,
            'external_calls' => 0,
            'ai_tokens' => 0,
        ];
    }
}
