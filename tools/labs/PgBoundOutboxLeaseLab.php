<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';

use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use PDO;
use Throwable;

/**
 * P28: offline, CI-only read-and-lease boundary.
 *
 * An outbox event is not eligible for a lease merely because it exists in P6.
 * The exact P16 ledger row and its historical P7/CONV-02 identity must also match.
 * A returned lease is a MOCK DB marker, not permission to contact n8n/AI.
 */
final class PgBoundOutboxLeaseLab
{
    public function __construct(private PDO $db)
    {
        // P22 validates driver and exact disposable DB/role. Never use on VPS.
        new PgSyntheticReplayGate($db);
    }

    /**
     * @return array{decision:string,reason:string,event_ref:?string,attempt:int,lease_until:int,ai_calls:int,service_effects:int}
     */
    public function reserve(string $tenant, string $worker, int $now): array
    {
        if (!self::ref($tenant) || !self::ref($worker)
            || $now < 0 || $now > 4_102_444_700
            || $this->db->inTransaction()) {
            return self::out('DENY', 'UNTRUSTED_SCOPE_OR_NESTED_TRANSACTION');
        }
        try {
            $this->db->beginTransaction();
            // Exhausted leases are retained as DEAD and never silently reset.
            $expire = $this->db->prepare(<<<'SQL'
UPDATE public.aia_lab_n8n_outbox_p6
 SET state='DEAD',lease_owner=NULL,lease_expires=NULL
 WHERE tenant_ref=:tenant AND state='LEASED' AND attempts>=3
   AND lease_expires<=:now
SQL);
            $expire->execute([':tenant'=>$tenant, ':now'=>$now]);

            // Lock only one outbox row, skipping rows owned by other workers.
            $select = $this->db->prepare(<<<'SQL'
SELECT event_ref,canonical_event,event_hash,attempts
 FROM public.aia_lab_n8n_outbox_p6
 WHERE tenant_ref=:tenant AND attempts<3 AND
  (state='PENDING' OR (state='LEASED' AND lease_expires<=:now))
 ORDER BY event_ref LIMIT 1 FOR UPDATE SKIP LOCKED
SQL);
            $select->execute([':tenant'=>$tenant, ':now'=>$now]);
            $row=$select->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)) {
                $this->db->commit();
                return self::out('LAB_NO_PENDING', 'NO_ELIGIBLE_EVENT');
            }

            // A separate P16 row is required, including event/request/nonce/hash.
            // Lock it FOR SHARE until the lease update is committed so that a
            // concurrent transaction cannot mutate these protected identities.
            $proof=$this->db->prepare(<<<'SQL'
SELECT payload_hash,request_ref,nonce_ref
 FROM aia_p16_lab.event_replay
 WHERE tenant_ref=:tenant AND event_ref=:event FOR SHARE
SQL);
            $proof->execute([':tenant'=>$tenant, ':event'=>$row['event_ref']]);
            $ledger=$proof->fetch(PDO::FETCH_ASSOC);
            $canonical=$row['canonical_event'];
            $event=is_string($canonical)
                ? MockN8nOutboxContract::decode($canonical)
                : null;
            if(!is_array($ledger) || !is_array($event)
                || !is_string($row['event_hash'])
                || $event['tenant_ref']!==$tenant
                || $event['event_ref']!==$row['event_ref']
                || !hash_equals($row['event_hash'],hash('sha256',$canonical))
                || !hash_equals($ledger['payload_hash'],$row['event_hash'])) {
                $this->db->rollBack();
                return self::out('DENY', 'CROSS_TABLE_BINDING_INVALID');
            }

            // Historical derivation is a consistency check only, NEVER a
            // fresh authorization, service token or live trust decision.
            $check=MockP7ToAutoIaAdapter::propose(
                $canonical,$tenant,(int)$event['occurred_at']+5
            );
            $p=$check['proposal'] ?? null;
            if($check['decision']!=='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
                || !is_array($p)
                || !hash_equals($p['request_ref'],$ledger['request_ref'])
                || !hash_equals($p['nonce_ref'],$ledger['nonce_ref'])
                || !hash_equals($p['payload_hash'],$ledger['payload_hash'])) {
                $this->db->rollBack();
                return self::out('DENY', 'REQUEST_OR_NONCE_BOUNDARY_INVALID');
            }

            $attempt=(int)$row['attempts']+1;
            $until=$now+60;
            $update=$this->db->prepare(<<<'SQL'
UPDATE public.aia_lab_n8n_outbox_p6
 SET state='LEASED',attempts=:attempt,lease_owner=:worker,lease_expires=:until
 WHERE tenant_ref=:tenant AND event_ref=:event AND attempts=:before
SQL);
            $update->execute([
                ':attempt'=>$attempt,':worker'=>$worker,':until'=>$until,
                ':tenant'=>$tenant,':event'=>$row['event_ref'],
                ':before'=>(int)$row['attempts'],
            ]);
            if($update->rowCount()!==1){
                $this->db->rollBack();
                return self::out('DENY','LOST_LOCK_OR_CONCURRENT_CONFLICT');
            }
            $this->db->commit();
            return self::out('LAB_LEASE_RESERVED_NO_DELIVERY', 'NO_REAL_IAM_OR_TRANSPORT',
                $row['event_ref'],$attempt,$until);
        }catch(Throwable){
            if($this->db->inTransaction()) $this->db->rollBack();
            return self::out('DENY','DB_UNAVAILABLE_OR_TRANSACTION_FAILED');
        }
    }

    private static function ref(string $value): bool
    {
        return strlen($value)<=64
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$value)===1;
    }

    /** @return array{decision:string,reason:string,event_ref:?string,attempt:int,lease_until:int,ai_calls:int,service_effects:int} */
    private static function out(string $decision,string $reason,
        ?string $event=null,int $attempt=0,int $until=0):array
    {
        return [
            'decision'=>$decision,'reason'=>$reason,'event_ref'=>$event,
            'attempt'=>$attempt,'lease_until'=>$until,
            'ai_calls'=>0,'service_effects'=>0,
        ];
    }
}
