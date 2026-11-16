<?php
declare(strict_types=1);

namespace ImagineAI\Automation;

use InvalidArgumentException;

/**
 * P48 pure-PHP deterministic funnel math.
 *
 * No API, storage, AI provider, n8n, budget or tenant access.
 * Projection counts are *estimates*, not actual visitors/leads/customers.
 * Integer basis points avoid floating-point ambiguity and use PHP64-safe
 * bounds. Rounding is half-up for nonnegative quantities, matching the
 * reviewed native n8n workflow for integer percentages.
 */
final class DeterministicFunnelProjector
{
    public const MAX_WEEKLY_VISITS=100_000_000;

    /**
     * @return array{visits:int,projected_leads:int,projected_customers:int}
     */
    public static function project(int $visits,int $leadRateBp,int $clientRateBp):array
    {
        if($visits<0||$visits>self::MAX_WEEKLY_VISITS
            ||$leadRateBp<0||$leadRateBp>10000
            ||$clientRateBp<0||$clientRateBp>10000){
            throw new InvalidArgumentException('FUNNEL_INPUT_BOUNDS_DENIED');
        }
        $leads=intdiv($visits*$leadRateBp+5000,10000);
        $customers=intdiv($leads*$clientRateBp+5000,10000);
        return [
            'visits'=>$visits,
            'projected_leads'=>$leads,
            'projected_customers'=>$customers
        ];
    }
}

/**
 * Explicitly synthetic application-facing deterministic service. It is a
 * reusable pure domain function, NOT a production authorization boundary.
 * The real Portal/CRM must bind tenant, permission and input externally.
 */
final class DeterministicAutomationService
{
    private const KEYS=[
        'schema','operation','tenant_ref','request_ref',
        'weekly_visits','lead_rate_bp','client_rate_bp'
    ];

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public function calculate(array $input):array
    {
        if(array_keys($input)!==self::KEYS
            ||$input['schema']!=='AIA_DETERMINISTIC_COMMAND_V1'
            ||$input['operation']!=='funnel.calculate'
            ||!is_string($input['tenant_ref'])
            ||!is_string($input['request_ref'])
            ||!self::syntheticRef($input['tenant_ref'])
            ||!self::syntheticRef($input['request_ref'])
            ||!is_int($input['weekly_visits'])
            ||!is_int($input['lead_rate_bp'])
            ||!is_int($input['client_rate_bp'])){
            throw new InvalidArgumentException('DETERMINISTIC_CLOSED_SCHEMA_DENIED');
        }
        $v=DeterministicFunnelProjector::project(
            $input['weekly_visits'],
            $input['lead_rate_bp'],
            $input['client_rate_bp']
        );
        return [
            'schema'=>'AIA_DETERMINISTIC_RESULT_V1',
            'operation'=>'funnel.calculate',
            'tenant_ref'=>$input['tenant_ref'],
            'request_ref'=>$input['request_ref'],
            'visits'=>$v['visits'],
            'projected_leads'=>$v['projected_leads'],
            'projected_customers'=>$v['projected_customers'],
            'decision'=>'NO_AI',
            'status'=>'LAB_PREVIEW_ONLY',
            'ai_api_calls'=>0,
            'ai_tokens'=>0,
            'commercial_credits_debited'=>0,
            'operational_authorization'=>false,
        ];
    }

    private static function syntheticRef(string $v):bool
    {
        return strlen($v)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;
    }
}
