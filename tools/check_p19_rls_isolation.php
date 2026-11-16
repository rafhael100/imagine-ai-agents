<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticAtomicStager.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSyntheticAtomicStager;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if (getenv('AIA_P16_PG_LAB')!=='true' || getenv('AIA_P6_PG_LAB')!=='true'
    || getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('DB_HOST')!=='127.0.0.1'
    || getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P19_NOT_EPHEMERAL_CI');
}
$db = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgSyntheticAtomicStager($db);
$stager->createTables();
$outbox=new PgN8nOutboxLab($db);
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$checks=0;
function p19Check(bool $ok,string $case):void {
    global $checks;
    $checks++;
    if(!$ok) throw new RuntimeException('P19_RLS_FAILED_'.$case);
}
function p19TenantVisible(PDO $db,string $tenant):void {
    foreach (['aia_p16_lab.event_replay','public.aia_lab_n8n_outbox_p6'] as $table) {
        $visible=$db->query('SELECT tenant_ref FROM '.$table.' ORDER BY event_ref')
            ->fetchAll(PDO::FETCH_COLUMN);
        p19Check($visible===[$tenant], 'only_owned_rows_'.$table.'_'.$tenant);
        $other=$tenant==='SYNTHETIC_TENANT_A'?'SYNTHETIC_TENANT_B':'SYNTHETIC_TENANT_A';
        $probe=$db->query("SELECT COUNT(*) FROM ".$table." WHERE tenant_ref='".$other."'")
            ->fetchColumn();
        p19Check((int)$probe===0,'cross_tenant_hidden_'.$table.'_'.$tenant);
    }
}
try {
    foreach (['SYNTHETIC_TENANT_A','SYNTHETIC_TENANT_B'] as $tenant) {
        [$p7,$signed,$policy]=P18Fixture::make($tenant,'SYNTHETIC_P19_EVENT');
        $seen=[];
        $result=$stager->stage($p7,$signed,$policy,P18Fixture::NOW,
            ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
        p19Check($result['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','seed_'.$tenant);
    }
    // No LOGIN / no GRANT to application; roles exist strictly for CI simulation.
    $db->exec('CREATE ROLE aia_p19_tenant_a NOLOGIN');
    $db->exec('CREATE ROLE aia_p19_tenant_b NOLOGIN');
    $db->exec('GRANT USAGE ON SCHEMA aia_p16_lab TO aia_p19_tenant_a, aia_p19_tenant_b');
    $db->exec('GRANT USAGE ON SCHEMA public TO aia_p19_tenant_a, aia_p19_tenant_b');
    foreach (['aia_p16_lab.event_replay','public.aia_lab_n8n_outbox_p6'] as $table) {
        $db->exec('GRANT SELECT ON '.$table.' TO aia_p19_tenant_a, aia_p19_tenant_b');
        $db->exec('ALTER TABLE '.$table.' ENABLE ROW LEVEL SECURITY');
        $db->exec('ALTER TABLE '.$table.' FORCE ROW LEVEL SECURITY');
        $db->exec("CREATE POLICY p19_select_a ON ".$table."
            FOR SELECT TO aia_p19_tenant_a USING (tenant_ref = 'SYNTHETIC_TENANT_A')");
        $db->exec("CREATE POLICY p19_select_b ON ".$table."
            FOR SELECT TO aia_p19_tenant_b USING (tenant_ref = 'SYNTHETIC_TENANT_B')");
    }
    foreach ([
        'aia_p19_tenant_a'=>'SYNTHETIC_TENANT_A',
        'aia_p19_tenant_b'=>'SYNTHETIC_TENANT_B',
    ] as $role=>$tenant) {
        $db->exec('SET ROLE '.$role);
        p19Check($db->query('SELECT current_user')->fetchColumn()===$role, 'role_context');
        p19TenantVisible($db,$tenant);
        $pretend=$tenant==='SYNTHETIC_TENANT_A'?'SYNTHETIC_TENANT_B':'SYNTHETIC_TENANT_A';
        $db->exec("SET aia.tenant_ref='".$pretend."'");
        p19TenantVisible($db,$tenant); // Caller-set GUC must never cross RLS boundaries.
        $blocked=false;
        try {
            $db->exec("INSERT INTO aia_p16_lab.event_replay
                (tenant_ref,event_ref,request_ref,nonce_ref,payload_hash)
                VALUES ('SYNTHETIC_TENANT_A','SYNTHETIC_FORGED','SYNTHETIC_FAKE_REQUEST',
                    'SYNTHETIC_FAKE_NONCE','".str_repeat('a',64)."')");
        }catch(PDOException){$blocked=true;}
        p19Check($blocked,'role_cannot_write_claim');
        $blocked=false;
        try {
            $db->exec('SET ROLE '.($role==='aia_p19_tenant_a'?'aia_p19_tenant_b':'aia_p19_tenant_a'));
        }catch(PDOException){$blocked=true;}
        p19Check($blocked,'cannot_impersonate_other_role');
        $db->exec('RESET ROLE');
    }
    echo "AIA_P19_RLS_READ_ISOLATION_PASS checks={$checks} tenant_roles=2 tables=2 ai_calls=0 operational_identity=false NO_GO\n";
} finally {
    $db->exec('RESET ROLE');
    if ($db->inTransaction()) $db->rollBack();
    // Never contaminate following tests; this database is CI-disposable anyway.
    foreach (['aia_p16_lab.event_replay','public.aia_lab_n8n_outbox_p6'] as $table) {
        $db->exec('DROP POLICY IF EXISTS p19_select_a ON '.$table);
        $db->exec('DROP POLICY IF EXISTS p19_select_b ON '.$table);
        $db->exec('ALTER TABLE '.$table.' DISABLE ROW LEVEL SECURITY');
    }
    $outbox->dropTables();
    $db->exec('DROP OWNED BY aia_p19_tenant_a, aia_p19_tenant_b');
    $db->exec('DROP ROLE IF EXISTS aia_p19_tenant_a, aia_p19_tenant_b');
}
