<?php
declare(strict_types=1);

require_once __DIR__ . '/labs/PgSyntheticAtomicStager.php';
require_once __DIR__ . '/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if (getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('GITHUB_REPOSITORY') !== 'rafhael100/imagine-ai-agents'
    || getenv('AIA_P6_PG_LAB') !== 'true'
    || getenv('AIA_P16_PG_LAB') !== 'true'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'imagine_agents_ci') {
    throw new RuntimeException('P24_DISPOSABLE_PG_REQUIRED');
}

$admin = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres', 'ci-only-password', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager = new PgSyntheticAtomicStager($admin);
$stager->createTables();
$admin->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox = new PgN8nOutboxLab($admin);
$tables = ['aia_p16_lab.event_replay', 'public.aia_lab_n8n_outbox_p6'];
$roles = [
    'aia_p24_writer_a' => 'SYNTHETIC_TENANT_A',
    'aia_p24_writer_b' => 'SYNTHETIC_TENANT_B',
];
$connections = [];
$checks = 0;

function p24Assert(bool $condition, string $label): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('P24_FAIL_' . $label);
}
function p24Forbidden(PDO $db, string $sql, array $params, string $label): void {
    $denied = false;
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        $denied = $e->getCode() === '42501';
    }
    p24Assert($denied, $label);
}
function p24InsertReplay(PDO $db, string $tenant, string $suffix): void {
    $stmt = $db->prepare('INSERT INTO aia_p16_lab.event_replay
        (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
        VALUES (:tenant,:event,:request,:nonce,:digest)');
    $stmt->execute([
        ':tenant'=>$tenant,
        ':event'=>'SYNTHETIC_P24_EVENT_'.$suffix,
        ':request'=>'SYNTHETIC_P24_REQUEST_'.$suffix,
        ':nonce'=>'SYNTHETIC_P24_NONCE_'.$suffix,
        ':digest'=>str_repeat('a',64),
    ]);
}
function p24OutboxValues(string $tenant, string $suffix): array {
    $canonical = \ImagineAI\MockOrchestration\MockN8nOutboxContract::encode([
        'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref'=>'SYNTHETIC_P24_EVENT_'.$suffix,
        'tenant_ref'=>$tenant,
        'run_ref'=>'SYNTHETIC_P24_RUN',
        'kind'=>'mock.run.completed',
        'reference_ref'=>'SYNTHETIC_P24_REFERENCE',
        'occurred_at'=>P18Fixture::NOW-5,
    ]);
    if (!is_string($canonical)) throw new RuntimeException('P24_BAD_SYNTHETIC_FIXTURE');
    return [
        ':tenant'=>$tenant, ':event'=>'SYNTHETIC_P24_EVENT_'.$suffix,
        ':canonical'=>$canonical, ':digest'=>hash('sha256',$canonical),
    ];
}
const P24_OUTBOX_INSERT = 'INSERT INTO public.aia_lab_n8n_outbox_p6
    (tenant_ref,event_ref,canonical_event,event_hash)
    VALUES (:tenant,:event,:canonical,:digest)';

try {
    foreach ($roles as $role=>$tenant) {
        [$p7, $signed, $policy] = P18Fixture::make($tenant, 'SYNTHETIC_P24_SEEDED');
        $seen=[];
        $result=$stager->stage($p7,$signed,$policy,P18Fixture::NOW,
            ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
        p24Assert($result['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH', 'seed_'.$role);
    }
    foreach ($roles as $role=>$tenant) {
        $password='ci_'.bin2hex(random_bytes(18));
        $admin->exec('CREATE ROLE '.$role.' LOGIN PASSWORD '.$admin->quote($password));
        $connections[$role] = [
            'password'=>$password,
            'tenant'=>$tenant,
        ];
    }
    $admin->exec('GRANT USAGE ON SCHEMA aia_p16_lab TO aia_p24_writer_a, aia_p24_writer_b');
    $admin->exec('GRANT USAGE ON SCHEMA public TO aia_p24_writer_a, aia_p24_writer_b');
    foreach ($tables as $table) {
        $admin->exec('GRANT SELECT, INSERT ON '.$table.' TO aia_p24_writer_a, aia_p24_writer_b');
        $admin->exec('ALTER TABLE '.$table.' ENABLE ROW LEVEL SECURITY');
        $admin->exec('ALTER TABLE '.$table.' FORCE ROW LEVEL SECURITY');
        foreach ($roles as $role=>$tenant) {
            $admin->exec('CREATE POLICY p24_select_'.$role.' ON '.$table.
                ' FOR SELECT TO '.$role.' USING (tenant_ref = '.$admin->quote($tenant).')');
            $admin->exec('CREATE POLICY p24_insert_'.$role.' ON '.$table.
                ' FOR INSERT TO '.$role.' WITH CHECK (tenant_ref = '.$admin->quote($tenant).')');
        }
    }

    foreach ($connections as $role=>$data) {
        $tenant=$data['tenant'];
        $other=$tenant==='SYNTHETIC_TENANT_A'?'SYNTHETIC_TENANT_B':'SYNTHETIC_TENANT_A';
        $client=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
            $role, $data['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        p24Assert($client->query('SELECT current_user')->fetchColumn()===$role, 'role_is_nonadmin_'.$role);
        foreach ($tables as $table) {
            $read=$client->query('SELECT tenant_ref FROM '.$table.' ORDER BY event_ref')
                ->fetchAll(PDO::FETCH_COLUMN);
            p24Assert($read===[$tenant], 'only_own_tenant_read_'.$role.'_'.$table);
            $client->exec("SET aia.tenant_ref='".$other."'");
            $forgedRead=$client->query('SELECT tenant_ref FROM '.$table)
                ->fetchAll(PDO::FETCH_COLUMN);
            p24Assert($forgedRead===[$tenant], 'ignore_untrusted_guc_'.$role.'_'.$table);
        }
        $ownSuffix=$role==='aia_p24_writer_a'?'OWN_A':'OWN_B';
        $otherSuffix=$role==='aia_p24_writer_a'?'CROSS_A':'CROSS_B';
        p24InsertReplay($client,$tenant,$ownSuffix);
        p24Assert((int)$client->query("SELECT COUNT(*) FROM aia_p16_lab.event_replay")->fetchColumn()===2,
            'own_replay_insert_'.$role);
        p24Forbidden($client,
            'INSERT INTO aia_p16_lab.event_replay
             (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
             VALUES (:tenant,:event,:request,:nonce,:digest)',
            [
                ':tenant'=>$other, ':event'=>'SYNTHETIC_P24_EVENT_'.$otherSuffix,
                ':request'=>'SYNTHETIC_P24_REQUEST_'.$otherSuffix,
                ':nonce'=>'SYNTHETIC_P24_NONCE_'.$otherSuffix,
                ':digest'=>str_repeat('b',64),
            ], 'reject_cross_tenant_replay_'.$role);

        $stmt=$client->prepare(P24_OUTBOX_INSERT);
        $stmt->execute(p24OutboxValues($tenant,$ownSuffix));
        p24Assert((int)$client->query("SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6")->fetchColumn()===2,
            'own_outbox_insert_'.$role);
        p24Forbidden($client,P24_OUTBOX_INSERT,
            p24OutboxValues($other,$otherSuffix),'reject_cross_tenant_outbox_'.$role);
        p24Forbidden($client,"UPDATE aia_p16_lab.event_replay SET payload_hash='".
            str_repeat('b',64)."' WHERE tenant_ref=".$client->quote($tenant),
            [],'no_replay_update_grant_'.$role);
        p24Forbidden($client,'DELETE FROM public.aia_lab_n8n_outbox_p6',[],
            'no_outbox_delete_grant_'.$role);
        p24Forbidden($client,'SET ROLE '.
            ($role==='aia_p24_writer_a'?'aia_p24_writer_b':'aia_p24_writer_a'),
            [], 'cannot_assume_other_writer_'.$role);
        p24Forbidden($client,'CREATE TABLE public.aia_p24_unauthorized (id INT)',[],
            'cannot_create_table_'.$role);
        $client=null;
    }
    p24Assert((int)$admin->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn()===4,
        'only_own_tenant_replay_writes');
    p24Assert((int)$admin->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn()===4,
        'only_own_tenant_outbox_writes');
    echo "AIA_P24_RLS_WRITER_PASS checks={$checks} tenant_writers=2 tables=2 real_iam=false ai_calls=0 NO_GO\n";
} finally {
    $client=null;
    if ($admin->inTransaction()) $admin->rollBack();
    foreach ($tables as $table) {
        foreach (array_keys($roles) as $role) {
            $admin->exec('DROP POLICY IF EXISTS p24_select_'.$role.' ON '.$table);
            $admin->exec('DROP POLICY IF EXISTS p24_insert_'.$role.' ON '.$table);
        }
        $admin->exec('ALTER TABLE '.$table.' DISABLE ROW LEVEL SECURITY');
    }
    $outbox->dropTables();
    $admin->exec('DROP OWNED BY aia_p24_writer_a, aia_p24_writer_b');
    $admin->exec('DROP ROLE IF EXISTS aia_p24_writer_a, aia_p24_writer_b');
}
