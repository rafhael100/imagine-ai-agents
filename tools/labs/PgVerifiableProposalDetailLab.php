<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgReadOnlySnapshotLab.php';
require_once __DIR__.'/PgDraftFingerprintLab.php';
require_once __DIR__.'/../../src/Automation/DeterministicAutomationService.php';
require_once __DIR__.'/../../src/Automation/DeterministicRuleEngine.php';

use ImagineAI\Automation\DeterministicRuleEngine;
use PDO;
use Throwable;

/**
 * P63 explanation of one persisted, synthetic P51 proposal. The owning P62
 * bridge validates its reader profile, authors and reviews in the SAME tx.
 * This reader never opens/commits a tx, repairs inputs or grants authority.
 */
final class PgVerifiableProposalDetailLab
{
    private const JSON_FLAGS=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES
        |JSON_UNESCAPED_UNICODE;
    private const MAX_INPUT_BYTES=8192;

    public function __construct(private PDO $db){}

    public function readInsideReadOnlySnapshot(string $tenant,string $proposal):array
    {
        if(getenv('GITHUB_ACTIONS')!=='true'
            ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            ||getenv('AIA_P63_PROPOSAL_DETAIL_LAB')!=='true'
            ||!PgReadOnlySnapshotLab::active($this->db)
            ||preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$tenant)!==1
            ||preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',$proposal)!==1){
            return self::deny();
        }
        try{
            $scope=$this->db->query(
                'SELECT current_user,session_user,current_database()'
            )->fetch(PDO::FETCH_NUM);
            $scopes=[
                'aia_p55_ci_read_a'=>'SYNTHETIC_TENANT_A',
                'aia_p55_ci_read_b'=>'SYNTHETIC_TENANT_B',
            ];
            if(!is_array($scope)||!isset($scopes[$scope[0]])
                ||$scope[0]!==$scope[1]||$scope[2]!=='imagine_agents_ci'
                ||$scopes[$scope[0]]!==$tenant)return self::deny();

            $q=$this->db->prepare(<<<'SQL'
/* P63_DETAIL_INPUTS: within the caller's validated read-only snapshot. */
SELECT b.request_ref,b.ruleset_ref,b.revision,b.command_sha256,
 b.ruleset_sha256,b.preview_sha256,b.proposal_count,
 b.command_canonical,b.ruleset_canonical,b.inputs_version
FROM aia_p51_lab.preview_batches b
JOIN aia_p51_lab.review_drafts d
 ON d.tenant_ref=b.tenant_ref AND d.request_ref=b.request_ref
WHERE b.tenant_ref=:tenant AND d.proposal_ref=:proposal
SQL);
            $q->execute([':tenant'=>$tenant,':proposal'=>$proposal]);
            $batch=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($batch))return self::deny();
            foreach(['command_sha256','ruleset_sha256','preview_sha256'] as $key){
                if(!is_string($batch[$key])
                    ||preg_match('/\A[a-f0-9]{64}\z/D',$batch[$key])!==1){
                    return self::deny();
                }
            }
            $q=$this->db->prepare(<<<'SQL'
SELECT request_ref,ruleset_ref,revision,proposal_ref,rule_ref,draft_kind,
 metric,observed,threshold,state,draft_sha256
FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND request_ref=:request
ORDER BY proposal_ref LIMIT 9
SQL);
            $q->execute([':tenant'=>$tenant,':request'=>$batch['request_ref']]);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)<1||count($rows)>8
                ||count($rows)!==(int)$batch['proposal_count'])return self::deny();
            $selected=null;
            foreach($rows as &$row){
                foreach(['revision','observed','threshold'] as $key){
                    $row[$key]=(int)$row[$key];
                }
                if($row['request_ref']!==$batch['request_ref']
                    ||$row['ruleset_ref']!==$batch['ruleset_ref']
                    ||$row['revision']!==(int)$batch['revision']
                    ||$row['state']!=='AWAITING_HUMAN_REVIEW_LAB'
                    ||!is_string($row['draft_sha256'])
                    ||!hash_equals(self::fingerprint($tenant,$row),$row['draft_sha256'])){
                    return self::deny();
                }
                if($row['proposal_ref']===$proposal)$selected=$row;
            }
            unset($row);
            if($selected===null)return self::deny();
            $identity=[
                'tenant_ref'=>$tenant,'proposal_ref'=>$proposal,
                'request_ref'=>$batch['request_ref'],
                'ruleset_ref'=>$batch['ruleset_ref'],
                'revision'=>(int)$batch['revision'],
                'draft_sha256'=>$selected['draft_sha256'],
            ];
            if((int)$batch['inputs_version']===0
                &&$batch['command_canonical']===null
                &&$batch['ruleset_canonical']===null){
                return self::out('ORIGINAL_INPUTS_UNAVAILABLE',$identity,null);
            }
            if((int)$batch['inputs_version']!==1
                ||!is_string($batch['command_canonical'])
                ||!is_string($batch['ruleset_canonical']))return self::deny();
            $commandRaw=$batch['command_canonical'];
            $rulesetRaw=$batch['ruleset_canonical'];
            if(strlen('{"command":'.$commandRaw.',"ruleset":'.$rulesetRaw.'}')
                    >self::MAX_INPUT_BYTES
                ||!hash_equals($batch['command_sha256'],hash('sha256',$commandRaw))
                ||!hash_equals($batch['ruleset_sha256'],hash('sha256',$rulesetRaw))){
                return self::deny();
            }
            $command=json_decode($commandRaw,true,12,JSON_THROW_ON_ERROR);
            $ruleset=json_decode($rulesetRaw,true,12,JSON_THROW_ON_ERROR);
            if(!is_array($command)||!is_array($ruleset)
                ||json_encode($command,self::JSON_FLAGS)!==$commandRaw
                ||json_encode($ruleset,self::JSON_FLAGS)!==$rulesetRaw){
                return self::deny();
            }
            $result=(new DeterministicRuleEngine())->evaluate($command,$ruleset);
            if($result['tenant_ref']!==$tenant
                ||$result['request_ref']!==$batch['request_ref']
                ||$result['ruleset_ref']!==$batch['ruleset_ref']
                ||$result['revision']!==(int)$batch['revision']
                ||$result['matched_rules']!==count($rows)
                ||$result['ruleset_sha256']!==$batch['ruleset_sha256']
                ||!hash_equals($batch['preview_sha256'],hash('sha256',
                    json_encode($result,self::JSON_FLAGS))))return self::deny();

            $expected=[];$matched=null;
            foreach($result['proposals'] as $item){
                $row=[
                    'request_ref'=>$result['request_ref'],
                    'ruleset_ref'=>$result['ruleset_ref'],
                    'revision'=>$result['revision'],
                    'proposal_ref'=>$item['proposal_ref'],
                    'rule_ref'=>$item['rule_ref'],'draft_kind'=>$item['draft_kind'],
                    'metric'=>$item['metric'],'observed'=>$item['observed'],
                    'threshold'=>$item['threshold'],
                    'state'=>'AWAITING_HUMAN_REVIEW_LAB',
                ];
                $row['draft_sha256']=self::fingerprint($tenant,$row);
                $expected[]=$row;
                if($item['proposal_ref']===$proposal)$matched=$item;
            }
            usort($expected,static fn($a,$b)=>strcmp($a['proposal_ref'],$b['proposal_ref']));
            if($expected!==$rows||$matched===null)return self::deny();
            $rule=null;
            foreach($ruleset['rules'] as $candidate){
                if($candidate['rule_ref']===$matched['rule_ref'])$rule=$candidate;
            }
            if($rule===null)return self::deny();
            return self::out('VERIFIED',$identity,[
                'inputs'=>[
                    'weekly_visits'=>$command['weekly_visits'],
                    'lead_rate_bp'=>$command['lead_rate_bp'],
                    'client_rate_bp'=>$command['client_rate_bp'],
                ],
                'rule'=>$rule,
                'observed'=>$matched['observed'],
                'evaluated_rules'=>$result['evaluated_rules'],
                'matched_rules'=>$result['matched_rules'],
                'execution'=>'NOT_EXECUTED',
            ]);
        }catch(Throwable){
            return self::deny();
        }
    }

    private static function fingerprint(string $tenant,array $row):string
    {
        return PgDraftFingerprintLab::sha256(
            $tenant,$row['request_ref'],$row['ruleset_ref'],$row['revision'],
            $row['proposal_ref'],$row['rule_ref'],$row['draft_kind'],
            $row['metric'],$row['observed'],$row['threshold'],$row['state']
        );
    }

    private static function out(string $state,?array $identity,?array $explanation):array
    {
        return [
            'schema'=>'AIA_P63_VERIFIABLE_PROPOSAL_DETAIL_V1',
            'decision'=>$state==='DENY'?'DENY':'LAB_PROPOSAL_DETAIL_NO_GO',
            'state'=>$state,'inputs_available'=>$state==='VERIFIED',
            'proposal'=>$identity,'explanation'=>$explanation,
            'can_approve'=>false,'can_execute'=>false,
            'operational_authorization'=>false,'ai_calls'=>0,'service_effects'=>0,
        ];
    }

    private static function deny():array{return self::out('DENY',null,null);}
}
