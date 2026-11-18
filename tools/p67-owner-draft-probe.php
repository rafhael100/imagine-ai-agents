<?php
declare(strict_types=1);

/** P67 disposable fixtures and bounded TLS client; never an HTTP route. */
function p67Must(bool $condition, string $code): void
{
    if (!$condition) throw new RuntimeException('P67_'.$code);
}

function p67Database(): PDO
{
    p67Must(getenv('AIA_P67_PROBE') === 'isolated-p67-v1', 'PROBE_CONTEXT');
    $password = @file_get_contents('/run/secrets/p25-password');
    p67Must(is_string($password) && preg_match('/\A[a-f0-9]{64}\z/D', $password) === 1, 'ADMIN_PASSWORD');
    $db = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci', 'postgres', $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    p67Must($db->query('SELECT current_database()')->fetchColumn() === 'imagine_agents_ci', 'DISPOSABLE_DATABASE');
    $db->exec("SET statement_timeout='3s'; SET lock_timeout='1s'; SET search_path=pg_catalog");
    return $db;
}

function p67Denied(PDO $db, string $role, string $sql): void
{
    p67Must(in_array($role, ['aia_p67_create', 'aia_p67_read', 'aia_p67_create_login', 'aia_p67_read_login'], true), 'PROBE_ROLE');
    $db->exec('SET SESSION AUTHORIZATION '.$role);
    $denied = false;
    try { $db->query($sql); }
    catch (PDOException $error) { $denied = $error->getCode() === '42501'; }
    finally { $db->exec('RESET SESSION AUTHORIZATION'); }
    p67Must($denied, 'DIRECT_PRIVILEGE_NOT_DENIED');
}

