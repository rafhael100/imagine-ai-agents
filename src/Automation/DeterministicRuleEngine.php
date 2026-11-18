<?php
declare(strict_types=1);

namespace ImagineAI\Automation;

use InvalidArgumentException;

/**
 * AIA-P49: bounded, pure-PHP automation rule evaluator.
 *
 * Produces proposal drafts only, without sending a message, writing to CRM,
 * delivering to n8n, charging credits or granting operational permissions.
 * The input command must use the closed P48 synthetic contract.
 */
final class DeterministicRuleEngine
{
    private const RULESET_KEYS=[
        'schema','tenant_ref','ruleset_ref','revision','rules'
    ];
    private const RULE_KEYS=[
        'rule_ref','metric','operator','threshold','draft_kind'
    ];
    private const ALLOWED_METRICS=[
        'visits','projected_leads','projected_customers'
    ];
    private const ALLOWED_OPERATORS=['gte','lte','eq'];
    private const ALLOWED_DRAFTS=[
        'HUMAN_REVIEW_DRAFT','CRM_TASK_DRAFT','OPERATOR_NOTICE_DRAFT'
    ];
    private const MAX_RULES=8;

    private DeterministicAutomationService $calculator;

    public function __construct()
    {
        $this->calculator=new DeterministicAutomationService();
    }

    /**
     * @param array<string,mixed> $command P48 closed command
     * @param array<string,mixed> $ruleset P49 closed ruleset
     * @return array<string,mixed> Synthetic preview, never an executable plan.
     */
    public function evaluate(array $command,array $ruleset):array
    {
        $this->validateRuleset($ruleset);
        $calculation=$this->calculator->calculate($command);
        if(!hash_equals($ruleset['tenant_ref'],$calculation['tenant_ref'])){
            throw new InvalidArgumentException('RULESET_TENANT_SCOPE_MISMATCH');
        }

        $rulesetBytes=json_encode($ruleset,
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $digest=hash('sha256',$rulesetBytes);
        $matched=[];
        foreach($ruleset['rules'] as $rule){
            $value=$calculation[$rule['metric']];
            $threshold=$rule['threshold'];
            $isMatch=match($rule['operator']){
                'gte'=>$value>=$threshold,
                'lte'=>$value<=$threshold,
                'eq'=>$value===$threshold,
            };
            if(!$isMatch)continue;
            $fingerprint=hash('sha256',json_encode([
                $calculation['tenant_ref'],$calculation['request_ref'],
                $ruleset['ruleset_ref'],$ruleset['revision'],
                $digest,$rule['rule_ref'],$rule['metric'],
                $rule['operator'],$threshold,$value,$rule['draft_kind']
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
            $matched[]=[
                'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.strtoupper(substr($fingerprint,0,24)),
                'rule_ref'=>$rule['rule_ref'],
                'metric'=>$rule['metric'],
                'observed'=>$value,
                'operator'=>$rule['operator'],
                'threshold'=>$threshold,
                'draft_kind'=>$rule['draft_kind'],
                'execution'=>'NOT_EXECUTED',
                'human_approval_required'=>true,
            ];
        }

        return [
            'schema'=>'AIA_DETERMINISTIC_RULE_RESULT_V1',
            'tenant_ref'=>$calculation['tenant_ref'],
            'request_ref'=>$calculation['request_ref'],
            'ruleset_ref'=>$ruleset['ruleset_ref'],
            'revision'=>$ruleset['revision'],
            'ruleset_sha256'=>$digest,
            'evaluated_rules'=>count($ruleset['rules']),
            'matched_rules'=>count($matched),
            'proposals'=>$matched,
            'decision'=>'NO_AI',
            'status'=>'LAB_PROPOSALS_ONLY',
            'human_approval_required'=>true,
            'operational_authorization'=>false,
            'crm_tasks_created'=>0,
            'operator_messages_sent'=>0,
            'ai_api_calls'=>0,
            'ai_tokens'=>0,
            'commercial_credits_debited'=>0,
        ];
    }

    private function validateRuleset(array $ruleset):void
    {
        if(array_keys($ruleset)!==self::RULESET_KEYS
            ||$ruleset['schema']!=='AIA_RULESET_V1'
            ||!is_string($ruleset['tenant_ref'])
            ||!self::syntheticRef($ruleset['tenant_ref'])
            ||!is_string($ruleset['ruleset_ref'])
            ||!self::syntheticRef($ruleset['ruleset_ref'])
            ||!is_int($ruleset['revision'])
            ||$ruleset['revision']<1
            ||$ruleset['revision']>1_000_000
            ||!is_array($ruleset['rules'])
            ||!array_is_list($ruleset['rules'])
            ||count($ruleset['rules'])<1
            ||count($ruleset['rules'])>self::MAX_RULES){
            throw new InvalidArgumentException('RULESET_CLOSED_SCHEMA_DENIED');
        }

        $refs=[];
        foreach($ruleset['rules'] as $rule){
            if(!is_array($rule)
                ||array_keys($rule)!==self::RULE_KEYS
                ||!is_string($rule['rule_ref'])
                ||!self::syntheticRef($rule['rule_ref'])
                ||!is_string($rule['metric'])
                ||!in_array($rule['metric'],self::ALLOWED_METRICS,true)
                ||!is_string($rule['operator'])
                ||!in_array($rule['operator'],self::ALLOWED_OPERATORS,true)
                ||!is_int($rule['threshold'])
                ||$rule['threshold']<0
                ||$rule['threshold']>DeterministicFunnelProjector::MAX_WEEKLY_VISITS
                ||!is_string($rule['draft_kind'])
                ||!in_array($rule['draft_kind'],self::ALLOWED_DRAFTS,true)){
                throw new InvalidArgumentException('RULE_CLOSED_SCHEMA_DENIED');
            }
            if(isset($refs[$rule['rule_ref']])){
                throw new InvalidArgumentException('RULE_DUPLICATE_REFERENCE_DENIED');
            }
            $refs[$rule['rule_ref']]=true;
        }
    }

    private static function syntheticRef(string $s):bool
    {
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
}
