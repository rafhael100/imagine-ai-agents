<?php
declare(strict_types=1);

require_once __DIR__ . '/labs/PgSyntheticAtomicStager.php';
require_once __DIR__ . '/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\PgSyntheticReplayGate;
use ImagineAI\Convergence\P18Fixture;

if (getenv('AIA_P16_PG_LAB')!=='true'
    || getenv('AIA_P6_PG_LAB')!=='true'
    || getenv('GITHUB_ACTIONS')!=='true') {
    throw new RuntimeException('P18_CI_ONLY');
}
$mode = $argv[1] ?? '';
$tenant = $argv[2] ?? '';
$event = $argv[3] ?? '';
if (!in_array($mode,['stage','crash_after_replay','revoked'],true)
    || !in_array($tenant,['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'],true)
    || preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$event)!==1) {
    throw new RuntimeException('P18_WORKER_INVALID_ARGUMENT');
}
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
[$p7,$signed,$policy]=P18Fixture::make($tenant,$event,$mode!=='revoked');
$seen=[];
$ring=['SYNTHETIC_KEY_A'=>P18Fixture::KEY];
if($mode==='crash_after_replay'){
    $db->beginTransaction();
    $gate=new PgSyntheticReplayGate($db);
    $r=$gate->inspectAndClaim($p7,$signed,$policy,P18Fixture::NOW,$ring,$seen);
    if($r['decision']!=='LAB_ATOMIC_CLAIM_NO_EFFECT') {
        throw new RuntimeException('P18_CRASH_SEED_DENIED_'.$r['reason']);
    }
    // Simulation: process terminates with transaction intentionally uncommitted.
    echo "SIMULATED_CRASH_UNCOMMITTED_REPLAY\n";
    exit(0);
}
$stager=new PgSyntheticAtomicStager($db);
$r=$stager->stage($p7,$signed,$policy,P18Fixture::NOW,$ring,$seen);
if($r['ai_calls']!==0 || $r['service_effects']!==0) throw new RuntimeException('P18_EFFECT');
echo $r['decision']." ".$r['reason']."\n";
