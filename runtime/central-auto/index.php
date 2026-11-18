<?php
// Product-owned candidate ingress; existing durable NO_AI inbox. CI profile ONLY, no n8n dispatch.
declare(strict_types=1);
require_once __DIR__.'/strict-json.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
function deny(int $status):never {http_response_code($status);echo '{"error":"EVENT_DENIED"}';exit;}
if(getenv('AGENTS_PRIVATE_S2S_PROFILE')!=='synthetic-private-v1')deny(404);
$capture=getenv('AGENTS_PRIVATE_LEAD_CAPTURE');
// Server configuration only. Unknown modes must not silently lose the payload.
if($capture!==false&&$capture!==''&&$capture!=='immutable-v1')deny(503);
if(($_SERVER['IMAGINE_MTLS_VERIFY']??'')!=='SUCCESS'||isset($_SERVER['HTTP_COOKIE'])||isset($_SERVER['HTTP_AUTHORIZATION'])||$_SERVER['REQUEST_METHOD']!=='POST'||($_SERVER['CONTENT_TYPE']??'')!=='application/json'||!empty($_SERVER['QUERY_STRING']))deny(403);
try {
 $cert=rawurldecode($_SERVER['IMAGINE_MTLS_CERT']??'');$fp=openssl_x509_fingerprint($cert,'sha256');if(!$fp||strlen($cert)>8192)deny(403);
 $raw=file_get_contents('php://input',false,null,0,8193);if(strlen($raw)>8192)deny(413);
 $v=agents_json_decode($raw,8192,12);if(!is_array($v)||array_is_list($v))deny(403);
 $keys=array_keys($v);sort($keys);if($keys!==['company_id','envelope','payload_b64'])deny(403);
 if(!is_string($v['company_id'])||!preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$v['company_id'])||!is_string($v['payload_b64'])||!is_array($v['envelope']))deny(403);
 $payload=base64_decode($v['payload_b64'],true);if($payload===false||strlen($payload)>2048)deny(403);
 $p=agents_json_decode($payload,2048,8);if(!is_array($p)||array_is_list($p))deny(403);$k=array_keys($p);sort($k);
 if($k!==['action','company_id','event_id','lead_id','schema']||$p['schema']!=='CRM_LEAD_EVENT_V1'||$p['action']!=='lead.created'||$p['company_id']!==$v['company_id']||hash('sha256',$payload)!==($v['envelope']['payload_hash']??null))deny(403);
 // PostgreSQL UUID columns in the pinned sender serialize these identifiers
 // as strings. Accept UUID hex case/version without imposing a new JSON form.
 foreach(['company_id','event_id','lead_id'] as $field)
  if(!is_string($p[$field])||!preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/D',$p[$field]))deny(403);
 $expected=['schema','issuer','audience','product','operation','tenant_ref','event_ref','nonce_ref','request_ref','issued_at','expires_at','payload_hash','key_id','signature'];
 $actual=array_keys($v['envelope']);sort($actual);$sorted=$expected;sort($sorted);if($actual!==$sorted)deny(403);
 foreach($expected as $field)if(in_array($field,['issued_at','expires_at'],true)?!is_int($v['envelope'][$field]):!is_string($v['envelope'][$field]))deny(403);
 if($v['envelope']['event_ref']!=='SYNTHETIC_'.strtoupper(str_replace('-','',$p['event_id'])))deny(403);
} catch(Throwable){deny(403);}
try {
 $db=new PDO(getenv('AGENTS_PRIVATE_DSN'),'aia_private_transport',trim(file_get_contents(getenv('AGENTS_PRIVATE_DB_PASSWORD_FILE'))),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $db->exec("SET statement_timeout='2000ms'; SET lock_timeout='1000ms'");
 $role=$db->query('SELECT rolsuper OR rolbypassrls FROM pg_roles WHERE rolname=current_user')->fetchColumn();if($role)deny(503);
 $arguments=[$v['company_id'],$fp,json_encode($v['envelope'],JSON_THROW_ON_ERROR)];
 if($capture==='immutable-v1'){
  // One SQL statement commits the authenticated inbox and its exact content.
  // No execution authority, lease or n8n dispatch follows this capture.
  $query='SELECT auto_ia_lab.receive_company_signed_lead(CAST(? AS uuid),?,CAST(? AS jsonb),?)';
  $arguments[]=$payload;
 }else{
  $query='SELECT auto_ia_lab.receive_company_signed_no_ai(CAST(? AS uuid),?,CAST(? AS jsonb))';
 }
 $q=$db->prepare($query);$q->execute($arguments);$decision=$q->fetchColumn();
 if(!in_array($decision,['LAB_RECEIVED_NO_DELIVERY','LAB_DUPLICATE_NO_DELIVERY'],true))deny(403);
 $reply=['schema'=>'AUTO_IA_ACK_V1','company_id'=>$v['company_id'],'event_ref'=>$v['envelope']['event_ref'],'payload_hash'=>$v['envelope']['payload_hash'],'decision'=>$decision,'dispatch'=>'DISABLED'];
 $key=file_get_contents(getenv('AGENTS_PRIVATE_ACK_KEY_FILE'));if(!is_string($key)||strlen($key)<32)deny(503);
 $reply['signature']=hash_hmac('sha256',implode("\n",$reply),$key);echo json_encode($reply,JSON_THROW_ON_ERROR);
} catch(Throwable){deny(503);}
