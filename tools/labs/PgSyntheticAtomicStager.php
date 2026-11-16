<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/PgN8nOutboxLab.php';

use ImagineAI\Labs\PgN8nOutboxLab;
use PDO;
use Throwable;

/**
 * P17 atomic CI-only staging: one PostgreSQL transaction owns both
 * replay/idempotency evidence and the existing P6 outbox row.
 * No n8n delivery, no authorized operational effect, no AI model call.
 */
final class PgSyntheticAtomicStager
{
    private PgSyntheticReplayGate $replay;
    private PgN8nOutboxLab $outbox;

    public function __construct(private PDO $db)
    {
        $this->replay = new PgSyntheticReplayGate($db);
        $this->outbox = new PgN8nOutboxLab($db);
    }

    public function createTables(): void
    {
        $this->replay->createSchema();
        $this->outbox->createTables();
    }

    /**
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy
     * @param array<string,string> $keys
     * @param array<string,string> $seen
     * @return array{decision:string,reason:string,ai_calls:int,service_effects:int}
     */
    public function stage(
        string $p7,
        array $envelope,
        array $trustedPolicy,
        int $now,
        array $keys,
        array &$seen
    ): array {
        if ($this->db->inTransaction()) return self::deny('NESTED_TX_NOT_ALLOWED');
        $tentativeSeen = $seen;
        try {
            $this->db->beginTransaction();
            $claim = $this->replay->inspectAndClaim(
                $p7, $envelope, $trustedPolicy, $now, $keys, $tentativeSeen
            );
            if ($claim['decision'] !== 'LAB_ATOMIC_CLAIM_NO_EFFECT') {
                $this->db->rollBack();
                return self::deny('REPLAY_OR_POLICY_DENIED');
            }
            if ($this->outbox->enqueueWithinTransaction($p7) !== 'CREATED') {
                $this->db->rollBack();
                return self::deny('OUTBOX_CONFLICT_OR_ERROR');
            }
            $this->db->commit();
            $seen = $tentativeSeen;
            return [
                'decision' => 'LAB_ATOMIC_STAGED_NO_DISPATCH',
                'reason' => 'PERSISTED_TO_CI_ONLY_NO_IAM_NO_DELIVERY',
                'ai_calls' => 0,
                'service_effects' => 0,
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return self::deny('DATABASE_ERROR_ROLLED_BACK');
        }
    }

    /** @return array{decision:string,reason:string,ai_calls:int,service_effects:int} */
    private static function deny(string $reason): array
    {
        return [
            'decision' => 'DENY',
            'reason' => $reason,
            'ai_calls' => 0,
            'service_effects' => 0,
        ];
    }
}
