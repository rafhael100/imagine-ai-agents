<?php
declare(strict_types=1);
require_once __DIR__ . '/labs/PgSyntheticReplayGate.php';

use ImagineAI\Convergence\PgSyntheticReplayGate;

if (getenv('AIA_P16_PG_LAB') !== 'true'
    || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('GITHUB_REPOSITORY') !== 'rafhael100/imagine-ai-agents'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'imagine_agents_ci') {
    throw new RuntimeException('P16_TEST_CI_ONLY');
}
$db = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$gate = new PgSyntheticReplayGate($db);
$gate->createSchema();
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$checks = 0;
function p16Check(bool $ok, string $label): void {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException('P16_FAIL_' . $label);
}
function p16Spawn(string $tenant, string $event): array {
    $p = proc_open(
        [PHP_BINARY, __DIR__.'/p16_replay_worker.php', $tenant, $event],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
        $pipes
    );
    if (!is_resource($p)) throw new RuntimeException('P16_PROC_START_FAILED');
    fclose($pipes[0]);
    return [$p,$pipes[1],$pipes[2]];
}
function p16Result(array $proc): string {
    [$p,$out,$err] = $proc;
    $result = trim(stream_get_contents($out));
    $errors = trim(stream_get_contents($err));
    fclose($out); fclose($err);
    if (proc_close($p) !== 0) throw new RuntimeException('P16_WORKER_FAILED '.$errors);
    return $result;
}
$workers = [];
for ($i=0; $i<10; $i++) {
    $workers[] = p16Spawn('SYNTHETIC_TENANT_A','SYNTHETIC_P16_EVENT_01');
}
$results = array_map('p16Result', $workers);
$accepted = count(array_filter($results, static fn($x) => str_starts_with($x,'LAB_ATOMIC_CLAIM_NO_EFFECT')));
$denied = count(array_filter($results, static fn($x) => $x==='DENY DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT'));
p16Check($accepted === 1, 'exactly_one_winner');
p16Check($denied === 9, 'nine_replay_denials');
$stored = (int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn();
p16Check($stored === 1, 'one_durable_row');
p16Check(p16Result(p16Spawn('SYNTHETIC_TENANT_A','SYNTHETIC_P16_EVENT_01')) ===
    'DENY DURABLE_REPLAY_OR_IDEMPOTENCY_CONFLICT', 'replay_after_new_process');
p16Check(p16Result(p16Spawn('SYNTHETIC_TENANT_B','SYNTHETIC_P16_EVENT_01')) ===
    'LAB_ATOMIC_CLAIM_NO_EFFECT NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY', 'tenant_isolation');
p16Check(p16Result(p16Spawn('SYNTHETIC_TENANT_A','SYNTHETIC_P16_EVENT_02')) ===
    'LAB_ATOMIC_CLAIM_NO_EFFECT NO_REAL_IAM_NO_TRANSPORT_NO_DELIVERY', 'different_event_allowed');

$conflicts = [
    ['SYNTHETIC_TENANT_A','SYNTHETIC_P16_EVENT_03','SYNTHETIC_FRESH_REQUEST','SYNTHETIC_P16_NONCE_CONFLICT'],
    ['SYNTHETIC_TENANT_A','SYNTHETIC_P16_EVENT_04','SYNTHETIC_FRESH_REQUEST','SYNTHETIC_FRESH_NONCE'],
];
$initial = $db->query("SELECT request_ref,nonce_ref FROM aia_p16_lab.event_replay WHERE tenant_ref='SYNTHETIC_TENANT_A' ORDER BY event_ref LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$conflicts[0][3] = $initial['nonce_ref'];
$conflicts[1][2] = $initial['request_ref'];
$sql = $db->prepare("INSERT INTO aia_p16_lab.event_replay
 (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
 VALUES (:tenant,:event,:request,:nonce,:digest)
 ON CONFLICT DO NOTHING RETURNING event_ref");
foreach ($conflicts as $i => $v) {
    $sql->execute([
        ':tenant'=>$v[0],':event'=>$v[1],':request'=>$v[2],
        ':nonce'=>$v[3],':digest'=>str_repeat('a',64),
    ]);
    p16Check($sql->fetchColumn()===false, 'unique_request_or_nonce_'.$i);
}
p16Check((int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn()===3, 'no_collision_writes');
echo "AIA_P16_PG_REPLAY_PASS checks={$checks} workers=10 winners=1 denied=9 ai_calls=0 delivery=0 NO_GO\n";
