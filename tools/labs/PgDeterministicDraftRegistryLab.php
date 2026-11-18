<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/PgDraftFingerprintLab.php';
require_once __DIR__.'/PgScopedDraftWriterLab.php';
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
    private const MAX_INPUT_BYTES=8192;
    private DeterministicRuleEngine $engine;

    public function __construct(
        private PDO $db,private ?PgScopedDraftWriterLab $writer=null
    ) {
        // Existing callers keep the original postgres-only P16 guard.
        if($writer===null)new PgSyntheticReplayGate($db);
        else $writer->assertConnection($db);
        $this->engine=new DeterministicRuleEngine();
    }

    public static function forScopedWriter(PDO $db,PgScopedDraftWriterLab $scope):self
    {
        return new self($db,$scope);
    }

    public function createTables():void
    {
        if($this->writer!==null)throw new \RuntimeException('P64_SCHEMA_MUTATION_DENIED');
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
        // P63 is additive. Existing batches stay explicitly legacy; their
        // inputs cannot be reconstructed from a hash or a later replay.
        $this->db->exec(<<<'SQL'
ALTER TABLE aia_p51_lab.preview_batches
 ADD COLUMN IF NOT EXISTS command_canonical TEXT,
 ADD COLUMN IF NOT EXISTS ruleset_canonical TEXT,
 ADD COLUMN IF NOT EXISTS inputs_version SMALLINT NOT NULL DEFAULT 0;
DO $p63$
BEGIN
 IF NOT EXISTS (
  SELECT 1 FROM pg_constraint
  WHERE conrelid='aia_p51_lab.preview_batches'::regclass
    AND conname='p63_canonical_inputs_bound'
 ) THEN
  ALTER TABLE aia_p51_lab.preview_batches
   ADD CONSTRAINT p63_canonical_inputs_bound CHECK (
    (inputs_version=0 AND command_canonical IS NULL AND ruleset_canonical IS NULL)
    OR (inputs_version=1 AND command_canonical IS NOT NULL
        AND ruleset_canonical IS NOT NULL
        AND octet_length(command_canonical)>=2
        AND octet_length(ruleset_canonical)>=2
        AND octet_length(command_canonical)+octet_length(ruleset_canonical)<=8192)
   );
 END IF;
END;
$p63$;
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
 draft_sha256 CHAR(64) NOT NULL CHECK(draft_sha256 ~ '^[a-f0-9]{64}$'),
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
        if($this->db->inTransaction())return self::out('DENY','NESTED_TRANSACTION');
        if($this->writer!==null)return self::out('DENY','SCOPED_WRITER_REQUIRES_COMPOSITION');
        try{
            $this->db->beginTransaction();
            $result=$this->stageInsideTransaction($command,$ruleset);
            if($result['reason']==='CALLER_WRITE_TRANSACTION_REQUIRED'){
                $result=self::out('DENY','INVALID_INPUT_OR_DISPOSABLE_DB_FAILURE');
            }
            if(in_array($result['decision'],[
                'LAB_DRAFTS_STAGED_NO_ACTION','LAB_DRAFTS_ALREADY_STAGED'
            ],true))$this->db->commit();
            else $this->db->rollBack();
            return $result;
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::out('DENY','INVALID_INPUT_OR_DISPOSABLE_DB_FAILURE');
        }
    }

    /** Caller owns the transaction and must roll back every DENY. */
    public function stageInsideTransaction(array $command,array $ruleset):array
    {
        if(!$this->db->inTransaction()){
            return self::out('DENY','CALLER_WRITE_TRANSACTION_REQUIRED');
        }
        try{
            if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
                ||$this->db->query('SHOW transaction_read_only')->fetchColumn()!=='off'){
                return self::out('DENY','CALLER_WRITE_TRANSACTION_REQUIRED');
            }
            if($this->writer!==null)$this->writer->assertWriteTransaction($this->db);
            $result=$this->engine->evaluate($command,$ruleset);
            $tenant=$result['tenant_ref'];
            if($this->writer!==null&&$this->writer->tenant()!==$tenant){
                return self::out('DENY','SCOPED_WRITER_TENANT_DENIED');
            }
            $request=$result['request_ref'];
            $rulesetRef=$result['ruleset_ref'];
            $revision=$result['revision'];
            $count=$result['matched_rules'];
            $commandCanonical=json_encode($command,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $rulesetCanonical=json_encode($ruleset,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(strlen('{"command":'.$commandCanonical.',"ruleset":'.
                $rulesetCanonical.'}')>self::MAX_INPUT_BYTES){
                return self::out('DENY','CANONICAL_INPUT_LIMIT');
            }
            $cmdSha=hash('sha256',$commandCanonical);
            $resultSha=hash('sha256',json_encode($result,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            if($result['status']!=='LAB_PROPOSALS_ONLY'
                ||$result['decision']!=='NO_AI'
                ||$result['operational_authorization']!==false
                ||$count!==count($result['proposals'])
                ||$count>8){
                return self::out('DENY','UNAPPROVED_PREVIEW_RESULT');
            }

            $insert=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p51_lab.preview_batches
 (tenant_ref,request_ref,ruleset_ref,revision,
  command_sha256,ruleset_sha256,preview_sha256,proposal_count,
  command_canonical,ruleset_canonical,inputs_version)
VALUES (:t,:request,:ruleset,:rev,:command,:rs_hash,:out_hash,:count,
        :command_canonical,:ruleset_canonical,1)
ON CONFLICT DO NOTHING RETURNING preview_sha256
SQL);
            $insert->execute([
                ':t'=>$tenant,':request'=>$request,
                ':ruleset'=>$rulesetRef,':rev'=>$revision,
                ':command'=>$cmdSha,':rs_hash'=>$result['ruleset_sha256'],
                ':out_hash'=>$resultSha,':count'=>$count,
                ':command_canonical'=>$commandCanonical,
                ':ruleset_canonical'=>$rulesetCanonical,
            ]);
            if($insert->fetchColumn()!==false){
                $write=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p51_lab.review_drafts
 (tenant_ref,request_ref,ruleset_ref,revision,proposal_ref,
  rule_ref,draft_kind,metric,observed,threshold,draft_sha256,state)
 VALUES (:t,:request,:ruleset,:revision,:proposal,:rule,:kind,:metric,
         :observed,:threshold,:draft_hash,'AWAITING_HUMAN_REVIEW_LAB')
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
                        ':threshold'=>$proposal['threshold'],
                        ':draft_hash'=>PgDraftFingerprintLab::sha256(
                            $tenant,$request,$rulesetRef,$revision,
                            $proposal['proposal_ref'],$proposal['rule_ref'],
                            $proposal['draft_kind'],$proposal['metric'],
                            $proposal['observed'],$proposal['threshold'],
                            'AWAITING_HUMAN_REVIEW_LAB'
                        )
                    ]);
                    if($write->rowCount()!==1){
                        return self::out('DENY','DRAFT_INSERT_INCOMPLETE');
                    }
                }
                return self::out('LAB_DRAFTS_STAGED_NO_ACTION',
                    'PERSISTED_ONLY_AWAITING_HUMAN_REVIEW',$count);
            }

            // Existing batch: exact idempotent replay only. Lock the batch
            // and compare all hashes; never amend in place or resurrect.
            $read=$this->db->prepare(<<<'SQL'
SELECT ruleset_ref,revision,command_sha256,ruleset_sha256,
 preview_sha256,proposal_count,command_canonical,ruleset_canonical,inputs_version
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
                return self::out('DENY','REPLAY_INPUT_CHANGED_OR_TAMPERED');
            }
            $legacy=(int)$r['inputs_version']===0
                &&$r['command_canonical']===null&&$r['ruleset_canonical']===null;
            $captured=(int)$r['inputs_version']===1
                &&is_string($r['command_canonical'])
                &&is_string($r['ruleset_canonical'])
                &&hash_equals($commandCanonical,$r['command_canonical'])
                &&hash_equals($rulesetCanonical,$r['ruleset_canonical']);
            if(!$legacy&&!$captured){
                return self::out('DENY','STORED_CANONICAL_INPUTS_CHANGED');
            }
            $exists=$this->db->prepare(<<<'SQL'
SELECT proposal_ref,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:t AND request_ref=:request ORDER BY proposal_ref FOR SHARE
SQL);
            $exists->execute([':t'=>$tenant,':request'=>$request]);
            $stored=$exists->fetchAll(PDO::FETCH_ASSOC);
            $expected=[];
            foreach($result['proposals'] as $proposal){
                $expected[]=[
                    'proposal_ref'=>$proposal['proposal_ref'],
                    'draft_sha256'=>PgDraftFingerprintLab::sha256(
                        $tenant,$request,$rulesetRef,$revision,
                        $proposal['proposal_ref'],$proposal['rule_ref'],
                        $proposal['draft_kind'],$proposal['metric'],
                        $proposal['observed'],$proposal['threshold'],
                        'AWAITING_HUMAN_REVIEW_LAB'
                    )
                ];
            }
            usort($expected,static fn($a,$b)=>strcmp(
                $a['proposal_ref'],$b['proposal_ref']
            ));
            if($stored!==$expected){
                return self::out('DENY','STORED_DRAFTS_INCOMPLETE');
            }
            return self::out('LAB_DRAFTS_ALREADY_STAGED',
                'EXACT_REPLAY_NO_NEW_EFFECTS',$count);
        }catch(Throwable){
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
