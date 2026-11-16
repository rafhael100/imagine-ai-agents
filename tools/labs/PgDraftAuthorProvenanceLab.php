<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgDraftFingerprintLab.php';

use PDO;
use Throwable;

/**
 * P59: synthetic immutable author provenance for a P51 review draft.
 *
 * This is a laboratory fixture, NOT a real authenticated author.
 * The opt-in P59 review facade rejects votes from the author. The
 * author binding is read/locked within the same P57 review transaction.
 */
final class PgDraftAuthorProvenanceLab
{
    public function __construct(private PDO $db){}

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p59_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p59_lab.draft_authors(
 tenant_ref VARCHAR(64) NOT NULL
  CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 proposal_ref VARCHAR(64) NOT NULL
  CHECK (proposal_ref ~ '^SYNTHETIC_PROPOSAL_[A-F0-9]{24}$'),
 author_ref VARCHAR(64) NOT NULL
  CHECK (author_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 draft_sha256 CHAR(64) NOT NULL
  CHECK (draft_sha256 ~ '^[a-f0-9]{64}$'),
 source VARCHAR(32) NOT NULL
  CHECK (source='SYNTHETIC_CI_AUTHOR_FIXTURE'),
 observed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
 PRIMARY KEY (tenant_ref,proposal_ref),
 FOREIGN KEY (tenant_ref,proposal_ref)
   REFERENCES aia_p51_lab.review_drafts(tenant_ref,proposal_ref)
)
SQL);
    }

    /**
     * Seal a fictional author to the exact current P51/P52 draft.
     * Exact duplicate = idempotent. Different author/hash = DENY.
     */
    public function seal(string $tenant,string $proposal,string $author):string
    {
        if(!self::ci()||!self::ref($tenant)||!self::proposal($proposal)
            ||!self::ref($author)||$this->db->inTransaction()){
            return 'DENY_AUTHOR_SEAL_INPUT';
        }
        try{
            $this->db->beginTransaction();
            $q=$this->db->prepare(<<<'SQL'
SELECT request_ref,ruleset_ref,revision,rule_ref,draft_kind,
       metric,observed,threshold,state,draft_sha256
FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND proposal_ref=:proposal FOR SHARE
SQL);
            $q->execute([':tenant'=>$tenant,':proposal'=>$proposal]);
            $r=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)||$r['state']!=='AWAITING_HUMAN_REVIEW_LAB'){
                $this->db->rollBack();
                return 'DENY_AUTHOR_DRAFT_MISSING';
            }
            $hash=PgDraftFingerprintLab::sha256(
                $tenant,$r['request_ref'],$r['ruleset_ref'],
                (int)$r['revision'],$proposal,$r['rule_ref'],
                $r['draft_kind'],$r['metric'],(int)$r['observed'],
                (int)$r['threshold'],$r['state']
            );
            if(!hash_equals($hash,$r['draft_sha256'])){
                $this->db->rollBack();
                return 'DENY_AUTHOR_DRAFT_CORRUPTED';
            }
            $add=$this->db->prepare(<<<'SQL'
INSERT INTO aia_p59_lab.draft_authors
(tenant_ref,proposal_ref,author_ref,draft_sha256,source)
VALUES (:tenant,:proposal,:author,:digest,'SYNTHETIC_CI_AUTHOR_FIXTURE')
ON CONFLICT DO NOTHING RETURNING author_ref
SQL);
            $add->execute([
                ':tenant'=>$tenant,':proposal'=>$proposal,
                ':author'=>$author,':digest'=>$hash
            ]);
            if($add->fetchColumn()!==false){
                $this->db->commit();
                return 'LAB_AUTHOR_SEALED_NO_APPROVAL';
            }
            $existing=$this->db->prepare(<<<'SQL'
SELECT author_ref,draft_sha256
FROM aia_p59_lab.draft_authors
WHERE tenant_ref=:tenant AND proposal_ref=:proposal FOR SHARE
SQL);
            $existing->execute([':tenant'=>$tenant,':proposal'=>$proposal]);
            $e=$existing->fetch(PDO::FETCH_ASSOC);
            if(!is_array($e)||$e['author_ref']!==$author
                ||!hash_equals($hash,$e['draft_sha256'])){
                $this->db->rollBack();
                return 'DENY_AUTHOR_CHANGE_OR_STALE_REPLAY';
            }
            $this->db->commit();
            return 'LAB_AUTHOR_SEAL_REPLAY_NO_ACTION';
        }catch(Throwable){
            if($this->db->inTransaction())$this->db->rollBack();
            return 'DENY_AUTHOR_SEAL_DATABASE_FAILURE';
        }
    }

    /**
     * Used by P59 P57 reviewer facade inside the OWNED transaction.
     * Read-only status uses lock=false to honor PG READ ONLY isolation.
     */
    public function authorDistinctInsideTransaction(
        string $tenant,string $proposal,string $draftHash,
        ?string $reviewer,bool $lock
    ):bool {
        if(!self::ci()||!$this->db->inTransaction()
            ||!self::ref($tenant)||!self::proposal($proposal)
            ||preg_match('/\A[a-f0-9]{64}\z/D',$draftHash)!==1
            ||($reviewer!==null&&!self::ref($reviewer))){
            return false;
        }
        try{
            $sql=<<<'SQL'
SELECT author_ref,draft_sha256
FROM aia_p59_lab.draft_authors
WHERE tenant_ref=:tenant AND proposal_ref=:proposal
SQL;
            if($lock)$sql.=' FOR SHARE';
            $q=$this->db->prepare($sql);
            $q->execute([':tenant'=>$tenant,':proposal'=>$proposal]);
            $r=$q->fetch(PDO::FETCH_ASSOC);
            return is_array($r)
                &&hash_equals($draftHash,$r['draft_sha256'])
                &&($reviewer===null||$reviewer!==$r['author_ref']);
        }catch(Throwable){return false;}
    }

    private static function ci():bool {
        return getenv('GITHUB_ACTIONS')==='true'
            &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
            &&getenv('AIA_P59_AUTHOR_LAB')==='true'
            &&getenv('DB_HOST')==='127.0.0.1'
            &&getenv('DB_DATABASE')==='imagine_agents_ci';
    }
    private static function ref(string $s):bool{
        return strlen($s)<=64
            &&preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s)===1;
    }
    private static function proposal(string $s):bool{
        return preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',$s)===1;
    }
}

/**
 * P59 explicit opt-in interface: proof of synthetic authorship is mandatory.
 * No public endpoint; never promotes a review to live execution permission.
 */
final class PgAuthorBoundSignedReviewLab
{
    private PgSignedDraftReviewLab $review;
    public function __construct(PDO $db){
        $this->review=new PgSignedDraftReviewLab($db);
    }
    /** @return array<string,mixed> */
    public function submit(string $canonical,string $signatureB64,int $now):array{
        return $this->review->submit($canonical,$signatureB64,$now,true);
    }
    /** @return array<string,mixed> */
    public function status(string $tenant,string $proposal,int $now):array{
        return $this->review->status($tenant,$proposal,$now,true);
    }
    public static function operationalReadiness():array{
        return ['decision'=>'NO_GO',
            'reason'=>'SYNTHETIC_AUTHORS_ARE_NOT_REAL_CRM_IDENTITIES'];
    }
}
