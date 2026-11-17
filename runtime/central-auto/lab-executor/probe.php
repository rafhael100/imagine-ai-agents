<?php
declare(strict_types=1);
/** Fixed CI RPC. No HTTP route, provider, environment secret, or live authority. */
require_once __DIR__.'/PgNativeExecutionLedgerLab.php';
require_once __DIR__.'/../../../tools/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/../../../tools/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/../../../tools/labs/P18Fixture.php';

use ImagineAI\NativeLab\PgNativeExecutionLedgerLab;
use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;
use ImagineAI\Convergence\PgOwnerDecisionBoundStageLab;
use ImagineAI\Convergence\PgOwnerBoundSignedDispatchLab;
use ImagineAI\Convergence\PgTenantAuthorityLab;
use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\AutomationFirst\MockP7ToAutoIaAdapter;
use ImagineAI\MockOrchestration\MockN8nOutboxContract;

const P26_COMPANY='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const P26_TENANT='SYNTHETIC_P26_TENANT';
const P26_WORKER='SYNTHETIC_P26_WORKER';
const P26_JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
const P26_CASES=['MAIN'=>'90_MAIN','MALFORMED'=>'80_MALFORMED','FAILURE'=>'70_FAILURE',
    'REVOKED'=>'60_REVOKED','FENCE'=>'50_FENCE','AFTER_START'=>'40_AFTER_START'];

