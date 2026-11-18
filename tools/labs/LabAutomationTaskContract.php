<?php
declare(strict_types=1);
namespace ImagineAI\Convergence;

require_once __DIR__.'/../../src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__.'/../../src/MockOrchestration/CapturedLeadTaskContract.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract;
use ImagineAI\MockOrchestration\CapturedLeadTaskContract;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;

/** Explicit sum of two closed contracts. No injectable operation or verifier. */
final class LabAutomationTaskContract
{
    public static function decode(string $bytes):?array
    {
        return MockN8nOutboxContract::decode($bytes)??CapturedLeadTaskContract::decode($bytes);
    }

    public static function isLead(string $bytes):bool
    {
        return CapturedLeadTaskContract::decode($bytes)!==null;
    }

    public static function operation(string $bytes):?string
    {
        if(self::isLead($bytes))return 'lead.ingest';
        $event=MockN8nOutboxContract::decode($bytes);
        return match($event['kind']??null){
            'mock.run.completed'=>'run.status.project',
            'mock.quota.threshold'=>'usage.quota.evaluate',
            default=>null,
        };
    }

    public static function propose(string $bytes,string $tenant,int $now):array
    {
        if(self::isLead($bytes))return CapturedLeadTaskContract::propose($bytes,$tenant,$now);
        // Preserve the independently pinned historical adapter byte-for-byte.
        require_once __DIR__.'/../../_auto_ia_pinned_source/tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php';
        return MockP7ToAutoIaAdapter::propose($bytes,$tenant,$now);
    }
}
