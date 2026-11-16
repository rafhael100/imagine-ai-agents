<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/../../src/Automation/DeterministicRuleEngine.php';

use ImagineAI\Automation\DeterministicRuleEngine;
use PDO;
use Throwable;

/**
 * P51: durable, tenant-scoped HUMAN REVIEW DRAFTS in disposable PG16 CI.
 *
 * The true proposal output is calculated inside this class from P48/P49
 * closed inputs. A caller cannot inject pre-approved or executable actions.
 * No CRM, n8n, AI, HTTP, billing or human approval operations exist here.
 */
final class PgDeterministicDraftRegistryLab
{
    private DeterministicRuleEngine $engine;

    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
        $this->engine=new DeterministicRuleEngine();
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p51_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p51_lab.preview_batches(
 tenant_ref VARCHAR(64) NOT NULL
   CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 request_ref VARCHAR(64) NOT NULL
   CHECK (request_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 ruleset_ref VARCHAR(64) NOT NULL
   CHECK (ruleset_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 revision INTEGER NOT NULL CHECK (revision BETWEEN 1 AND 1000000),
 command_sha256 CHAR(64) NOT NULL CHECK(command_sha256 ~ '^[a-f0-9]{64}$'),
 ruleset_sha256 CHAR(64) NOT NULL CHECK(ruleset_sha256 ~ '^[a-f0-9]{64}$'),
 preview_sha256 CHAR(64) NOT NULL CHECK(preview_sha256 ~ '^[a-f0-9]{64}$'),
 proposal_count SMALLINT NOT NULL CHECK(proposal_count BETWEEN 0 AND 8),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY(tenant_ref,request_ref),
 UNIQUE(tenant_ref,request_ref,ruleset_ref,revision)
)
SQL);
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p51_lab.review_drafts(
 tenant_ref VARCHAR(64) NOT NULL,
 request_ref VARCHAR(64) NOT NULL,
 ruleset_ref VARCHAR(64) NOT NULL,
 revision INTEGER NOT NULL,
 proposal_ref VARCHAR(64) NOT NULL
   CHECK (proposal_ref ~ '^SYNTHETIC_PROPOSAL_[A-F0-9]{24}$'),
 rule_ref VARCHAR(64) NOT NULL
   CHECK(rule_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 draft_kind VARCHAR(30) NOT NULL CHECK(
   draft_kind IN ('HUMAN_REVIEW_DRAFT','CRM_TASK_DRAFT','OPERATOR_NOTICE_DRAFT')
 ),
 metric VARCHAR(32) NOT NULL CHECK(
   metric IN ('visits','projected_leads','projected_customers')
 ),
 observed BIGINT NOT NULL CHECK(observed BETWEEN 0 AND 100000000),
 threshold BIGINT NOT NULL CHECK(threshold BETWEEN 0 AND 100000000),
 state VARCHAR(40) NOT NULL DEFAULT 'AWAITING_HUMAN_REVIEW_LAB'
   CHECK(state='AWAITING_HUMAN_REVIEW_LAB'),
 PRIMARY KEY(tenant_ref,proposal_ref),
 FOREIGN KEY(tenant_ref,request_ref)
   REFERENCES aia_p51_lab.preview_batches(tenant_ref,request_ref)
)
SQL);
    }

