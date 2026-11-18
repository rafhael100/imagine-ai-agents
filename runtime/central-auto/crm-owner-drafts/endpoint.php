<?php
declare(strict_types=1);

use Imagine\Agents\Integration\Crm\OwnerDraftContract;

require_once __DIR__ . '/OwnerDraftContract.php';

function owner_draft_deny(int $status): never
{
    http_response_code($status);
    echo '{"error":"OWNER_DRAFT_DENIED"}';
    exit;
}

/** The entrypoint supplies the operation; no header/body can choose a DB role. */
function owner_draft_handle(string $operation): never
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    if (getenv('AGENTS_PRIVATE_CRM_OWNER_DRAFTS') !== 'owner-rule-draft-v1') {
        owner_draft_deny(404);
    }
    $create = $operation === OwnerDraftContract::CREATE;
    $path = '/private/owner-drafts/' . ($create ? 'create' : 'read');
    if (!in_array($operation, [OwnerDraftContract::CREATE, OwnerDraftContract::READ], true)
        || ($_SERVER['IMAGINE_MTLS_VERIFY'] ?? '') !== 'SUCCESS'
        || isset($_SERVER['HTTP_COOKIE']) || isset($_SERVER['HTTP_AUTHORIZATION'])
        || isset($_SERVER['HTTP_CONTENT_ENCODING'])
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json'
        || ($_SERVER['QUERY_STRING'] ?? '') !== ''
        || ($_SERVER['REQUEST_URI'] ?? '') !== $path) {
        owner_draft_deny(403);
    }
    try {
        $encoded = $_SERVER['IMAGINE_MTLS_CERT'] ?? '';
        if (!is_string($encoded) || strlen($encoded) > 16384) {
            owner_draft_deny(403);
        }
        $certificate = rawurldecode($encoded);
        if (strlen($certificate) > 8192) {
            owner_draft_deny(403);
        }
        $fingerprint = openssl_x509_fingerprint($certificate, 'sha256');
        if (!is_string($fingerprint) || preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint) !== 1) {
            owner_draft_deny(403);
        }
        $raw = file_get_contents('php://input', false, null, 0, 8193);
        if (!is_string($raw)) {
            owner_draft_deny(403);
        }
        $request = OwnerDraftContract::request($raw, $operation, time());
    } catch (Throwable) {
        owner_draft_deny(403);
    }

    $db = null;
    try {
        $dsn = getenv('AGENTS_PRIVATE_CRM_DRAFT_DSN');
        if (!in_array($dsn, ['pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
            'pgsql:host=agents-db;port=5432;dbname=imagine_agents_ci',
            'pgsql:host=agents-db;port=5432;dbname=imagine_agents_ci;sslmode=verify-full;sslrootcert=/certs/ca.crt'], true)
            || getenv('AGENTS_PRIVATE_CRM_DRAFT_DB_PASSWORD_FILE') !== '/run/secrets/owner-draft-db-password') {
            owner_draft_deny(503);
        }
        $password = file_get_contents('/run/secrets/owner-draft-db-password');
        if (!is_string($password) || preg_match('/\A[a-f0-9]{64}\z/D', $password) !== 1) {
            owner_draft_deny(503);
        }
        $role = $create ? 'aia_p67_create' : 'aia_p67_read';
        $login = $role . '_login';
        $db = new PDO($dsn, $login, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_PERSISTENT => false]);
        unset($password);
        if ($db->query('SELECT current_user')->fetchColumn() !== $login
            || $db->query('SELECT current_database()')->fetchColumn() !== 'imagine_agents_ci'
            || $db->query('SELECT rolcanlogin AND NOT (rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole'
                . ' OR rolreplication OR rolinherit) FROM pg_roles WHERE rolname=current_user')->fetchColumn() !== true) {
            owner_draft_deny(503);
        }
        $memberships = $db->query('SELECT r.rolname,m.admin_option,m.inherit_option,m.set_option'
            . ' FROM pg_auth_members m JOIN pg_roles r ON r.oid=m.roleid'
            . ' JOIN pg_roles u ON u.oid=m.member WHERE u.rolname=current_user ORDER BY r.rolname')->fetchAll(PDO::FETCH_ASSOC);
        if ($memberships !== [['rolname' => $role, 'admin_option' => false,
            'inherit_option' => false, 'set_option' => true]]) {
            owner_draft_deny(503);
        }
        $db->beginTransaction();
        $db->exec("SET LOCAL ROLE $role; SET LOCAL statement_timeout='3000ms'; SET LOCAL lock_timeout='1500ms'");
        if ($db->query('SELECT rolcanlogin OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole'
            . ' OR rolreplication OR rolinherit FROM pg_roles WHERE rolname=current_user')->fetchColumn() !== false
            || (string) $db->query('SELECT count(*) FROM pg_auth_members m JOIN pg_roles r ON r.oid=m.member'
                . ' WHERE r.rolname=current_user')->fetchColumn() !== '0') {
            $db->rollBack();
            owner_draft_deny(503);
        }
        $function = $create ? 'create_draft' : 'read_draft';
        $statement = $db->prepare("SELECT aia_p67_owner.$function(?, CAST(? AS jsonb))");
        $statement->execute([$fingerprint, OwnerDraftContract::canonical($request)]);
        $record = $statement->fetchColumn();
        if (!is_string($record)) {
            $db->rollBack();
            owner_draft_deny(403);
        }
        $reply = OwnerDraftContract::response(json_decode($record, true, 8, JSON_THROW_ON_ERROR),
            $request['authorization'], time());
        $body = OwnerDraftContract::canonical($reply);
        $db->commit();
        // Commit or the response may race a lost connection/deadline. This is
        // indeterminate to CRM, never proof that a remote draft was rolled back.
        if ($reply['expires_at'] <= time()) {
            owner_draft_deny(503);
        }
        echo $body;
        exit;
    } catch (Throwable) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        owner_draft_deny(503);
    }
}
