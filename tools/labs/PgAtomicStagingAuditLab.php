<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__.'/PgSyntheticReplayGate.php';
require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__.'/../../_auto_ia_pinned_source/tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php';
require_once __DIR__.'/LabAutomationTaskContract.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use PDO;
use Throwable;

/**
 * P23 read-only consistency scan of the two synthetic P17 tables.
 * Never repairs rows, acknowledges events, authorizes IAM or contacts n8n.
 */
final class PgAtomicStagingAuditLab
{
    public function __construct(private PDO $db)
    {
        // P22 performs an actual driver/database/role identity verification.
        new PgSyntheticReplayGate($db);
    }

    /**
     * @return array{decision:string,checked:int,issues:array,external_calls:int,ai_calls:int}
     */
    public function scan(): array
    {
        try {
            $rows=$this->db->query(<<<'SQL'
SELECT a.tenant_ref AS replay_tenant,
       a.event_ref AS replay_event,
       a.payload_hash AS replay_hash,
       a.request_ref AS replay_request,
       a.nonce_ref AS replay_nonce,
       b.tenant_ref AS outbox_tenant,
       b.event_ref AS outbox_event,
       b.canonical_event AS outbox_canonical,
       b.event_hash AS outbox_hash
FROM aia_p16_lab.event_replay a
FULL OUTER JOIN public.aia_lab_n8n_outbox_p6 b
 ON a.tenant_ref=b.tenant_ref AND a.event_ref=b.event_ref
ORDER BY COALESCE(a.tenant_ref,b.tenant_ref),COALESCE(a.event_ref,b.event_ref)
SQL)->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 2000) return self::output('INDETERMINATE', 0, [['reason'=>'CAPACITY_LIMIT']]);
            $issues=[];
            foreach ($rows as $row) {
                $reason=null;
                if ($row['replay_tenant'] === null) {
                    $reason='OUTBOX_ONLY';
                }elseif($row['outbox_tenant'] === null){
                    $reason='REPLAY_ONLY';
                }elseif(!is_string($row['replay_hash']) || !is_string($row['outbox_hash'])
                    || !hash_equals($row['replay_hash'],$row['outbox_hash'])){
                    $reason='STORED_HASH_MISMATCH';
                }else{
                    $canonical=$row['outbox_canonical'];
                    $event=is_string($canonical) ? LabAutomationTaskContract::decode($canonical) : null;
                    if($event===null){
                        $reason='INVALID_OUTBOX_CANONICAL';
                    }elseif($event['tenant_ref']!==$row['replay_tenant']
                        || $event['event_ref']!==$row['replay_event']){
                        $reason='CANONICAL_TENANT_OR_EVENT_MISMATCH';
                    }elseif(!hash_equals($row['replay_hash'],hash('sha256',$canonical))){
                        $reason='CANONICAL_PAYLOAD_HASH_MISMATCH';
                    }else{
                        // Historical offline reconstruction uses the recorded event timestamp,
                        // NEVER the current wall clock. This does NOT establish current validity.
                        $proposalResult=LabAutomationTaskContract::propose(
                            $canonical, $row['replay_tenant'], (int)$event['occurred_at'] + 5
                        );
                        $proposal=$proposalResult['proposal'] ?? null;
                        if($proposalResult['decision']!=='LAB_NO_AI_PROPOSAL_NOT_SIGNED'
                            || !is_array($proposal)){
                            $reason='HISTORICAL_CONTRACT_DERIVATION_DENIED';
                        }elseif(!is_string($row['replay_request'])
                            || !hash_equals($proposal['request_ref'],$row['replay_request'])){
                            $reason='REPLAY_REQUEST_BINDING_MISMATCH';
                        }elseif(!is_string($row['replay_nonce'])
                            || !hash_equals($proposal['nonce_ref'],$row['replay_nonce'])){
                            $reason='REPLAY_NONCE_BINDING_MISMATCH';
                        }
                    }
                }
                if($reason!==null){
                    // No payload, secret, customer data or canonical bytes in audit output.
                    $issues[]=[
                        'tenant_ref'=>$row['replay_tenant'] ?? $row['outbox_tenant'],
                        'event_ref'=>$row['replay_event'] ?? $row['outbox_event'],
                        'reason'=>$reason,
                    ];
                }
            }
            return self::output($issues ? 'DENY_INCONSISTENT' : 'LAB_CONSISTENT_NO_AUTHORIZATION',
                count($rows),$issues);
        }catch(Throwable){
            return self::output('INDETERMINATE',0,[['reason'=>'DB_UNAVAILABLE_OR_SCHEMA_ERROR']]);
        }
    }

    /** @return array{decision:string,checked:int,issues:array,external_calls:int,ai_calls:int} */
    private static function output(string $decision,int $checked,array $issues):array
    {
        return [
            'decision'=>$decision,'checked'=>$checked,'issues'=>$issues,
            'external_calls'=>0,'ai_calls'=>0,
        ];
    }
}
