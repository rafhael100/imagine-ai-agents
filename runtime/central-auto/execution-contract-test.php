<?php
declare(strict_types=1);
/** Pure synthetic evidence fixtures; never an n8n invocation or DB mutation. */
require_once __DIR__.'/execution-contract.php';
use ImagineAI\ExecutionStatus\ExecutionStatusContract as Status;
use ImagineAI\MockOrchestration\CapturedLeadTaskContract;

if(!extension_loaded('sodium'))throw new RuntimeException('EXECUTION_STATUS_SODIUM_REQUIRED');
const STATUS_TEST_JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
$checks=0;
function statusCheck(bool $ok,string $case):void
{global $checks;$checks++;if(!$ok)throw new RuntimeException('EXECUTION_STATUS_TEST_'.$case);}
function statusDenied(callable $call,string $case):void
{
    $denied=false;try{$call();}catch(DomainException|UnexpectedValueException){$denied=true;}
    statusCheck($denied,$case);
}
function statusCanonical(array $v):string{return json_encode($v,STATUS_TEST_JSON);}
$company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';$tenant='SYNTHETIC_TENANT_A';
$eventId='11111111-1111-4111-8111-111111111111';$event='SYNTHETIC_'.strtoupper(str_replace('-','',$eventId));
$lead='abcdef12-3456-7123-8abc-0123456789ab';$now=1800000000;
$payload='{ "schema": "CRM_LEAD_EVENT_V1", "action": "lead.created", "company_id": "'.$company.'", '
    .'"event_id": "'.$eventId.'", "lead_id": "'.strtoupper($lead).'" }';
$sourceHash=hash('sha256',$payload);
$q=['schema'=>'AUTO_IA_EXECUTION_QUERY_V1','issuer'=>'imagine-crm','audience'=>'imagine-ai-agents',
    'product'=>'imagine-crm','operation'=>'event.execution.read','company_id'=>$company,
    'tenant_ref'=>$tenant,'event_ref'=>$event,'payload_hash'=>$sourceHash,'request_ref'=>'SYNTHETIC_QUERY',
    'nonce_ref'=>'SYNTHETIC_NONCE','issued_at'=>$now,'expires_at'=>$now+30,'key_id'=>'SYNTHETIC_REQUEST_KEY',
    'signature'=>str_repeat('a',64)];
statusCheck(Status::query(statusCanonical($q))===$q,'QUERY');
$duplicate=substr(statusCanonical($q),0,-1).',"operati\\u006fn":"event.execution.read"}';
statusDenied(fn()=>Status::query($duplicate),'QUERY_ESCAPED_DUPLICATE');
$wrong=$q;$wrong['issued_at']=(float)$now;
statusDenied(fn()=>Status::query(json_encode($wrong,STATUS_TEST_JSON|JSON_PRESERVE_ZERO_FRACTION)),'QUERY_FLOAT');
$wrong=$q;$wrong['schema']='AUTO_IA_RECEIPT_QUERY_V1';
statusDenied(fn()=>Status::query(statusCanonical($wrong)),'RECEIPT_QUERY_NOT_EXECUTION_QUERY');

$source=['company_id'=>$company,'tenant'=>$tenant,'product'=>'imagine-crm','operation'=>'lead.ingest',
    'event_ref'=>$event,'event_id'=>$eventId,'lead_id'=>$lead,'payload_hash'=>$sourceHash,
    'payload_bytes'=>'\\x'.bin2hex($payload),'nonce_ref'=>'SYNTHETIC_SOURCE_NONCE',
    'issued_epoch'=>$now-10000,'expires_epoch'=>$now-9900,'state'=>'CAPTURED_NOT_DISPATCHED'];
$inbox=array_intersect_key($source,array_flip(['tenant','product','operation','event_ref','nonce_ref',
    'payload_hash','issued_epoch','expires_epoch']));
$capture=['source'=>$source,'inbox'=>$inbox,'provenance'=>null,'replay'=>null,'outbox'=>null,
    'owner_grant'=>null,'owner_key'=>null,'execution'=>null,'executor_key'=>null,'ack_evidence'=>null,
    'audit_received'=>true,'legacy_proof'=>false,'auth_expires_at'=>$now+30];
$facts=Status::project($capture,$q);
statusCheck($facts['execution_state']==='NOT_STAGED'&&$facts['queue_state']==='NONE'
    &&$facts['attempt']===0&&$facts['task_payload_hash']===''&&$facts['lead_id']===$lead,'CAPTURED_HISTORY');
