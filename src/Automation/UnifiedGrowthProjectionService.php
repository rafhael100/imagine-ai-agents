<?php
declare(strict_types=1);

namespace ImagineAI\Automation;

require_once __DIR__.'/DeterministicAutomationService.php';
require_once __DIR__.'/ComercialGrowthScenarioProjector.php';

use InvalidArgumentException;

/**
 * P50: explicit mode/period contract for the two distinct NO_AI funnels.
 * Rejects silently mixing n8n WEEKLY results with the Imagine Comercial
 * four-week illustrative MONTHLY scenarios. This class has no IO, secrets,
 * storage, provider SDK or production authorization.
 */
final class UnifiedGrowthProjectionService
{
    private const WEEKLY_KEYS=[
        'schema','mode','tenant_ref','request_ref',
        'weekly_visits','lead_rate_bp','client_rate_bp'
    ];
    private const MONTHLY_KEYS=[
        'schema','mode','tenant_ref','request_ref',
        'weekly_visits','scenario'
    ];

    /** @param array<string,mixed> $request
     *  @return array<string,mixed>
     */
    public function project(array $request):array
    {
        $mode=$request['mode']??null;
        if(!is_string($mode)
            ||!in_array($mode,['n8n_weekly','comercial_monthly'],true)){
            throw new InvalidArgumentException('GROWTH_UNSUPPORTED_MODE');
        }
        $expected=$mode==='n8n_weekly'
            ?self::WEEKLY_KEYS:self::MONTHLY_KEYS;
        if(array_keys($request)!==$expected
            ||$request['schema']!=='AIA_GROWTH_PROJECTION_COMMAND_V1'
            ||!is_string($request['tenant_ref'])
            ||!is_string($request['request_ref'])
            ||!self::ref($request['tenant_ref'])
            ||!self::ref($request['request_ref'])
            ||!is_int($request['weekly_visits'])){
            throw new InvalidArgumentException('GROWTH_CLOSED_SCHEMA_OR_REF_DENIED');
        }

        $base=[
            'schema'=>'AIA_GROWTH_PROJECTION_RESULT_V1',
            'mode'=>$mode,
            'tenant_ref'=>$request['tenant_ref'],
            'request_ref'=>$request['request_ref'],
            'weekly_visits'=>$request['weekly_visits'],
        ];
        if($mode==='n8n_weekly'){
            if(!is_int($request['lead_rate_bp'])
                ||!is_int($request['client_rate_bp'])){
                throw new InvalidArgumentException('GROWTH_WEEKLY_RATE_TYPE_DENIED');
            }
            $v=DeterministicFunnelProjector::project(
                $request['weekly_visits'],
                $request['lead_rate_bp'],
                $request['client_rate_bp']
            );
            $fields=[
                'period'=>'week',
                'visits'=>$v['visits'],
                'leads'=>$v['projected_leads'],
                'customers'=>$v['projected_customers'],
                'lead_rate_bp'=>$request['lead_rate_bp'],
                'client_rate_bp'=>$request['client_rate_bp'],
            ];
        }else{
            if(!is_string($request['scenario'])){
                throw new InvalidArgumentException('GROWTH_MONTHLY_SCENARIO_TYPE_DENIED');
            }
            $v=ComercialGrowthScenarioProjector::project(
                $request['weekly_visits'],$request['scenario']
            );
            $fields=[
                'period'=>'illustrative_4_week_month',
                'visits'=>$v['monthlyVisits'],
                'leads'=>$v['leads'],
                'customers'=>$v['customers'],
                'scenario'=>$v['scenario'],
                'weeks_per_month'=>ComercialGrowthScenarioProjector::WEEKS_PER_MONTH,
            ];
        }
        return array_merge($base,$fields,[
            'decision'=>'NO_AI',
            'status'=>'LAB_PREVIEW_ONLY',
            'assumption_only'=>true,
            'actual_performance_data'=>false,
            'real_results_guaranteed'=>false,
            'operational_authorization'=>false,
            'ai_api_calls'=>0,
            'ai_tokens'=>0,
            'commercial_credits_debited'=>0,
        ]);
    }

    private static function ref(string $v):bool
    {
        return strlen($v)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$v)===1;
    }
}
