<?php
declare(strict_types=1);

namespace ImagineAI\AutomationFirst;

require_once __DIR__ . '/AutoIaRouter.php';

/**
 * AUTO-IA-02: pure in-memory SYNTHETIC cost preflight for gateway design.
 * Never a provider authorization, reservation, money balance or real IAM proof.
 */
final class MockAiBudgetGate
{
    /**
     * @param array<string,mixed> $event Synthetic event already classified by AutoIaRouter
     * @param array<string,mixed>|null $fixture Synthetic budget fixture from a *separate* source
     * @return array{decision:string,reason:string,provider_calls:int,model_tokens:int,billed_minor:int}
     */
    public static function evaluate(array $event, ?array $fixture): array
    {
        $route = AutoIaRouter::classify($event);
        if ($route['decision'] === 'NO_AI') {
            return self::result('NO_AI', 'DETERMINISTIC_OPERATION');
        }
        if ($route['decision'] !== 'AI_REVIEW_REQUIRED') {
            return self::result('DENY', 'ROUTE_NOT_ELIGIBLE');
        }
        if ($fixture === null) return self::result('DENY', 'BUDGET_FIXTURE_MISSING');

        $keys = [
            'schema', 'tenant_ref', 'event_ref', 'product', 'operation',
            'policy_ref', 'issued_at', 'expires_at', 'now',
            'max_model_tokens', 'requested_model_tokens', 'available_model_tokens',
            'estimated_cost_minor', 'remaining_budget_minor', 'enabled',
        ];
        $provided = array_keys($fixture);
        sort($provided);
        sort($keys);
        if ($keys !== $provided) return self::result('DENY', 'INVALID_BUDGET_FIXTURE');

        foreach (['schema', 'tenant_ref', 'event_ref', 'product', 'operation', 'policy_ref'] as $field) {
            if (!is_string($fixture[$field])) return self::result('DENY', 'INVALID_BUDGET_FIXTURE');
        }
        foreach (['issued_at', 'expires_at', 'now', 'max_model_tokens',
                     'requested_model_tokens', 'available_model_tokens',
                     'estimated_cost_minor', 'remaining_budget_minor'] as $field) {
            if (!is_int($fixture[$field]) || $fixture[$field] < 0 || $fixture[$field] > 1_000_000_000) {
                return self::result('DENY', 'INVALID_BUDGET_FIXTURE');
            }
        }
        if (!is_bool($fixture['enabled'])
            || $fixture['schema'] !== 'AUTO_IA_BUDGET_FIXTURE_V1'
            || !self::syntheticRef($fixture['policy_ref'])) {
            return self::result('DENY', 'INVALID_BUDGET_FIXTURE');
        }
        foreach (['tenant_ref', 'event_ref', 'product', 'operation'] as $key) {
            if (!hash_equals($event[$key], $fixture[$key])) {
                return self::result('DENY', 'SCOPE_MISMATCH');
            }
        }
        if (!$fixture['enabled']) return self::result('DENY', 'POLICY_DISABLED');
        if ($fixture['issued_at'] > $fixture['now']
            || $fixture['expires_at'] <= $fixture['issued_at']
            || $fixture['expires_at'] <= $fixture['now']
            || $fixture['expires_at'] - $fixture['issued_at'] > 3600) {
            return self::result('DENY', 'POLICY_EXPIRED_OR_INVALID');
        }
        if ($fixture['requested_model_tokens'] === 0
            || $fixture['max_model_tokens'] === 0
            || $fixture['requested_model_tokens'] > $fixture['max_model_tokens']
            || $fixture['requested_model_tokens'] > $fixture['available_model_tokens']) {
            return self::result('DENY', 'TOKEN_BUDGET_EXCEEDED');
        }
        if ($fixture['estimated_cost_minor'] === 0
            || $fixture['estimated_cost_minor'] > $fixture['remaining_budget_minor']) {
            return self::result('DENY', 'COST_BUDGET_EXCEEDED');
        }

        // Even fully consistent fixtures MUST NOT initiate an API call.
        // A future gateway still needs actual IAM, signed policy, a durable
        // atomic reservation, proof of provider spend and reconciliation.
        return self::result('LAB_BUDGET_WITHIN_LIMIT', 'EXTERNAL_AUTHORIZATION_NOT_IMPLEMENTED');
    }

    private static function syntheticRef(string $ref): bool
    {
        return strlen($ref) <= 64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D', $ref) === 1;
    }

    /** @return array{decision:string,reason:string,provider_calls:int,model_tokens:int,billed_minor:int} */
    private static function result(string $decision, string $reason): array
    {
        return [
            'decision' => $decision,
            'reason' => $reason,
            'provider_calls' => 0,
            'model_tokens' => 0,
            'billed_minor' => 0,
        ];
    }
}
