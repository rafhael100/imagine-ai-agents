<?php
declare(strict_types=1);
/** Fixed disposable-CI RPC. This is not an HTTP endpoint or a CRM authority. */
require_once __DIR__.'/PgNativeExecutionLedgerLab.php';
require_once __DIR__.'/../../../tools/labs/PgCapturedLeadStageLab.php';
require_once __DIR__.'/../../../tools/labs/PgSyntheticCompanyTenantBridgeLab.php';
require_once __DIR__.'/../../../tools/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/../../../tools/labs/PgAtomicStagingAuditLab.php';
require_once __DIR__.'/../../../tools/labs/PgTransportOwnerLineageAuditLab.php';

use ImagineAI\NativeLab\PgNativeExecutionLedgerLab;
use ImagineAI\Convergence\PgCapturedLeadStageLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;
use ImagineAI\Convergence\PgOwnerBoundSignedDispatchLab;
use ImagineAI\Convergence\PgSyntheticCompanyTenantBridgeLab;
use ImagineAI\Convergence\PgTenantAuthorityLab;
use ImagineAI\Convergence\PgSyntheticSignerRegistryLab;
use ImagineAI\Convergence\PgSyntheticAckEvidenceLab;
use ImagineAI\Convergence\PgAtomicStagingAuditLab;
use ImagineAI\Convergence\PgTransportOwnerLineageAuditLab;
use ImagineAI\MockOrchestration\CapturedLeadTaskContract;

const LEAD_JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
const LEAD_COMPANIES=[
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'=>'SYNTHETIC_TENANT_A',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'=>'SYNTHETIC_TENANT_B',
];
const LEAD_CASES=['MAIN','ABSENT','FOREIGN','HASH','RUN_GRANT','REVOKED','UNKNOWN','RESULT','PENDING'];
const LEAD_STAGE_FIELDS=['company_id','tenant_ref','event_ref','worker_ref','attempt',
    'authority_revision','mapping_revision','executor_key_id','canonical_event','payload_hash'];

