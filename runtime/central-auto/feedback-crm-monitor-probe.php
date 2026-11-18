<?php

declare(strict_types=1);

use App\Homologation\ExecutionMonitor;
use App\Homologation\S2S\StrictJson;

/**
 * Disposable database/presentation proof, not a human authorization boundary.
 * No Laravel bootstrap, IAM configuration, S2S credential or outbound HTTP.
 * The harness supplies its two fictional company contexts explicitly.
 */
function feedbackMonitorNeed(bool $condition, string $reason = 'MONITOR_PROBE_GUARD'): void
{
    if (!$condition) {
        throw new RuntimeException($reason);
    }
}

function feedbackMonitorFiles(string $mode): void
{
    // This process must not reuse the credential-rich reconciliation container.
    feedbackMonitorNeed(!file_exists('/certs') && !is_link('/certs'));
    $allowed = $mode === 'validate' ? [] : ['/run/secrets/crm-monitor-app-password'];
    if (is_dir('/run/secrets')) {
        $entries = scandir('/run/secrets');
        feedbackMonitorNeed(is_array($entries));
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $file = '/run/secrets/'.$entry;
            feedbackMonitorNeed(in_array($file, $allowed, true) && is_file($file) && !is_link($file));
        }
    }
    foreach (['CRM_AUTO_S2S_DSN', 'CRM_AGENTS_S2S_PROFILE', 'CRM_AGENTS_EXECUTION_PROFILE',
        'CRM_PRIVATE_APP_PASSWORD', 'CRM_PRIVATE_IAM_PASSWORD', 'CRM_PRIVATE_OWNER_PASSWORD',
        'PGPASSWORD', 'PGPASSFILE', 'PGOPTIONS', 'POSTGRES_PASSWORD', 'POSTGRES_PASSWORD_FILE'] as $name) {
        feedbackMonitorNeed(in_array(getenv($name), [false, ''], true));
    }
    if ($mode === 'validate') {
        feedbackMonitorNeed(in_array(getenv('CRM_MONITOR_DSN'), [false, ''], true));
    } else {
        feedbackMonitorNeed(getenv('CRM_MONITOR_DSN')
            === 'pgsql:host=127.0.0.1;port=5433;dbname=imagine_crm_private_homologation');
        feedbackMonitorNeed(is_file('/run/secrets/crm-monitor-app-password')
            && !is_link('/run/secrets/crm-monitor-app-password'));
    }

    // Compare the installed public code with the exact Git archive pinned by CI.
    foreach (['composer.lock', 'app/Homologation/S2S/StrictJson.php',
        'app/Homologation/ExecutionMonitor.php',
        'infra/homologation/s2s/execution-monitor-candidate.sql'] as $source) {
        $archived = '/crm-source/'.$source;
        $installed = '/var/www/html/'.$source;
        feedbackMonitorNeed(is_file($archived) && !is_link($archived)
            && is_file($installed) && !is_link($installed));
        $archiveHash = hash_file('sha256', $archived);
        $installedHash = hash_file('sha256', $installed);
        feedbackMonitorNeed(is_string($archiveHash) && is_string($installedHash)
            && hash_equals($archiveHash, $installedHash));
    }
}

function feedbackMonitorDatabase(): PDO
{
    $password = file_get_contents('/run/secrets/crm-monitor-app-password');
    feedbackMonitorNeed(is_string($password) && preg_match('/\A[a-f0-9]{64}\z/D', $password) === 1);
    $db = new PDO('pgsql:host=127.0.0.1;port=5433;dbname=imagine_crm_private_homologation',
        'crm_homologation_app', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_PERSISTENT => false]);
    $state = $db->query("SELECT current_database() AS database_name, current_user AS role_name,
        session_user AS session_name, pg_catalog.current_setting('port') AS port,
        pg_catalog.current_setting('server_version_num') AS version,
        pg_catalog.current_setting('row_security') AS row_security,
        COALESCE(pg_catalog.current_setting('app.tenant_id', true), '') AS tenant_scope,
        CASE WHEN r.rolsuper OR r.rolbypassrls OR r.rolcreatedb OR r.rolcreaterole
            OR r.rolreplication OR r.rolinherit OR NOT r.rolcanlogin THEN 1 ELSE 0 END AS unsafe,
        (SELECT count(*) FROM pg_catalog.pg_auth_members m WHERE m.member=r.oid) AS memberships,
        (SELECT count(*) FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
            WHERE c.relowner=r.oid AND n.nspname IN ('public','crm_auto_service')) AS owned_relations,
        (SELECT mode FROM public.crm_private_installation WHERE id=1) AS installation
        FROM pg_catalog.pg_roles r WHERE r.rolname=current_user")->fetch(PDO::FETCH_ASSOC);
    feedbackMonitorNeed(is_array($state) && $state['database_name'] === 'imagine_crm_private_homologation'
        && $state['port'] === '5433' && preg_match('/\A16[0-9]{4}\z/D', $state['version']) === 1
        && $state['installation'] === 'synthetic-v1', 'MONITOR_CONNECTION_DENIED');
    feedbackMonitorNeed($state['role_name'] === 'crm_homologation_app'
        && $state['session_name'] === 'crm_homologation_app'
        && (string) $state['unsafe'] === '0' && (string) $state['memberships'] === '0'
        && (string) $state['owned_relations'] === '0'
        && $state['row_security'] === 'on', 'MONITOR_ROLE_DENIED');
    feedbackMonitorNeed($state['tenant_scope'] === '', 'MONITOR_CONTEXT_DENIED');
    $db->exec("SET statement_timeout='2000ms'");
    $db->exec("SET lock_timeout='1000ms'");
    $db->exec("SET idle_in_transaction_session_timeout='6000ms'");

    return $db;
}

