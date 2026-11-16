<?php
declare(strict_types=1);

namespace ImagineAI\Automation;

use InvalidArgumentException;

/**
 * P53: frontend-oriented, read-only presentation contract for P51 draft rows.
 *
 * It accepts ONLY PgDeterministicDraftRegistryLab::readPending() output.
 * It cannot authenticate an actual operator, approve anything, mutate the
 * registry, call CRM or n8n, send a notice, or spend AI credits.
 */
final class ReviewQueueReadModel
{
    private const ENTRY_KEYS=[
        'request_ref','proposal_ref','rule_ref','draft_kind',
        'metric','observed','threshold','state'
    ];
    private const KINDS=[
        'HUMAN_REVIEW_DRAFT','CRM_TASK_DRAFT','OPERATOR_NOTICE_DRAFT'
    ];
    private const METRICS=[
        'visits','projected_leads','projected_customers'
    ];
    private const MAX_ROWS=128;

    /**
     * @param array<string,mixed> $registryRead
     * @return array<string,mixed> JSON-safe strict read-only UI payload
     */
    public function present(array $registryRead,string $expectedTenant):array
    {
        if(!self::ref($expectedTenant)
            ||array_keys($registryRead)!==[
                'decision','tenant_ref','drafts','ai_calls','service_effects'
            ]
            ||$registryRead['decision']!=='LAB_READONLY_DRAFTS_NO_APPROVAL'
            ||$registryRead['tenant_ref']!==$expectedTenant
            ||$registryRead['ai_calls']!==0
            ||$registryRead['service_effects']!==0
            ||!is_array($registryRead['drafts'])
            ||!array_is_list($registryRead['drafts'])
            ||count($registryRead['drafts'])>self::MAX_ROWS){
            throw new InvalidArgumentException('REVIEW_QUEUE_READ_SCOPE_DENIED');
        }

        $counts=array_fill_keys(self::KINDS,0);
        $items=[];$seen=[];
        foreach($registryRead['drafts'] as $record){
            if(!is_array($record)
                ||array_keys($record)!==self::ENTRY_KEYS
                ||!is_string($record['request_ref'])
                ||!self::ref($record['request_ref'])
                ||!is_string($record['proposal_ref'])
                ||preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',
                    $record['proposal_ref'])!==1
                ||!is_string($record['rule_ref'])
                ||!self::ref($record['rule_ref'])
                ||!is_string($record['draft_kind'])
                ||!in_array($record['draft_kind'],self::KINDS,true)
                ||!is_string($record['metric'])
                ||!in_array($record['metric'],self::METRICS,true)
                ||!is_string($record['state'])
                ||$record['state']!=='AWAITING_HUMAN_REVIEW_LAB'){
                throw new InvalidArgumentException('REVIEW_QUEUE_DRAFT_SCHEMA_DENIED');
            }
            $observed=self::number($record['observed']);
            $threshold=self::number($record['threshold']);
            if($observed===null||$threshold===null){
                throw new InvalidArgumentException('REVIEW_QUEUE_METRIC_INVALID');
            }
            if(isset($seen[$record['proposal_ref']])){
                throw new InvalidArgumentException('REVIEW_QUEUE_DUPLICATE_PROPOSAL');
            }
            $seen[$record['proposal_ref']]=true;
            ++$counts[$record['draft_kind']];
            $items[]=[
                'request_ref'=>$record['request_ref'],
                'proposal_ref'=>$record['proposal_ref'],
                'rule_ref'=>$record['rule_ref'],
                'kind'=>$record['draft_kind'],
                'metric'=>$record['metric'],
                'observed'=>$observed,
                'threshold'=>$threshold,
                'status'=>'AWAITING_HUMAN_REVIEW_LAB',
                'human_action_available'=>false,
                'execution'=>'NOT_EXECUTED',
            ];
        }
        return [
            'schema'=>'AIA_REVIEW_QUEUE_READ_V1',
            'tenant_ref'=>$expectedTenant,
            'status'=>'LAB_READONLY_DASHBOARD',
            'decision'=>'NO_GO',
            'total_pending'=>count($items),
            'by_kind'=>[
                'human_review'=>$counts['HUMAN_REVIEW_DRAFT'],
                'crm_task_draft'=>$counts['CRM_TASK_DRAFT'],
                'operator_notice_draft'=>$counts['OPERATOR_NOTICE_DRAFT'],
            ],
            'items'=>$items,
            'can_approve'=>false,
            'can_execute'=>false,
            'operational_authorization'=>false,
            'ai_api_calls'=>0,
            'credits_debited'=>0,
            'crm_tasks_created'=>0,
            'operator_messages_sent'=>0,
        ];
    }

    private static function number(mixed $v):?int
    {
        if(is_int($v))return $v>=0&&$v<=100_000_000?$v:null;
        if(!is_string($v)
            ||preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D',$v)!==1){
            return null;
        }
        $number=(int)$v;
        return $number<=100_000_000?$number:null;
    }

    private static function ref(string $value):bool
    {
        return strlen($value)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$value)===1;
    }
}
