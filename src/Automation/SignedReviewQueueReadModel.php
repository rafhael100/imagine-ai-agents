<?php
declare(strict_types=1);

namespace ImagineAI\Automation;

use InvalidArgumentException;

/**
 * P60 strict pure-PHP presentation composition for synthetic P53 queue
 * and P59 signed-review statuses.
 *
 * This is NOT an approval, IAM, live authorization or CRM/n8n API.
 * It only prepares a read-only status view. No I/O or network.
 */
final class SignedReviewQueueReadModel
{
    private const QUEUE_KEYS=[
        'schema','tenant_ref','status','decision','total_pending','by_kind',
        'items','can_approve','can_execute','operational_authorization',
        'ai_api_calls','credits_debited','crm_tasks_created','operator_messages_sent'
    ];
    private const ITEM_KEYS=[
        'request_ref','proposal_ref','rule_ref','kind','metric','observed',
        'threshold','status','human_action_available','execution'
    ];
    private const STATUS_KEYS=[
        'decision','observations','roles_observed','can_approve','can_execute',
        'operational_authorization','ai_calls','service_effects'
    ];
    private const STATES=[
        'LAB_AWAITING_REVIEWS_NO_ACTION',
        'LAB_PARTIAL_REVIEW_NO_ACTION',
        'LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION',
        'LAB_REVIEW_REJECTED_NO_ACTION'
    ];
    private const KINDS=[
        'HUMAN_REVIEW_DRAFT','CRM_TASK_DRAFT','OPERATOR_NOTICE_DRAFT'
    ];
    private const METRICS=[
        'visits','projected_leads','projected_customers'
    ];