function leadMust(bool $ok,string $code):void {
    if(!$ok)throw new DomainException($code);
}
function leadClosed(array $value,array $fields):void {
    $keys=array_keys($value);sort($keys);sort($fields);
    leadMust($keys===$fields,'LEAD_PROBE_CLOSED_FIELDS');
}
function leadPair(array $input,string $purpose):string {
    $encoded=$input['seed_b64']??null;
    leadMust(is_string($encoded)&&strlen($encoded)===44,'LEAD_PROBE_SEED_FORMAT');
    $seed=base64_decode($encoded,true);
    leadMust(is_string($seed)&&strlen($seed)===32&&base64_encode($seed)===$encoded,'LEAD_PROBE_SEED_BYTES');
    return sodium_crypto_sign_seed_keypair(hash_hkdf('sha256',$seed,32,'CRM_LEAD_'.$purpose));
}
function leadPrepare(PDO $db,array $input,int $now):array {
    leadClosed($input,['case','seed_b64','source']);
    $case=$input['case'];$s=$input['source'];
    leadMust(is_string($case)&&in_array($case,LEAD_CASES,true)&&is_array($s),'LEAD_PROBE_CASE');
    leadClosed($s,['company_id','tenant_ref','event_ref','lead_id','source_payload_hash']);
    foreach($s as $value)leadMust(is_string($value),'LEAD_PROBE_SOURCE_TYPE');
    leadMust(isset(LEAD_COMPANIES[$s['company_id']])
        &&LEAD_COMPANIES[$s['company_id']]===$s['tenant_ref'],'LEAD_PROBE_SYNTHETIC_COMPANY');
    leadMust(preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,48}\z/D',$s['event_ref'])===1
        &&preg_match('/\A[a-f0-9]{64}\z/D',$s['source_payload_hash'])===1
        &&preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D',
            $s['lead_id'])===1,'LEAD_PROBE_SOURCE_FORMAT');
    $ownerKey='SYNTHETIC_LEAD_OWNER_'.$case;
    $executorKey='SYNTHETIC_LEAD_EXECUTOR_'.$case;
    $ownerPair=leadPair($input,'OWNER');$executorPair=leadPair($input,'EXECUTOR');
    leadMust((new PgOwnerDecisionLab($db))->registerKey($s['company_id'],$ownerKey,
        sodium_crypto_sign_publickey($ownerPair)),'LEAD_PROBE_OWNER_REGISTER');
    leadMust((new PgSyntheticSignerRegistryLab($db))->registerActive($s['tenant_ref'],$executorKey,
        sodium_crypto_sign_publickey($executorPair)),'LEAD_PROBE_EXECUTOR_REGISTER');
    // The task is a distinct, fresh owner-approved operation. It preserves the
    // source event reference and the hash of the exact captured HTTP bytes.
    $task=CapturedLeadTaskContract::encode(['contract'=>'SYNTHETIC_CRM_LEAD_INGEST_V1',
        'event_ref'=>$s['event_ref'],'tenant_ref'=>$s['tenant_ref'],'company_id'=>$s['company_id'],
        'lead_id'=>$s['lead_id'],'source_payload_hash'=>$s['source_payload_hash'],
        'kind'=>'crm.lead.ingest','occurred_at'=>$now]);
    leadMust(is_string($task),'LEAD_PROBE_TASK_CONTRACT');
    $taskHash=hash('sha256',$task);
    $grant=json_encode(['schema'=>'AIA_OWNER_DECISION_V1','issuer'=>'SYNTHETIC_CRM_POLICY_OWNER',
        'audience'=>'imagine-ai-agents','company_id'=>$s['company_id'],'tenant_ref'=>$s['tenant_ref'],
        'event_ref'=>$s['event_ref'],'grant_ref'=>'SYNTHETIC_LEAD_GRANT_'.$case,
        'operation'=>$case==='RUN_GRANT'?'run.status.project':'lead.ingest',
        'owner_revision'=>1,'payload_hash'=>$taskHash,'issued_at'=>$now,'expires_at'=>$now+120,
        'decision'=>'ALLOW_NO_AI_LAB','key_id'=>$ownerKey],LEAD_JSON);
    return ['schema'=>'LEAD_PREPARED_PROPOSAL_V1','context'=>[
        'company_id'=>$s['company_id'],'tenant_ref'=>$s['tenant_ref'],'event_ref'=>$s['event_ref'],
        'worker_ref'=>'SYNTHETIC_LEAD_WORKER','attempt'=>1,'authority_revision'=>1,'mapping_revision'=>1,
        'executor_key_id'=>$executorKey,'canonical_event'=>$task,'payload_hash'=>$taskHash],
        'owner_decision'=>$grant,'owner_signature_b64'=>base64_encode(sodium_crypto_sign_detached(
            $grant,sodium_crypto_sign_secretkey($ownerPair))),'owner_key_id'=>$ownerKey];
}
function leadQuarantine(PDO $db):array {
    // This fixed, revocation-only fixture is used AFTER the source dump and
    // again on the isolated destination. It never extends a time window.
    $db->beginTransaction();
    try{
        $counts=[];
        foreach(['aia_p34_lab.tenant_policy','aia_p37_lab.executor_keys','aia_p41_lab.company_tenant_links',
            'aia_p44_lab.owner_keys','auto_ia_lab.company_links','auto_ia_lab.service_company_grants',
            'auto_ia_lab.receipt_read_grants'] as $table){
            $counts[$table]=$db->exec('UPDATE '.$table.' SET active=false,revision=revision+1 WHERE active');
        }
        $counts['auto_ia_lab.mock_s2s_keys']=$db->exec('UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE enabled');
        $db->exec('REVOKE EXECUTE ON FUNCTION auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text) '
            .'FROM aia_private_transport');
        $db->exec('REVOKE EXECUTE ON FUNCTION auto_ia_lab.read_company_event_receipt(uuid,text,jsonb) '
            .'FROM aia_private_receipt');
        $db->commit();
        return ['schema'=>'LEAD_EXECUTION_QUARANTINED_V1','revocations'=>$counts,
            'business_grants_reenabled'=>0,'expiry_windows_extended'=>0,'real_authority_verified'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

try{
    leadMust(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('AIA_P16_PG_LAB')==='true'&&getenv('AIA_P6_PG_LAB')==='true'
        &&getenv('DB_HOST')==='127.0.0.1'&&getenv('DB_DATABASE')==='imagine_agents_ci'
        &&getenv('DB_USERNAME')==='postgres','LEAD_PROBE_DISPOSABLE_CI_ONLY');
    leadMust(extension_loaded('pdo_pgsql')&&extension_loaded('sodium'),'LEAD_PROBE_EXTENSIONS');
    $mode=$argv[1]??'';
    leadMust(in_array($mode,['init','prepare','stage','lease','begin','record-result','reconcile',
        'revoke-owner','quarantine','audits','stats'],true),'LEAD_PROBE_MODE');
    $raw=file_get_contents('php://stdin',false,null,0,1048577);
    leadMust(is_string($raw)&&strlen($raw)<=1048576,'LEAD_PROBE_INPUT_SIZE');
    $input=$raw===''?[]:json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    leadMust(is_array($input),'LEAD_PROBE_INPUT_OBJECT');
    $password=file_get_contents('/run/secrets/p25-password');
    leadMust(is_string($password)&&strlen($password)>=32,'LEAD_PROBE_EPHEMERAL_PASSWORD');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',trim($password),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET lock_timeout='1500ms';SET statement_timeout='5s';SET idle_in_transaction_session_timeout='10s'");
    $ledger=new PgNativeExecutionLedgerLab($db);
    $now=time(); // No caller clock, historical epoch or fixture P18 timestamp.
    $c=$input['context']??[];
    if($mode==='init'){
        leadClosed($input,[]);
        (new PgCapturedLeadStageLab($db))->createTables();
        $tenant=new PgTenantAuthorityLab($db);$tenant->createTables();
        $bridge=new PgSyntheticCompanyTenantBridgeLab($db);$bridge->createTable();
        (new PgSyntheticSignerRegistryLab($db))->createTables();
        (new PgSyntheticAckEvidenceLab($db))->createTables();
        foreach(LEAD_COMPANIES as $company=>$tenantRef)
            leadMust($tenant->seedActive($tenantRef)&&$bridge->seedLabBinding($company,$tenantRef),
                'LEAD_PROBE_INITIAL_SCOPE');
        $db->exec(file_get_contents(__DIR__.'/schema.sql'));
        $out=['schema'=>'LEAD_EXECUTION_LAB_READY_V1','transport_fixture'=>'REAL_SYNTHETIC_MTLS_CAPTURE',
            'p7_nonce_admissions'=>0,'sodium'=>true,'runtime_clock'=>true];
    }elseif($mode==='prepare'){$out=leadPrepare($db,$input,$now);
    }elseif($mode==='stage'){
        leadClosed($input,['proposal']);$p=$input['proposal'];
        leadMust(is_array($p),'LEAD_PROBE_PROPOSAL');
        leadClosed($p,['schema','context','owner_decision','owner_signature_b64','owner_key_id']);
        leadMust($p['schema']==='LEAD_PREPARED_PROPOSAL_V1'&&is_array($p['context']),'LEAD_PROBE_PROPOSAL_SCHEMA');
        $c=$p['context'];leadClosed($c,LEAD_STAGE_FIELDS);
        $out=(new PgCapturedLeadStageLab($db))->stage($c['company_id'],$c['tenant_ref'],
            $c['mapping_revision'],$c['canonical_event'],$p['owner_decision'],
            $p['owner_signature_b64'],$p['owner_key_id'],$now);
    }elseif($mode==='lease'){
        leadClosed($input,['context']);
        $out=(new PgOwnerBoundSignedDispatchLab($db))->reserve($c['company_id'],$c['tenant_ref'],
            $c['worker_ref'],$now,$c['authority_revision'],$c['mapping_revision']);
    }elseif($mode==='begin'){
        leadClosed($input,['context']);$out=$ledger->begin($c,$now);
    }elseif($mode==='record-result'){
        leadClosed($input,['context','seed_b64','execution']);
        $out=$ledger->record($c,$input['execution'],sodium_crypto_sign_secretkey(leadPair($input,'EXECUTOR')),$now);
    }elseif($mode==='reconcile'){
        leadClosed($input,['context']);$out=$ledger->reconcile($c,$now);
    }elseif($mode==='revoke-owner'){
        leadClosed($input,['context']);
        $q=$db->prepare('SELECT signer_key_id FROM aia_p44_lab.consumed_owner_grants '
            .'WHERE company_id=CAST(:company AS uuid) AND tenant_ref=:tenant AND event_ref=:event');
        $q->execute([':company'=>$c['company_id'],':tenant'=>$c['tenant_ref'],':event'=>$c['event_ref']]);
        $key=$q->fetchColumn();leadMust(is_string($key),'LEAD_PROBE_OWNER_LOOKUP');
        leadMust((new PgOwnerDecisionLab($db))->revokeKey($c['company_id'],$key)===2,'LEAD_PROBE_OWNER_REVOKE');
        $out=['decision'=>'LEAD_OWNER_REVOKED'];
    }elseif($mode==='quarantine'){
        leadClosed($input,[]);$out=leadQuarantine($db);
    }elseif($mode==='audits'){
        leadClosed($input,[]);
        $out=['schema'=>'LEAD_DOMAIN_AUDITS_V1',
            'atomic'=>(new PgAtomicStagingAuditLab($db))->scan(),
            'lineage'=>(new PgTransportOwnerLineageAuditLab($db))->scan()];
    }else{
        leadClosed($input,[]);
        $out=['schema'=>'LEAD_EXECUTION_COUNTS_V1',
            'outbox'=>(int)$db->query('SELECT count(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn(),
            'acked'=>(int)$db->query("SELECT count(*) FROM public.aia_lab_n8n_outbox_p6 WHERE state='ACKED'")->fetchColumn(),
            'evidence'=>(new PgSyntheticAckEvidenceLab($db))->countEvidence(),
            'sources'=>(int)$db->query('SELECT count(*) FROM aia_lead_ingest_lab.captured_job_sources')->fetchColumn(),
            'states'=>$db->query('SELECT event_ref,state,reason FROM aia_p26_native.executions ORDER BY event_ref')
                ->fetchAll(PDO::FETCH_ASSOC)];
    }
    echo json_encode($out,LEAD_JSON)."\n";
}catch(DomainException $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    $reason=preg_match('/\A[A-Z0-9_]{1,120}\z/D',$e->getMessage())===1
        ?$e->getMessage():'LEAD_PROBE_UNTRUSTED_DIAGNOSTIC_SUPPRESSED';
    echo json_encode(['decision'=>'DENY','reason'=>$reason,'execute_again'=>false],LEAD_JSON)."\n";
}catch(Throwable){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,"LEAD_PROBE_INTERNAL_FAILURE\n");exit(1);
}
