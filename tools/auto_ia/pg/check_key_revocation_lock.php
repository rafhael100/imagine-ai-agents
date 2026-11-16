<?php
/** Generates one signed synthetic statement; no transport, keys or SQL roles for production. */
declare(strict_types=1);
require __DIR__.'/../MockS2SEnvelopeVerifier.php';
use ImagineAI\AutomationFirst\MockS2SEnvelopeVerifier;
$now=time();$key='SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY';
$e=['schema'=>'AUTO_IA_S2S_V1','issuer'=>'imagine-crm','audience'=>'imagine-ai-agents','product'=>'imagine-crm','operation'=>'lead.ingest',
 'tenant_ref'=>'SYNTHETIC_TENANT_A','event_ref'=>'SYNTHETIC_LOCK_EVENT','nonce_ref'=>'SYNTHETIC_LOCK_NONCE','request_ref'=>'SYNTHETIC_LOCK_REQUEST',
 'issued_at'=>$now-1,'expires_at'=>$now+120,'payload_hash'=>hash('sha256','SYNTHETIC_LOCK_PAYLOAD'),'key_id'=>'SYNTHETIC_KEY_A'];
$e['signature']=hash_hmac('sha256',implode("\n",array_map(strval(...),array_values($e))),$key);
if(MockS2SEnvelopeVerifier::verify($e,$now,['SYNTHETIC_KEY_A'=>$key])['decision']!=='LAB_VERIFIED_NO_AUTHORIZATION')throw new RuntimeException('INVALID_FIXTURE');
$args=[];foreach($e as $v){if(is_int($v))$args[]=(string)$v;elseif(preg_match('/\A[A-Za-z0-9_.-]+\z/D',$v))$args[]="'".$v."'";else throw new RuntimeException('INVALID_SQL_FIXTURE');}
echo 'SELECT auto_ia_lab.receive_signed_no_ai('.implode(',',$args).") AS result \\gset\n";
echo "SELECT :'result'=:'expected' AS ok \\gset\n\\if :ok\n\\else\n\\quit 1\n\\endif\n";
