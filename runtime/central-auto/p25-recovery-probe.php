<?php
declare(strict_types=1);
/** P25 disposable recovery probes. Calls the existing candidate classes/functions.
 * No HTTP transport, n8n dispatch, operational authority or real secret is used.
 */
require_once __DIR__.'/../../tools/labs/PgTenantAuthorityLab.php';
require_once __DIR__.'/../../tools/labs/P18Fixture.php';

use ImagineAI\Convergence\PgRevocationBoundCycleLab;
use ImagineAI\Convergence\P18Fixture;

const COMPANY_A='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const COMPANY_B='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
const COMPANY_C='cccccccc-cccc-4ccc-8ccc-cccccccccccc';
const TENANT_A='SYNTHETIC_TENANT_A';
const TENANT_B='SYNTHETIC_TENANT_B';
const WORKER_A='SYNTHETIC_P25_WORKER_A';
const WORKER_B='SYNTHETIC_P25_WORKER_B';
const KEY_A='SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY';
const KEY_B='SYNTHETIC_OFFLINE_HMAC_TEST_KEY_B_32_BYTES_ONLY';

function must(bool $ok,string $reason):void {
    if(!$ok)throw new RuntimeException('P25_'.$reason);
}
function scalar(PDO $db,string $sql):string {
    return (string)$db->query($sql)->fetchColumn();
}
function role(PDO $db,string $who,callable $action):mixed {
    must(in_array($who,['aia_private_transport','aia_lab_tenant_a','aia_lab_tenant_b','aia_p25_guest'],true),'ROLE_ALLOWLIST');
    $db->exec('SET ROLE '.$who);
    try{return $action();}finally{$db->exec('RESET ROLE');}
}
function denies(PDO $db,string $who,string $sql):void {
    $denied=false;
    role($db,$who,function()use($db,$sql,&$denied):void {
        try{$db->query($sql);}catch(PDOException $e){$denied=$e->getCode()==='42501';}
    });
    must($denied,'EXPECTED_SQL_PERMISSION_DENIAL');
}
function envelope(string $tenant,string $event,string $key='SYNTHETIC_KEY_B',?int $issued=null):array {
    $at=$issued??time();
    $e=['schema'=>'AUTO_IA_S2S_V1','issuer'=>'imagine-crm','audience'=>'imagine-ai-agents',
        'product'=>'imagine-crm','operation'=>'lead.ingest','tenant_ref'=>$tenant,
        'event_ref'=>$event,'nonce_ref'=>$event.'_N','request_ref'=>$event.'_R',
        'issued_at'=>$at,'expires_at'=>$at+290,'payload_hash'=>hash('sha256','P25_ONLY_'.$event),
        'key_id'=>$key];
    $e['signature']=hash_hmac('sha256',implode("\n",array_values($e)),
        $key==='SYNTHETIC_KEY_A'?KEY_A:KEY_B);
    return $e;
}
function receive(PDO $db,string $company,string $fingerprint,array $e):string {
    return role($db,'aia_private_transport',function()use($db,$company,$fingerprint,$e):string {
        $q=$db->prepare('SELECT auto_ia_lab.receive_company_signed_no_ai(CAST(? AS uuid),?,CAST(? AS jsonb))');
        $q->execute([$company,$fingerprint,json_encode($e,JSON_THROW_ON_ERROR)]);
        return (string)$q->fetchColumn();
    });
}
function stage(PgRevocationBoundCycleLab $cycle,string $tenant,string $event):array {
    [$p7,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    return $cycle->stage($p7,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen);
}
function zero(array $result):array {
    must(($result['ai_calls']??null)===0 && ($result['service_effects']??null)===0,'NONZERO_EFFECT');
    return $result;
}
function states(PDO $db):array {
    $data=$db->query('SELECT tenant_ref,event_ref,state,attempts,lease_owner,lease_expires '
        .'FROM public.aia_lab_n8n_outbox_p6 ORDER BY tenant_ref,event_ref')->fetchAll(PDO::FETCH_ASSOC);
    return $data;
}
function security(PDO $db):void {
    must(scalar($db,"SELECT count(*) FROM pg_roles WHERE rolname IN "
        ."('aia_lab_owner','aia_lab_tenant_a','aia_lab_tenant_b','aia_private_transport','aia_p25_guest') "
        .'AND NOT (rolcanlogin OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication)')==='5','ROLE_PRIVILEGES');
    foreach(['budgets','allowed_ops','requests','no_ai_ops','event_inbox','mock_s2s_keys',
             'company_links','service_company_grants','service_event_audit'] as $table){
        must(scalar($db,"SELECT relrowsecurity AND relforcerowsecurity FROM pg_class "
            ."WHERE oid='auto_ia_lab.$table'::regclass")==='1','RLS_FORCE');
        must(scalar($db,"SELECT pg_get_userbyid(relowner) FROM pg_class "
            ."WHERE oid='auto_ia_lab.$table'::regclass")==='aia_lab_owner','TABLE_OWNER');
    }
    must(scalar($db,"SELECT pg_get_userbyid(proowner) FROM pg_proc WHERE "
        ."oid='auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb)'::regprocedure")==='aia_lab_owner','DEFINER_OWNER');
    must(scalar($db,"SELECT prosecdef AND proconfig @> ARRAY['search_path=pg_catalog, auto_ia_lab'] "
        ."FROM pg_proc WHERE oid='auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb)'::regprocedure")==='1','DEFINER_SEARCH_PATH');
    must(scalar($db,"SELECT has_function_privilege('aia_private_transport',"
        ."'auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb)','EXECUTE')")==='1','TRANSPORT_EXECUTE_ACL');
    must(scalar($db,"SELECT has_function_privilege('aia_p25_guest',"
        ."'auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb)','EXECUTE')")==='','PUBLIC_EXECUTE_REVOKED');
    must(scalar($db,"SELECT has_function_privilege('aia_lab_tenant_a',"
        ."'auto_ia_lab.receive_no_ai(text,text,text,text,text,bigint,bigint)','EXECUTE')")==='','UNSIGNED_EXECUTE_REVOKED');
    denies($db,'aia_p25_guest',"SELECT auto_ia_lab.receive_company_signed_no_ai('".COMPANY_A."',repeat('a',64),'{}'::jsonb)");
    denies($db,'aia_private_transport','SELECT * FROM auto_ia_lab.mock_s2s_keys');
    denies($db,'aia_private_transport','SELECT * FROM auto_ia_lab.service_company_grants');
    denies($db,'aia_private_transport',"INSERT INTO auto_ia_lab.event_inbox VALUES ('x','x','x','x','x','x',1,2,now())");
    foreach(['aia_lab_tenant_a'=>TENANT_A,'aia_lab_tenant_b'=>TENANT_B] as $who=>$tenant){
        role($db,$who,function()use($db,$tenant):void {
            must(scalar($db,"SELECT count(*) FROM auto_ia_lab.event_inbox WHERE tenant<>'$tenant'")==='0','CROSS_TENANT_READ');
            must((int)scalar($db,'SELECT count(*) FROM auto_ia_lab.event_inbox')>=1,'OWN_TENANT_VISIBLE');
        });
    }
}

try{
    foreach(['GITHUB_ACTIONS'=>'true','GITHUB_REPOSITORY'=>'rafhael100/imagine-ai-agents',
             'AIA_P6_PG_LAB'=>'true','AIA_P16_PG_LAB'=>'true','DB_HOST'=>'127.0.0.1',
             'DB_DATABASE'=>'imagine_agents_ci','DB_USERNAME'=>'postgres'] as $name=>$value){
        must(getenv($name)===$value,'DISPOSABLE_CONTEXT');
    }
    $password=file_get_contents('/run/secrets/p25-password');
    must(is_string($password)&&strlen($password)===64,'PASSWORD_FILE');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET statement_timeout='5000ms'; SET lock_timeout='1000ms'");
    $mode=$argv[1]??'';
    $cycle=new PgRevocationBoundCycleLab($db);
    $now=P18Fixture::NOW;
    if($mode==='seed'){
        $cycle->createTables();
        must($cycle->authority()->seedActive(TENANT_A),'SEED_AUTHORITY_A');
        must($cycle->authority()->seedActive(TENANT_B),'SEED_AUTHORITY_B');
        must(zero(stage($cycle,TENANT_A,'SYNTHETIC_P25_A_ACKED'))['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','STAGE_ACKED');
        must(zero($cycle->reserve(TENANT_A,WORKER_A,$now,1))['attempt']===1,'LEASE_ACKED');
        must(zero($cycle->acknowledge(TENANT_A,'SYNTHETIC_P25_A_ACKED',WORKER_A,1,$now+1,1))['decision']==='LAB_ACKED_NO_DELIVERY_PROOF','ACK_TERMINAL');
        must(zero(stage($cycle,TENANT_A,'SYNTHETIC_P25_A_DEAD'))['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','STAGE_DEAD');
        for($attempt=1;$attempt<=3;$attempt++){
            must(zero($cycle->reserve(TENANT_A,WORKER_A,$now+($attempt-1)*60,1))['attempt']===$attempt,'LEASE_BOUNDED');
        }
        must(zero($cycle->reserve(TENANT_A,WORKER_A,$now+180,1))['decision']==='LAB_NO_PENDING','DEAD_TERMINAL');
        foreach([[TENANT_A,'SYNTHETIC_P25_A_LEASED',WORKER_A],[TENANT_B,'SYNTHETIC_P25_B_LEASED',WORKER_B]] as [$tenant,$event,$worker]){
            must(zero(stage($cycle,$tenant,$event))['decision']==='LAB_ATOMIC_STAGED_NO_DISPATCH','STAGE_LEASED');
            must(zero($cycle->reserve($tenant,$worker,$now+200,1))['attempt']===1,'INITIAL_LEASE');
        }
        $db->exec("CREATE ROLE aia_p25_guest NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION; GRANT USAGE ON SCHEMA auto_ia_lab TO aia_p25_guest");
        $db->exec("UPDATE auto_ia_lab.mock_s2s_keys SET enabled=true WHERE key_id='SYNTHETIC_KEY_B'");
        $expiry=time()+1800;
        foreach([[COMPANY_A,TENANT_A,'a',true,1],[COMPANY_B,TENANT_B,'b',true,1],[COMPANY_C,'SYNTHETIC_TENANT_C','c',false,2]] as [$company,$tenant,$fp,$active,$revision]){
            $q=$db->prepare('INSERT INTO auto_ia_lab.company_links VALUES(CAST(? AS uuid),?,true,1)');
            $q->execute([$company,$tenant]);
            $q=$db->prepare("INSERT INTO auto_ia_lab.service_company_grants VALUES(CAST(? AS uuid),?,?,'lead.ingest',CAST(? AS boolean),?,?)");
            $q->execute([$company,str_repeat($fp,64),$tenant,$active?'true':'false',$revision,$expiry]);
        }
        $a=envelope(TENANT_A,'SYNTHETIC_P25_INBOX_A','SYNTHETIC_KEY_A');
        $b=envelope(TENANT_B,'SYNTHETIC_P25_INBOX_B');
        must(receive($db,COMPANY_A,str_repeat('a',64),$a)==='LAB_RECEIVED_NO_DELIVERY','INBOX_A');
        must(receive($db,COMPANY_B,str_repeat('b',64),$b)==='LAB_RECEIVED_NO_DELIVERY','INBOX_B');
        must(receive($db,COMPANY_B,str_repeat('b',64),$b)==='LAB_DUPLICATE_NO_DELIVERY','SOURCE_DUPLICATE');
        must(receive($db,COMPANY_C,str_repeat('c',64),envelope('SYNTHETIC_TENANT_C','SYNTHETIC_P25_REVOKED_PRE'))==='LAB_DENY_GRANT','PRE_SNAPSHOT_REVOKED');
        security($db);
        echo json_encode(['schema'=>'P25_SEEDED_CANDIDATE_V1','outbox_states'=>states($db),
            'inbox_a'=>$a,'inbox_b'=>$b,'outbox_clock'=>$now,'no_go'=>true],JSON_THROW_ON_ERROR)."\n";
    }elseif($mode==='revoke-after-snapshot'){
        must($cycle->authority()->revoke(TENANT_A)===2,'POST_SNAPSHOT_REVOKE');
        $db->exec("UPDATE auto_ia_lab.service_company_grants SET active=false,revision=2 WHERE company_id='".COMPANY_A."'; "
            ."UPDATE auto_ia_lab.company_links SET active=false,revision=2 WHERE company_id='".COMPANY_A."'; "
            ."UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE key_id='SYNTHETIC_KEY_A'");
        must(receive($db,COMPANY_A,str_repeat('a',64),envelope(TENANT_A,'SYNTHETIC_P25_AFTER_REVOKE','SYNTHETIC_KEY_A'))==='LAB_DENY_GRANT','SOURCE_REVOKE_DENIAL');
        $current=['schema'=>'P25_CURRENT_AUTHORITY_FIXTURE_V1','tenant_ref'=>TENANT_A,
            'company_id'=>COMPANY_A,'authority'=>$cycle->authority()->snapshot(TENANT_A),
            'grant_revision'=>(int)scalar($db,"SELECT revision FROM auto_ia_lab.service_company_grants WHERE company_id='".COMPANY_A."'"),
            'grant_active'=>scalar($db,"SELECT active FROM auto_ia_lab.service_company_grants WHERE company_id='".COMPANY_A."'")!=='',
            'link_revision'=>(int)scalar($db,"SELECT revision FROM auto_ia_lab.company_links WHERE company_id='".COMPANY_A."'"),
            'link_active'=>scalar($db,"SELECT active FROM auto_ia_lab.company_links WHERE company_id='".COMPANY_A."'")!=='',
            'key_a_enabled'=>scalar($db,"SELECT enabled FROM auto_ia_lab.mock_s2s_keys WHERE key_id='SYNTHETIC_KEY_A'")!=='',
            'real_authority_verified'=>false];
        must($current['authority']===['active'=>false,'revision'=>2] && $current['grant_active']===false &&
            $current['link_active']===false && $current['key_a_enabled']===false,'CURRENT_REVOCATION_STATE');
        echo json_encode($current,JSON_THROW_ON_ERROR)."\n";
    }elseif($mode==='verify-restored'){
        $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
        must(is_array($input)&&array_keys($input)===['seed','current'],'RESTORE_INPUT_SCHEMA');
        $seed=$input['seed'];$current=$input['current'];
        must(($seed['schema']??null)==='P25_SEEDED_CANDIDATE_V1' &&
            ($current['schema']??null)==='P25_CURRENT_AUTHORITY_FIXTURE_V1' &&
            ($current['tenant_ref']??null)===TENANT_A && ($current['company_id']??null)===COMPANY_A &&
            ($current['authority']??null)===['active'=>false,'revision'=>2] &&
            ($current['grant_revision']??null)===2 && ($current['grant_active']??null)===false &&
            ($current['link_revision']??null)===2 && ($current['link_active']??null)===false &&
            ($current['key_a_enabled']??null)===false && ($current['real_authority_verified']??null)===false,
            'INDEPENDENT_CURRENT_FIXTURE_REQUIRED');
        security($db);
        must(states($db)===$seed['outbox_states'],'RESTORED_OUTBOX_STATES');
        must(scalar($db,'SELECT count(*) FROM aia_p16_lab.event_replay')==='4','RESTORED_REPLAY_LEDGER');
        must($cycle->authority()->snapshot(TENANT_A)===['active'=>true,'revision'=>1],'OLD_SNAPSHOT_FIXTURE_REQUIRED');
        $before=states($db);
        must(zero($cycle->reserve(TENANT_A,WORKER_A,$now+261,2))['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE','STALE_SNAPSHOT_LEASE_DENIED');
        must(zero($cycle->acknowledge(TENANT_A,'SYNTHETIC_P25_A_LEASED',WORKER_A,1,$now+201,2))['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE','STALE_SNAPSHOT_ACK_DENIED');
        must(states($db)===$before,'QUARANTINE_CHANGED_OUTBOX');
        // Only monotonic revocations from the retained source fixture are applied.
        // Transport remains NOLOGIN before AND after this reconciliation.
        $db->beginTransaction();
        must($cycle->authority()->revoke(TENANT_A)===2,'RESTORE_AUTHORITY_RECONCILIATION');
        must($db->exec("UPDATE auto_ia_lab.service_company_grants SET active=false,revision=2 WHERE company_id='".COMPANY_A."' AND revision=1")===1,'RESTORE_GRANT_RECONCILIATION');
        must($db->exec("UPDATE auto_ia_lab.company_links SET active=false,revision=2 WHERE company_id='".COMPANY_A."' AND revision=1")===1,'RESTORE_LINK_RECONCILIATION');
        must($db->exec("UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE key_id='SYNTHETIC_KEY_A' AND enabled")===1,'RESTORE_KEY_RECONCILIATION');
        $db->commit();
        must(receive($db,COMPANY_A,str_repeat('a',64),envelope(TENANT_A,'SYNTHETIC_P25_RECOVERED_A','SYNTHETIC_KEY_A'))==='LAB_DENY_GRANT','POST_SNAPSHOT_REVOCATION_PRESERVED');
        must(receive($db,COMPANY_C,str_repeat('c',64),envelope('SYNTHETIC_TENANT_C','SYNTHETIC_P25_RECOVERED_C'))==='LAB_DENY_GRANT','PRE_SNAPSHOT_REVOCATION_PRESERVED');
        must(receive($db,COMPANY_B,str_repeat('b',64),envelope(TENANT_B,'SYNTHETIC_P25_REVOKED_KEY','SYNTHETIC_KEY_A'))==='LAB_DENY_KEY','REVOKED_KEY_DENIED');
        must(receive($db,COMPANY_B,str_repeat('a',64),envelope(TENANT_B,'SYNTHETIC_P25_WRONG_CERT'))==='LAB_DENY_GRANT','CERTIFICATE_BINDING_DENIED');
        must(receive($db,COMPANY_B,str_repeat('b',64),envelope(TENANT_A,'SYNTHETIC_P25_WRONG_TENANT'))==='LAB_DENY_GRANT','CROSS_TENANT_DENIED');
        // Re-sign the historical event with a deliberately different hash and a
        // fresh window: the restored event/nonce cannot be admitted a second time.
        $historical=envelope(TENANT_B,'SYNTHETIC_P25_INBOX_B');
        $historical['payload_hash']=hash('sha256','P25_DIFFERENT_HISTORY');
        unset($historical['signature']);
        $historical['signature']=hash_hmac('sha256',implode("\n",array_values($historical)),KEY_B);
        must(receive($db,COMPANY_B,str_repeat('b',64),$historical)==='LAB_DENY_REPLAY_CONFLICT','RESTORED_INBOX_REPLAY_CONFLICT');
        must(scalar($db,'SELECT count(*) FROM auto_ia_lab.event_inbox')==='2','NEGATIVES_CREATED_INBOX');
        $fresh=envelope(TENANT_B,'SYNTHETIC_P25_FRESH_B');
        must(receive($db,COMPANY_B,str_repeat('b',64),$fresh)==='LAB_RECEIVED_NO_DELIVERY','RESTORED_POSITIVE_CONTROL');
        must(receive($db,COMPANY_B,str_repeat('b',64),$fresh)==='LAB_DUPLICATE_NO_DELIVERY','RESTORED_DUPLICATE');
        must(scalar($db,'SELECT count(*) FROM auto_ia_lab.event_inbox')==='3','RESTORED_INBOX_IDEMPOTENCY');
        must(zero(stage($cycle,TENANT_B,'SYNTHETIC_P25_B_LEASED'))['decision']==='DENY','RESTORED_OUTBOX_REPLAY');
        $lease=zero($cycle->reserve(TENANT_B,WORKER_B,$now+260,1));
        must($lease['decision']==='LAB_LEASE_RESERVED_NO_DELIVERY'&&$lease['attempt']===2,'RESTORED_LEASE_RETRY');
        $fenced=states($db);
        must(zero($cycle->acknowledge(TENANT_B,'SYNTHETIC_P25_B_LEASED',WORKER_B,1,$now+261,1))['reason']==='LEASE_OR_FENCING_DENIED','RESTORED_STALE_ACK');
        must(zero($cycle->acknowledge(TENANT_B,'SYNTHETIC_P25_B_LEASED',WORKER_A,2,$now+261,1))['reason']==='LEASE_OR_FENCING_DENIED','RESTORED_WRONG_WORKER_ACK');
        must(states($db)===$fenced,'FENCING_DENIAL_CHANGED_OUTBOX');
        must(zero($cycle->acknowledge(TENANT_B,'SYNTHETIC_P25_B_LEASED',WORKER_B,2,$now+261,1))['decision']==='LAB_ACKED_NO_DELIVERY_PROOF','RESTORED_VALID_ACK');
        must(zero($cycle->reserve(TENANT_B,WORKER_B,$now+262,1))['decision']==='LAB_NO_PENDING','ACKED_NOT_REDISPATCHED');
        must(scalar($db,"SELECT state FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P25_A_ACKED'")==='ACKED','ACKED_TERMINAL_PRESERVED');
        must(scalar($db,"SELECT state FROM public.aia_lab_n8n_outbox_p6 WHERE event_ref='SYNTHETIC_P25_A_DEAD'")==='DEAD','DEAD_TERMINAL_PRESERVED');
        must(scalar($db,'SELECT count(*) FROM public.aia_lab_n8n_outbox_p6')==='4'&&
            scalar($db,'SELECT count(*) FROM aia_p16_lab.event_replay')==='4','OUTBOX_OR_LEDGER_COUNT_CHANGED');
        must(zero($cycle->reserve(TENANT_A,WORKER_A,$now+261,1))['reason']==='TENANT_AUTHORITY_REVOKED_OR_STALE','OLD_AUTHORITY_NOT_RESUMED');
        security($db);
        echo json_encode(['schema'=>'P25_AGENT_RECOVERY_BEHAVIOR_V1',
            'actual_candidate_inbox_schema_restored'=>true,'actual_candidate_outbox_schema_restored'=>true,
            'owners_acls_rls_preserved'=>true,'roles_remained_nologin'=>true,
            'pre_snapshot_revocation_preserved'=>true,'post_snapshot_revocation_reconciled_from_separate_fixture'=>true,
            'stale_snapshot_lease_and_ack_denied_before_reconciliation'=>true,
            'historical_inbox_conflict_denied'=>true,'new_event_duplicate_idempotent'=>true,
            'durable_outbox_replay_denied'=>true,'lease_attempt_fencing_preserved'=>true,
            'lease_worker_fencing_preserved'=>true,
            'terminal_acked_dead_preserved'=>true,'outbox_rls_verified'=>false,
            'real_authority_verified'=>false,'live_mtls_runtime_verified'=>false,
            'dispatch'=>'DISABLED','ai_calls'=>0,'service_effects'=>0,'go_for_installation'=>false],JSON_THROW_ON_ERROR)."\n";
    }else{throw new RuntimeException('P25_MODE_DENIED');}
}catch(Throwable $error){
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    $code=$error instanceof RuntimeException && str_starts_with($error->getMessage(),'P25_')
        ?$error->getMessage():strtoupper(str_replace('\\','_',get_class($error)));
    fwrite(STDERR,'P25_PROBE_DENIED_'.$code."\n");
    exit(1);
}