    /**
     * Save only previously unauthorised P49 drafts, or replay exact same
     * command idempotently. A differing command under same tenant/request
     * must be DENIED, never overwritten or split into partial records.
     *
     * @return array{decision:string,reason:string,proposal_count:int,ai_calls:int,service_effects:int}
     */
    public function stage(array $command,array $ruleset):array
    {
        if($this->db->inTransaction()){
            return self::out('DENY','NESTED_TRANSACTION');
        }
        try{
            $result=$this->engine->evaluate($command,$ruleset);
            $tenant=$result['tenant_ref'];
            $request=$result['request_ref'];
            $rulesetRef=$result['ruleset_ref'];
            $revision=$result['revision'];
            $count=$result['matched_rules'];
            $cmdSha=hash('sha256',json_encode($command,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            $resultSha=hash('sha256',json_encode($result,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            if($result['status']!=='LAB_PROPOSALS_ONLY'
                ||$result['decision']!=='NO_AI'
                ||$result['operational_authorization']!==false
                ||$count!==count($result['proposals'])
                ||$count>8){
                return self::out('DENY','UNAPPROVED_PREVIEW_RESULT');
            }

            $this->db->beginTransaction();
            $insert=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p51_lab.preview_batches
 (tenant_ref,request_ref,ruleset_ref,revision,
  command_sha256,ruleset_sha256,preview_sha256,proposal_count)
VALUES (:t,:request,:ruleset,:rev,:command,:rs_hash,:out_hash,:count)
ON CONFLICT DO NOTHING RETURNING preview_sha256
SQL);
            $insert->execute([
                ':t'=>$tenant,':request'=>$request,
                ':ruleset'=>$rulesetRef,':rev'=>$revision,
                ':command'=>$cmdSha,':rs_hash'=>$result['ruleset_sha256'],
                ':out_hash'=>$resultSha,':count'=>$count
            ]);
            if($insert->fetchColumn()!==false){
                $write=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p51_lab.review_drafts
 (tenant_ref,request_ref,ruleset_ref,revision,proposal_ref,
  rule_ref,draft_kind,metric,observed,threshold,state)
 VALUES (:t,:request,:ruleset,:revision,:proposal,:rule,:kind,:metric,
         :observed,:threshold,'AWAITING_HUMAN_REVIEW_LAB')
SQL);
                foreach($result['proposals'] as $proposal){
                    $write->execute([
                        ':t'=>$tenant,':request'=>$request,
                        ':ruleset'=>$rulesetRef,':revision'=>$revision,
                        ':proposal'=>$proposal['proposal_ref'],
                        ':rule'=>$proposal['rule_ref'],
                        ':kind'=>$proposal['draft_kind'],
                        ':metric'=>$proposal['metric'],
                        ':observed'=>$proposal['observed'],
                        ':threshold'=>$proposal['threshold']
                    ]);
                    if($write->rowCount()!==1){
                        $this->db->rollBack();
                        return self::out('DENY','DRAFT_INSERT_INCOMPLETE');
                    }
                }
                $this->db->commit();
                return self::out('LAB_DRAFTS_STAGED_NO_ACTION',
                    'PERSISTED_ONLY_AWAITING_HUMAN_REVIEW',$count);
            }

            // Existing batch: exact idempotent replay only. Lock the batch
            // and compare all hashes; never amend in place or resurrect.
            $read=$this->db->prepare(<<<'SQL'
SELECT ruleset_ref,revision,command_sha256,ruleset_sha256,
 preview_sha256,proposal_count
FROM aia_p51_lab.preview_batches
WHERE tenant_ref=:t AND request_ref=:request FOR UPDATE
SQL);
            $read->execute([':t'=>$tenant,':request'=>$request]);
            $r=$read->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)
                ||$r['ruleset_ref']!==$rulesetRef
                ||(int)$r['revision']!==$revision
                ||!hash_equals($r['command_sha256'],$cmdSha)
                ||!hash_equals($r['ruleset_sha256'],$result['ruleset_sha256'])
                ||!hash_equals($r['preview_sha256'],$resultSha)
                ||(int)$r['proposal_count']!==$count){
                $this->db->rollBack();
                return self::out('DENY','REPLAY_INPUT_CHANGED_OR_TAMPERED');
            }
            $exists=$this->db->prepare(<<<'SQL'
SELECT proposal_ref FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:t AND request_ref=:request ORDER BY proposal_ref FOR SHARE
SQL);
            $exists->execute([':t'=>$tenant,':request'=>$request]);
            $stored=$exists->fetchAll(PDO::FETCH_COLUMN);
            $expected=array_column($result['proposals'],'proposal_ref');
            sort($expected,SORT_STRING);
            if($stored!==$expected){
                $this->db->rollBack();
                return self::out('DENY','STORED_DRAFTS_INCOMPLETE');
            }
            $this->db->commit();
            return self::out('LAB_DRAFTS_ALREADY_STAGED',
                'EXACT_REPLAY_NO_NEW_EFFECTS',$count);
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::out('DENY','INVALID_INPUT_OR_DISPOSABLE_DB_FAILURE');
        }
    }

    /** @return array<string,mixed> */
    public function readPending(string $tenant):array
    {
        if(!self::ref($tenant)||$this->db->inTransaction()){
            return ['decision'=>'DENY','drafts'=>[],'ai_calls'=>0,
                'service_effects'=>0];
        }
        try{
            $q=$this->db->prepare(<<<'SQL'
SELECT request_ref,proposal_ref,rule_ref,draft_kind,metric,
       observed,threshold,state
FROM aia_p51_lab.review_drafts WHERE tenant_ref=:tenant
ORDER BY request_ref,proposal_ref LIMIT 129
SQL);
            $q->execute([':tenant'=>$tenant]);
            $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>128){
                return ['decision'=>'DENY','drafts'=>[],
                    'ai_calls'=>0,'service_effects'=>0];
            }
            return [
                'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
                'tenant_ref'=>$tenant,
                'drafts'=>$rows,
                'ai_calls'=>0,
                'service_effects'=>0
            ];
        }catch(Throwable){
            return ['decision'=>'DENY','drafts'=>[],'ai_calls'=>0,
                'service_effects'=>0];
        }
    }

    private static function ref(string $s):bool
    {
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }

    /** @return array{decision:string,reason:string,proposal_count:int,ai_calls:int,service_effects:int} */
    private static function out(string $decision,string $reason,int $count=0):array
    {
        return [
            'decision'=>$decision,'reason'=>$reason,
            'proposal_count'=>$count,'ai_calls'=>0,'service_effects'=>0
        ];
    }
}
