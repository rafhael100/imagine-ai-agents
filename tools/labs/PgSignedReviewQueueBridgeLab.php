<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgScopedReviewQueueBridgeLab.php';
require_once __DIR__.'/PgSignedDraftReviewLab.php';
require_once __DIR__.'/PgHistoricalSignedReviewAuditLab.php';
require_once __DIR__.'/PgVerifiableProposalDetailLab.php';
require_once __DIR__.'/../../src/Automation/SignedReviewQueueReadModel.php';

use ImagineAI\Automation\SignedReviewQueueReadModel;
use PDO;
use Throwable;

/**
 * P62: one tenant-scoped PG snapshot for P55 queue, P59 current reviews,
 * P61 historical proof and the unchanged P60 presentation contract.
 *
 * A past signed observation is never substituted for a current P59 status.
 * This laboratory view grants no approval, execution, IAM or service access.
 */
final class PgSignedReviewQueueBridgeLab
{
    private const TABLES=[
        'aia_p51_lab.preview_batches','aia_p51_lab.review_drafts',
        'aia_p57_lab.reviewers','aia_p57_lab.signed_observations',
        'aia_p59_lab.draft_authors',
    ];

    public function __construct(private PDO $db){}

    /** @return array<string,mixed> */
    public function read(string $tenant,?string $proposal=null):array
    {
        if(getenv('AIA_P62_SIGNED_REVIEW_LAB')!=='true'
            ||getenv('GITHUB_ACTIONS')!=='true'
            ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            ||$this->db->inTransaction()){
            return self::deny();
        }
        if($proposal!==null&&(getenv('AIA_P63_PROPOSAL_DETAIL_LAB')!=='true'
            ||preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',$proposal)!==1)){
            return self::deny();
        }
        try{
            $this->db->beginTransaction();
            $this->db->exec(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
            );
            $this->db->exec("SET LOCAL statement_timeout='2000ms'");
            $this->db->exec("SET LOCAL lock_timeout='750ms'");
            // LOCK is deliberately before the first SELECT/snapshot. A
            // concurrent ALTER/DROP POLICY must finish before access is
            // validated; its DDL cannot pass these locks until we commit.
            $this->db->exec('LOCK TABLE '.implode(',',self::TABLES).
                ' IN ACCESS SHARE MODE');
            if(!$this->accessProfile($tenant)){
                $this->db->rollBack();
                return self::deny();
            }
            // An observation time, never a caller-supplied authority clock.
            $observed=(int)$this->db->query(
                'SELECT floor(extract(epoch FROM clock_timestamp()))::bigint'
            )->fetchColumn();
            $queue=(new PgScopedReviewQueueBridgeLab($this->db))
                ->readInsideReadOnlySnapshot($tenant);
            if(($queue['decision']??null)!=='LAB_READONLY_PG_REVIEW_NO_GO'){
                $this->db->rollBack();
                return self::deny();
            }
            $historical=(new PgHistoricalSignedReviewAuditLab($this->db))
                ->scanTenantInsideReadOnlySnapshot($tenant);
            if($historical['decision']!==
                    'LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO'
                ||$historical['issues']!==[]
                ||$historical['checked_drafts']!==$queue['checked_drafts']){
                $this->db->rollBack();
                return self::deny();
            }
            $reviews=new PgSignedDraftReviewLab($this->db);
            $statuses=[];
            $available=true;
            foreach($queue['presentation']['items'] as $item){
                $id=$item['proposal_ref'];
                $status=$reviews->statusInsideReadOnlySnapshot(
                    $tenant,$id,$observed
                );
                if(($status['decision']??null)==='DENY'){
                    if(($status['reason']??null)!==
                        'HISTORICAL_REVIEW_TAMPERED_EXPIRED_OR_REVOKED'){
                        $this->db->rollBack();
                        return self::deny();
                    }
                    $available=false;
                }
                $statuses[$id]=$status;
            }
            $presentation=null;
            if($available){
                // P60 still requires the exact complete proposal set, closed
                // types, signed-review states, counts and all NO_GO flags.
                $presentation=(new SignedReviewQueueReadModel())->present(
                    $queue['presentation'],$statuses,$tenant
                );
                if(array_sum(array_column(
                    $presentation['items'],'signed_observations'
                ))!==$historical['checked_signed_observations']){
                    $this->db->rollBack();
                    return self::deny();
                }
            }
            $detail=null;
            if($proposal!==null){
                $detail=(new PgVerifiableProposalDetailLab($this->db))
                    ->readInsideReadOnlySnapshot($tenant,$proposal);
                if($detail['decision']!=='LAB_PROPOSAL_DETAIL_NO_GO'
                    ||!array_key_exists($proposal,$statuses)){
                    $this->db->rollBack();
                    return self::deny();
                }
                // P61/P59 already verified this exact proposal's sealed
                // author in this snapshot. Do not expose identity or keys.
                $detail['author_provenance_verified']=true;
            }
            $this->db->commit();
            $result=[
                'schema'=>'AIA_P62_SIGNED_REVIEW_SNAPSHOT_V1',
                'decision'=>'LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO',
                'tenant_ref'=>$tenant,
                'snapshot_observed_at'=>$observed,
                'snapshot_isolation'=>'REPEATABLE_READ_READ_ONLY',
                'queue'=>$queue['presentation'],
                'current_review_available'=>$available,
                'signed_review'=>$presentation,
                'historical'=>[
                    'decision'=>$historical['decision'],
                    'checked_drafts'=>$historical['checked_drafts'],
                    'checked_signed_observations'=>
                        $historical['checked_signed_observations'],
                    'current_authority_granted'=>false,
                ],
                'can_approve'=>false,
                'can_execute'=>false,
                'operational_authorization'=>false,
                'ai_api_calls'=>0,
                'service_effects'=>0,
            ];
            // Original P62 callers retain their exact closed return shape.
            if($proposal!==null)$result['detail']=$detail;
            return $result;
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny();
        }
    }

    /**
     * P62's closed disposable reader profile. A missing/restrictive RLS
     * policy hides rows without a SQL error; that must never become "zero
     * observations". Validate the exact policy before interpreting absence.
     */
    private function accessProfile(string $tenant):bool
    {
        $context=$this->db->query(<<<'SQL'
SELECT current_user,session_user,r.rolsuper,r.rolbypassrls,
 r.rolcreaterole,r.rolcreatedb,r.rolinherit,
 EXISTS(SELECT 1 FROM pg_auth_members m WHERE m.member=r.oid)
FROM pg_roles r WHERE r.rolname=current_user
SQL)->fetch(PDO::FETCH_NUM);
        $scopes=[
            'aia_p55_ci_read_a'=>'SYNTHETIC_TENANT_A',
            'aia_p55_ci_read_b'=>'SYNTHETIC_TENANT_B',
        ];
        if(!is_array($context)||!isset($scopes[$context[0]])
            ||$context[0]!==$context[1]||$scopes[$context[0]]!==$tenant
            ||array_slice($context,2)!==[false,false,false,false,false,false]){
            return false;
        }
        $suffix=$context[0]==='aia_p55_ci_read_a'?'a':'b';
        foreach(self::TABLES as $table){
            $query=$this->db->prepare(<<<'SQL'
SELECT c.relrowsecurity,c.relforcerowsecurity,
 c.relowner<>(SELECT oid FROM pg_roles WHERE rolname=current_user),
 row_security_active(c.oid),
 has_table_privilege(current_user,c.oid,'SELECT'),
 has_table_privilege(current_user,c.oid,
   'INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER'),
 has_any_column_privilege(current_user,c.oid,'INSERT,UPDATE,REFERENCES')
FROM pg_class c WHERE c.oid=CAST(? AS regclass)
SQL);
            $query->execute([$table]);
            if($query->fetch(PDO::FETCH_NUM)!==
                [true,true,true,true,true,false,false])return false;
            $policy=$this->db->prepare(<<<'SQL'
SELECT p.polname,p.polcmd,p.polpermissive,
 cardinality(p.polroles)=1 AND p.polroles[1]=r.oid AS exact_role,
 pg_get_expr(p.polqual,p.polrelid) AS expression,
 p.polwithcheck IS NULL AS no_write_check
FROM pg_policy p
JOIN pg_roles r ON r.rolname=current_user
WHERE p.polrelid=CAST(? AS regclass)
 AND (0=ANY(p.polroles) OR r.oid=ANY(p.polroles))
ORDER BY p.polname
SQL);
            $policy->execute([$table]);
            $policies=$policy->fetchAll(PDO::FETCH_NUM);
            if(count($policies)!==1)return false;
            $p=$policies[0];
            if($p[0]!=='p62_read_'.$suffix||$p[1]!=='r'||$p[2]!==true
                ||$p[3]!==true||!is_string($p[4])||$p[5]!==true){
                return false;
            }
            // Exact PG16 deparser form for this closed VARCHAR profile.
            // Do not strip characters inside a SQL string literal: a tenant
            // containing spaces/parentheses would otherwise look authorized
            // while its policy silently hides the real observations.
            if($p[4]!=="((tenant_ref)::text = '{$tenant}'::text)")return false;
        }
        return true;
    }

    private static function deny():array
    {
        return [
            'schema'=>'AIA_P62_SIGNED_REVIEW_SNAPSHOT_V1',
            'decision'=>'DENY',
            'queue'=>null,
            'current_review_available'=>false,
            'signed_review'=>null,
            'historical'=>null,
            'can_approve'=>false,
            'can_execute'=>false,
            'operational_authorization'=>false,
            'ai_api_calls'=>0,
            'service_effects'=>0,
        ];
    }
}
