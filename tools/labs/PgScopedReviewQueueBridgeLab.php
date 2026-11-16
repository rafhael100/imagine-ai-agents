<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgDraftFingerprintLab.php';
require_once __DIR__.'/../../src/Automation/ReviewQueueReadModel.php';

use ImagineAI\Automation\ReviewQueueReadModel;
use PDO;
use Throwable;

/**
 * P55: CI-only real PostgreSQL16 -> P53 read model bridge.
 *
 * One REPEATABLE READ / READ ONLY transaction covers integrity and the
 * read-model snapshot. Role and tenant are bound to two disposable PG roles
 * and enforced AGAIN by PostgreSQL RLS policy in the P55 CI fixture.
 *
 * Not a public API; no owner IAM, human approvals, CRM, n8n or AI effects.
 */
final class PgScopedReviewQueueBridgeLab
{
    private const MAX_BATCHES=128;
    private const MAX_DRAFTS=128;
    private const SCOPES=[
        'aia_p55_ci_read_a'=>'SYNTHETIC_TENANT_A',
        'aia_p55_ci_read_b'=>'SYNTHETIC_TENANT_B',
    ];

    public function __construct(private PDO $db){}

    /** @return array<string,mixed> */
    public function read(string $tenant):array
    {
        if(!self::ref($tenant)
            ||$this->db->inTransaction()
            ||getenv('GITHUB_ACTIONS')!=='true'
            ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            ||getenv('AIA_P55_SCOPED_LAB')!=='true'
            ||getenv('DB_HOST')!=='127.0.0.1'
            ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
            return self::deny('CI_SCOPED_SNAPSHOT_REQUIRED');
        }
        try{
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $ctx=$this->db->query('SELECT session_user,current_database()')
                ->fetch(PDO::FETCH_NUM);
            if(!is_array($ctx)
                ||!isset(self::SCOPES[$ctx[0]])
                ||self::SCOPES[$ctx[0]]!==$tenant
                ||$ctx[1]!=='imagine_agents_ci'){
                $this->db->rollBack();
                return self::deny('DATABASE_ROLE_TENANT_SCOPE_DENIED');
            }
            $batches=$this->db->prepare(<<<'SQL'
SELECT request_ref,ruleset_ref,revision,proposal_count,
       command_sha256,ruleset_sha256,preview_sha256
FROM aia_p51_lab.preview_batches
WHERE tenant_ref=:tenant ORDER BY request_ref LIMIT 129
SQL);
            $batches->execute([':tenant'=>$tenant]);
            $batchRows=$batches->fetchAll(PDO::FETCH_ASSOC);
            if(count($batchRows)>self::MAX_BATCHES){
                $this->db->rollBack();
                return self::deny('BATCH_CAPACITY_LIMIT');
            }
            $index=[];
            foreach($batchRows as $batch){
                foreach(['command_sha256','ruleset_sha256','preview_sha256'] as $hash){
                    if(!is_string($batch[$hash])
                        ||preg_match('/\A[a-f0-9]{64}\z/D',$batch[$hash])!==1){
                        $this->db->rollBack();
                        return self::deny('BATCH_METADATA_INVALID');
                    }
                }
                $index[$batch['request_ref']]=[
                    'ruleset'=>$batch['ruleset_ref'],
                    'revision'=>(int)$batch['revision'],
                    'declared'=>(int)$batch['proposal_count'],
                    'count'=>0,
                ];
            }
            $drafts=$this->db->prepare(<<<'SQL'
SELECT request_ref,proposal_ref,rule_ref,draft_kind,metric,
       observed,threshold,state,ruleset_ref,revision,draft_sha256
FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant
ORDER BY request_ref,proposal_ref LIMIT 129
SQL);
            $drafts->execute([':tenant'=>$tenant]);
            $rows=$drafts->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>self::MAX_DRAFTS){
                $this->db->rollBack();
                return self::deny('REVIEW_QUEUE_CAPACITY_LIMIT');
            }

            $items=[];
            foreach($rows as $row){
                $request=$row['request_ref'];
                if(!isset($index[$request])){
                    $this->db->rollBack();
                    return self::deny('ORPHANED_REVIEW_DRAFT');
                }
                $batch=&$index[$request];
                $revision=(int)$row['revision'];
                if($batch['ruleset']!==$row['ruleset_ref']
                    ||$batch['revision']!==$revision
                    ||$row['state']!=='AWAITING_HUMAN_REVIEW_LAB'){
                    $this->db->rollBack();
                    return self::deny('REVIEW_DRAFT_SCOPE_OR_STATE_CHANGED');
                }
                $expected=PgDraftFingerprintLab::sha256(
                    $tenant,$request,$row['ruleset_ref'],$revision,
                    $row['proposal_ref'],$row['rule_ref'],$row['draft_kind'],
                    $row['metric'],(int)$row['observed'],
                    (int)$row['threshold'],$row['state']
                );
                if(!is_string($row['draft_sha256'])
                    ||!hash_equals($expected,$row['draft_sha256'])){
                    $this->db->rollBack();
                    return self::deny('REVIEW_DRAFT_HASH_INCONSISTENT');
                }
                ++$batch['count'];
                unset($batch);
                $items[]=[
                    'request_ref'=>$request,
                    'proposal_ref'=>$row['proposal_ref'],
                    'rule_ref'=>$row['rule_ref'],
                    'draft_kind'=>$row['draft_kind'],
                    'metric'=>$row['metric'],
                    'observed'=>$row['observed'],
                    'threshold'=>$row['threshold'],
                    'state'=>$row['state'],
                ];
            }
            foreach($index as $batch){
                if($batch['declared']!==$batch['count']){
                    $this->db->rollBack();
                    return self::deny('REVIEW_DRAFT_COUNT_INCONSISTENT');
                }
            }
            $presentation=(new ReviewQueueReadModel())->present([
                'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
                'tenant_ref'=>$tenant,
                'drafts'=>$items,
                'ai_calls'=>0,
                'service_effects'=>0,
            ],$tenant);
            $this->db->commit();
            return [
                'schema'=>'AIA_P55_PG_READONLY_REVIEW_V1',
                'decision'=>'LAB_READONLY_PG_REVIEW_NO_GO',
                'tenant_ref'=>$tenant,
                'checked_batches'=>count($index),
                'checked_drafts'=>count($items),
                'presentation'=>$presentation,
                'operational_authorization'=>false,
                'human_approval_granted'=>false,
                'ai_calls'=>0,
                'service_effects'=>0,
            ];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('READONLY_QUERY_OR_INTEGRITY_FAILURE');
        }
    }

    private static function ref(string $s):bool{
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }

    /** @return array<string,mixed> */
    private static function deny(string $reason):array
    {
        return [
            'schema'=>'AIA_P55_PG_READONLY_REVIEW_V1',
            'decision'=>'DENY',
            'reason'=>$reason,
            'presentation'=>null,
            'operational_authorization'=>false,
            'human_approval_granted'=>false,
            'ai_calls'=>0,
            'service_effects'=>0,
        ];
    }
}
