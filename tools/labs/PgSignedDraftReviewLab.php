<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgDraftFingerprintLab.php';
require_once __DIR__.'/PgDraftAuthorProvenanceLab.php';

use PDO;
use Throwable;

/**
 * P57: synthetic, double-observer notebook for deterministic P51 review
 * drafts. Ed25519 observations are revocable and tenant-scoped. Even two
 * valid ACKNOWLEDGE votes do NOT grant an operational approval or delivery.
 *
 * This class is CI-only and cannot authenticate a real human or CRM owner.
 */
final class PgSignedDraftReviewLab
{
    private const FIELDS=[
        'schema','issuer','audience','tenant_ref','proposal_ref',
        'reviewer_ref','role','decision','draft_sha256','key_revision',
        'issued_at','expires_at'
    ];
    private const ROLES=['PRODUCT','SECURITY'];
    private const DECISIONS=['ACKNOWLEDGE','REJECT'];

    public function __construct(private PDO $db)
    {
        if(!self::ciOnly()
            ||$db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql'
            ||$db->query('SELECT current_database()')->fetchColumn()
                !=='imagine_agents_ci'){
            throw new \RuntimeException('P57_CI_ONLY_DB_DENIED');
        }
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p57_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p57_lab.reviewers(
 tenant_ref VARCHAR(64) NOT NULL
  CHECK(tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 reviewer_ref VARCHAR(64) NOT NULL
  CHECK(reviewer_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 reviewer_role VARCHAR(8) NOT NULL
  CHECK(reviewer_role IN ('PRODUCT','SECURITY')),
 public_key_b64 VARCHAR(44) NOT NULL,
 key_fingerprint CHAR(64) NOT NULL
  CHECK(key_fingerprint ~ '^[a-f0-9]{64}$'),
 active BOOLEAN NOT NULL,
 revision INTEGER NOT NULL CHECK(revision BETWEEN 1 AND 1000000),
 PRIMARY KEY(tenant_ref,reviewer_ref),
 UNIQUE(tenant_ref,key_fingerprint)
)
SQL);
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p57_lab.signed_observations(
 tenant_ref VARCHAR(64) NOT NULL,
 proposal_ref VARCHAR(64) NOT NULL,
 reviewer_role VARCHAR(8) NOT NULL
  CHECK(reviewer_role IN ('PRODUCT','SECURITY')),
 reviewer_ref VARCHAR(64) NOT NULL,
 decision VARCHAR(12) NOT NULL
  CHECK(decision IN ('ACKNOWLEDGE','REJECT')),
 draft_sha256 CHAR(64) NOT NULL
  CHECK(draft_sha256 ~ '^[a-f0-9]{64}$'),
 canonical_vote VARCHAR(1024) NOT NULL,
 signature_b64 VARCHAR(88) NOT NULL,
 observed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY(tenant_ref,proposal_ref,reviewer_role),
 UNIQUE(tenant_ref,proposal_ref,reviewer_ref),
 FOREIGN KEY(tenant_ref,reviewer_ref)
  REFERENCES aia_p57_lab.reviewers(tenant_ref,reviewer_ref)
)
SQL);
    }

    public function registerReviewer(string $tenant,string $reviewer,
        string $role,string $publicKey):bool
    {
        if(!self::ref($tenant)||!self::ref($reviewer)
            ||!in_array($role,self::ROLES,true)
            ||strlen($publicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES){
            return false;
        }
        try{
            $q=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p57_lab.reviewers
(tenant_ref,reviewer_ref,reviewer_role,public_key_b64,
 key_fingerprint,active,revision)
VALUES(:t,:r,:role,:key,:fp,true,1)
ON CONFLICT DO NOTHING
SQL);
            $q->execute([
                ':t'=>$tenant,':r'=>$reviewer,':role'=>$role,
                ':key'=>base64_encode($publicKey),
                ':fp'=>hash('sha256',$publicKey)
            ]);
            return $q->rowCount()===1;
        }catch(Throwable){return false;}
    }

    public function revokeReviewer(string $tenant,string $reviewer):bool
    {
        if(!self::ref($tenant)||!self::ref($reviewer))return false;
        try{
            $q=$this->db->prepare(<<<'SQL'
UPDATE aia_p57_lab.reviewers
SET active=false,revision=revision+1
WHERE tenant_ref=:t AND reviewer_ref=:r AND active=true
 AND revision<1000000
SQL);
            $q->execute([':t'=>$tenant,':r'=>$reviewer]);
            return $q->rowCount()===1;
        }catch(Throwable){return false;}
    }

