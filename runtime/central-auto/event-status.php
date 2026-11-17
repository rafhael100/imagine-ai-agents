<?php
declare(strict_types=1);
/** P27 receipt-only candidate. No payload, outbox projection or dispatch. */

function receipt_space(string $raw,int &$at):void {
    while(isset($raw[$at])&&str_contains(" \t\r\n",$raw[$at]))$at++;
}
function receipt_string(string $raw,int &$at):string {
    $start=$at;
    if(($raw[$at]??'')!=='"')throw new InvalidArgumentException('STRING');
    $at++;
    while(isset($raw[$at])){
        $char=$raw[$at++];
        if($char==='"'){
            $value=json_decode(substr($raw,$start,$at-$start),true,2,JSON_THROW_ON_ERROR);
            if(!is_string($value))throw new InvalidArgumentException('STRING');
            return $value;
        }
        if(ord($char)<32)throw new InvalidArgumentException('CONTROL');
        if($char==='\\'){
            $escaped=$raw[$at++]??'';
            if($escaped==='u'){
                if(!preg_match('/\A[0-9a-fA-F]{4}\z/D',substr($raw,$at,4)))
                    throw new InvalidArgumentException('ESCAPE');
                $at+=4;
            }elseif($escaped===''||!str_contains('"\\/bfnrt',$escaped)){
                throw new InvalidArgumentException('ESCAPE');
            }
        }
    }
    throw new InvalidArgumentException('UNTERMINATED');
}
function receipt_query(string $raw):array {
    // Flat closed JSON permits a small parser which rejects duplicate decoded
    // names, including Unicode-escaped duplicates, before jsonb can erase them.
    if(strlen($raw)>4096)throw new InvalidArgumentException('SIZE');
    $at=0;receipt_space($raw,$at);
    if(($raw[$at++]??'')!=='{')throw new InvalidArgumentException('OBJECT');
    $query=[];
    do{
        receipt_space($raw,$at);$name=receipt_string($raw,$at);
        if(array_key_exists($name,$query))throw new InvalidArgumentException('DUPLICATE');
        receipt_space($raw,$at);
        if(($raw[$at++]??'')!==':')throw new InvalidArgumentException('COLON');
        receipt_space($raw,$at);
        if(($raw[$at]??'')==='"'){$value=receipt_string($raw,$at);}
        else{
            $start=$at;
            while(isset($raw[$at])&&$raw[$at]>='0'&&$raw[$at]<='9')$at++;
            $number=substr($raw,$start,$at-$start);
            if(!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',$number))
                throw new InvalidArgumentException('INTEGER');
            $value=(int)$number;
        }
        $query[$name]=$value;receipt_space($raw,$at);
        $delimiter=$raw[$at++]??'';
        if($delimiter==='}')break;
        if($delimiter!==','||count($query)>=15)throw new InvalidArgumentException('DELIMITER');
    }while(true);
    receipt_space($raw,$at);
    if($at!==strlen($raw))throw new InvalidArgumentException('TRAILING');
    $expected=['schema','issuer','audience','product','operation','company_id',
        'tenant_ref','event_ref','payload_hash','request_ref','nonce_ref',
        'issued_at','expires_at','key_id','signature'];
    $actual=array_keys($query);sort($actual);$keys=$expected;sort($keys);
    if($actual!==$keys)throw new InvalidArgumentException('KEYS');
    foreach($expected as $field){
        if(in_array($field,['issued_at','expires_at'],true)?!is_int($query[$field]):!is_string($query[$field]))
            throw new InvalidArgumentException('TYPE');
    }
    foreach(['schema'=>'AUTO_IA_RECEIPT_QUERY_V1','issuer'=>'imagine-crm',
             'audience'=>'imagine-ai-agents','product'=>'imagine-crm',
             'operation'=>'event.receipt.read'] as $field=>$value){
        if($query[$field]!==$value)throw new InvalidArgumentException('DOMAIN');
    }
    if(!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$query['company_id']))
        throw new InvalidArgumentException('COMPANY');
    foreach(['tenant_ref','event_ref','request_ref','nonce_ref','key_id'] as $field){
        if(!preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$query[$field]))
            throw new InvalidArgumentException('REFERENCE');
    }
    foreach(['payload_hash','signature'] as $field){
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$query[$field]))
            throw new InvalidArgumentException('DIGEST');
    }
    return $query;
}
function receipt_deny(int $status):never {
    http_response_code($status);echo '{"error":"RECEIPT_DENIED"}';exit;
}