function feedbackMonitorPermissions(PDO $db): void
{
    // The preexisting app role can edit ordinary CRM data. This proof only
    // admits a READ ONLY transaction and the one narrow S2S display function.
    $functions = $db->query("SELECT p.oid::regprocedure::text AS signature,
        p.provolatile, p.prosecdef::integer AS security_definer,
        pg_catalog.pg_get_userbyid(p.proowner) AS owner, p.proconfig::text AS configuration
        FROM pg_catalog.pg_proc p JOIN pg_catalog.pg_namespace n ON n.oid=p.pronamespace
        WHERE n.nspname='crm_auto_service'
        AND pg_catalog.has_function_privilege(current_user,p.oid,'EXECUTE')")->fetchAll(PDO::FETCH_ASSOC);
    feedbackMonitorNeed(count($functions) === 1
        && $functions[0]['signature'] === 'crm_auto_service.read_execution_monitor(uuid)'
        && $functions[0]['provolatile'] === 's'
        && (string) $functions[0]['security_definer'] === '1'
        && $functions[0]['owner'] === 'crm_auto_reader_owner'
        && in_array($functions[0]['configuration'],
            ['{"search_path=pg_catalog,public"}', '{"search_path=pg_catalog, public"}'], true),
        'MONITOR_PERMISSION_DRIFT');
    $direct = $db->query("SELECT count(*) FROM pg_catalog.pg_class c
        JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace
        WHERE n.nspname='crm_auto_service' AND c.relkind IN ('r','p','v','m','f')
        AND (pg_catalog.has_table_privilege(current_user,c.oid,'SELECT,INSERT,UPDATE,DELETE,TRUNCATE,REFERENCES,TRIGGER')
            OR pg_catalog.has_any_column_privilege(current_user,c.oid,'SELECT,INSERT,UPDATE,REFERENCES'))")
        ->fetchColumn();
    feedbackMonitorNeed((string) $direct === '0', 'MONITOR_PERMISSION_DRIFT');
}

function feedbackMonitorAbsentContext(PDO $db): void
{
    $db->exec('SAVEPOINT monitor_absent_context');
    $denied = false;
    try {
        $db->query('SELECT crm_auto_service.read_execution_monitor(NULL::uuid)')->fetchColumn();
    } catch (PDOException $error) {
        if ((string) $error->getCode() !== 'P0001'
            || !str_contains($error->getMessage(), 'EXECUTION_MONITOR_CONTEXT_DENIED')) {
            throw $error;
        }
        $denied = true;
    } finally {
        $db->exec('ROLLBACK TO SAVEPOINT monitor_absent_context');
        $db->exec('RELEASE SAVEPOINT monitor_absent_context');
    }
    feedbackMonitorNeed($denied, 'MONITOR_CONTEXT_DENIED');
}

try {
    feedbackMonitorNeed(PHP_SAPI === 'cli' && ($argc ?? 0) === 2
        && getenv('GITHUB_ACTIONS') === 'true'
        && getenv('GITHUB_REPOSITORY') === 'rafhael100/imagine-ai-agents'
        && getenv('CRM_FEEDBACK_CI') === 'synthetic-private-v1'
        && preg_match('/\A[a-f0-9]{40}\z/D', getenv('CRM_FEEDBACK_SOURCE_SHA') ?: '') === 1);
    $mode = $argv[1];
    feedbackMonitorNeed(in_array($mode, ['read', 'validate', 'isolation'], true));
    feedbackMonitorFiles($mode);
    require '/var/www/html/app/Homologation/S2S/StrictJson.php';
    require '/var/www/html/app/Homologation/ExecutionMonitor.php';

    $raw = file_get_contents('php://stdin', false, null, 0, 90001);
    feedbackMonitorNeed(is_string($raw) && strlen($raw) <= 90000, 'MONITOR_INPUT_DENIED');
    try {
        $input = StrictJson::decodeObject($raw, 90000, 4);
    } catch (JsonException) {
        throw new RuntimeException('MONITOR_INPUT_DENIED');
    }
    $fields = array_keys($input);
    sort($fields);
    feedbackMonitorNeed($fields === ($mode === 'validate'
        ? ['body_b64', 'company_id', 'event_id'] : ['company_id', 'event_id']), 'MONITOR_INPUT_DENIED');
    $company = $input['company_id'];
    $event = $input['event_id'];
    feedbackMonitorNeed(in_array($company, ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'], true)
        && ($event === null || (is_string($event)
            && preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $event) === 1)),
        'MONITOR_INPUT_DENIED');

    if ($mode === 'validate') {
        feedbackMonitorNeed(is_string($input['body_b64']) && strlen($input['body_b64']) <= 87384,
            'MONITOR_INPUT_DENIED');
        $body = base64_decode($input['body_b64'], true);
        feedbackMonitorNeed(is_string($body) && strlen($body) <= 65536
            && base64_encode($body) === $input['body_b64'], 'MONITOR_INPUT_DENIED');
        $result = ExecutionMonitor::read($body, $company, $event);
    } else {
        $db = feedbackMonitorDatabase();
        $db->beginTransaction();
        $db->exec('SET TRANSACTION READ ONLY');
        feedbackMonitorNeed($db->query('SHOW transaction_read_only')->fetchColumn() === 'on',
            'MONITOR_CONNECTION_DENIED');
        feedbackMonitorPermissions($db);
        if ($mode === 'isolation') {
            feedbackMonitorAbsentContext($db);
        }
        // This is a controlled fixture context, not evidence of a login or membership.
        $scope = $db->prepare("SELECT pg_catalog.set_config('app.tenant_id', ?, true)");
        $scope->execute([$company]);
        feedbackMonitorNeed($scope->fetchColumn() === $company, 'MONITOR_CONTEXT_DENIED');
        $statement = $db->prepare('SELECT crm_auto_service.read_execution_monitor(CAST(? AS uuid))::text');
        $statement->execute([$event]);
        $body = $statement->fetchColumn();
        feedbackMonitorNeed(is_string($body), 'MONITOR_MODEL_DENIED');
        $result = ExecutionMonitor::read($body, $company, $event);
        feedbackMonitorNeed($db->query('SHOW transaction_read_only')->fetchColumn() === 'on',
            'MONITOR_CONNECTION_DENIED');
        $db->rollBack();
        feedbackMonitorNeed(!$db->inTransaction()
            && $db->query("SELECT COALESCE(pg_catalog.current_setting('app.tenant_id',true),'')")
                ->fetchColumn() === '', 'MONITOR_CONTEXT_DENIED');
        if ($mode === 'isolation') {
            $result = ['database_role' => 'crm_homologation_app', 'transaction_read_only' => true,
                'service_capabilities_denied' => true, 'tenant_context_cleared' => true,
                'human_authorization_tested' => false];
        }
    }
    echo json_encode(['ok' => true, 'value' => $result], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        try { $db->rollBack(); } catch (Throwable) { /* The nonpersistent connection closes on exit. */ }
    }
    $reason = 'MONITOR_DATABASE_DENIED';
    if ($error instanceof JsonException || $error instanceof UnexpectedValueException) {
        $reason = 'MONITOR_MODEL_DENIED';
    } elseif ($error instanceof PDOException) {
        if (in_array((string) $error->getCode(), ['57014', '55P03'], true)) {
            $reason = 'MONITOR_DATABASE_TIMEOUT';
        } elseif (preg_match('/\bEXECUTION_MONITOR_(CONTEXT_DENIED|SOURCE_DRIFT|HISTORY_DRIFT|PROJECTION_DRIFT)\b/',
            $error->getMessage(), $match)) {
            $reason = 'MONITOR_'.$match[1];
        }
    } elseif (in_array($error->getMessage(), ['MONITOR_PROBE_GUARD', 'MONITOR_INPUT_DENIED',
        'MONITOR_CONNECTION_DENIED', 'MONITOR_ROLE_DENIED', 'MONITOR_CONTEXT_DENIED',
        'MONITOR_PERMISSION_DRIFT', 'MONITOR_MODEL_DENIED'], true)) {
        $reason = $error->getMessage();
    }
    // No SQL exception text, stack, secret, receipt wire or request fields escape.
    echo json_encode(['ok' => false, 'reason' => $reason], JSON_THROW_ON_ERROR)."\n";
}
