<?php
declare(strict_types=1);
namespace ImagineAI\Automation;

use InvalidArgumentException;

/**
 * P49: pure deterministic monthly funnel with exact reviewed assumptions
 * from Imagine Comercial F331-F340 (pinned in CI), kept separate from the
 * P48 WEEKLY funnel / n8n RC07 contract.
 *
 * Hypothetical mathematical illustration, NEVER a sector benchmark,
 * forecast, promise or real CRM measurement.
 */
final class ComercialGrowthScenarioProjector
{
    public const WEEKS_PER_MONTH=4;
    public const MAX_WEEKLY_VISITS=100_000;
    public const MAX_MONTHLY_CUSTOMER_GOAL=10_000;

    private const SCENARIOS=[
        'conservative'=>['leadBasisPoints'=>200,'closeBasisPoints'=>1000],
        'reference'=>['leadBasisPoints'=>400,'closeBasisPoints'=>2000],
        'optimistic'=>['leadBasisPoints'=>600,'closeBasisPoints'=>3000],
    ];

    /** @return array{monthlyVisits:int,leads:int,customers:int,scenario:string,assumptionOnly:bool,actualPerformanceData:bool} */
    public static function project(int $weeklyVisits,string $scenario='reference'):array
    {
        if($weeklyVisits<0||$weeklyVisits>self::MAX_WEEKLY_VISITS
            ||!array_key_exists($scenario,self::SCENARIOS)){
            throw new InvalidArgumentException('COMERCIAL_GROWTH_INPUT_DENIED');
        }
        $visits=$weeklyVisits*self::WEEKS_PER_MONTH;
        $leadRate=self::SCENARIOS[$scenario]['leadBasisPoints'];
        $closeRate=self::SCENARIOS[$scenario]['closeBasisPoints'];

        // Leads presented rounded, but customers are calculated from
        // the UNROUNDED expected leads, exactly like the Comercial F331
        // source. Do NOT reuse weekly P48 sequential rounded leads here.
        $leads=intdiv($visits*$leadRate+5000,10000);
        $customers=intdiv($visits*$leadRate*$closeRate+50_000_000,100_000_000);
        return [
            'monthlyVisits'=>$visits,
            'leads'=>$leads,
            'customers'=>$customers,
            'scenario'=>$scenario,
            'assumptionOnly'=>true,
            'actualPerformanceData'=>false,
        ];
    }

    /** @return array{reachable:bool,scenario:string,monthlyCustomerGoal:int,weeklyVisits?:int,monthlyVisits?:int,leads?:int,customers?:int,weeklyCap?:int,maxIllustratedCustomers?:int,assumptionOnly:bool,realResultsGuaranteed:bool} */
    public static function planVisitsForCustomerGoal(
        int $goal,string $scenario='reference'
    ):array {
        if($goal<0||$goal>self::MAX_MONTHLY_CUSTOMER_GOAL
            ||!array_key_exists($scenario,self::SCENARIOS)){
            throw new InvalidArgumentException('COMERCIAL_GOAL_DENIED');
        }
        $max=self::project(self::MAX_WEEKLY_VISITS,$scenario);
        if($max['customers']<$goal){
            return [
                'reachable'=>false,'scenario'=>$scenario,
                'monthlyCustomerGoal'=>$goal,
                'weeklyCap'=>self::MAX_WEEKLY_VISITS,
                'maxIllustratedCustomers'=>$max['customers'],
                'assumptionOnly'=>true,'realResultsGuaranteed'=>false,
            ];
        }
        $low=0;$high=self::MAX_WEEKLY_VISITS;
        while($low<$high){
            $mid=intdiv($low+$high,2);
            if(self::project($mid,$scenario)['customers']>=$goal){
                $high=$mid;
            }else{
                $low=$mid+1;
            }
        }
        $chosen=self::project($low,$scenario);
        return [
            'reachable'=>true,'scenario'=>$scenario,
            'monthlyCustomerGoal'=>$goal,
            'weeklyVisits'=>$low,
            'monthlyVisits'=>$chosen['monthlyVisits'],
            'leads'=>$chosen['leads'],
            'customers'=>$chosen['customers'],
            'assumptionOnly'=>true,'realResultsGuaranteed'=>false,
        ];
    }

    /** @return array<string,array{monthlyVisits:int,leads:int,customers:int,scenario:string,assumptionOnly:bool,actualPerformanceData:bool}> */
    public static function compareScenarios(int $weeklyVisits):array
    {
        $out=[];
        foreach(array_keys(self::SCENARIOS) as $scenario){
            $out[$scenario]=self::project($weeklyVisits,$scenario);
        }
        return $out;
    }
}