function p26Must(bool $ok,string $code):void {
    if(!$ok)throw new DomainException($code);
}
function p26Pair(array $input,string $purpose):string {
    $encoded=$input['seed_b64']??null;
    p26Must(is_string($encoded)&&strlen($encoded)===44,'P26_SEED_FORMAT');
    $seed=base64_decode($encoded,true);
    p26Must(is_string($seed)&&strlen($seed)===32&&base64_encode($seed)===$encoded,'P26_SEED_BYTES');
    return sodium_crypto_sign_seed_keypair(hash_hkdf('sha256',$seed,32,'P26_'.$purpose));
}
function p26Stage(PDO $db,array $input,int $now):array {
    $case=$input['case']??null;
    p26Must(is_string($case)&&isset(P26_CASES[$case]),'P26_CASE_ALLOWLIST');
    $event='SYNTHETIC_P26_'.P26_CASES[$case];
    $ownerKey='SYNTHETIC_P26_OWNER_'.$case;
    $executorKey='SYNTHETIC_P26_EXECUTOR_'.$case;
    $ownerPair=p26Pair($input,'OWNER');$executorPair=p26Pair($input,'EXECUTOR');
    p26Must((new PgOwnerDecisionLab($db))->registerKey(
        P26_COMPANY,$ownerKey,sodium_crypto_sign_publickey($ownerPair)),'P26_OWNER_REGISTER');
    p26Must((new PgSyntheticSignerRegistryLab($db))->registerActive(
        P26_TENANT,$executorKey,sodium_crypto_sign_publickey($executorPair)),'P26_EXECUTOR_REGISTER');
    $canonical=MockN8nOutboxContract::encode(['contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref'=>$event,'tenant_ref'=>P26_TENANT,'run_ref'=>'SYNTHETIC_P26_RUN_'.$case,
        'kind'=>'mock.run.completed','reference_ref'=>'SYNTHETIC_P26_RESOURCE','occurred_at'=>$now]);
    p26Must(is_string($canonical),'P26_EVENT_ENCODING');
    $proposal=MockP7ToAutoIaAdapter::propose($canonical,P26_TENANT,$now)['proposal']??null;
    p26Must(is_array($proposal)&&$proposal['operation']==='run.status.project','P26_ORIGINAL_ADAPTER');
    $envelope=['schema'=>'AUTO_IA_S2S_V1','issuer'=>$proposal['issuer'],
        'audience'=>$proposal['audience'],'product'=>$proposal['product'],'operation'=>$proposal['operation'],
        'tenant_ref'=>P26_TENANT,'event_ref'=>$event,'nonce_ref'=>$proposal['nonce_ref'],
        'request_ref'=>$proposal['request_ref'],'issued_at'=>$now,'expires_at'=>$now+60,
        'payload_hash'=>$proposal['payload_hash'],'key_id'=>'SYNTHETIC_KEY_A'];
    $envelope['signature']=hash_hmac('sha256',implode("\n",array_map('strval',array_values($envelope))),P18Fixture::KEY);
    $grant=json_encode(['schema'=>'AIA_OWNER_DECISION_V1','issuer'=>'SYNTHETIC_CRM_POLICY_OWNER',
        'audience'=>'imagine-ai-agents','company_id'=>P26_COMPANY,'tenant_ref'=>P26_TENANT,
        'event_ref'=>$event,'grant_ref'=>'SYNTHETIC_P26_GRANT_'.$case,'operation'=>'run.status.project',
        'owner_revision'=>1,'payload_hash'=>$proposal['payload_hash'],'issued_at'=>$now,
        'expires_at'=>$now+120,'decision'=>'ALLOW_NO_AI_LAB','key_id'=>$ownerKey],P26_JSON);
    $request='SYNTHETIC_P26_REQUEST_'.$case;$nonce='SYNTHETIC_P26_NONCE_'.$case;
    $transportHash=hash('sha256','P26_DATABASE_ADMISSION_FIXTURE_'.$event);
    // Reuse the original P7 storage-admission function. This deliberately does
    // not claim a fresh mTLS exchange or a real CRM policy-owner decision.
    $q=$db->prepare("SELECT central_auto_lab.admit_nonce('portal-automation-admin',
        'crm-automation-state',:company,:request,:nonce,:hash,:now)");
    $q->execute([':company'=>P26_COMPANY,':request'=>$request,':nonce'=>$nonce,
        ':hash'=>$transportHash,':now'=>$now]);
    p26Must($q->fetchColumn()==='LAB_ADMITTED_NO_DELIVERY','P26_TRANSPORT_STORAGE_FIXTURE');
    $seen=[];
    $stage=(new PgOwnerDecisionBoundStageLab($db))->stage(P26_COMPANY,P26_TENANT,
        $request,$nonce,$transportHash,1,$now,$canonical,$envelope,
        ['service'=>'imagine-ai-agents','tenant_ref'=>P26_TENANT,'operations'=>['run.status.project'],'active'=>true],
        $now,['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen,$grant,
        base64_encode(sodium_crypto_sign_detached($grant,sodium_crypto_sign_secretkey($ownerPair))),$ownerKey);
    p26Must($stage['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY'&&count($seen)===1,
        'P26_ORIGINAL_OWNER_BOUND_STAGE');
    return ['company_id'=>P26_COMPANY,'tenant_ref'=>P26_TENANT,'event_ref'=>$event,
        'worker_ref'=>P26_WORKER,'attempt'=>1,'authority_revision'=>1,'mapping_revision'=>1,
        'executor_key_id'=>$executorKey,'canonical_event'=>$canonical,'payload_hash'=>$proposal['payload_hash']];
}

try{
    p26Must(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('AIA_P16_PG_LAB')==='true'&&getenv('AIA_P6_PG_LAB')==='true'
        &&getenv('DB_HOST')==='127.0.0.1'&&getenv('DB_DATABASE')==='imagine_agents_ci'
        &&getenv('DB_USERNAME')==='postgres','P26_DISPOSABLE_CI_ONLY');
    p26Must(extension_loaded('pdo_pgsql')&&extension_loaded('sodium'),'P26_PHP_EXTENSIONS_REQUIRED');
    $mode=$argv[1]??'';
    p26Must(in_array($mode,['init','stage','lease','begin','record-result','reconcile','revoke-owner',
        'fixture-expire-lease','fixture-expire-receipt','fixture-ledger-lag','stats'],true),'P26_MODE_ALLOWLIST');
    $raw=file_get_contents('php://stdin',false,null,0,1048577);
    p26Must(is_string($raw)&&strlen($raw)<=1048576,'P26_INPUT_SIZE');
    $input=$raw===''?[]:json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    p26Must(is_array($input),'P26_INPUT_OBJECT');
    $password=file_get_contents('/run/secrets/p25-password');
    p26Must(is_string($password)&&strlen($password)>=32,'P26_EPHEMERAL_PASSWORD');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',trim($password),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET lock_timeout='1500ms'");
    $db->exec("SET statement_timeout='5s'");
    $db->exec("SET idle_in_transaction_session_timeout='10s'");
    $ledger=new PgNativeExecutionLedgerLab($db);
    $now=time(); // Runtime clock only: no caller-supplied epoch and no P18::NOW.
    $c=$input['context']??[];
    if($mode==='init'){
        $stager=new PgAtomicCompanyTenantStageLab($db);$stager->createTables();
        (new PgOwnerDecisionLab($db))->createTables();
        $tenant=new PgTenantAuthorityLab($db);$tenant->createTables();
        (new PgSyntheticSignerRegistryLab($db))->createTables();
        (new PgSyntheticAckEvidenceLab($db))->createTables();
        p26Must($tenant->seedActive(P26_TENANT)&&$stager->bridge()->seedLabBinding(P26_COMPANY,P26_TENANT),
            'P26_INITIAL_SCOPE');
        $db->exec(file_get_contents(__DIR__.'/schema.sql'));
        $out=['schema'=>'P26_LAB_READY_V1','sodium'=>true,'current_clock'=>true];
    }elseif($mode==='stage'){$out=p26Stage($db,$input,$now);
    }elseif($mode==='lease'){
        $out=(new PgOwnerBoundSignedDispatchLab($db))->reserve($c['company_id'],$c['tenant_ref'],
            $c['worker_ref'],$now,$c['authority_revision'],$c['mapping_revision']);
    }elseif($mode==='begin'){$out=$ledger->begin($c,$now);
    }elseif($mode==='record-result'){
        $out=$ledger->record($c,$input['execution']??null,
            sodium_crypto_sign_secretkey(p26Pair($input,'EXECUTOR')),$now);
    }elseif($mode==='reconcile'){$out=$ledger->reconcile($c,$now);
    }elseif($mode==='revoke-owner'){
        $q=$db->prepare('SELECT signer_key_id FROM aia_p44_lab.consumed_owner_grants '
            .'WHERE company_id=CAST(:company AS uuid) AND tenant_ref=:tenant AND event_ref=:event');
        $q->execute([':company'=>$c['company_id'],':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
        $key=$q->fetchColumn();p26Must(is_string($key),'P26_OWNER_KEY_LOOKUP');
        p26Must((new PgOwnerDecisionLab($db))->revokeKey($c['company_id'],$key)===2,'P26_OWNER_REVOKE');
        $out=['decision'=>'P26_OWNER_REVOKED'];
    }elseif($mode==='fixture-expire-lease'){
        // Fault injection exclusively in this lab probe. The ledger never
        // rewrites leases or renews authority to make a late result succeed.
        $q=$db->prepare("UPDATE public.aia_lab_n8n_outbox_p6 SET lease_expires=:expired
            WHERE tenant_ref=:tenant AND event_ref=:event AND state='LEASED' AND attempts=:attempt");
        $q->execute([':expired'=>$now-1,':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref'],':attempt'=>$c['attempt']]);
        p26Must($q->rowCount()===1,'P26_INJECT_LEASE_EXPIRY');$out=['decision'=>'P26_FIXTURE_LEASE_EXPIRED'];
    }elseif($mode==='fixture-ledger-lag'){
        // Model death after the original ACK+evidence commit, before the
        // auxiliary marker. Never change the authoritative ACK or evidence.
        $q=$db->prepare("UPDATE aia_p26_native.executions SET state='RESULT'
            WHERE tenant_ref=:tenant AND event_ref=:event AND state='ACKED'");
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
        p26Must($q->rowCount()===1,'P26_INJECT_LEDGER_LAG');$out=['decision'=>'P26_FIXTURE_LEDGER_LAG'];
    }elseif($mode==='fixture-expire-receipt'){
        // A signed but expired negative fixture, derived only from a result
        // already observed and retained. Runtime time() is never overwritten.
        $q=$db->prepare("SELECT canonical_receipt FROM aia_p26_native.executions
            WHERE tenant_ref=:tenant AND event_ref=:event AND state='RESULT'");
        $q->execute([':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);$raw=$q->fetchColumn();
        p26Must(is_string($raw),'P26_EXPIRY_REQUIRES_OBSERVED_RESULT');
        $receipt=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        $receipt['issued_at']=$now-61;$receipt['expires_at']=$now-1;
        $raw=json_encode($receipt,P26_JSON);$pair=p26Pair($input,'EXECUTOR');
        $pub=(new PgSyntheticSignerRegistryLab($db))->resolveActive($c['tenant_ref'],$c['executor_key_id']);
        p26Must(is_string($pub)&&hash_equals($pub,sodium_crypto_sign_publickey($pair)),
            'P26_EXPIRY_SIGNER_REQUIRED');
        $q=$db->prepare("UPDATE aia_p26_native.executions SET canonical_receipt=:receipt,signature_b64=:sig
            WHERE tenant_ref=:tenant AND event_ref=:event AND state='RESULT'");
        $q->execute([':receipt'=>$raw,':sig'=>base64_encode(sodium_crypto_sign_detached($raw,
            sodium_crypto_sign_secretkey($pair))),':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
        p26Must($q->rowCount()===1,'P26_INJECT_RECEIPT_EXPIRY');$out=['decision'=>'P26_FIXTURE_RECEIPT_EXPIRED'];
    }else{
        $out=['schema'=>'P26_DURABLE_COUNTS_V1',
            'acked'=>(int)$db->query("SELECT count(*) FROM public.aia_lab_n8n_outbox_p6 WHERE state='ACKED'")->fetchColumn(),
            'evidence'=>(new PgSyntheticAckEvidenceLab($db))->countEvidence(),
            'states'=>$db->query('SELECT event_ref,state,reason FROM aia_p26_native.executions ORDER BY event_ref')->fetchAll(PDO::FETCH_ASSOC)];
    }
    echo json_encode($out,P26_JSON)."\n";
}catch(DomainException $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    echo json_encode(['decision'=>'DENY','reason'=>$e->getMessage(),'execute_again'=>false],P26_JSON)."\n";
}catch(Throwable){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    // Driver logs a fixed label; never dump SQL, credentials or native output.
    fwrite(STDERR,"P26_PROBE_INTERNAL_FAILURE\n");exit(1);
}