try {
    p67Must(PHP_SAPI === 'cli' && is_file('/.dockerenv')
        && getenv('GITHUB_ACTIONS') === 'true'
        && getenv('GITHUB_REPOSITORY') === 'rafhael100/imagine-ai-agents'
        && getenv('AIA_P67_PROBE') === 'isolated-p67-v1', 'CLI_CI_ONLY');
    $raw = stream_get_contents(STDIN, 40001);
    p67Must(is_string($raw) && strlen($raw) <= 40000, 'PROBE_INPUT_LIMIT');
    $input = $raw === '' ? [] : json_decode($raw, true, 20, JSON_THROW_ON_ERROR);
    p67Must(is_array($input), 'PROBE_INPUT');
    $mode = $argv[1] ?? '';
    if ($mode === 'seed') {
        $db = p67Database();
        foreach (['fingerprint_a','fingerprint_b','create_password','read_password',
            'create_a_key','read_a_key','create_b_key','read_b_key'] as $field) {
            p67Must(is_string($input[$field] ?? null)
                && preg_match('/\A[a-f0-9]{64}\z/D', $input[$field]) === 1, 'FIXTURE_FIELD');
        }
        foreach (['create', 'read'] as $operation) {
            $login = 'aia_p67_'.$operation.'_login';
            p67Must((int) $db->query('SELECT count(*) FROM pg_roles WHERE rolname='.$db->quote($login))->fetchColumn() === 0, 'NEW_LOGIN');
            $db->exec('CREATE ROLE '.$login.' LOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '
                .$db->quote($input[$operation.'_password']));
            $db->exec('GRANT aia_p67_'.$operation.' TO '.$login.' WITH INHERIT FALSE, SET TRUE');
        }
        $db->beginTransaction();
        $expires = time() + 1800;
        foreach (['a' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'b' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'] as $suffix => $company) {
            $stmt = $db->prepare('INSERT INTO aia_p67_owner.companies(company_id,active,revision) VALUES(?::uuid,true,1)');
            $stmt->execute([$company]);
            foreach (['create', 'read'] as $operation) {
                $keyId = 'p67_'.$operation.'_'.$suffix;
                $scope = 'crm.rule.draft.'.$operation;
                $stmt = $db->prepare('INSERT INTO aia_p67_owner.mac_keys(key_id,operation,secret,active,revision,expires_at) VALUES(?,?,?,true,1,?)');
                $stmt->execute([$keyId, $scope, $input[$operation.'_'.$suffix.'_key'], $expires]);
                $stmt = $db->prepare('INSERT INTO aia_p67_owner.service_grants(company_id,fingerprint,operation,key_id,active,revision,expires_at) VALUES(?::uuid,?,?,?,true,1,?)');
                $stmt->execute([$company, $input['fingerprint_'.$suffix], $scope, $keyId, $expires]);
            }
        }
        $db->commit();
        $result = ['schema' => 'P67_DISPOSABLE_SERVICE_FIXTURE_V1', 'human_crm_session_verified' => false];
    } elseif ($mode === 'stats') {
        $db = p67Database();
        $rows = [];
        foreach (['drafts' => 'company_id,draft_ref', 'nonces' => 'company_id,operation,nonce', 'audit' => 'id'] as $table => $order) {
            $rows[$table] = $db->query('SELECT to_jsonb(t)::text FROM aia_p67_owner.'.$table.' t ORDER BY '.$order)->fetchAll(PDO::FETCH_COLUMN);
        }
        $result = ['drafts' => count($rows['drafts']), 'nonces' => count($rows['nonces']), 'audit' => count($rows['audit']),
            'draft_fingerprint' => hash('sha256', json_encode($rows['drafts'], JSON_THROW_ON_ERROR)),
            'fingerprint' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    } elseif ($mode === 'permissions') {
        $db = p67Database();
        foreach (['owner', 'create', 'read'] as $name) {
            $role = 'aia_p67_'.$name;
            p67Must($db->query("SELECT NOT(rolcanlogin OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname=".$db->quote($role))->fetchColumn() === true, 'EXECUTOR_ROLE_PROFILE');
        }
        foreach (['create', 'read'] as $operation) {
            $role = 'aia_p67_'.$operation;
            $login = $role.'_login';
            p67Must($db->query("SELECT rolcanlogin AND NOT(rolinherit OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname=".$db->quote($login))->fetchColumn() === true, 'LOGIN_ROLE_PROFILE');
            p67Must((int) $db->query('SELECT count(*) FROM pg_auth_members WHERE member='.$db->quote($login).'::regrole')->fetchColumn() === 1, 'EXACT_LOGIN_MEMBERSHIP');
            p67Must((int) $db->query('SELECT count(*) FROM pg_auth_members WHERE member='.$db->quote($login).'::regrole AND roleid='.$db->quote($role).'::regrole AND NOT admin_option AND NOT inherit_option AND set_option')->fetchColumn() === 1, 'SCOPED_LOGIN_MEMBERSHIP');
            $fn = 'aia_p67_owner.'.$operation.'_draft(text,jsonb)';
            p67Must($db->query('SELECT prosecdef AND pg_get_userbyid(proowner)=\'aia_p67_owner\' AND proconfig @> ARRAY[\'search_path=pg_catalog, aia_p67_owner\'] FROM pg_proc WHERE oid='.$db->quote($fn).'::regprocedure')->fetchColumn() === true, 'FIXED_FUNCTION_OWNER_PATH');
            foreach (['companies','mac_keys','service_grants','drafts','nonces','audit'] as $table) {
                p67Must($db->query("SELECT relrowsecurity AND relforcerowsecurity AND pg_get_userbyid(relowner)='aia_p67_owner' FROM pg_class WHERE oid=".$db->quote('aia_p67_owner.'.$table).'::regclass')->fetchColumn() === true, 'FORCED_RLS');
                p67Denied($db, $role, 'SELECT * FROM aia_p67_owner.'.$table);
                p67Denied($db, $login, 'SELECT * FROM aia_p67_owner.'.$table);
            }
            $db->exec("SET aia_p67_owner.company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'");
            p67Denied($db, $role, 'UPDATE aia_p67_owner.service_grants SET active=true');
            p67Denied($db, $role, 'SET ROLE aia_p67_owner');
            p67Denied($db, $role, "SELECT aia_p67_owner.process_request(repeat('a',64),'crm.rule.draft.create','{}'::jsonb)");
            $other = $operation === 'create' ? 'read' : 'create';
            p67Denied($db, $role, "SELECT aia_p67_owner.{$other}_draft(repeat('a',64),'{}'::jsonb)");
            p67Denied($db, $login, "SELECT aia_p67_owner.{$operation}_draft(repeat('a',64),'{}'::jsonb)");
        }
        $db->exec('SET SESSION AUTHORIZATION aia_p67_owner');
        p67Must((int) $db->query('SELECT count(*) FROM aia_p67_owner.companies')->fetchColumn() === 1
            && (int) $db->query("SELECT count(*) FROM aia_p67_owner.companies WHERE company_id='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'")->fetchColumn() === 0,
            'RLS_COMPANY_BOUNDARY');
        $db->exec('RESET SESSION AUTHORIZATION');
        $result = ['execute_only' => true, 'roles_separate' => true, 'direct_tables_denied' => true, 'forced_rls' => true];
    } elseif ($mode === 'ready') {
        $result = [];
        foreach (['create' => 9000, 'read' => 9001, 'https' => 8443] as $name => $port) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.5);
            $result[$name] = is_resource($socket);
            if (is_resource($socket)) fclose($socket);
        }
    } elseif ($mode === 'egress') {
        $socket = @fsockopen('192.0.2.1', 443, $errno, $error, 0.5);
        p67Must(!is_resource($socket), 'EGRESS_NOT_BLOCKED');
        $result = ['namespace_egress_denied' => true];
    } elseif ($mode === 'unauthenticated-db') {
        $denied = false;
        try {
            new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci;connect_timeout=2', 'aia_p67_create_login', 'SYNTHETIC_WRONG_PASSWORD', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException) { $denied = true; }
        p67Must($denied, 'DATABASE_PASSWORD_REQUIRED');
        $result = ['database_password_required' => true];
    } elseif ($mode === 'http') {
        p67Must(in_array($input['path'] ?? null, ['/private/owner-drafts/create','/private/owner-drafts/read',
            '/private/owner-drafts/create?x=1','/private/owner-drafts/read?x=1'], true), 'HTTP_PATH');
        p67Must(in_array($input['method'] ?? null, ['POST','GET','HEAD','PUT'], true), 'HTTP_METHOD');
        $body = base64_decode($input['body_b64'] ?? '', true);
        p67Must(is_string($body) && strlen($body) <= 17000, 'HTTP_BODY');
        p67Must(in_array($input['content_type'] ?? null, ['application/json','text/plain','application/json; charset=utf-8'], true), 'HTTP_CONTENT_TYPE');
        $headers = ['Host: agents-ci.internal', 'Connection: close', 'Content-Type: '.$input['content_type']];
        p67Must(is_array($input['headers'] ?? []), 'HTTP_HEADERS');
        foreach ($input['headers'] ?? [] as $header) {
            p67Must(is_string($header) && strlen($header) <= 4096 && !str_contains($header, "\r") && !str_contains($header, "\n"), 'HTTP_HEADER');
            $headers[] = $header;
        }
        $ssl = ['cafile' => '/certs/ca.crt', 'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => 'agents-ci.internal', 'allow_self_signed' => false, 'SNI_enabled' => true];
        if (($input['client_certificate'] ?? null) === true) {
            $ssl['local_cert'] = '/certs/client.crt'; $ssl['local_pk'] = '/certs/client.key';
        }
        $context = stream_context_create(['ssl' => $ssl, 'http' => ['method' => $input['method'], 'header' => $headers,
            'content' => $body, 'timeout' => 5.0, 'ignore_errors' => true, 'follow_location' => 0,
            'max_redirects' => 0, 'protocol_version' => 1.1]]);
        $http_response_header = [];
        $response = @file_get_contents('https://127.0.0.1:8443'.$input['path'], false, $context, 0, 16385);
        $status = 0; $cache = ''; $type = '';
        foreach ($http_response_header as $header) {
            if (preg_match('/\AHTTP\/1\.[01] ([0-9]{3})\b/', $header, $match)) $status = (int) $match[1];
            if (str_starts_with(strtolower($header), 'cache-control:')) $cache = trim(substr($header, 14));
            if (str_starts_with(strtolower($header), 'content-type:')) $type = trim(substr($header, 13));
        }
        p67Must($response === false || strlen($response) <= 16384, 'HTTP_RESPONSE_LIMIT');
        $result = ['transport_ok' => is_string($response), 'status' => $status, 'cache_control' => $cache,
            'content_type' => $type, 'body_b64' => base64_encode(is_string($response) ? $response : '')];
    } else { throw new RuntimeException('P67_MODE'); }
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    $code = $error instanceof RuntimeException && preg_match('/\AP67_[A-Z0-9_]+\z/D', $error->getMessage()) === 1
        ? $error->getMessage() : 'P67_RUNTIME';
    fwrite(STDERR, 'P67_PROBE_DENIED_'.$code."\n");
    exit(1);
}