if(PHP_SAPI==='cli'&&defined('P27_RECEIPT_LIBRARY_ONLY')&&P27_RECEIPT_LIBRARY_ONLY===true)return;
header('Content-Type: application/json');header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(getenv('AGENTS_PRIVATE_S2S_PROFILE')!=='synthetic-private-v1')receipt_deny(404);
if(($_SERVER['IMAGINE_MTLS_VERIFY']??'')!=='SUCCESS'
    ||isset($_SERVER['HTTP_COOKIE'])||isset($_SERVER['HTTP_AUTHORIZATION'])
    ||isset($_SERVER['HTTP_CONTENT_ENCODING'])
    ||($_SERVER['REQUEST_METHOD']??'')!=='POST'
    ||($_SERVER['CONTENT_TYPE']??'')!=='application/json'
    ||($_SERVER['QUERY_STRING']??'')!=='')receipt_deny(403);
try{
    $certificate=rawurldecode($_SERVER['IMAGINE_MTLS_CERT']??'');
    if(strlen($certificate)>8192)receipt_deny(403);
    $fingerprint=openssl_x509_fingerprint($certificate,'sha256');
    if(!is_string($fingerprint)||!preg_match('/\A[a-f0-9]{64}\z/D',$fingerprint))receipt_deny(403);
    $raw=file_get_contents('php://input',false,null,0,4097);
    if(!is_string($raw))receipt_deny(403);
    $query=receipt_query($raw);
}catch(Throwable){receipt_deny(403);}
try{
    $key=file_get_contents(getenv('AGENTS_PRIVATE_RECEIPT_KEY_FILE'));
    $keyId=getenv('AGENTS_PRIVATE_RECEIPT_KEY_ID');
    if(!is_string($key)||strlen($key)<32||strlen($key)>256||!is_string($keyId)
        ||!preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$keyId))receipt_deny(503);
    $password=trim(file_get_contents(getenv('AGENTS_PRIVATE_RECEIPT_DB_PASSWORD_FILE')));
    $db=new PDO(getenv('AGENTS_PRIVATE_DSN'),'aia_private_receipt_login',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if($db->query('SELECT current_user')->fetchColumn()!=='aia_private_receipt_login')receipt_deny(503);
    $db->beginTransaction();
    $db->exec("SET LOCAL ROLE aia_private_receipt; SET LOCAL statement_timeout='1500ms'; SET LOCAL lock_timeout='750ms'");
    if($db->query('SELECT rolcanlogin OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication FROM pg_roles WHERE rolname=current_user')->fetchColumn()!==false){
        $db->rollBack();receipt_deny(503);
    }
    $stmt=$db->prepare('SELECT auto_ia_lab.read_company_event_receipt(CAST(? AS uuid),?,CAST(? AS jsonb))');
    $stmt->execute([$query['company_id'],$fingerprint,json_encode($query,JSON_THROW_ON_ERROR)]);
    $record=$stmt->fetchColumn();
    if(!is_string($record)){$db->rollBack();receipt_deny(403);}
    $record=json_decode($record,true,4,JSON_THROW_ON_ERROR);
    $expected=['event_ref','payload_hash','state','tenant_ref'];
    $actual=array_keys($record);sort($actual);
    if($actual!==$expected||$record['state']!=='RECEIVED'
        ||$record['tenant_ref']!==$query['tenant_ref']||$record['event_ref']!==$query['event_ref']
        ||$record['payload_hash']!==$query['payload_hash']){
        $db->rollBack();receipt_deny(503);
    }
    $now=time();$expires=min($now+30,$query['expires_at']);
    if($expires<=$now){$db->rollBack();receipt_deny(403);}
    $reply=['schema'=>'AUTO_IA_EVENT_RECEIPT_V1','issuer'=>'imagine-ai-agents',
        'audience'=>'imagine-crm','company_id'=>$query['company_id'],
        'tenant_ref'=>$record['tenant_ref'],'event_ref'=>$record['event_ref'],
        'payload_hash'=>$record['payload_hash'],'state'=>'RECEIVED','dispatch'=>'DISABLED',
        'processing'=>'NOT_DISPATCHED','request_ref'=>$query['request_ref'],
        'nonce_ref'=>$query['nonce_ref'],'issued_at'=>$now,'expires_at'=>$expires,'key_id'=>$keyId];
    $reply['signature']=hash_hmac('sha256',"IMAGINE_AGENTS_EVENT_RECEIPT_V1\n".implode("\n",$reply),$key);
    $body=json_encode($reply,JSON_THROW_ON_ERROR);
    $db->commit();echo $body;
}catch(Throwable){
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    receipt_deny(503);
}
