<?php
declare(strict_types=1);
/** Private, read-only execution observation; P27 remains receipt-only. */
require_once __DIR__.'/execution-contract.php';
use ImagineAI\ExecutionStatus\ExecutionStatusContract;

function execution_status_deny(int $status):never
{http_response_code($status);echo '{"error":"EXECUTION_STATUS_DENIED"}';exit;}

header('Content-Type: application/json');header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(getenv('AGENTS_PRIVATE_S2S_PROFILE')!=='synthetic-private-v1')execution_status_deny(404);
if(($_SERVER['IMAGINE_MTLS_VERIFY']??'')!=='SUCCESS'
    ||isset($_SERVER['HTTP_COOKIE'])||isset($_SERVER['HTTP_AUTHORIZATION'])
    ||isset($_SERVER['HTTP_CONTENT_ENCODING'])||($_SERVER['REQUEST_METHOD']??'')!=='POST'
    ||($_SERVER['CONTENT_TYPE']??'')!=='application/json'||($_SERVER['QUERY_STRING']??'')!=='')
    execution_status_deny(403);
try{
    $certificate=rawurldecode($_SERVER['IMAGINE_MTLS_CERT']??'');
    if(strlen($certificate)>8192)execution_status_deny(403);
    $fp=openssl_x509_fingerprint($certificate,'sha256');
    if(!is_string($fp)||preg_match('/\A[a-f0-9]{64}\z/D',$fp)!==1)execution_status_deny(403);
    $raw=file_get_contents('php://input',false,null,0,4097);
    if(!is_string($raw))execution_status_deny(403);
    $q=ExecutionStatusContract::query($raw);
}catch(Throwable){execution_status_deny(403);}
try{
    $keyPath=getenv('AGENTS_PRIVATE_EXECUTION_KEY_FILE');
    $keyId=getenv('AGENTS_PRIVATE_EXECUTION_KEY_ID');
    if($keyPath!=='/certs/agents-execution.key'||$keyId!=='SYNTHETIC_EXECUTION_STATUS_KEY'
        ||!is_file($keyPath)||is_link($keyPath))execution_status_deny(503);
    $key=file_get_contents($keyPath);
    if(!is_string($key)||strlen($key)<32||strlen($key)>256||!extension_loaded('sodium'))execution_status_deny(503);
    $password=trim(file_get_contents(getenv('AGENTS_PRIVATE_EXECUTION_DB_PASSWORD_FILE')));
    $db=new PDO(getenv('AGENTS_PRIVATE_DSN'),'aia_private_execution_login',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if($db->query('SELECT current_user')->fetchColumn()!=='aia_private_execution_login'
        ||$db->query('SELECT rolcanlogin AND NOT(rolinherit OR rolsuper OR rolbypassrls '
            .'OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname=current_user')
            ->fetchColumn()!==true)execution_status_deny(503);
    $db->beginTransaction();
    // A fixed snapshot prevents observations assembled from different commits.
    // Grant/key/link row locks also fence concurrent revocation through signing.
    $db->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ; SET LOCAL ROLE aia_private_execution; "
        ."SET LOCAL statement_timeout='1500ms'; SET LOCAL lock_timeout='750ms'");
    if($db->query('SELECT rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls '
        .'OR rolcreatedb OR rolcreaterole OR rolreplication FROM pg_roles WHERE rolname=current_user')
        ->fetchColumn()!==false){$db->rollBack();execution_status_deny(503);}
    $stmt=$db->prepare('SELECT auto_ia_lab.read_company_execution_snapshot(CAST(? AS uuid),?,CAST(? AS jsonb))');
    $stmt->execute([$q['company_id'],$fp,json_encode($q,JSON_THROW_ON_ERROR)]);
    $record=$stmt->fetchColumn();
    if(!is_string($record)){$db->rollBack();execution_status_deny(403);}
    $snapshot=agents_json_decode($record,700000,16);
    if(!is_array($snapshot)||!is_int($snapshot['auth_expires_at']??null)){
        $db->rollBack();execution_status_deny(503);
    }
    $facts=ExecutionStatusContract::project($snapshot,$q);
    $now=time();
    if($q['issued_at']<$now-30||$q['issued_at']>$now+5
        ||$q['expires_at']<=$now||$snapshot['auth_expires_at']<=$now){
        $db->rollBack();execution_status_deny(403);
    }
    $reply=ExecutionStatusContract::reply($facts,$q,$keyId,$key,$now,$snapshot['auth_expires_at']);
    $body=json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if(strlen($body)>4096){$db->rollBack();execution_status_deny(503);}
    $db->commit();echo $body;
}catch(Throwable){
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    // Includes serialization failure after a concurrent revocation, lock/query
    // timeout, missing schema and corrupt evidence. No false UNKNOWN is signed.
    execution_status_deny(503);
}
