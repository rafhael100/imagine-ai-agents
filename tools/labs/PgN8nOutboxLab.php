<?php
declare(strict_types=1);
namespace ImagineAI\Labs;

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use PDO;
use Throwable;

/**
 * P6 PostgreSQL16 disposable CI outbox. No n8n connection, webhook or API.
 * All calls are denied outside the fixed public GitHub test database.
 */
final readonly class PgN8nOutboxLab
{
    private const TABLE='public.aia_lab_n8n_outbox_p6';
    private const MAX_ATTEMPTS=3;

    public function __construct(private PDO $db)
    {
        if (getenv('AIA_P6_PG_LAB')!=='true'
            || getenv('GITHUB_ACTIONS')!=='true'
            || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
            || getenv('DB_HOST')!=='127.0.0.1'
            || getenv('DB_DATABASE')!=='imagine_agents_ci'
            || getenv('DB_USERNAME')!=='postgres'
            || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') {
            throw new \RuntimeException('P6_NON_DISPOSABLE_ENVIRONMENT_DENIED');
        }
        $who=$db->query('SELECT current_database() AS db,current_user AS name')
            ->fetch(PDO::FETCH_ASSOC);
        if (!is_array($who) || $who['db']!=='imagine_agents_ci'
            || $who['name']!=='postgres') {
            throw new \RuntimeException('P6_DB_IDENTITY_DENIED');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function createTables(): void
    {
        $this->db->exec('CREATE TABLE '.self::TABLE.' (
            tenant_ref varchar(90) NOT NULL,
            event_ref varchar(90) NOT NULL,
            canonical_event varchar(512) NOT NULL,
            event_hash char(64) NOT NULL,
            state varchar(12) NOT NULL DEFAULT \'PENDING\' CHECK(
                state IN (\'PENDING\',\'LEASED\',\'ACKED\',\'DEAD\')
            ),
            attempts smallint NOT NULL DEFAULT 0 CHECK (attempts BETWEEN 0 AND 3),
            lease_owner varchar(90),
            lease_expires bigint,
            PRIMARY KEY (tenant_ref,event_ref)
        )');
    }

    public function dropTables(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS '.self::TABLE);
    }

    /** @return 'CREATED'|'REPLAY'|null */
    public function enqueue(string $canonical): ?string
    {
        $event=MockN8nOutboxContract::decode($canonical);
        if ($event===null) return null;
        $tenant=$event['tenant_ref'];
        $ref=$event['event_ref'];
        $hash=hash('sha256',$canonical);
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare('INSERT INTO '.self::TABLE.'
                (tenant_ref,event_ref,canonical_event,event_hash)
                VALUES (:tenant,:ref,:body,:hash)
                ON CONFLICT (tenant_ref,event_ref) DO NOTHING');
            $stmt->execute([
                'tenant'=>$tenant,'ref'=>$ref,
                'body'=>$canonical,'hash'=>$hash,
            ]);
            $created=$stmt->rowCount()===1;
            $stmt=$this->db->prepare('SELECT event_hash,canonical_event
                FROM '.self::TABLE.' WHERE tenant_ref=:tenant AND event_ref=:ref
                FOR UPDATE');
            $stmt->execute(['tenant'=>$tenant,'ref'=>$ref]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)
                || !hash_equals($row['event_hash'],$hash)
                || !hash_equals($row['canonical_event'],$canonical)) {
                $this->db->rollBack();
                return null;
            }
            $this->db->commit();
            return $created?'CREATED':'REPLAY';
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return null;
        }
    }

    /**
     * Claims are local leasing evidence, NOT permission to deliver to n8n.
     * @return ?array{event_ref:string,canonical_event:string,attempt:int,lease_until:int}
     */
    public function claim(string $tenant,string $worker,int $now): ?array
    {
        if (!self::synthetic($tenant) || !self::synthetic($worker)
            || $now<0 || $now>4102444700) return null;
        try {
            $this->db->beginTransaction();
            // A timed-out third attempt becomes DEAD; never silently reset
            // its attempt count and never evict idempotency evidence.
            $dead=$this->db->prepare('UPDATE '.self::TABLE.'
                SET state=\'DEAD\',lease_owner=NULL,lease_expires=NULL
                WHERE tenant_ref=:tenant AND state=\'LEASED\'
                  AND attempts>=3 AND lease_expires<=:now');
            $dead->execute(['tenant'=>$tenant,'now'=>$now]);
            $select=$this->db->prepare('SELECT event_ref,canonical_event,attempts
                FROM '.self::TABLE.'
                WHERE tenant_ref=:tenant AND (
                    state=\'PENDING\' OR (
                        state=\'LEASED\' AND lease_expires<=:now
                    )
                ) AND attempts<3
                ORDER BY event_ref LIMIT 1
                FOR UPDATE SKIP LOCKED');
            $select->execute(['tenant'=>$tenant,'now'=>$now]);
            $row=$select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->db->commit();
                return null;
            }
            $attempt=(int)$row['attempts']+1;
            $leaseUntil=$now+60;
            $update=$this->db->prepare('UPDATE '.self::TABLE.'
                SET state=\'LEASED\',attempts=:attempt,
                    lease_owner=:worker,lease_expires=:until
                WHERE tenant_ref=:tenant AND event_ref=:event');
            $update->execute([
                'attempt'=>$attempt,'worker'=>$worker,'until'=>$leaseUntil,
                'tenant'=>$tenant,'event'=>$row['event_ref'],
            ]);
            $this->db->commit();
            return [
                'event_ref'=>$row['event_ref'],
                'canonical_event'=>$row['canonical_event'],
                'attempt'=>$attempt,
                'lease_until'=>$leaseUntil,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return null;
        }
    }

    /**
     * One ACK only by the SAME worker, for the exact currently leased
     * attempt and before expiry. Attempt is a monotonic fencing token:
     * a delayed receipt for an earlier lease must NEVER ACK a re-lease,
     * including when the same logical worker ID is reused.
     */
    public function acknowledge(
        string $tenant,string $event,string $worker,int $attempt,int $now
    ): bool {
        if (!self::synthetic($tenant) || !self::synthetic($event)
            || !self::synthetic($worker) || $attempt<1
            || $attempt>self::MAX_ATTEMPTS
            || $now<0 || $now>4102444800) return false;
        try {
            $stmt=$this->db->prepare('UPDATE '.self::TABLE.'
                SET state=\'ACKED\',lease_owner=NULL,lease_expires=NULL
                WHERE tenant_ref=:tenant AND event_ref=:event
                  AND state=\'LEASED\' AND lease_owner=:worker
                  AND attempts=:attempt AND lease_expires>:now');
            $stmt->execute([
                'tenant'=>$tenant,'event'=>$event,
                'worker'=>$worker,'attempt'=>$attempt,'now'=>$now,
            ]);
            return $stmt->rowCount()===1;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return ?array{pending:int,leased:int,acked:int,dead:int} */
    public function stats(string $tenant): ?array
    {
        if (!self::synthetic($tenant)) return null;
        try {
            $stmt=$this->db->prepare('SELECT state,COUNT(*) AS total
                FROM '.self::TABLE.' WHERE tenant_ref=:tenant GROUP BY state');
            $stmt->execute(['tenant'=>$tenant]);
            $result=['pending'=>0,'leased'=>0,'acked'=>0,'dead'=>0];
            while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
                $key=strtolower($row['state']);
                if (array_key_exists($key,$result)) $result[$key]=(int)$row['total'];
            }
            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    private static function synthetic(string $value): bool
    {
        return strlen($value)<=90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D',$value)===1;
    }
}
