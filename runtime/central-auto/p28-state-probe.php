<?php
declare(strict_types=1);
/** P28 disposable recovery probes. No transport listener or executor capability. */
require_once __DIR__.'/lab-executor/PgNativeExecutionLedgerLab.php';
require_once __DIR__.'/../../tools/labs/PgOwnerDecisionLab.php';
require_once __DIR__.'/p28-constraint-compare.php';

use ImagineAI\NativeLab\PgNativeExecutionLedgerLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;

const P28_JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
const P28_A='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const P28_B='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
const P28_ROLES=['aia_lab_owner','aia_lab_tenant_a','aia_lab_tenant_b',
    'aia_private_transport','aia_private_receipt','ca_mtls_lab_owner','ca_mtls_lab_service'];
const P28_TABLES=[
    'public.aia_lab_n8n_outbox_p6'=>'tenant_ref,event_ref',
    'aia_p16_lab.event_replay'=>'tenant_ref,event_ref',
    'aia_p26_native.executions'=>'tenant_ref,event_ref',
    'aia_p34_lab.tenant_policy'=>'tenant_ref',
    'aia_p37_lab.executor_keys'=>'tenant_ref,key_id',
    'aia_p38_lab.signed_ack_evidence'=>'tenant_ref,event_ref',
    'aia_p41_lab.company_tenant_links'=>'company_id',
    'aia_p42_lab.consumed_transport_proofs'=>'company_id,nonce',
    'aia_p44_lab.owner_keys'=>'company_id,key_id',
    'aia_p44_lab.consumed_owner_grants'=>'company_id,grant_ref',
    'central_auto_lab.mtls_nonce_ledger'=>'company_id,nonce',
    'auto_ia_lab.event_inbox'=>'tenant,product,event_ref',
    'auto_ia_lab.company_links'=>'company_id',
    'auto_ia_lab.service_company_grants'=>'company_id,fingerprint,operation',
    'auto_ia_lab.service_event_audit'=>'id',
    'auto_ia_lab.mock_s2s_keys'=>'issuer,key_id',
    'auto_ia_lab.receipt_read_grants'=>'company_id,fingerprint,operation',
];
const P28_READ_FUNCTION='auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)';

