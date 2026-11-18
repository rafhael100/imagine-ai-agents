<?php
declare(strict_types=1);

/**
 * AIA-P50: safe offline CLI preview of P48+P49 deterministic automation.
 *
 * This is intentionally NOT an HTTP endpoint, queue worker, CRM gateway,
 * IAM, S2S authorization, workflow deploy or n8n connector. No provider,
 * filesystem storage, network, persistence, side effects or paid API.
 */
require_once __DIR__.'/../src/Automation/DeterministicAutomationService.php';
require_once __DIR__.'/../src/Automation/DeterministicRuleEngine.php';

use ImagineAI\Automation\DeterministicRuleEngine;

function p50Deny():never {
    $out=[
        'schema'=>'AIA_RULE_PREVIEW_CLI_V1',
        'decision'=>'DENY',
        'reason'=>'PREVIEW_INPUT_DENIED',
        'operational_authorization'=>false,
        'ai_api_calls'=>0,
        'crm_tasks_created'=>0,
        'messages_sent'=>0
    ];
    fwrite(STDOUT,json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n");
    exit(2);
}

if(PHP_SAPI!=='cli'||!isset($argv)||count($argv)!==2
    ||!in_array($argv[1],['--fixture','--stdin'],true)){
    p50Deny();
}
try {
    if($argv[1]==='--fixture'){
        $input=[
            'command'=>[
                'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
                'operation'=>'funnel.calculate',
                'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
                'request_ref'=>'SYNTHETIC_P50_DEMO',
                'weekly_visits'=>120,
                'lead_rate_bp'=>1000,
                'client_rate_bp'=>2000
            ],
            'ruleset'=>[
                'schema'=>'AIA_RULESET_V1',
                'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
                'ruleset_ref'=>'SYNTHETIC_P50_RULESET',
                'revision'=>1,
                'rules'=>[
                    [
                        'rule_ref'=>'SYNTHETIC_P50_RULE_LEADS',
                        'metric'=>'projected_leads',
                        'operator'=>'gte',
                        'threshold'=>12,
                        'draft_kind'=>'HUMAN_REVIEW_DRAFT'
                    ],
                    [
                        'rule_ref'=>'SYNTHETIC_P50_RULE_CUSTOMERS',
                        'metric'=>'projected_customers',
                        'operator'=>'gte',
                        'threshold'=>2,
                        'draft_kind'=>'CRM_TASK_DRAFT'
                    ]
                ]
            ]
        ];
    }else{
        $raw=stream_get_contents(STDIN,8193);
        if(!is_string($raw)||strlen($raw)<2||strlen($raw)>8192){
            p50Deny();
        }
        // Canonical bytes required: rejects duplicated JSON keys, extra
        // whitespace, noncanonical numbers and ambiguous representations.
        $raw=rtrim($raw,"\r\n");
        $input=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
        if(!is_array($input)
            ||array_keys($input)!==['command','ruleset']
            ||!is_array($input['command'])
            ||!is_array($input['ruleset'])
            ||!hash_equals(
                $raw,
                json_encode($input,JSON_THROW_ON_ERROR
                    |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            )){
            p50Deny();
        }
    }

    $result=(new DeterministicRuleEngine())->evaluate(
        $input['command'],$input['ruleset']
    );
    $response=[
        'schema'=>'AIA_RULE_PREVIEW_CLI_V1',
        'decision'=>'NO_GO',
        'source'=>'SYNTHETIC_OFFLINE_ONLY',
        'preview'=>$result,
        'operational_authorization'=>false,
        'ai_api_calls'=>0,
        'crm_tasks_created'=>0,
        'messages_sent'=>0
    ];
    fwrite(STDOUT,json_encode(
        $response,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
            |JSON_UNESCAPED_UNICODE)."\n");
}catch(Throwable){
    p50Deny();
}
