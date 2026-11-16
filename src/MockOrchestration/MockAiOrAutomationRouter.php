<?php
declare(strict_types=1);

namespace ImagineAI\MockOrchestration;

/**
 * P5: deterministic routing policy for synthetic automation vs AI eligibility.
 *
 * This is a PURE decision aid, NOT a router with side effects, credit
 * reservation, n8n adapter or authorization. It never receives free-text
 * content, client data, secrets or keys. No provider invocation is possible.
 */
final class MockAiOrAutomationRouter
{
    private const INTENT_KEYS = [
        'tenant_ref','run_ref','operation','content_class',
        'requires_external_effect','estimated_model_units',
    ];
    private const POLICY_KEYS = [
        'tenant_ref','ai_opt_in','consent_verified','approved_for_model',
        'available_units','per_call_limit','provider_configured','ledger_certain',
    ];
    private const DETERMINISTIC = [
        'mock.lead.normalize_stage',
        'mock.lead.route_approval',
        'mock.funnel.estimate',
        'mock.quota.check',
        'mock.run.deduplicate',
    ];
    private const SEMANTIC = [
        'mock.copy.draft',
        'mock.text.classify',
    ];

    /**
     * @param array<string,mixed> $intent
     * @param array<string,mixed> $policy
     * @return array{
     * contract:string,decision:string,reason:string,
     * proposed_units:int,credits_consumed:0,external_calls:0,
     * execution_allowed:false,operational_decision:string
     * }
     */
    public static function inspect(array $intent, array $policy): array
    {
        if (!self::keysMatch($intent, self::INTENT_KEYS)
            || !self::keysMatch($policy, self::POLICY_KEYS)
            || !self::syntheticRef($intent['tenant_ref'])
            || !self::syntheticRef($intent['run_ref'])
            || !self::syntheticRef($policy['tenant_ref'])
            || !hash_equals($intent['tenant_ref'], $policy['tenant_ref'])
            || !is_string($intent['operation'])
            || !is_string($intent['content_class'])
            || !in_array($intent['content_class'],[
                'SYNTHETIC_ONLY','REVIEW_REQUIRED','SENSITIVE',
            ], true)
            || !is_bool($intent['requires_external_effect'])
            || !is_int($intent['estimated_model_units'])
            || $intent['estimated_model_units'] < 0
            || $intent['estimated_model_units'] > 10000
            || !self::booleans($policy, [
                'ai_opt_in','consent_verified','approved_for_model',
                'provider_configured','ledger_certain',
            ])
            || !is_int($policy['available_units'])
            || !is_int($policy['per_call_limit'])
            || $policy['available_units'] < 0
            || $policy['available_units'] > 1000000
            || $policy['per_call_limit'] < 0
            || $policy['per_call_limit'] > 10000
        ) {
            return self::result('DENIED','INVALID_SCOPE_OR_CONTRACT',0);
        }

        // Never permit a software automation or model to produce an external
        // effect merely because a policy selected its route.
        if ($intent['requires_external_effect']) {
            return self::result('DENIED','EXTERNAL_EFFECT_NOT_APPROVED',0);
        }
        if ($intent['content_class'] !== 'SYNTHETIC_ONLY') {
            return self::result('DENIED','CONTENT_NOT_APPROVED',0);
        }
        if (in_array($intent['operation'], self::DETERMINISTIC, true)) {
            if ($intent['estimated_model_units'] !== 0) {
                return self::result('DENIED','DETERMINISTIC_CANNOT_SPEND_AI',0);
            }
            // Eligible for local deterministic handling without LLM credits.
            // Nothing is actually run by this decision-only class.
            return self::result('DETERMINISTIC_ONLY','NO_MODEL_REQUIRED',0);
        }
        if (!in_array($intent['operation'],self::SEMANTIC,true)) {
            return self::result('DENIED','UNKNOWN_OPERATION',0);
        }
        $units = $intent['estimated_model_units'];
        if ($units <= 0 || !$policy['ledger_certain']) {
            return self::result('DENIED','MEASUREMENT_NOT_SAFE',0);
        }
        if (!$policy['ai_opt_in'] || !$policy['consent_verified']
            || !$policy['approved_for_model'] || !$policy['provider_configured']) {
            return self::result('DENIED','MODEL_ACCESS_NOT_APPROVED',0);
        }
        if ($policy['per_call_limit'] === 0
            || $units > $policy['per_call_limit']
            || $units > $policy['available_units']) {
            return self::result('DENIED','MODEL_BUDGET_NOT_AVAILABLE',0);
        }
        // In production a separate, durable atomic reservation and a new
        // authorization boundary would be required before ANY API request.
        return self::result('MODEL_ELIGIBLE_NOT_EXECUTED','PRECHECK_ONLY',$units);
    }

    private static function result(string $decision,string $reason,int $units): array
    {
        return [
            'contract'=>'SYNTHETIC_AIA_ROUTE_PREFLIGHT_V1',
            'decision'=>$decision,
            'reason'=>$reason,
            'proposed_units'=>$units,
            'credits_consumed'=>0,
            'external_calls'=>0,
            'execution_allowed'=>false,
            'operational_decision'=>'NO_GO',
        ];
    }

    private static function keysMatch(array $input,array $keys): bool
    {
        if (count($input)!==count($keys)) return false;
        $found=array_keys($input);
        sort($found,SORT_STRING);
        sort($keys,SORT_STRING);
        return $found===$keys;
    }

    private static function syntheticRef(mixed $ref): bool
    {
        return is_string($ref) && strlen($ref)<=90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$ref)===1;
    }

    private static function booleans(array $source,array $keys): bool
    {
        foreach ($keys as $key) {
            if (!is_bool($source[$key])) return false;
        }
        return true;
    }
}
