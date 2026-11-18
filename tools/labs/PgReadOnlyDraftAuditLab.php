<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/PgDraftFingerprintLab.php';

use PDO;
use Throwable;

/**
 * P52: consistent, read-only integrity audit of synthetic P51 review drafts.
 * Checks batch cardinality and a deterministic per-row checksum. No
 * corrections, approvals, external effects, customer data or credentials.
 */
final class PgReadOnlyDraftAuditLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    /** @return array<string,mixed> */
    public function scan():array
    {
        if($this->db->inTransaction())return self::out('INDETERMINATE',0,0,[
            ['reason'=>'REQUIRES_INDEPENDENT_SNAPSHOT']
        ]);
        try {
            $this->db->beginTransaction();
            $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $rows=$this->db->query(<<<'SQL'
SELECT b.tenant_ref,b.request_ref,b.ruleset_ref,b.revision,b.proposal_count,
       b.command_sha256,b.ruleset_sha256,b.preview_sha256,
       p.proposal_ref,p.rule_ref,p.draft_kind,p.metric,
       p.observed,p.threshold,p.state,p.draft_sha256
FROM aia_p51_lab.preview_batches b
LEFT JOIN aia_p51_lab.review_drafts p
 ON p.tenant_ref=b.tenant_ref AND p.request_ref=b.request_ref
ORDER BY b.tenant_ref,b.request_ref,p.proposal_ref
LIMIT 5001
SQL)->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>5000){
                $this->db->commit();
                return self::out('INDETERMINATE',0,0,[
                    ['reason'=>'AUDIT_CAPACITY_LIMIT']
                ]);
            }
            $groups=[];
            $issues=[];
            $drafts=0;
            foreach($rows as $r){
                $id=$r['tenant_ref']."\x1f".$r['request_ref'];
                if(!isset($groups[$id])){
                    $groups[$id]=[
                        'tenant_ref'=>$r['tenant_ref'],
                        'request_ref'=>$r['request_ref'],
                        'declared'=>(int)$r['proposal_count'],
                        'seen'=>0,
                        'ruleset_ref'=>$r['ruleset_ref'],
                        'revision'=>(int)$r['revision'],
                    ];
                    foreach(['command_sha256','ruleset_sha256','preview_sha256'] as $hashField){
                        if(!is_string($r[$hashField])
                            ||preg_match('/\A[a-f0-9]{64}\z/D',$r[$hashField])!==1){
                            $issues[]=self::finding($r,'BATCH_HASH_INVALID');
                        }
                    }
                }
                if($r['proposal_ref']===null)continue;
                ++$groups[$id]['seen'];
                ++$drafts;
                if($r['state']!=='AWAITING_HUMAN_REVIEW_LAB'){
                    $issues[]=self::finding($r,'UNEXPECTED_DRAFT_STATE');
                    continue;
                }
                $expected=PgDraftFingerprintLab::sha256(
                    $r['tenant_ref'],$r['request_ref'],$r['ruleset_ref'],
                    (int)$r['revision'],$r['proposal_ref'],$r['rule_ref'],
                    $r['draft_kind'],$r['metric'],(int)$r['observed'],
                    (int)$r['threshold'],$r['state']
                );
                if(!is_string($r['draft_sha256'])
                    ||!hash_equals($expected,$r['draft_sha256'])){
                    $issues[]=self::finding($r,'DRAFT_FINGERPRINT_MISMATCH');
                }
            }
            foreach($groups as $group){
                if($group['declared']!==$group['seen']){
                    $issues[]=[
                        'tenant_ref'=>$group['tenant_ref'],
                        'request_ref'=>$group['request_ref'],
                        'reason'=>'DRAFT_COUNT_MISMATCH',
                    ];
                }
            }
            $this->db->commit();
            return self::out(
                $issues?'DENY_INCONSISTENT':'LAB_READONLY_DRAFTS_INTEGRITY_NO_GO',
                count($groups),$drafts,$issues
            );
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::out('INDETERMINATE',0,0,[
                ['reason'=>'READONLY_SCHEMA_OR_QUERY_UNAVAILABLE']
            ]);
        }
    }

    /** @return array{tenant_ref:string,request_ref:string,reason:string} */
    private static function finding(array $row,string $reason):array
    {
        return [
            'tenant_ref'=>$row['tenant_ref'],
            'request_ref'=>$row['request_ref'],
            'reason'=>$reason
        ];
    }

    /** @return array<string,mixed> */
    private static function out(string $decision,int $batches,int $drafts,
        array $issues):array
    {
        return [
            'decision'=>$decision,
            'checked_batches'=>$batches,
            'checked_drafts'=>$drafts,
            'issues'=>$issues,
            'ai_calls'=>0,
            'external_calls'=>0,
            'repaired_rows'=>0,
            'operational_authorization'=>false,
        ];
    }
}