    /** @param array<string,mixed> $queue P53 presentation only
     *  @param array<string,array<string,mixed>> $statuses exact P59 status per proposal
     *  @return array<string,mixed> immutable JSON-friendly preview
     */
    public function present(array $queue,array $statuses,string $tenant):array
    {
        if(!self::syntheticRef($tenant)
            ||array_keys($queue)!==self::QUEUE_KEYS
            ||$queue['schema']!=='AIA_REVIEW_QUEUE_READ_V1'
            ||$queue['tenant_ref']!==$tenant
            ||$queue['status']!=='LAB_READONLY_DASHBOARD'
            ||$queue['decision']!=='NO_GO'
            ||$queue['can_approve']!==false
            ||$queue['can_execute']!==false
            ||$queue['operational_authorization']!==false
            ||$queue['ai_api_calls']!==0
            ||$queue['credits_debited']!==0
            ||$queue['crm_tasks_created']!==0
            ||$queue['operator_messages_sent']!==0
            ||!is_int($queue['total_pending'])
            ||$queue['total_pending']<0||$queue['total_pending']>128
            ||!is_array($queue['items'])
            ||!array_is_list($queue['items'])
            ||count($queue['items'])!==$queue['total_pending']
            ||!is_array($queue['by_kind'])
            ||array_keys($queue['by_kind'])!==[
                'human_review','crm_task_draft','operator_notice_draft'
            ]){
            throw new InvalidArgumentException('P60_CLOSED_QUEUE_SCOPE_DENIED');
        }
        $byKind=[
            'human_review'=>0,'crm_task_draft'=>0,
            'operator_notice_draft'=>0
        ];
        $reviewSummary=[
            'awaiting'=>0,'partial'=>0,'two_observations'=>0,'rejected'=>0
        ];
        $mapped=[];
        $seen=[];
        foreach($queue['items'] as $item){
            if(!is_array($item)
                ||array_keys($item)!==self::ITEM_KEYS
                ||!is_string($item['request_ref'])
                ||!self::syntheticRef($item['request_ref'])
                ||!is_string($item['proposal_ref'])
                ||preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',
                    $item['proposal_ref'])!==1
                ||!is_string($item['rule_ref'])
                ||!self::syntheticRef($item['rule_ref'])
                ||!is_string($item['kind'])
                ||!in_array($item['kind'],self::KINDS,true)
                ||!is_string($item['metric'])
                ||!in_array($item['metric'],self::METRICS,true)
                ||!is_int($item['observed'])
                ||$item['observed']<0||$item['observed']>100000000
                ||!is_int($item['threshold'])
                ||$item['threshold']<0||$item['threshold']>100000000
                ||$item['status']!=='AWAITING_HUMAN_REVIEW_LAB'
                ||$item['human_action_available']!==false
                ||$item['execution']!=='NOT_EXECUTED'){
                throw new InvalidArgumentException('P60_UNTRUSTED_QUEUE_ITEM_DENIED');
            }
            $id=$item['proposal_ref'];
            if(isset($seen[$id])||!array_key_exists($id,$statuses)){
                throw new InvalidArgumentException('P60_MISSING_OR_DUPLICATE_REVIEW');
            }
            $seen[$id]=true;
            $status=$statuses[$id];
            if(!is_array($status)||array_keys($status)!==self::STATUS_KEYS
                ||!is_string($status['decision'])
                ||!in_array($status['decision'],self::STATES,true)
                ||!is_int($status['observations'])
                ||$status['observations']<0||$status['observations']>2
                ||!is_array($status['roles_observed'])
                ||!array_is_list($status['roles_observed'])
                ||count($status['roles_observed'])!==$status['observations']
                ||$status['can_approve']!==false
                ||$status['can_execute']!==false
                ||$status['operational_authorization']!==false
                ||$status['ai_calls']!==0
                ||$status['service_effects']!==0){
                throw new InvalidArgumentException('P60_REVIEW_STATUS_NOT_TRUSTED');
            }
            $roles=[];
            foreach($status['roles_observed'] as $role){
                if(!is_string($role)
                    ||!in_array($role,['PRODUCT','SECURITY'],true)
                    ||in_array($role,$roles,true)){
                    throw new InvalidArgumentException('P60_DUPLICATE_OR_UNTRUSTED_ROLE');
                }
                $roles[]=$role;
            }
            $state=$status['decision'];
            if(($state==='LAB_AWAITING_REVIEWS_NO_ACTION'
                    &&$status['observations']!==0)
                ||($state==='LAB_PARTIAL_REVIEW_NO_ACTION'
                    &&$status['observations']!==1)
                ||($state==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'
                    &&($status['observations']!==2
                    ||!in_array('PRODUCT',$roles,true)
                    ||!in_array('SECURITY',$roles,true)))
                ||($state==='LAB_REVIEW_REJECTED_NO_ACTION'
                    &&!in_array($status['observations'],[1,2],true))){
                throw new InvalidArgumentException('P60_REVIEW_STATUS_INCONSISTENT');
            }
            $category=match($item['kind']){
                'HUMAN_REVIEW_DRAFT'=>'human_review',
                'CRM_TASK_DRAFT'=>'crm_task_draft',
                'OPERATOR_NOTICE_DRAFT'=>'operator_notice_draft'
            };
            ++$byKind[$category];
            $summary=match($state){
                'LAB_AWAITING_REVIEWS_NO_ACTION'=>'awaiting',
                'LAB_PARTIAL_REVIEW_NO_ACTION'=>'partial',
                'LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'=>'two_observations',
                'LAB_REVIEW_REJECTED_NO_ACTION'=>'rejected'
            };
            ++$reviewSummary[$summary];
            $mapped[]=[
                'proposal_ref'=>$id,
                'request_ref'=>$item['request_ref'],
                'rule_ref'=>$item['rule_ref'],
                'kind'=>$item['kind'],
                'metric'=>$item['metric'],
                'observed'=>$item['observed'],
                'threshold'=>$item['threshold'],
                'review_state'=>$state,
                'signed_observations'=>$status['observations'],
                'review_roles'=>$roles,
                'human_action_available'=>false,
                'execution'=>'NOT_EXECUTED',
            ];
        }
        if($queue['by_kind']!==$byKind
            ||count($statuses)!==count($seen)){
            throw new InvalidArgumentException('P60_QUEUE_COUNT_OR_REVIEW_SET_MISMATCH');
        }
        return [
            'schema'=>'AIA_SIGNED_REVIEW_QUEUE_READ_V1',
            'tenant_ref'=>$tenant,
            'decision'=>'NO_GO',
            'status'=>'LAB_SIGNED_REVIEW_READONLY',
            'total_pending'=>$queue['total_pending'],
            'review_summary'=>$reviewSummary,
            'by_kind'=>$byKind,
            'items'=>$mapped,
            'can_approve'=>false,
            'can_execute'=>false,
            'operational_authorization'=>false,
            'ai_api_calls'=>0,
            'credits_debited'=>0,
            'service_effects'=>0,
        ];
    }

    private static function syntheticRef(string $ref):bool
    {
        return strlen($ref)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$ref)===1;
    }
}
