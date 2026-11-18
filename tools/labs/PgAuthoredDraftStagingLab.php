<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgScopedDraftWriterLab.php';
require_once __DIR__.'/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/PgDraftAuthorProvenanceLab.php';

use ImagineAI\Automation\DeterministicRuleEngine;
use PDO;
use RuntimeException;
use Throwable;

/** P64: one commit for a deterministic batch, all drafts and their fixture author. */
final class PgAuthoredDraftStagingLab
{
    private const MAX_BATCHES=128;
    private const MAX_DRAFTS=128;

    public function __construct(private PDO $db,private PgScopedDraftWriterLab $scope)
    {
        $scope->assertConnection($db);
    }

    /** The existing P50 fixture rules, presented as a bounded local form. */
    public static function inputs(
        string $tenant,string $request,int $visits,int $leadRateBp,int $clientRateBp
    ):array {
        return [
            'command'=>[
                'schema'=>'AIA_DETERMINISTIC_COMMAND_V1','operation'=>'funnel.calculate',
                'tenant_ref'=>$tenant,'request_ref'=>$request,'weekly_visits'=>$visits,
                'lead_rate_bp'=>$leadRateBp,'client_rate_bp'=>$clientRateBp,
            ],
            'ruleset'=>[
                'schema'=>'AIA_RULESET_V1','tenant_ref'=>$tenant,
                'ruleset_ref'=>'SYNTHETIC_P50_RULESET','revision'=>1,
                'rules'=>[
                    ['rule_ref'=>'SYNTHETIC_P50_RULE_LEADS','metric'=>'projected_leads',
                        'operator'=>'gte','threshold'=>12,'draft_kind'=>'HUMAN_REVIEW_DRAFT'],
                    ['rule_ref'=>'SYNTHETIC_P50_RULE_CUSTOMERS','metric'=>'projected_customers',
                        'operator'=>'gte','threshold'=>2,'draft_kind'=>'CRM_TASK_DRAFT'],
                ],
            ],
        ];
    }

    public function stage(array $command,array $ruleset):array
    {
        // A caller's transaction is never ended, even on invalid input.
        if($this->db->inTransaction())return self::out('DENY','CALLER_TRANSACTION');
        try{
            if(($command['tenant_ref']??null)!==$this->scope->tenant()){
                return self::out('DENY','WRITER_SCOPE');
            }
            $preview=(new DeterministicRuleEngine())->evaluate($command,$ruleset);
            if($preview['matched_rules']<1)return self::out('DENY','NO_MATCHED_DRAFTS');
            $refs=array_column($preview['proposals'],'proposal_ref');
            sort($refs,SORT_STRING);
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');
            $this->db->exec("SET LOCAL lock_timeout='3s'");
            $this->db->exec("SET LOCAL statement_timeout='4s'");
            $this->db->exec("SET LOCAL idle_in_transaction_session_timeout='6s'");
            $this->db->exec('SET LOCAL row_security=on');
            $this->db->exec('SET LOCAL search_path=pg_catalog');
            $this->scope->assertWriteTransaction($this->db);
            $this->scope->serializeTenant($this->db);
            $registry=PgDeterministicDraftRegistryLab::forScopedWriter($this->db,$this->scope);
            $stored=$registry->stageInsideTransaction($command,$ruleset);
            $fresh=$stored['decision']==='LAB_DRAFTS_STAGED_NO_ACTION';
            if(!$fresh&&$stored['decision']!=='LAB_DRAFTS_ALREADY_STAGED'){
                throw new RuntimeException('P64_STAGE_DENIED');
            }
            if($stored['proposal_count']!==count($refs))throw new RuntimeException('P64_COUNT_DENIED');
            $authors=new PgDraftAuthorProvenanceLab($this->db);
            foreach($refs as $proposal){
                if($fresh){
                    if($authors->sealInsideTransaction(
                        $this->scope->tenant(),$proposal,$this->scope->author()
                    )!=='LAB_AUTHOR_SEALED_NO_APPROVAL'){
                        throw new RuntimeException('P64_AUTHOR_DENIED');
                    }
                }elseif(!$authors->verifyExactAuthorInsideTransaction(
                    $this->scope->tenant(),$proposal,$this->scope->author()
                )){
                    // An old partial batch must not be silently repaired.
                    throw new RuntimeException('P64_REPLAY_AUTHOR_DENIED');
                }
            }
            foreach([
                'aia_p51_lab.preview_batches'=>self::MAX_BATCHES,
                'aia_p51_lab.review_drafts'=>self::MAX_DRAFTS,
            ] as $table=>$maximum){
                $q=$this->db->prepare('SELECT count(*) FROM '.$table.' WHERE tenant_ref=?');
                $q->execute([$this->scope->tenant()]);
                if((int)$q->fetchColumn()>$maximum)throw new RuntimeException('P64_CAPACITY_DENIED');
            }
            $this->db->commit();
            return self::out(
                $fresh?'LAB_AUTHORED_DRAFTS_STORED':'LAB_AUTHORED_DRAFTS_REPLAY',
                'NO_OPERATIONAL_AUTHORITY',$this->scope->tenant(),$command['request_ref'],$refs
            );
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::out('DENY','ATOMIC_WRITE_DENIED');
        }
    }

    private static function out(
        string $decision,string $reason,?string $tenant=null,
        ?string $request=null,array $refs=[]
    ):array {
        return [
            'schema'=>'AIA_P64_AUTHORED_DRAFT_BATCH_V1',
            'decision'=>$decision,'reason'=>$reason,'tenant_ref'=>$tenant,
            'request_ref'=>$request,'proposal_count'=>count($refs),'proposal_refs'=>$refs,
            'can_approve'=>false,'can_execute'=>false,'operational_authorization'=>false,
            'ai_api_calls'=>0,'crm_tasks_created'=>0,'operator_messages_sent'=>0,
            'commercial_credits_debited'=>0,'service_effects'=>0,
        ];
    }
}