function p28Must(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('P28_'.$label);
}
function p28Hash(mixed $value):string {return hash('sha256',json_encode($value,P28_JSON));}
function p28Rows(PDO $db,string $query):array {return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);}
function p28Scalar(PDO $db,string $query):mixed {return $db->query($query)->fetchColumn();}
function p28True(mixed $value):bool {return in_array($value,[true,1,'1','t'],true);}
function p28Digest(mixed $value):bool {
    return is_string($value)&&preg_match('/\A[a-f0-9]{64}\z/D',$value)===1;
}
function p28Role(PDO $db,string $role,callable $operation):mixed {
    p28Must(in_array($role,['aia_private_receipt','aia_private_transport'],true),'ROLE_ALLOWLIST');
    $db->exec('SET ROLE '.$role);
    try{return $operation();}finally{$db->exec('RESET ROLE');}
}
/** Diagnostic text only. Literal values never leave this probe in DDL form. */
function p28ConstraintShape(string $definition):array {
    return P28ConstraintCompare::shape($definition);
}
/** Nominal catalog identities only: OIDs and raw conbin/location are unstable. */
function p28ConstraintDetails(PDO $db,array $constraints):array {
    $catalog=p28Rows($db,<<<'SQL'
SELECT n.nspname,c.relname,k.conname,k.contype::text AS kind,
 k.convalidated AS validated,k.condeferrable AS deferrable,
 k.condeferred AS initially_deferred,k.conislocal AS local,
 k.coninhcount AS inherited_count,k.connoinherit AS no_inherit,
 COALESCE((SELECT jsonb_agg(a.attname ORDER BY ord.position)
  FROM unnest(k.conkey) WITH ORDINALITY AS ord(attnum,position)
  JOIN pg_attribute a ON a.attrelid=k.conrelid AND a.attnum=ord.attnum),'[]'::jsonb)::text AS columns_json,
 COALESCE((SELECT jsonb_agg(jsonb_build_object('name',a.attname,
   'type',format_type(a.atttypid,a.atttypmod),
   'collation',cn.nspname||'.'||co.collname) ORDER BY ord.position)
  FROM unnest(k.conkey) WITH ORDINALITY AS ord(attnum,position)
  JOIN pg_attribute a ON a.attrelid=k.conrelid AND a.attnum=ord.attnum
  LEFT JOIN pg_collation co ON co.oid=a.attcollation
  LEFT JOIN pg_namespace cn ON cn.oid=co.collnamespace),'[]'::jsonb)::text AS column_types_json,
 CASE WHEN k.confrelid<>0 THEN rn.nspname||'.'||rc.relname END AS referenced_table,
 COALESCE((SELECT jsonb_agg(a.attname ORDER BY ord.position)
  FROM unnest(k.confkey) WITH ORDINALITY AS ord(attnum,position)
  JOIN pg_attribute a ON a.attrelid=k.confrelid AND a.attnum=ord.attnum),'[]'::jsonb)::text AS referenced_columns_json,
 COALESCE((SELECT jsonb_agg(jsonb_build_object('name',a.attname,
   'type',format_type(a.atttypid,a.atttypmod),
   'collation',cn.nspname||'.'||co.collname) ORDER BY ord.position)
  FROM unnest(k.confkey) WITH ORDINALITY AS ord(attnum,position)
  JOIN pg_attribute a ON a.attrelid=k.confrelid AND a.attnum=ord.attnum
  LEFT JOIN pg_collation co ON co.oid=a.attcollation
  LEFT JOIN pg_namespace cn ON cn.oid=co.collnamespace),'[]'::jsonb)::text AS referenced_column_types_json,
 CASE WHEN k.contype='f' THEN k.confmatchtype::text END AS match_type,
 CASE WHEN k.contype='f' THEN k.confupdtype::text END AS on_update,
 CASE WHEN k.contype='f' THEN k.confdeltype::text END AS on_delete,
 CASE WHEN k.conindid<>0 THEN ixn.nspname||'.'||ix.relname END AS index_name,
 pg_get_expr(k.conbin,k.conrelid,false) AS expression,
 (SELECT e.extname FROM pg_depend d JOIN pg_extension e ON e.oid=d.refobjid
  WHERE d.classid='pg_constraint'::regclass AND d.objid=k.oid
   AND d.refclassid='pg_extension'::regclass AND d.deptype='e') AS extension
FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid
JOIN pg_namespace n ON n.oid=c.relnamespace
LEFT JOIN pg_class rc ON rc.oid=k.confrelid
LEFT JOIN pg_namespace rn ON rn.oid=rc.relnamespace
LEFT JOIN pg_class ix ON ix.oid=k.conindid
LEFT JOIN pg_namespace ixn ON ixn.oid=ix.relnamespace
WHERE n.nspname LIKE 'aia\_%' ESCAPE '\'
 OR n.nspname IN ('auto_ia_lab','central_auto_lab')
 OR (n.nspname='public' AND c.relname='aia_lab_n8n_outbox_p6')
ORDER BY n.nspname,c.relname,k.conname
SQL);
    p28Must(count($catalog)===count($constraints),'CONSTRAINT_DIAGNOSTIC_COUNT');
    $contexts=[];
    foreach($catalog as $i=>$row){
        $original=$constraints[$i];
        foreach(['nspname','relname','conname'] as $name){
            p28Must($row[$name]===$original[$name]
                &&preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,62}\z/D',$row[$name])===1,
                'CONSTRAINT_DIAGNOSTIC_IDENTITY');
        }
        $identity=$row['nspname'].'.'.$row['relname'].'.'.$row['conname'];
        p28Must(!isset($contexts[$identity]),'CONSTRAINT_DIAGNOSTIC_DUPLICATE');
        $structure=['kind'=>$row['kind']];
        foreach(['validated','deferrable','initially_deferred','local','no_inherit'] as $name)
            $structure[$name]=p28True($row[$name]);
        $structure['inherited_count']=(int)$row['inherited_count'];
        $structure['columns']=json_decode($row['columns_json'],true,8,JSON_THROW_ON_ERROR);
        $structure['column_types']=json_decode($row['column_types_json'],true,8,JSON_THROW_ON_ERROR);
        $structure['referenced_table']=$row['referenced_table'];
        $structure['referenced_columns']=json_decode($row['referenced_columns_json'],true,8,JSON_THROW_ON_ERROR);
        $structure['referenced_column_types']=json_decode($row['referenced_column_types_json'],true,8,JSON_THROW_ON_ERROR);
        foreach(['match_type','on_update','on_delete','index_name','extension'] as $name)
            $structure[$name]=$row[$name];
        $contexts[$identity]=['expression'=>$row['expression'],'structure'=>$structure];
    }
    return P28ConstraintCompare::capture($constraints,$contexts);
}
function p28Snapshot(PDO $db):array {
    $tables=[];
    foreach(P28_TABLES as $table=>$order){
        $rows=p28Rows($db,'SELECT * FROM '.$table.' ORDER BY '.$order);
        $tables[$table]=['rows'=>count($rows),'sha256'=>p28Hash($rows)];
    }
    $objects=p28Rows($db,<<<'SQL'
SELECT n.nspname,c.relname,pg_get_userbyid(c.relowner) AS owner,
 c.relrowsecurity,c.relforcerowsecurity
FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE c.relkind='r' AND (n.nspname LIKE 'aia\_%' ESCAPE '\'
 OR n.nspname IN ('auto_ia_lab','central_auto_lab')
 OR (n.nspname='public' AND c.relname='aia_lab_n8n_outbox_p6'))
ORDER BY n.nspname,c.relname
SQL);
    $functions=p28Rows($db,<<<'SQL'
SELECT n.nspname,p.proname,pg_get_function_identity_arguments(p.oid) AS arguments,
 pg_get_userbyid(p.proowner) AS owner,p.prosecdef,p.proconfig,
 pg_get_functiondef(p.oid) AS definition
FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
WHERE n.nspname IN ('auto_ia_lab','central_auto_lab')
ORDER BY n.nspname,p.proname,arguments
SQL);
    $constraints=p28Rows($db,<<<'SQL'
SELECT n.nspname,c.relname,k.conname,pg_get_constraintdef(k.oid) AS definition
FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid
JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname LIKE 'aia\_%' ESCAPE '\'
 OR n.nspname IN ('auto_ia_lab','central_auto_lab')
 OR (n.nspname='public' AND c.relname='aia_lab_n8n_outbox_p6')
ORDER BY n.nspname,c.relname,k.conname
SQL);
    return ['schema'=>'P28_DATABASE_SNAPSHOT_V1','tables'=>$tables,
        'objects_sha256'=>p28Hash($objects),'functions_sha256'=>p28Hash($functions),
        // capture retains the original ordered raw hash and additionally
        // commits to the one exact, observed varchar-array round-trip form.
        ...p28ConstraintDetails($db,$constraints)];
}
function p28Permissions(PDO $db,bool $readEnabled):void {
    foreach(P28_ROLES as $role){
        $q=$db->prepare('SELECT NOT(rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls '
            .'OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname=:role');
        $q->execute([':role'=>$role]);p28Must(p28True($q->fetchColumn()),'RESTORED_ROLE_NOT_SAFE');
        $q=$db->prepare('SELECT count(*) FROM pg_auth_members WHERE member=CAST(:role AS regrole)');
        $q->execute([':role'=>$role]);p28Must((int)$q->fetchColumn()===0,'RESTORED_MEMBERSHIP_FORBIDDEN');
    }
    p28Must((int)p28Scalar($db,"SELECT count(*) FROM pg_roles WHERE rolname='aia_private_receipt_login'")===0,
        'HTTP_LOGIN_MUST_NOT_BE_RESTORED');
    p28Must(p28True(p28Scalar($db,"SELECT has_function_privilege('aia_private_receipt',
        'auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)','EXECUTE')"))===$readEnabled,
        'RECEIPT_EXECUTE_ACL');
    p28Must(!p28True(p28Scalar($db,"SELECT has_function_privilege('aia_private_transport',
        'auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)','EXECUTE')")),
        'TRANSPORT_CANNOT_READ_RECEIPT');
    p28Must(!p28True(p28Scalar($db,"SELECT has_function_privilege('aia_private_receipt',
        'auto_ia_lab.receive_company_signed_no_ai(uuid,text,jsonb)','EXECUTE')")),
        'RECEIPT_CANNOT_INGEST');
    p28Must((int)p28Scalar($db,"SELECT count(*) FROM pg_proc p,
        LATERAL aclexplode(coalesce(p.proacl,acldefault('f',p.proowner))) a
        WHERE p.oid='auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)'::regprocedure
        AND a.grantee=0 AND a.privilege_type='EXECUTE'")===0,'PUBLIC_EXECUTE_RESTORED');
    foreach(['event_inbox','receipt_read_grants','service_company_grants','mock_s2s_keys'] as $table){
        p28Must(!p28True(p28Scalar($db,"SELECT has_table_privilege('aia_private_receipt','auto_ia_lab."
            .$table."','SELECT,INSERT,UPDATE,DELETE')")),'RECEIPT_DIRECT_TABLE_ACCESS');
        p28Must(p28True(p28Scalar($db,"SELECT relrowsecurity AND relforcerowsecurity
            AND pg_get_userbyid(relowner)='aia_lab_owner' FROM pg_class
            WHERE oid='auto_ia_lab.".$table."'::regclass")),'READ_TABLE_OWNER_RLS');
    }
    p28Must(p28True(p28Scalar($db,"SELECT prosecdef AND pg_get_userbyid(proowner)='aia_lab_owner'
        AND proconfig @> ARRAY['search_path=pg_catalog, auto_ia_lab'] FROM pg_proc
        WHERE oid='auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)'::regprocedure")),
        'READ_FUNCTION_OWNER_SEARCH_PATH');
}
function p28Grant(PDO $db,string $company):array {
    $q=$db->prepare("SELECT company_id,fingerprint,tenant,operation,active,revision,expires_at
        FROM auto_ia_lab.receipt_read_grants WHERE company_id=CAST(:company AS uuid)
        AND operation='event.receipt.read'");
    $q->execute([':company'=>$company]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    p28Must(count($rows)===1,'READ_GRANT_NOT_UNIQUE');return $rows[0];
}
function p28Owner(PDO $db,array $context):array {
    $q=$db->prepare('SELECT k.company_id,k.key_id,k.public_key_b64,k.fingerprint,k.active,k.revision
        FROM aia_p44_lab.owner_keys k JOIN aia_p44_lab.consumed_owner_grants g
        ON k.company_id=g.company_id AND k.key_id=g.signer_key_id
        WHERE g.company_id=CAST(:company AS uuid) AND g.tenant_ref=:tenant AND g.event_ref=:event');
    $q->execute([':company'=>$context['company_id'],':tenant'=>$context['tenant_ref'],':event'=>$context['event_ref']]);
    $row=$q->fetch(PDO::FETCH_ASSOC);p28Must(is_array($row),'OWNER_NOT_FOUND');return $row;
}

try{
    p28Must(PHP_SAPI==='cli','CLI_ONLY');
    foreach(['GITHUB_ACTIONS'=>'true','GITHUB_REPOSITORY'=>'rafhael100/imagine-ai-agents',
        'AIA_P6_PG_LAB'=>'true','AIA_P16_PG_LAB'=>'true','DB_HOST'=>'127.0.0.1',
        'DB_DATABASE'=>'imagine_agents_ci','DB_USERNAME'=>'postgres'] as $name=>$value)
        p28Must(getenv($name)===$value,'DISPOSABLE_CONTEXT');
    $mode=$argv[1]??'';
    // Only restore comparison carries the complete diagnostic snapshot back
    // through RPC. Other request limits remain unchanged.
    $inputLimit=$mode==='verify-and-quarantine'?524288:131072;
    $raw=stream_get_contents(STDIN,$inputLimit+1);
    p28Must(is_string($raw)&&strlen($raw)<=$inputLimit,'INPUT_LIMIT');
    $input=$raw===''?[]:json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    p28Must(is_array($input),'INPUT_OBJECT');
    $password=file_get_contents('/run/secrets/p25-password');
    p28Must(is_string($password)&&strlen($password)===64,'PASSWORD_FILE');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET statement_timeout='5000ms'; SET lock_timeout='1000ms'; SET idle_in_transaction_session_timeout='10s'");
    if($mode==='seed-receipts'){
        $events=$input['events']??null;$fingerprints=$input['fingerprints']??null;
        p28Must(is_array($events)&&array_keys($events)===['A','B']&&is_array($fingerprints),
            'RECEIPT_FIXTURE_FIELDS');
        $expiry=time()+1800;
        $db->exec("UPDATE auto_ia_lab.mock_s2s_keys SET enabled=true WHERE key_id='SYNTHETIC_KEY_B'");
        foreach(['A'=>P28_A,'B'=>P28_B] as $name=>$company){
            $tenant='SYNTHETIC_TENANT_'.$name;$fp=$fingerprints[$name]??null;$event=$events[$name];
            p28Must(p28Digest($fp)&&$event['company_id']===$company
                &&$event['envelope']['tenant_ref']===$tenant,'RECEIPT_FIXTURE_SCOPE');
            $q=$db->prepare('INSERT INTO auto_ia_lab.company_links VALUES(CAST(? AS uuid),?,true,1)');
            $q->execute([$company,$tenant]);
            $q=$db->prepare("INSERT INTO auto_ia_lab.service_company_grants
                VALUES(CAST(? AS uuid),?,?,'lead.ingest',true,1,?)");
            $q->execute([$company,$fp,$tenant,$expiry]);
            $q=$db->prepare("INSERT INTO auto_ia_lab.receipt_read_grants
                VALUES(CAST(? AS uuid),?,?,'event.receipt.read',true,1,?)");
            $q->execute([$company,$fp,$tenant,$expiry]);
            $decision=p28Role($db,'aia_private_transport',function()use($db,$company,$fp,$event):string{
                $q=$db->prepare('SELECT auto_ia_lab.receive_company_signed_no_ai(CAST(? AS uuid),?,CAST(? AS jsonb))');
                $q->execute([$company,$fp,json_encode($event['envelope'],P28_JSON)]);return (string)$q->fetchColumn();
            });
            p28Must($decision==='LAB_RECEIVED_NO_DELIVERY','ORIGINAL_P19_ADMISSION');
        }
        $out=['schema'=>'P28_RECEIPT_FIXTURE_V1','received'=>2,'read_grants_active'=>2,
            'new_mtls_exchange_verified'=>false,'operational_authority'=>false];
    }elseif($mode==='snapshot'){$out=p28Snapshot($db);
    }elseif($mode==='revoke-after-snapshot'){
        p28Must(p28Digest($input['dump_sha256']??null),'DUMP_BINDING');
        $q=$db->prepare("UPDATE auto_ia_lab.receipt_read_grants SET active=false,revision=revision+1
            WHERE company_id=CAST(:company AS uuid) AND operation='event.receipt.read' AND active AND revision=1");
        $q->execute([':company'=>P28_A]);p28Must($q->rowCount()===1,'POST_SNAPSHOT_READ_REVOCATION');
        $context=$input['main_context'];$owner=p28Owner($db,$context);
        p28Must((new PgOwnerDecisionLab($db))->revokeKey($owner['company_id'],$owner['key_id'])===2,
            'POST_SNAPSHOT_OWNER_REVOCATION');
        $out=['schema'=>'P28_CURRENT_REVOCATION_FIXTURE_V1','dump_sha256'=>$input['dump_sha256'],
            'read_revoked'=>p28Grant($db,P28_A),'read_unchanged'=>p28Grant($db,P28_B),
            'owner_revoked'=>p28Owner($db,$context),'real_authority_verified'=>false];
    }elseif($mode==='verify-and-quarantine'){
        p28Must(is_array($input['snapshot']??null),'RESTORED_SNAPSHOT_REQUIRED');
        $comparison=P28ConstraintCompare::compare($input['snapshot'],p28Snapshot($db));
        p28Must($comparison!==null,'RESTORED_CONTENT_OR_SCHEMA_CHANGED');
        p28Permissions($db,true);
        $db->exec('REVOKE EXECUTE ON FUNCTION '.P28_READ_FUNCTION.' FROM aia_private_receipt');
        p28Permissions($db,false);
        $out=['schema'=>'P28_RESTORED_QUARANTINE_V1','exact_state_restored'=>true,
            'receipt_execute_suspended'=>true,'roles_nologin'=>true,'memberships_absent'=>true,
            'constraint_cast_equivalences'=>$comparison['equivalences'],
            'constraint_comparison_rule'=>$comparison['rule'],
            'raw_target_constraint_aggregate_verified'=>true];
    }elseif($mode==='apply-current-revocations'){
        $current=$input['current']??null;
        p28Must(is_array($current)&&($current['schema']??null)==='P28_CURRENT_REVOCATION_FIXTURE_V1'
            &&($current['real_authority_verified']??null)===false
            &&p28Digest($current['dump_sha256']??null)
            &&hash_equals($input['dump_sha256'],$current['dump_sha256']),'INDEPENDENT_CURRENT_FIXTURE_REQUIRED');
        p28Permissions($db,false);
        $db->beginTransaction();
        $old=p28Grant($db,P28_A);$revoked=$current['read_revoked'];
        p28Must(p28True($old['active'])&&(int)$old['revision']===1
            &&!p28True($revoked['active'])&&(int)$revoked['revision']===2,'READ_MONOTONIC_REVOCATION');
        $expected=$old;$expected['active']=false;$expected['revision']=2;
        p28Must($revoked===$expected,'READ_REVOCATION_CHANGED_SCOPE_OR_EXPIRY');
        p28Must(p28Grant($db,P28_B)===$current['read_unchanged']
            &&p28True($current['read_unchanged']['active'])
            &&(int)$current['read_unchanged']['expires_at']>time(),'CONTROL_CURRENT_AUTHORITY_MISMATCH');
        $owner=p28Owner($db,$input['main_context']);$expectedOwner=$owner;
        $expectedOwner['active']=false;$expectedOwner['revision']=2;
        p28Must(p28True($owner['active'])&&(int)$owner['revision']===1
            &&$current['owner_revoked']===$expectedOwner,'OWNER_MONOTONIC_REVOCATION');
        $q=$db->prepare("UPDATE auto_ia_lab.receipt_read_grants SET active=false,revision=2
            WHERE company_id=CAST(:company AS uuid) AND active AND revision=1");
        $q->execute([':company'=>P28_A]);p28Must($q->rowCount()===1,'APPLY_READ_REVOCATION');
        p28Must((new PgOwnerDecisionLab($db))->revokeKey($owner['company_id'],$owner['key_id'])===2,
            'APPLY_OWNER_REVOCATION');
        p28Must(p28Grant($db,P28_A)===$revoked&&p28Owner($db,$input['main_context'])===$expectedOwner,
            'CURRENT_REVOCATION_NOT_APPLIED');
        // Restore only this reviewed technical EXECUTE ACL, while all roles
        // stay NOLOGIN. No business grant is enabled or extended by recovery.
        $db->exec('GRANT EXECUTE ON FUNCTION '.P28_READ_FUNCTION.' TO aia_private_receipt');
        $db->commit();p28Permissions($db,true);
        $out=['schema'=>'P28_REVOCATIONS_APPLIED_V1','read_revision'=>2,'owner_revision'=>2,
            'business_grants_reenabled'=>0,'expiry_windows_extended'=>0,'roles_nologin'=>true];
    }elseif($mode==='query-receipt'){
        $query=$input['query'];$fp=$input['fingerprint'];p28Must(p28Digest($fp),'QUERY_FINGERPRINT');
        $denied=false;$receipt=null;
        try{
            $raw=p28Role($db,'aia_private_receipt',function()use($db,$query,$fp){
                $q=$db->prepare('SELECT auto_ia_lab.read_company_event_receipt(CAST(? AS uuid),?,CAST(? AS jsonb))');
                $q->execute([$query['company_id'],$fp,json_encode($query,P28_JSON)]);return $q->fetchColumn();
            });
            if(is_string($raw))$receipt=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        }catch(PDOException $e){if($e->getCode()!=='42501')throw $e;$denied=true;}
        $out=['schema'=>'P28_RECEIPT_QUERY_V1','permission_denied'=>$denied,'receipt'=>$receipt];
    }elseif($mode==='verify-native'){
        $contexts=$input['contexts'];$ledger=new PgNativeExecutionLedgerLab($db);
        $before=p28Snapshot($db);
        $stableLedger="SELECT (to_jsonb(e)-'updated_at')::text AS stable_state
            FROM aia_p26_native.executions e ORDER BY tenant_ref,event_ref";
        $ledgerBefore=p28Rows($db,$stableLedger);
        foreach(['MAIN','MAIN','MALFORMED','FAILURE','MALFORMED','FAILURE','AFTER_START'] as $name){
            $r=$ledger->reconcile($contexts[$name],time());
            if($name==='MAIN')p28Must(($r['decision']??null)==='P26_ACK_EVIDENCE_CONFIRMED'
                &&$r['state']==='ACKED','HISTORICAL_ACK_NOT_IDEMPOTENT');
            elseif($name==='AFTER_START')p28Must($r['decision']==='DENY'&&$r['state']==='RESULT'
                &&$r['reason']==='RECEIPT_INVALID_WINDOW','REVOKED_RESULT_MUST_NOT_ACK');
            else p28Must($r['decision']==='P26_MANUAL_RECONCILIATION_REQUIRED'
                &&$r['state']==='UNKNOWN'&&$r['execute_again']===false,'UNKNOWN_RETRY_FORBIDDEN');
        }
        $after=p28Snapshot($db);
        p28Must(p28Rows($db,$stableLedger)===$ledgerBefore,'STORED_RESULT_RECEIPT_OR_CONTEXT_CHANGED');
        foreach(['public.aia_lab_n8n_outbox_p6','aia_p16_lab.event_replay',
            'aia_p38_lab.signed_ack_evidence','aia_p44_lab.consumed_owner_grants'] as $table)
            p28Must($before['tables'][$table]===$after['tables'][$table],'NATIVE_RECONCILIATION_CHANGED_AUTHORITY_OR_EVIDENCE');
        p28Must((int)p28Scalar($db,"SELECT count(*) FROM public.aia_lab_n8n_outbox_p6 WHERE state='ACKED'")===1
            &&(int)p28Scalar($db,'SELECT count(*) FROM aia_p38_lab.signed_ack_evidence')===1,'EXACT_ACK_EVIDENCE_COUNT');
        $states=p28Rows($db,'SELECT state,count(*) AS count FROM aia_p26_native.executions GROUP BY state ORDER BY state');
        p28Must($states===[['state'=>'ACKED','count'=>1],['state'=>'RESULT','count'=>1],['state'=>'UNKNOWN','count'=>2]],
            'RESTORED_LEDGER_STATES');
        $out=['schema'=>'P28_NATIVE_RECONCILIATION_V1','historical_ack_idempotent'=>true,
            'signed_evidence_count'=>1,'result_preserved_without_ack'=>true,'unknown_automatic_retries'=>0,
            'outbox_attempts_unchanged'=>true,'native_execution_capability_present'=>false];
    }else{throw new RuntimeException('P28_MODE_DENIED');}
    echo json_encode($out,P28_JSON)."\n";
}catch(Throwable $error){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    $label=$error instanceof RuntimeException&&preg_match('/\AP28_[A-Z0-9_]+\z/D',$error->getMessage())
        ?$error->getMessage():'P28_INTERNAL_FAILURE';
    if($error instanceof PDOException&&preg_match('/\A[A-Z0-9]{5}\z/D',(string)$error->getCode()))
        $label='P28_DATABASE_'.$error->getCode();
    // P25's shared RPC accepts closed JSON. Keep a fixed error verdict there
    // so the P28 caller can report the diagnostic without exposing SQL/stderr.
    echo json_encode(['schema'=>'P28_PROBE_DENIED_V1','reason'=>$label],P28_JSON)."\n";
}