$bad=$capture;$bad['source']['payload_bytes']='\\x'.bin2hex($payload.' ');
statusDenied(fn()=>Status::project($bad,$q),'SOURCE_BYTES_HASH');
$bad=$capture;$bad['legacy_proof']=true;statusDenied(fn()=>Status::project($bad,$q),'NO_P7_SUBSTITUTION');
$bad=$capture;$bad['audit_received']=false;statusDenied(fn()=>Status::project($bad,$q),'ADMISSION_REQUIRED');
$bad=$capture;$bad['inbox']['issued_epoch']++;
statusDenied(fn()=>Status::project($bad,$q),'INBOX_HISTORY_BINDING');

$task=CapturedLeadTaskContract::encode(['contract'=>'SYNTHETIC_CRM_LEAD_INGEST_V1','event_ref'=>$event,
    'tenant_ref'=>$tenant,'company_id'=>$company,'lead_id'=>$lead,'source_payload_hash'=>$sourceHash,
    'kind'=>'crm.lead.ingest','occurred_at'=>$now]);
$taskHash=hash('sha256',$task);$proposal=CapturedLeadTaskContract::propose($task,$tenant,$now)['proposal'];
$ownerPair=sodium_crypto_sign_seed_keypair(str_repeat('o',32));
$ownerPub=sodium_crypto_sign_publickey($ownerPair);$ownerSecret=sodium_crypto_sign_secretkey($ownerPair);
$owner=['schema'=>'AIA_OWNER_DECISION_V1','issuer'=>'SYNTHETIC_CRM_POLICY_OWNER','audience'=>'imagine-ai-agents',
    'company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,'grant_ref'=>'SYNTHETIC_OWNER_GRANT',
    'operation'=>'lead.ingest','owner_revision'=>1,'payload_hash'=>$taskHash,
    'issued_at'=>$now,'expires_at'=>$now+60,'decision'=>'ALLOW_NO_AI_LAB','key_id'=>'SYNTHETIC_OWNER_KEY'];
$ownerRaw=statusCanonical($owner);
$staged=$capture;
$staged['outbox']=['tenant_ref'=>$tenant,'event_ref'=>$event,'canonical_event'=>$task,
    'event_hash'=>$taskHash,'state'=>'PENDING','attempts'=>0,'lease_owner'=>null,'lease_expires'=>null];
$staged['provenance']=['company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,
    'source_product'=>'imagine-crm','source_payload_hash'=>$sourceHash,'task_payload_hash'=>$taskHash];
$staged['replay']=['tenant_ref'=>$tenant,'event_ref'=>$event,'payload_hash'=>$taskHash,
    'nonce_ref'=>$proposal['nonce_ref'],'request_ref'=>$proposal['request_ref']];
$staged['owner_grant']=['company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,
    'grant_ref'=>$owner['grant_ref'],'payload_hash'=>$taskHash,'signer_key_id'=>$owner['key_id'],
    'owner_revision'=>1,'canonical_decision'=>$ownerRaw,'signed_decision_sha256'=>hash('sha256',$ownerRaw),
    'signature_b64'=>base64_encode(sodium_crypto_sign_detached($ownerRaw,$ownerSecret))];
$staged['owner_key']=['company_id'=>$company,'key_id'=>$owner['key_id'],'revision'=>2,'active'=>false,
    'public_key_b64'=>base64_encode($ownerPub),'fingerprint'=>hash('sha256',$ownerPub)];
$facts=Status::project($staged,$q);
statusCheck($facts['execution_state']==='NOT_STARTED'&&$facts['attempt']===0,'STAGED_REVOKED_OWNER_HISTORY');
$leased=$staged;$leased['outbox']['state']='LEASED';$leased['outbox']['attempts']=1;
$leased['outbox']['lease_owner']='SYNTHETIC_WORKER';$leased['outbox']['lease_expires']=$now+60;
statusCheck(Status::project($leased,$q)['execution_state']==='NOT_STARTED','LEASE_IS_NOT_NATIVE_START');
$dead=$staged;$dead['outbox']['state']='DEAD';$dead['outbox']['attempts']=3;
statusCheck(Status::project($dead,$q)['attempt']===0,'DEAD_WITHOUT_INTENT');
$bad=$staged;$wrongOwner=$owner;$wrongOwner['operation']='run.status.project';$raw=statusCanonical($wrongOwner);
$bad['owner_grant']['canonical_decision']=$raw;$bad['owner_grant']['signed_decision_sha256']=hash('sha256',$raw);
$bad['owner_grant']['signature_b64']=base64_encode(sodium_crypto_sign_detached($raw,$ownerSecret));
statusDenied(fn()=>Status::project($bad,$q),'VALID_SIGNATURE_WRONG_OPERATION');
$bad=$staged;$bad['provenance']['source_payload_hash']=str_repeat('f',64);
statusDenied(fn()=>Status::project($bad,$q),'SOURCE_AND_TASK_HASHES_DISTINCT');
$bad=$staged;$bad['owner_key']['fingerprint']=str_repeat('f',64);
statusDenied(fn()=>Status::project($bad,$q),'HISTORICAL_KEY_HASH');

