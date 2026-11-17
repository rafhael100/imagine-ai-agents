<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgDraftFingerprintLab.php';
require_once __DIR__.'/PgReadOnlySnapshotLab.php';

use PDO;
use Throwable;

/**
 * P61: historical (not current-authority) signed reviewer audit.
 *
 * One READ ONLY REPEATABLE READ PostgreSQL16 snapshot traces P51/P52 drafts
 * -> P59 sealed author -> P57 reviewer key -> Ed25519 signed observations.
 * Revocation/expiry AFTER a valid past vote does not invalidate its historical
 * signature; this audit NEVER reauthorizes revoked keys.
 *
 * No write, repair, n8n message, IAM lookup, approval, API or AI call.
 */
final class PgHistoricalSignedReviewAuditLab
{
    private const FIELDS=[
        'schema','issuer','audience','tenant_ref','proposal_ref',
        'reviewer_ref','role','decision','draft_sha256','key_revision',
        'issued_at','expires_at'
    ];
    private const LIMIT=2000;

    public function __construct(private PDO $db){}

    /** @return array<string,mixed> */
    public function scan():array
    {
        return $this->snapshot(null,false);
    }

    /** P62 tenant-scoped historical scan; does not commit the caller's tx. */
    public function scanTenantInsideReadOnlySnapshot(string $tenant):array
    {
        if(strlen($tenant)>64
            ||preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$tenant)!==1){
            return self::out('INDETERMINATE',0,0,[
                ['reason'=>'LAB_TENANT_SCOPE_REQUIRED']
            ]);
        }
        return $this->snapshot($tenant,true);
    }

    private function snapshot(?string $tenant,bool $inside):array
    {
        if(!self::ciOnly()
            ||($inside?!PgReadOnlySnapshotLab::active($this->db)
                :$this->db->inTransaction())
            ||$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'){
            return self::out('INDETERMINATE',0,0,[
                ['reason'=>'LAB_READONLY_SNAPSHOT_REQUIRED']
            ]);
        }
        try{
            if(!$inside){
                $this->db->beginTransaction();
                $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            if($this->db->query('SELECT current_database()')->fetchColumn()
                !=='imagine_agents_ci'){
                if(!$inside)$this->db->rollBack();
                return self::out('INDETERMINATE',0,0,[
                    ['reason'=>'WRONG_LAB_DATABASE']
                ]);
            }
            $sql=<<<'SQL'
SELECT d.tenant_ref,d.request_ref,d.ruleset_ref,d.revision,
 d.proposal_ref,d.rule_ref,d.draft_kind,d.metric,d.observed,
 d.threshold,d.state,d.draft_sha256,
 a.author_ref,a.draft_sha256 AS sealed_sha,a.source AS author_source,
 v.reviewer_ref,v.reviewer_role,v.decision AS vote_decision,
 v.draft_sha256 AS vote_sha,v.canonical_vote,v.signature_b64,
 k.public_key_b64,k.key_fingerprint,k.reviewer_role AS trusted_role
FROM aia_p51_lab.review_drafts d
LEFT JOIN aia_p59_lab.draft_authors a
 ON a.tenant_ref=d.tenant_ref AND a.proposal_ref=d.proposal_ref
LEFT JOIN aia_p57_lab.signed_observations v
 ON v.tenant_ref=d.tenant_ref AND v.proposal_ref=d.proposal_ref
LEFT JOIN aia_p57_lab.reviewers k
 ON k.tenant_ref=v.tenant_ref AND k.reviewer_ref=v.reviewer_ref
SQL;
            if($tenant!==null)$sql.=' WHERE d.tenant_ref=:tenant';
            $sql.=' ORDER BY d.tenant_ref,d.proposal_ref,v.reviewer_role LIMIT 2001';
            $query=$this->db->prepare($sql);
            $query->execute($tenant===null?[]:[':tenant'=>$tenant]);
            $rows=$query->fetchAll(PDO::FETCH_ASSOC);

            if(count($rows)>self::LIMIT){
                if(!$inside)$this->db->commit();
                return self::out('INDETERMINATE',0,0,[
                    ['reason'=>'REVIEW_AUDIT_CAPACITY_LIMIT']
                ]);
            }
            $issues=[];$seen=[];$votes=0;
            foreach($rows as $row){
                $proposalKey=$row['tenant_ref']."\x1f".$row['proposal_ref'];
                if(!isset($seen[$proposalKey])){
                    $seen[$proposalKey]=[
                        'roles'=>[],
                        'reviewers'=>[],
                        'rejections'=>0,
                    ];
                    $expected=PgDraftFingerprintLab::sha256(
                        $row['tenant_ref'],$row['request_ref'],$row['ruleset_ref'],
                        (int)$row['revision'],$row['proposal_ref'],$row['rule_ref'],
                        $row['draft_kind'],$row['metric'],(int)$row['observed'],
                        (int)$row['threshold'],$row['state']
                    );
                    if($row['state']!=='AWAITING_HUMAN_REVIEW_LAB'
                        ||!is_string($row['draft_sha256'])
                        ||!hash_equals($expected,$row['draft_sha256'])){
                        $issues[]=self::issue($row,'DRAFT_FINGERPRINT_OR_STATE_INVALID');
                    }
                    if(!is_string($row['author_ref'])
                        ||$row['author_source']!=='SYNTHETIC_CI_AUTHOR_FIXTURE'
                        ||!is_string($row['sealed_sha'])
                        ||!hash_equals($expected,$row['sealed_sha'])){
                        $issues[]=self::issue($row,'AUTHOR_PROVENANCE_MISSING_OR_CHANGED');
                    }
                }
                if($row['reviewer_ref']===null)continue;
                ++$votes;
                if(!is_string($row['author_ref'])
                    ||$row['reviewer_ref']===$row['author_ref']){
                    $issues[]=self::issue($row,'SELF_REVIEW_OR_AUTHOR_MISSING');
                }
                if(in_array($row['reviewer_role'],$seen[$proposalKey]['roles'],true)
                    ||in_array($row['reviewer_ref'],$seen[$proposalKey]['reviewers'],true)){
                    $issues[]=self::issue($row,'DUPLICATE_REVIEW_ROLE_OR_IDENTITY');
                }
                $seen[$proposalKey]['roles'][]=$row['reviewer_role'];
                $seen[$proposalKey]['reviewers'][]=$row['reviewer_ref'];
                if($row['vote_decision']==='REJECT'){
                    ++$seen[$proposalKey]['rejections'];
                }
                $reason=self::verifyHistoricalObservation($row);
                if($reason!==null)$issues[]=self::issue($row,$reason);
            }
            foreach($seen as $key=>$group){
                if(count($group['roles'])>2||$group['rejections']>1){
                    [$tenant,$proposal]=explode("\x1f",$key,2);
                    $issues[]=[
                        'tenant_ref'=>$tenant,
                        'proposal_ref'=>$proposal,
                        'reason'=>'REVIEW_ROLE_CAPACITY_OR_REJECTION_CONFLICT'
                    ];
                }
            }
            if(!$inside)$this->db->commit();
            return self::out(
                $issues?'DENY_INCONSISTENT':
                    'LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO',
                count($seen),$votes,$issues
            );
        }catch(Throwable){
            if(!$inside&&$this->db->inTransaction())$this->db->rollBack();
            return self::out('INDETERMINATE',0,0,[
                ['reason'=>'LAB_READONLY_QUERY_OR_SCHEMA_UNAVAILABLE']
            ]);
        }
    }

    private static function verifyHistoricalObservation(array $row):?string
    {
        try{
            if(!is_string($row['canonical_vote'])
                ||strlen($row['canonical_vote'])>1024
                ||!is_string($row['signature_b64'])
                ||!is_string($row['vote_sha'])
                ||!hash_equals($row['vote_sha'],$row['draft_sha256'])){
                return 'SIGNED_REVIEW_EVIDENCE_MISSING_OR_WRONG_DRAFT';
            }
            $v=json_decode($row['canonical_vote'],true,5,JSON_THROW_ON_ERROR);
            if(!is_array($v)||array_keys($v)!==self::FIELDS
                ||!hash_equals($row['canonical_vote'],
                    json_encode($v,JSON_THROW_ON_ERROR
                        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))){
                return 'SIGNED_REVIEW_CANONICAL_SCHEMA_INVALID';
            }
            foreach(['schema','issuer','audience','tenant_ref','proposal_ref',
                'reviewer_ref','role','decision','draft_sha256'] as $field){
                if(!is_string($v[$field]))return 'SIGNED_REVIEW_CANONICAL_SCHEMA_INVALID';
            }
            foreach(['key_revision','issued_at','expires_at'] as $field){
                if(!is_int($v[$field]))return 'SIGNED_REVIEW_CANONICAL_SCHEMA_INVALID';
            }
            if($v['schema']!=='AIA_SIGNED_DRAFT_REVIEW_V1'
                ||$v['issuer']!=='SYNTHETIC_INDEPENDENT_REVIEWER'
                ||$v['audience']!=='imagine-ai-agents'
                ||$v['tenant_ref']!==$row['tenant_ref']
                ||$v['proposal_ref']!==$row['proposal_ref']
                ||$v['reviewer_ref']!==$row['reviewer_ref']
                ||$v['role']!==$row['reviewer_role']
                ||$v['role']!==$row['trusted_role']
                ||!in_array($v['role'],['PRODUCT','SECURITY'],true)
                ||$v['decision']!==$row['vote_decision']
                ||!in_array($v['decision'],['ACKNOWLEDGE','REJECT'],true)
                ||!hash_equals($v['draft_sha256'],$row['draft_sha256'])
                ||$v['key_revision']<1
                ||$v['issued_at']<0
                ||$v['expires_at']<=$v['issued_at']
                ||$v['expires_at']-$v['issued_at']>120){
                return 'SIGNED_REVIEW_HISTORICAL_SCOPE_INVALID';
            }
            $pub=is_string($row['public_key_b64'])
                ?base64_decode($row['public_key_b64'],true):false;
            $sig=base64_decode($row['signature_b64'],true);
            if(!is_string($pub)
                ||strlen($pub)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ||!is_string($row['key_fingerprint'])
                ||!hash_equals(hash('sha256',$pub),$row['key_fingerprint'])){
                return 'SIGNED_REVIEW_ORIGINAL_KEY_MISSING_OR_CHANGED';
            }
            if(!is_string($sig)
                ||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($sig)!==$row['signature_b64']
                ||!sodium_crypto_sign_verify_detached(
                    $sig,$row['canonical_vote'],$pub)){
                return 'SIGNED_REVIEW_HISTORICAL_SIGNATURE_INVALID';
            }
            return null;
        }catch(Throwable){
            return 'SIGNED_REVIEW_CANONICAL_SCHEMA_INVALID';
        }
    }

    /** @return array{tenant_ref:string,proposal_ref:string,reason:string} */
    private static function issue(array $row,string $reason):array
    {
        return [
            'tenant_ref'=>$row['tenant_ref'],
            'proposal_ref'=>$row['proposal_ref'],
            'reason'=>$reason
        ];
    }

    /** @return array<string,mixed> */
    private static function out(
        string $decision,int $drafts,int $votes,array $issues
    ):array {
        return [
            'decision'=>$decision,
            'checked_drafts'=>$drafts,
            'checked_signed_observations'=>$votes,
            'issues'=>$issues,
            'ai_calls'=>0,
            'service_effects'=>0,
            'repaired_rows'=>0,
            'operational_authorization'=>false
        ];
    }

    private static function ciOnly():bool
    {
        return getenv('GITHUB_ACTIONS')==='true'
            &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
            &&getenv('AIA_P61_HISTORY_LAB')==='true'
            &&getenv('DB_HOST')==='127.0.0.1'
            &&getenv('DB_DATABASE')==='imagine_agents_ci';
    }
}
