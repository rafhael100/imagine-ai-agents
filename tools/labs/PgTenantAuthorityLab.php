<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgGuardedAutomationCycleLab.php';
require_once __DIR__.'/PgSyntheticReplayGate.php';

use PDO;
use Throwable;

/**
 * P34 CI-only synthetic tenant authority. Never an operational IAM system.
 * Transactional SELECT ... FOR SHARE in P28/P30 serializes lease/ACK with
 * the UPDATE that revokes or rotates the revision.
 */
final class PgTenantAuthorityLab
{
    public function __construct(private PDO $db)
    {
        new PgSyntheticReplayGate($db);
    }

    public function createTables():void
    {
        $this->db->exec('CREATE SCHEMA IF NOT EXISTS aia_p34_lab');
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS aia_p34_lab.tenant_policy(
 tenant_ref VARCHAR(64) PRIMARY KEY
   CHECK (tenant_ref ~ '^SYNTHETIC_[A-Z0-9_]{1,48}$'),
 active BOOLEAN NOT NULL,
 revision INTEGER NOT NULL CHECK (revision BETWEEN 1 AND 1000000)
)
SQL);
    }

    public function seedActive(string $tenant):bool
    {
        if(!self::ref($tenant))return false;
        try{
            $s=$this->db->prepare("INSERT INTO aia_p34_lab.tenant_policy
              (tenant_ref,active,revision) VALUES (:tenant,true,1)
              ON CONFLICT DO NOTHING");
            $s->execute([':tenant'=>$tenant]);
            return $s->rowCount()===1;
        }catch(Throwable){return false;}
    }

    public function revoke(string $tenant):?int
    {
        return $this->transition($tenant,false,true);
    }

    public function reactivate(string $tenant):?int
    {
        return $this->transition($tenant,true,false);
    }

    /** @return ?array{active:bool,revision:int} */
    public function snapshot(string $tenant):?array
    {
        if(!self::ref($tenant))return null;
        try{
            $s=$this->db->prepare('SELECT active,revision FROM aia_p34_lab.tenant_policy
                WHERE tenant_ref=:tenant');
            $s->execute([':tenant'=>$tenant]);
            $r=$s->fetch(PDO::FETCH_ASSOC);
            return is_array($r)?[
                'active'=>in_array($r['active'],[true,1,'1','t'],true),
                'revision'=>(int)$r['revision']
            ]:null;
        }catch(Throwable){return null;}
    }

    private function transition(string $tenant,bool $target,bool $expected):?int
    {
        if(!self::ref($tenant))return null;
        try{
            $s=$this->db->prepare(
                'UPDATE aia_p34_lab.tenant_policy
                 SET active=:target,revision=revision+1
                 WHERE tenant_ref=:tenant AND active=:expected AND revision<1000000
                 RETURNING revision'
            );
            $s->bindValue(':target',$target,PDO::PARAM_BOOL);
            $s->bindValue(':expected',$expected,PDO::PARAM_BOOL);
            $s->bindValue(':tenant',$tenant,PDO::PARAM_STR);
            $s->execute();
            $r=$s->fetchColumn();
            return $r===false?null:(int)$r;
        }catch(Throwable){return null;}
    }

    private static function ref(string $value):bool
    {
        return strlen($value)<=64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$value)===1;
    }
}

/**
 * Only the revision-bound variant may lease or ACK through this facade.
 * P31 stage is preflighted against mock policy, but unlike lease/ACK that
 * check does not own the transaction; a staged row is not executable proof.
 */
final class PgRevocationBoundCycleLab
{
    private PgGuardedAutomationCycleLab $cycle;
    private PgBoundOutboxLeaseLab $lease;
    private PgBoundOutboxAckLab $ack;
    private PgTenantAuthorityLab $authority;

    public function __construct(private PDO $db)
    {
        $this->cycle=new PgGuardedAutomationCycleLab($db);
        $this->lease=new PgBoundOutboxLeaseLab($db);
        $this->ack=new PgBoundOutboxAckLab($db);
        $this->authority=new PgTenantAuthorityLab($db);
    }

    public function createTables():void
    {
        $this->cycle->createTables();
        $this->authority->createTables();
    }
    public function authority():PgTenantAuthorityLab
    {
        return $this->authority;
    }

    /** @return array<string,mixed> */
    public function stage(string $p7,array $envelope,array $trustedPolicy,
        int $now,array $keys,array &$seen):array
    {
        $tenant=$trustedPolicy['tenant_ref']??null;
        $state=is_string($tenant)?$this->authority->snapshot($tenant):null;
        if($state===null||!$state['active']) {
            return [
                'decision'=>'DENY','reason'=>'AUTHORITY_REVOKED_BEFORE_STAGE',
                'ai_calls'=>0,'service_effects'=>0,
            ];
        }
        return $this->cycle->stage($p7,$envelope,$trustedPolicy,$now,$keys,$seen);
    }

    /** @return array<string,mixed> */
    public function reserve(string $tenant,string $worker,int $now,int $revision):array
    {
        return $this->lease->reserve($tenant,$worker,$now,$revision);
    }

    /** @return array<string,mixed> */
    public function acknowledge(string $tenant,string $event,string $worker,
        int $attempt,int $now,int $revision):array
    {
        return $this->ack->acknowledge($tenant,$event,$worker,$attempt,$now,$revision);
    }

    /** @return array<string,mixed> */
    public function audit():array
    {
        return $this->cycle->audit();
    }
}