$context=['company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>'SYNTHETIC_WORKER',
    'attempt'=>1,'authority_revision'=>1,'mapping_revision'=>1,'executor_key_id'=>'SYNTHETIC_EXECUTOR_KEY',
    'canonical_event'=>$task,'payload_hash'=>$taskHash,'template_sha256'=>Status::TEMPLATE_SHA256,
    'workflow_id'=>'lead'.substr($taskHash,0,20),'workflow_sha256'=>''];
$input=['company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,'lead_id'=>$lead,
    'source_payload_hash'=>$sourceHash,'payload_hash'=>$taskHash,'worker_ref'=>$context['worker_ref'],
    'attempt'=>1,'template_sha256'=>Status::TEMPLATE_SHA256,'workflow_ref'=>'SYNTHETIC_CRM_LEAD_INGEST_V1'];
$flow=json_decode(file_get_contents(__DIR__.'/../../infra/n8n-private/workflows/native-crm-lead-noai.workflow.json'),
    false,32,JSON_THROW_ON_ERROR);
foreach($flow->nodes[1]->parameters->assignments->assignments as $a)$a->value=$input[$a->name];
$flow->id=$context['workflow_id'];$context['workflow_sha256']=hash('sha256',json_encode($flow,STATUS_TEST_JSON));
$started=$leased;$started['execution']=array_intersect_key($context,array_flip(['company_id','tenant_ref','event_ref',
    'worker_ref','attempt','payload_hash','template_sha256','workflow_sha256','workflow_id']))+[
    'context_json'=>statusCanonical($context),'context_sha256'=>hash('sha256',statusCanonical($context)),
    'state'=>'STARTED','result_json'=>null,'result_sha256'=>null,'canonical_receipt'=>null,'signature_b64'=>null];
statusCheck(Status::project($started,$q)['execution_state']==='STARTED','INTENT_IS_NOT_SUCCESS');
$unknown=$started;$unknown['execution']['state']='UNKNOWN';$unknown['outbox']['attempts']=2;
statusCheck(Status::project($unknown,$q)['execution_state']==='UNKNOWN','UNKNOWN_REMAINS_UNKNOWN');
$bad=$started;$bad['outbox']['state']='PENDING';statusDenied(fn()=>Status::project($bad,$q),'INTENT_CANNOT_BE_PENDING');

$executorPair=sodium_crypto_sign_seed_keypair(str_repeat('e',32));
$executorPub=sodium_crypto_sign_publickey($executorPair);$executorSecret=sodium_crypto_sign_secretkey($executorPair);
$output=['schema'=>'AIA_NATIVE_LEAD_INGEST_V1']+$input+['operation'=>'lead.ingest','lead_status'=>'PREPARED',
    'crm_mutated'=>false,'ai_api_calls'=>0,'operational_authorization'=>false];
$result=['data'=>['resultData'=>['lastNodeExecuted'=>'Preparar lead recebido','runData'=>[
    'Disparo manual'=>[['executionStatus'=>'success']],
    'Referencias do lead'=>[['executionStatus'=>'success','data'=>['main'=>[[['json'=>$input]]]]]],
    'Preparar lead recebido'=>[['executionStatus'=>'success','data'=>['main'=>[[['json'=>$output]]]]]],
]]]];
$receipt=['schema'=>'AIA_N8N_ACK_RECEIPT_V2','issuer'=>'SYNTHETIC_N8N_EXECUTOR','audience'=>'imagine-ai-agents',
    'tenant_ref'=>$tenant,'event_ref'=>$event,'worker_ref'=>$context['worker_ref'],'attempt'=>1,
    'authority_revision'=>1,'payload_hash'=>$taskHash,'issued_at'=>$now,'expires_at'=>$now+60,
    'result'=>'NO_AI_COMPLETED_LAB'];$receiptRaw=statusCanonical($receipt);
