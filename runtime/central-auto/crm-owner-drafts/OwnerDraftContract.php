<?php
declare(strict_types=1);

namespace Imagine\Agents\Integration\Crm;

require_once dirname(__DIR__) . '/strict-json.php';

/** Closed CRM attestation contract. It never authorizes, plans or executes a CRM command. */
final class OwnerDraftContract
{
    public const CREATE = 'crm.rule.draft.create';
    public const READ = 'crm.rule.draft.read';
    public const REQUEST_DOMAIN = "IMAGINE_CRM_OWNER_DRAFT_REQUEST_V1\n";
    public const RESPONSE_DOMAIN = "IMAGINE_CRM_OWNER_DRAFT_RESPONSE_V1\n";
    private const UUID = '/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D';
    private const HASH = '/\A[a-f0-9]{64}\z/D';

    public static function canonical(mixed $value): string
    {
        return json_encode(self::ordered($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE);
    }

    private static function ordered(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                throw new \UnexpectedValueException('OWNER_DRAFT_OBJECT_REQUIRED');
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $item) {
                if (!is_string($key) || !preg_match('/\A[a-z_]+\z/D', $key)) {
                    throw new \UnexpectedValueException('OWNER_DRAFT_KEY');
                }
                $value[$key] = self::ordered($item);
            }
            return $value;
        }
        if ($value !== null && !is_string($value) && !is_int($value) && !is_bool($value)) {
            throw new \UnexpectedValueException('OWNER_DRAFT_VALUE');
        }
        return $value;
    }

    private static function object(mixed $value, array $keys): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new \UnexpectedValueException('OWNER_DRAFT_OBJECT');
        }
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        if ($actual !== $keys) {
            throw new \UnexpectedValueException('OWNER_DRAFT_KEYS');
        }
        return $value;
    }

    private static function exact(array $value, array $constants): void
    {
        foreach ($constants as $name => $expected) {
            if ($value[$name] !== $expected) {
                throw new \UnexpectedValueException('OWNER_DRAFT_DOMAIN');
            }
        }
    }

    private static function pattern(mixed $value, string $pattern): void
    {
        if (!is_string($value) || strlen($value) > 128 || preg_match($pattern, $value) !== 1) {
            throw new \UnexpectedValueException('OWNER_DRAFT_STRING');
        }
    }

    private static function integer(mixed $value, int $minimum, int $maximum): void
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new \UnexpectedValueException('OWNER_DRAFT_INTEGER');
        }
    }

    public static function business(mixed $value): array
    {
        $value = self::object($value, ['schema', 'company_id', 'draft_ref', 'creator_id',
            'source_run_id', 'rule_id', 'settings_version', 'lead_id', 'lead_version',
            'source_created_at', 'command_hash', 'projection']);
        self::exact($value, ['schema' => 'CRM_AIA_OWNER_RULE_DRAFT_V1', 'rule_id' => 'followup_qualified']);
        foreach (['company_id', 'draft_ref', 'creator_id', 'source_run_id', 'lead_id'] as $name) {
            self::pattern($value[$name], self::UUID);
        }
        self::pattern($value['command_hash'], self::HASH);
        // Keep the raw CRM revision, including legitimate legacy revision zero.
        self::integer($value['settings_version'], 0, 20);
        self::integer($value['lead_version'], 1, 2147483647);
        self::integer($value['source_created_at'], 1, 4102444800);
        $projection = self::object($value['projection'], ['action', 'stage', 'delay_seconds', 'due_at']);
        self::exact($projection, ['action' => 'task.followup.create', 'stage' => 'qualified']);
        self::integer($projection['delay_seconds'], 0, 86400);
        self::integer($projection['due_at'], 1, 4102531200);
        if ($projection['due_at'] !== $value['source_created_at'] + $projection['delay_seconds']) {
            throw new \UnexpectedValueException('OWNER_DRAFT_DUE_AT');
        }
        return $value;
    }

    public static function request(string $raw, string $operation, int $now): array
    {
        if (!in_array($operation, [self::CREATE, self::READ], true)) {
            throw new \UnexpectedValueException('OWNER_DRAFT_OPERATION');
        }
        $request = self::object(\agents_json_decode($raw, 8192, 8),
            $operation === self::CREATE ? ['authorization', 'business'] : ['authorization']);
        $auth = self::object($request['authorization'], ['schema', 'issuer', 'audience',
            'operation', 'company_id', 'actor_role', 'actor_id', 'draft_ref', 'business_hash',
            'nonce', 'issued_at', 'expires_at', 'key_id', 'mac']);
        self::exact($auth, ['schema' => 'CRM_AIA_OWNER_DRAFT_AUTH_V1', 'issuer' => 'imagine-crm',
            'audience' => 'imagine-ai-agents', 'operation' => $operation, 'actor_role' => 'owner']);
        foreach (['company_id', 'actor_id', 'draft_ref', 'nonce'] as $name) {
            self::pattern($auth[$name], self::UUID);
        }
        foreach (['business_hash', 'mac'] as $name) {
            self::pattern($auth[$name], self::HASH);
        }
        self::pattern($auth['key_id'], '/\A[a-z][a-z0-9_-]{0,63}\z/D');
        self::integer($auth['issued_at'], 1, 4102444800);
        self::integer($auth['expires_at'], 1, 4102444800);
        if ($auth['issued_at'] < $now - 30 || $auth['issued_at'] > $now + 5
            || $auth['expires_at'] <= $now || $auth['expires_at'] <= $auth['issued_at']
            || $auth['expires_at'] - $auth['issued_at'] > 30) {
            throw new \UnexpectedValueException('OWNER_DRAFT_EXPIRED');
        }
        if ($operation === self::CREATE) {
            $business = self::business($request['business']);
            if ($business['company_id'] !== $auth['company_id']
                || $business['draft_ref'] !== $auth['draft_ref']
                || $business['creator_id'] !== $auth['actor_id']
                || !hash_equals($auth['business_hash'], hash('sha256', self::canonical($business)))) {
                throw new \UnexpectedValueException('OWNER_DRAFT_BINDING');
            }
        }
        return $request;
    }

    /** Checks a DB result before commit; the CRM independently verifies its MAC. */
    public static function response(mixed $reply, array $auth, int $now): array
    {
        $reply = self::object($reply, ['schema', 'issuer', 'audience', 'operation', 'company_id',
            'actor_id', 'draft_ref', 'business_hash', 'nonce', 'status', 'business',
            'executed', 'operational', 'issued_at', 'expires_at', 'key_id', 'mac']);
        self::exact($reply, ['schema' => 'CRM_AIA_OWNER_DRAFT_RESPONSE_V1',
            'issuer' => 'imagine-ai-agents', 'audience' => 'imagine-crm',
            'executed' => false, 'operational' => false]);
        foreach (['operation', 'company_id', 'actor_id', 'draft_ref', 'business_hash', 'nonce', 'key_id'] as $name) {
            if ($reply[$name] !== $auth[$name]) {
                throw new \UnexpectedValueException('OWNER_DRAFT_RESPONSE_BINDING');
            }
        }
        self::pattern($reply['mac'], self::HASH);
        self::integer($reply['issued_at'], 1, 4102444800);
        self::integer($reply['expires_at'], 1, 4102444800);
        if ($reply['issued_at'] < $now - 30 || $reply['issued_at'] > $now + 5
            || $reply['expires_at'] <= $now || $reply['expires_at'] <= $reply['issued_at']
            || $reply['expires_at'] > min($auth['expires_at'], $reply['issued_at'] + 15)) {
            throw new \UnexpectedValueException('OWNER_DRAFT_RESPONSE_EXPIRED');
        }
        if ($reply['status'] === 'DRAFT') {
            $business = self::business($reply['business']);
            if ($business['company_id'] !== $auth['company_id']
                || $business['draft_ref'] !== $auth['draft_ref']
                || !hash_equals($auth['business_hash'], hash('sha256', self::canonical($business)))
                || ($auth['operation'] === self::CREATE && $business['creator_id'] !== $auth['actor_id'])) {
                throw new \UnexpectedValueException('OWNER_DRAFT_RESPONSE_BUSINESS');
            }
        } elseif ($reply['status'] !== 'ABSENT' || $auth['operation'] !== self::READ || $reply['business'] !== null) {
            throw new \UnexpectedValueException('OWNER_DRAFT_RESPONSE_STATUS');
        }
        return $reply;
    }
}