    /** @return array<string,mixed> */
    public function submit(string $canonical,string $signatureB64,int $now,
        bool $enforceAuthor=false):array
    {
        if(!self::ciOnly()||$this->db->inTransaction()
            ||strlen($canonical)<180||strlen($canonical)>1024
            ||strlen($signatureB64)!==88)return self::deny('SIGNED_OBSERVATION_REQUIRED');
        try{
            $v=self::parse($canonical,$now);
            if($v===null)return self::deny('INVALID_SIGNED_REVIEW_SCHEMA_OR_SCOPE');
            $sig=base64_decode($signatureB64,true);
            if(!is_string($sig)
                ||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES
                ||base64_encode($sig)!==$signatureB64){
                return self::deny('INVALID_SIGNED_REVIEW_SIGNATURE');
            }
            $this->db->beginTransaction();
            $draft=$this->draft(
                $v['tenant_ref'],$v['proposal_ref'],true
            );
            if($draft===null
                ||!hash_equals($draft['draft_sha256'],$v['draft_sha256'])){
                $this->db->rollBack();
                return self::deny('REVIEW_DRAFT_MISSING_CHANGED_OR_CROSS_TENANT');
            }
            // P59: synthetic author provenance is checked under the SAME
            // proposal-level FOR UPDATE transaction as the signed vote.
            if($enforceAuthor && !(new PgDraftAuthorProvenanceLab($this->db))
                ->authorDistinctInsideTransaction(
                    $v['tenant_ref'],$v['proposal_ref'],
                    $v['draft_sha256'],$v['reviewer_ref'],true
                )){
                $this->db->rollBack();
                return self::deny('REVIEW_AUTHOR_SEPARATION_DENIED');
            }
            $key=$this->reviewer($v['tenant_ref'],$v['reviewer_ref'],true);
            if(!self::validKey($key,$v)
                ||!self::validSignature($key,$canonical,$sig)){
                $this->db->rollBack();
                return self::deny('REVIEWER_NOT_TRUSTED_REVOKED_OR_INVALID');
            }
            $seen=$this->db->prepare(<<<'SQL'
SELECT reviewer_ref,reviewer_role,decision,canonical_vote,signature_b64
FROM aia_p57_lab.signed_observations
WHERE tenant_ref=:t AND proposal_ref=:proposal
FOR SHARE
SQL);
            $seen->execute([':t'=>$v['tenant_ref'],':proposal'=>$v['proposal_ref']]);
            foreach($seen->fetchAll(PDO::FETCH_ASSOC) as $old){
                if($old['reviewer_role']===$v['role']){
                    if($old['reviewer_ref']===$v['reviewer_ref']
                        &&$old['decision']===$v['decision']
                        &&hash_equals($old['canonical_vote'],$canonical)
                        &&hash_equals($old['signature_b64'],$signatureB64)){
                        $this->db->commit();
                        return self::out(
                            'LAB_SIGNED_REVIEW_OBSERVATION_REPLAY',
                            'EXACT_DUPLICATE_NO_NEW_ACTION'
                        );
                    }
                    $this->db->rollBack();
                    return self::deny('REVIEW_ROLE_ALREADY_OBSERVED');
                }
                if($old['reviewer_ref']===$v['reviewer_ref']
                    ||$old['decision']==='REJECT'){
                    $this->db->rollBack();
                    return self::deny('REVIEW_SEPARATION_OR_REJECTION_DENIED');
                }
            }
            $add=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p57_lab.signed_observations
(tenant_ref,proposal_ref,reviewer_role,reviewer_ref,decision,
 draft_sha256,canonical_vote,signature_b64)
VALUES(:t,:proposal,:role,:reviewer,:decision,:hash,:canonical,:signature)
ON CONFLICT DO NOTHING RETURNING proposal_ref
SQL);
            $add->execute([
                ':t'=>$v['tenant_ref'],':proposal'=>$v['proposal_ref'],
                ':role'=>$v['role'],':reviewer'=>$v['reviewer_ref'],
                ':decision'=>$v['decision'],':hash'=>$v['draft_sha256'],
                ':canonical'=>$canonical,':signature'=>$signatureB64
            ]);
            if($add->fetchColumn()===false){
                $this->db->rollBack();
                return self::deny('SIGNED_REVIEW_CONCURRENT_CONFLICT');
            }
            $this->db->commit();
            return self::out(
                'LAB_SIGNED_REVIEW_OBSERVATION_STORED',
                'NEVER_AN_OPERATIONAL_APPROVAL'
            );
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('SIGNED_REVIEW_CHECK_OR_STORAGE_FAILED');
        }
    }

    /** @return array<string,mixed> */
    public function status(string $tenant,string $proposal,int $now,
        bool $enforceAuthor=false):array
    {
        if(!self::ciOnly()||!self::ref($tenant)
            ||!self::proposal($proposal)
            ||$now<0||$now>4102444800||$this->db->inTransaction()){
            return self::deny('INVALID_REVIEW_STATUS_SCOPE');
        }
        try{
            $this->db->beginTransaction();
            $this->db->exec(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY'
            );
            $draft=$this->draft($tenant,$proposal,false);
            if($draft===null){
                $this->db->rollBack();
                return self::deny('REVIEW_DRAFT_NOT_FOUND_OR_INCONSISTENT');
            }
            if($enforceAuthor && !(new PgDraftAuthorProvenanceLab($this->db))
                ->authorDistinctInsideTransaction(
                    $tenant,$proposal,$draft['draft_sha256'],null,false
                )){
                $this->db->rollBack();
                return self::deny('DRAFT_AUTHOR_PROVENANCE_DENIED');
            }
            $q=$this->db->prepare(<<<'SQL'
SELECT reviewer_role,reviewer_ref,decision,draft_sha256,
       canonical_vote,signature_b64
FROM aia_p57_lab.signed_observations
WHERE tenant_ref=:t AND proposal_ref=:proposal
ORDER BY reviewer_role
SQL);
            $q->execute([':t'=>$tenant,':proposal'=>$proposal]);
            $votes=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($votes)>2){
                $this->db->rollBack();
                return self::deny('REVIEW_OBSERVATION_CAPACITY_INVALID');
            }
            $roles=[];$refs=[];$rejected=false;
            foreach($votes as $vote){
                $v=self::parse($vote['canonical_vote'],$now);
                $signature=base64_decode($vote['signature_b64'],true);
                $key=$this->reviewer($tenant,$vote['reviewer_ref'],false);
                if($v===null||!is_string($signature)
                    ||strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
                    ||!hash_equals($draft['draft_sha256'],$vote['draft_sha256'])
                    ||$v['tenant_ref']!==$tenant
                    ||$v['proposal_ref']!==$proposal
                    ||$v['reviewer_ref']!==$vote['reviewer_ref']
                    ||$v['role']!==$vote['reviewer_role']
                    ||$v['decision']!==$vote['decision']
                    ||($enforceAuthor && !(new PgDraftAuthorProvenanceLab($this->db))
                        ->authorDistinctInsideTransaction(
                            $tenant,$proposal,$draft['draft_sha256'],
                            $vote['reviewer_ref'],false
                        ))
                    ||!self::validKey($key,$v)
                    ||!self::validSignature($key,$vote['canonical_vote'],$signature)
                    ||in_array($v['role'],$roles,true)
                    ||in_array($v['reviewer_ref'],$refs,true)){
                    $this->db->rollBack();
                    return self::deny('HISTORICAL_REVIEW_TAMPERED_EXPIRED_OR_REVOKED');
                }
                $roles[]=$v['role'];
                $refs[]=$v['reviewer_ref'];
                $rejected=$rejected||$v['decision']==='REJECT';
            }
            $this->db->commit();
            $label=$rejected?'LAB_REVIEW_REJECTED_NO_ACTION':
                (count($votes)===2?
                    'LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION':
                    (count($votes)===1?'LAB_PARTIAL_REVIEW_NO_ACTION':
                        'LAB_AWAITING_REVIEWS_NO_ACTION'));
            return [
                'decision'=>$label,
                'observations'=>count($votes),
                'roles_observed'=>$roles,
                'can_approve'=>false,
                'can_execute'=>false,
                'operational_authorization'=>false,
                'ai_calls'=>0,
                'service_effects'=>0
            ];
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return self::deny('REVIEW_STATUS_UNAVAILABLE');
        }
    }

    private function draft(string $tenant,string $proposal,bool $lock):?array
    {
        // P58: serialize all competing review submissions for this same
        // proposal before inspecting REJECT / existing role votes.
        // Signature keys still use FOR SHARE to fence concurrent revocation.
        $q=$this->db->prepare(<<<'SQL'
SELECT tenant_ref,request_ref,ruleset_ref,revision,proposal_ref,
       rule_ref,draft_kind,metric,observed,threshold,state,draft_sha256
FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:t AND proposal_ref=:proposal
SQL.($lock?' FOR UPDATE':''));
        $q->execute([':t'=>$tenant,':proposal'=>$proposal]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['state']!=='AWAITING_HUMAN_REVIEW_LAB')return null;
        $expected=PgDraftFingerprintLab::sha256(
            $tenant,$r['request_ref'],$r['ruleset_ref'],(int)$r['revision'],
            $r['proposal_ref'],$r['rule_ref'],$r['draft_kind'],
            $r['metric'],(int)$r['observed'],(int)$r['threshold'],$r['state']
        );
        return hash_equals($expected,$r['draft_sha256'])?$r:null;
    }

    private function reviewer(string $tenant,string $reviewer,bool $lock):?array
    {
        $q=$this->db->prepare(<<<'SQL'
SELECT reviewer_role,public_key_b64,key_fingerprint,active,revision
FROM aia_p57_lab.reviewers
WHERE tenant_ref=:t AND reviewer_ref=:reviewer
SQL.($lock?' FOR SHARE':''));
        $q->execute([':t'=>$tenant,':reviewer'=>$reviewer]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        return is_array($r)?$r:null;
    }

    private static function validKey(?array $row,array $vote):bool
    {
        if($row===null
            ||$row['reviewer_role']!==$vote['role']
            ||!in_array($row['active'],[true,'t',1,'1'],true)
            ||(int)$row['revision']!==$vote['key_revision'])return false;
        $key=base64_decode($row['public_key_b64'],true);
        return is_string($key)
            &&strlen($key)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            &&hash_equals(hash('sha256',$key),$row['key_fingerprint']);
    }

    private static function validSignature(?array $row,string $raw,string $sig):bool
    {
        if($row===null)return false;
        $pub=base64_decode($row['public_key_b64'],true);
        return is_string($pub)
            &&strlen($pub)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            &&sodium_crypto_sign_verify_detached($sig,$raw,$pub);
    }

    private static function parse(string $raw,int $now):?array
    {
        try{
            if(strlen($raw)>1024||strlen($raw)<180)return null;
            $v=json_decode($raw,true,5,JSON_THROW_ON_ERROR);
            if(!is_array($v)||array_keys($v)!==self::FIELDS
                ||!hash_equals($raw,json_encode($v,JSON_THROW_ON_ERROR
                    |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))){
                return null;
            }
            foreach(['schema','issuer','audience','tenant_ref','proposal_ref',
                'reviewer_ref','role','decision','draft_sha256'] as $field){
                if(!is_string($v[$field]))return null;
            }
            foreach(['key_revision','issued_at','expires_at'] as $field){
                if(!is_int($v[$field]))return null;
            }
            if($v['schema']!=='AIA_SIGNED_DRAFT_REVIEW_V1'
                ||$v['issuer']!=='SYNTHETIC_INDEPENDENT_REVIEWER'
                ||$v['audience']!=='imagine-ai-agents'
                ||!self::ref($v['tenant_ref'])
                ||!self::ref($v['reviewer_ref'])
                ||!self::proposal($v['proposal_ref'])
                ||!in_array($v['role'],self::ROLES,true)
                ||!in_array($v['decision'],self::DECISIONS,true)
                ||preg_match('/\A[a-f0-9]{64}\z/D',$v['draft_sha256'])!==1
                ||$v['key_revision']<1
                ||$v['issued_at']>$now+5
                ||$v['issued_at']<$now-60
                ||$v['expires_at']<=$now
                ||$v['expires_at']-$v['issued_at']>120)return null;
            return $v;
        }catch(Throwable){return null;}
    }

    private static function ref(string $s):bool{
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
    private static function proposal(string $s):bool{
        return preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',$s)===1;
    }
    private static function ciOnly():bool {
        return getenv('GITHUB_ACTIONS')==='true'
            &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
            &&getenv('AIA_P57_REVIEW_LAB')==='true'
            &&getenv('DB_HOST')==='127.0.0.1'
            &&getenv('DB_DATABASE')==='imagine_agents_ci';
    }
    private static function out(string $decision,string $reason):array {
        return [
            'decision'=>$decision,'reason'=>$reason,
            'can_approve'=>false,'can_execute'=>false,
            'operational_authorization'=>false,
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }
    private static function deny(string $reason):array {
        return self::out('DENY',$reason);
    }
}
