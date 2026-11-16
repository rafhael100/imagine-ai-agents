<?php
declare(strict_types=1);

namespace ImagineAI\Convergence;

require_once __DIR__ . '/../../_auto_ia_pinned_source/tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php';
require_once __DIR__ . '/../../src/MockOrchestration/MockN8nOutboxContract.php';

use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;

/** CI-only fixture for P18; never a signing or grant API. */
final class P18Fixture
{
    public const NOW = 1700000000;
    public const KEY = 'SYNTHETIC_P18_CRASH_LAB_SIGNER_ONLY_123456';

    /** @return array{0:string,1:array,2:array} */
    public static function make(string $tenant, string $event, bool $active = true): array
    {
        $p7 = MockN8nOutboxContract::encode([
            'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
            'event_ref'=>$event,
            'tenant_ref'=>$tenant,
            'run_ref'=>'SYNTHETIC_RUN_P18',
            'kind'=>'mock.run.completed',
            'reference_ref'=>'SYNTHETIC_P18_RESOURCE',
            'occurred_at'=>self::NOW-5,
        ]);
        if ($p7 === null) throw new \RuntimeException('P18_BAD_EVENT');
        $proposal=MockP7ToAutoIaAdapter::propose($p7,$tenant,self::NOW)['proposal'];
        if (!is_array($proposal)) throw new \RuntimeException('P18_BAD_PROPOSAL');
        $signed=[
            'schema'=>'AUTO_IA_S2S_V1',
            'issuer'=>$proposal['issuer'],
            'audience'=>$proposal['audience'],
            'product'=>$proposal['product'],
            'operation'=>$proposal['operation'],
            'tenant_ref'=>$proposal['tenant_ref'],
            'event_ref'=>$proposal['event_ref'],
            'nonce_ref'=>$proposal['nonce_ref'],
            'request_ref'=>$proposal['request_ref'],
            'issued_at'=>self::NOW-5,
            'expires_at'=>self::NOW+60,
            'payload_hash'=>$proposal['payload_hash'],
            'key_id'=>'SYNTHETIC_KEY_A',
            'signature'=>'',
        ];
        $canonical=[];
        foreach($signed as $field=>$value) if($field!=='signature') $canonical[]=(string)$value;
        $signed['signature']=hash_hmac('sha256',implode("\n",$canonical),self::KEY);
        return [$p7,$signed,[
            'service'=>'imagine-ai-agents',
            'tenant_ref'=>$tenant,
            'operations'=>['run.status.project'],
            'active'=>$active,
        ]];
    }
}