$observed=$started;$observed['execution']['state']='RESULT';
$observed['execution']['result_json']=statusCanonical($result);
$observed['execution']['result_sha256']=hash('sha256',statusCanonical($result));
$observed['execution']['canonical_receipt']=$receiptRaw;
$observed['execution']['signature_b64']=base64_encode(sodium_crypto_sign_detached($receiptRaw,$executorSecret));
$observed['executor_key']=['tenant_ref'=>$tenant,'key_id'=>$context['executor_key_id'],'active'=>false,'revision'=>2,
    'public_key_b64'=>base64_encode($executorPub),'key_fingerprint'=>hash('sha256',$executorPub)];
$facts=Status::project($observed,$q);
statusCheck($facts['execution_state']==='RESULT'&&$facts['result_code']==='PREPARED','RESULT_WITHOUT_ACK');
$bad=$observed;$badResult=$result;
$badResult['data']['resultData']['runData']['Preparar lead recebido'][0]['data']['main'][0][0]['json']['crm_mutated']=true;
$bad['execution']['result_json']=statusCanonical($badResult);
$bad['execution']['result_sha256']=hash('sha256',statusCanonical($badResult));
statusDenied(fn()=>Status::project($bad,$q),'RESULT_SEMANTICS_BEYOND_HASH');
$bad=$observed;$bad['execution']['signature_b64']=base64_encode(str_repeat('x',64));
statusDenied(fn()=>Status::project($bad,$q),'EXECUTOR_SIGNATURE');
$acked=$observed;$acked['outbox']['state']='ACKED';
$acked['outbox']['lease_owner']=null;$acked['outbox']['lease_expires']=null;
$acked['ack_evidence']=array_intersect_key($context,array_flip(['tenant_ref','event_ref','worker_ref',
    'attempt','authority_revision','payload_hash']))+['signer_key_id'=>$context['executor_key_id'],
    'key_fingerprint'=>hash('sha256',$executorPub),'canonical_receipt'=>$receiptRaw,
    'signature_b64'=>$observed['execution']['signature_b64'],'receipt_sha256'=>hash('sha256',$receiptRaw)];
$facts=Status::project($acked,$q);
statusCheck($facts['execution_state']==='ACKED'&&$acked['execution']['state']==='RESULT','DURABLE_ACK_BEFORE_LEDGER_MIRROR');
$mirrored=$acked;$mirrored['execution']['state']='ACKED';
statusCheck(Status::observationHash($facts)===Status::observationHash(Status::project($mirrored,$q)),
    'MIRROR_DOES_NOT_CREATE_NEW_OBSERVATION');
$bad=$acked;$bad['ack_evidence']['attempt']=2;
statusDenied(fn()=>Status::project($bad,$q),'ACK_ATTEMPT_BINDING');
$bad=$mirrored;$bad['ack_evidence']=null;statusDenied(fn()=>Status::project($bad,$q),'ACKED_REQUIRES_EVIDENCE');
$bad=$mirrored;$bad['execution']['result_sha256']=str_repeat('f',64);
statusDenied(fn()=>Status::project($bad,$q),'CORRUPTION_IS_NOT_UNKNOWN');
$reply=Status::reply($facts,$q,'SYNTHETIC_EXECUTION_STATUS_KEY',str_repeat('s',64),$now,$now+30);
statusCheck(array_keys($reply)===Status::RESPONSE_FIELDS&&$reply['signature']===hash_hmac('sha256',
    Status::RESPONSE_PREFIX."\n".implode("\n",array_slice($reply,0,-1)),str_repeat('s',64)),'RESPONSE_CANONICAL_MAC');
$next=$q;$next['nonce_ref']='SYNTHETIC_NEXT_NONCE';$nextReply=Status::reply($facts,$next,
    'SYNTHETIC_EXECUTION_STATUS_KEY',str_repeat('s',64),$now+1,$now+30);
statusCheck($nextReply['observation_hash']===$reply['observation_hash']
    &&$nextReply['signature']!==$reply['signature'],'STABLE_FACTS_REQUEST_BOUND_SIGNATURE');
echo "EXECUTION_STATUS_CONTRACT_PASS checks={$checks} six_states=1 historical_signatures=1 NO_GO\n";
