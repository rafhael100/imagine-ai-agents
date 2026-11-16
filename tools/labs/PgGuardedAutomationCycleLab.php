<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticAtomicStager.php';
require_once __DIR__.'/PgBoundOutboxLeaseLab.php';
require_once __DIR__.'/PgBoundOutboxAckLab.php';
require_once __DIR__.'/PgAtomicStagingAuditLab.php';

use PDO;

/**
 * P31: one opt-in CI facade for P17 -> P28 -> P30 -> P23/P25.
 *
 * There is deliberately no generic P6::claim(), unsigned ACK, provider call,
 * webhook URL, production secret, model request or automatic n8n dispatcher.
 * All parameters remain synthetic fixtures and callers must supply trusted
 * tenant/policy out-of-band. THIS IS NOT A REAL IAM IMPLEMENTATION.
 */
final class PgGuardedAutomationCycleLab
{
    private PgSyntheticAtomicStager $stager;
    private PgBoundOutboxLeaseLab $lease;
    private PgBoundOutboxAckLab $ack;
    private PgAtomicStagingAuditLab $auditor;

    public function __construct(PDO $db)
    {
        $this->stager=new PgSyntheticAtomicStager($db);
        $this->lease=new PgBoundOutboxLeaseLab($db);
        $this->ack=new PgBoundOutboxAckLab($db);
        $this->auditor=new PgAtomicStagingAuditLab($db);
    }

    public function createTables():void
    {
        $this->stager->createTables();
    }

    /**
     * @param array<string,mixed> $envelope
     * @param array<string,mixed> $trustedPolicy CI fixture, never a bearer grant
     * @param array<string,string> $testKeys
     * @param array<string,string> $seen
     * @return array<string,mixed>
     */
    public function stage(string $canonicalP7,array $envelope,array $trustedPolicy,
        int $now,array $testKeys,array &$seen):array
    {
        return $this->stager->stage(
            $canonicalP7,$envelope,$trustedPolicy,$now,$testKeys,$seen
        );
    }

    /** @return array<string,mixed> */
    public function reserve(string $trustedTenant,string $worker,int $now):array
    {
        return $this->lease->reserve($trustedTenant,$worker,$now);
    }

    /** @return array<string,mixed> */
    public function acknowledge(string $trustedTenant,string $eventRef,
        string $worker,int $attempt,int $now):array
    {
        return $this->ack->acknowledge(
            $trustedTenant,$eventRef,$worker,$attempt,$now
        );
    }

    /** @return array<string,mixed> Read-only, CI-scoped cross-table consistency. */
    public function audit():array
    {
        return $this->auditor->scan();
    }

    /** @return array{decision:string,reason:string} */
    public static function operationalReadiness():array
    {
        return [
            'decision'=>'NO_GO',
            'reason'=>'MISSING_REAL_IAM_S2S_SIGNED_RECEIPTS_AND_N8N_DEPLOYMENT',
        ];
    }
}
