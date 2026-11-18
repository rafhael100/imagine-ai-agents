<?php
declare(strict_types=1);

/** Administrative evidence inside a new, job-owned PG16 only; never an HTTP route. */
function p68Need(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('P68_'.$label);
}
function p68Rows(PDO $db, string $sql): array { return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
function p68Hash(mixed $value): string { return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR)); }
function p68Sorted(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as $key=>$item) $value[$key]=p68Sorted($item);
    if (!array_is_list($value)) ksort($value,SORT_STRING);
    return $value;
}
function p68Acl(string $expression): string
{
    return "(SELECT COALESCE(jsonb_agg(jsonb_build_array(pg_get_userbyid(x.grantor),"
        ."CASE WHEN x.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(x.grantee) END,x.privilege_type,x.is_grantable) "
        ."ORDER BY pg_get_userbyid(x.grantor),CASE WHEN x.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(x.grantee) END,"
        ."x.privilege_type,x.is_grantable),'[]'::jsonb) FROM aclexplode($expression) x)";
}
function p68Capture(PDO $db): array
{
    $tables=[];
    foreach (['companies'=>'company_id','mac_keys'=>'key_id','service_grants'=>'company_id,fingerprint,operation',
        'drafts'=>'company_id,draft_ref','nonces'=>'company_id,operation,nonce','audit'=>'id'] as $name=>$order) {
        $rows=$db->query("SELECT to_jsonb(t)::text FROM aia_p67_owner.$name t ORDER BY $order")->fetchAll(PDO::FETCH_COLUMN);
        $tables[$name]=['rows'=>count($rows),'sha256'=>p68Hash($rows)];
    }
    $schemaAcl=p68Acl("coalesce(n.nspacl,acldefault('n',n.nspowner))");
    // pg_class uses 'S'; acldefault uses 's' for SEQUENCE ('S' is FOREIGN SERVER).
    $relationAcl=p68Acl("coalesce(c.relacl,acldefault(CASE WHEN c.relkind='S' THEN 's'::\"char\" ELSE 'r'::\"char\" END,c.relowner))");
    $columnAcl=p68Acl('a.attacl');
    $functionAcl=p68Acl("coalesce(p.proacl,acldefault('f',p.proowner))");
    $defaultAcl=p68Acl('d.defaclacl');
    $queries=[
        'schemas'=>"SELECT n.nspname,pg_get_userbyid(n.nspowner) owner,$schemaAcl acl FROM pg_namespace n WHERE n.nspname IN ('public','aia_p67_owner') ORDER BY n.nspname",
        'relations'=>"SELECT c.relname,c.relkind,pg_get_userbyid(c.relowner) owner,c.relrowsecurity,c.relforcerowsecurity,$relationAcl acl FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='aia_p67_owner' AND c.relkind IN ('r','S') ORDER BY c.relname",
        'columns'=>"SELECT c.relname,a.attname,format_type(a.atttypid,a.atttypmod) type,a.attnotnull,a.attidentity,pg_get_expr(d.adbin,d.adrelid) default_expression,$columnAcl acl FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum WHERE n.nspname='aia_p67_owner' AND a.attnum>0 AND NOT a.attisdropped AND c.relkind='r' ORDER BY c.relname,a.attnum",
        'constraints'=>"SELECT c.relname,k.conname,k.contype,k.convalidated,k.condeferrable,k.condeferred,pg_get_constraintdef(k.oid) definition FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='aia_p67_owner' ORDER BY c.relname,k.conname",
        'indexes'=>"SELECT c.relname,pg_get_indexdef(i.indexrelid) definition,i.indisunique,i.indisprimary,i.indisvalid FROM pg_index i JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='aia_p67_owner' ORDER BY c.relname,definition",
        'policies'=>"SELECT c.relname,p.polname,p.polcmd,p.polpermissive,(SELECT jsonb_agg(pg_get_userbyid(role_id) ORDER BY pg_get_userbyid(role_id)) FROM unnest(p.polroles) role_id) roles,pg_get_expr(p.polqual,p.polrelid) using_expression,pg_get_expr(p.polwithcheck,p.polrelid) check_expression FROM pg_policy p JOIN pg_class c ON c.oid=p.polrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='aia_p67_owner' ORDER BY c.relname,p.polname",
        'functions'=>"SELECT p.proname,pg_get_function_identity_arguments(p.oid) arguments,pg_get_userbyid(p.proowner) owner,p.prosecdef,p.proisstrict,p.provolatile,p.proconfig,pg_get_functiondef(p.oid) definition,$functionAcl acl FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname='aia_p67_owner' ORDER BY p.proname,arguments",
        'defaults'=>"SELECT pg_get_userbyid(d.defaclrole) owner,coalesce(n.nspname,'GLOBAL') scope,d.defaclobjtype,$defaultAcl acl FROM pg_default_acl d LEFT JOIN pg_namespace n ON n.oid=d.defaclnamespace WHERE d.defaclrole='aia_p67_owner'::regrole ORDER BY scope,d.defaclobjtype",
        'roles'=>"SELECT rolname,rolcanlogin,rolinherit,rolsuper,rolbypassrls,rolcreatedb,rolcreaterole,rolreplication FROM pg_roles WHERE rolname IN ('aia_p67_owner','aia_p67_create','aia_p67_read') ORDER BY rolname",
        'extensions'=>"SELECT e.extname,e.extversion,n.nspname FROM pg_extension e JOIN pg_namespace n ON n.oid=e.extnamespace ORDER BY e.extname",
    ];
    $security=[];
    foreach ($queries as $name=>$query) {
        $rows=p68Rows($db,$query);
        $security[$name]=['rows'=>count($rows),'sha256'=>p68Hash($rows)];
        if ($name==='relations') {
            $security[$name]['items']=[];
            foreach ($rows as $row) {
                $relation=$row['relname'];unset($row['relname']);
                $row['acl']=json_decode($row['acl'],true,8,JSON_THROW_ON_ERROR);
                $security[$name]['items'][$relation]=$row;
            }
        }
    }
    p68Need($security['relations']['rows']===7 && $security['functions']['rows']===9
        && $security['roles']['rows']===3 && $security['policies']['rows']===6,'EXACT_P67_INVENTORY');
    $sequence=$db->query('SELECT last_value,is_called FROM aia_p67_owner.audit_id_seq')->fetch(PDO::FETCH_ASSOC);
    $sequence['last_value']=(int)$sequence['last_value'];
    p68Need(is_bool($sequence['is_called']),'SEQUENCE_TYPE');
    return ['schema'=>'P68_P67_STATE_V1','tables'=>$tables,'security'=>$security,'sequence'=>$sequence];
}
function p68Authority(PDO $db): array
{
    return p68Sorted(json_decode($db->query("SELECT jsonb_build_object(
        'companies',(SELECT jsonb_agg(to_jsonb(t) ORDER BY company_id) FROM aia_p67_owner.companies t),
        'keys',(SELECT jsonb_agg((to_jsonb(t)-'secret')||jsonb_build_object('secret_hash',encode(public.digest(secret,'sha256'),'hex')) ORDER BY key_id) FROM aia_p67_owner.mac_keys t),
        'grants',(SELECT jsonb_agg(to_jsonb(t) ORDER BY company_id,fingerprint,operation) FROM aia_p67_owner.service_grants t))::text")->fetchColumn(),true,12,JSON_THROW_ON_ERROR));
}
function p68Denied(PDO $db,string $role,string $sql): void
{
    p68Need(in_array($role,['aia_p67_create','aia_p67_read'],true),'ROLE_ALLOWLIST');
    $db->exec('SET SESSION AUTHORIZATION '.$role);
    $denied=false;
    try {$db->query($sql);} catch (PDOException $error) {$denied=$error->getCode()==='42501';}
    finally {$db->exec('RESET SESSION AUTHORIZATION');}
    p68Need($denied,'QUARANTINE_EXECUTE_DENIED');
}
function p68Inert(PDO $db): void
{
    p68Need((int)$db->query("SELECT count(*) FROM pg_roles WHERE rolname IN ('aia_p67_owner','aia_p67_create','aia_p67_read') AND NOT(rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication)")->fetchColumn()===3,'INERT_ROLES');
    p68Need((int)$db->query("SELECT count(*) FROM pg_roles WHERE rolname IN ('aia_p67_create_login','aia_p67_read_login')")->fetchColumn()===0,'LOGIN_RESTORED_FROM_BACKUP');
    p68Need((int)$db->query("SELECT count(*) FROM pg_auth_members WHERE member IN ('aia_p67_owner'::regrole,'aia_p67_create'::regrole,'aia_p67_read'::regrole) OR roleid IN ('aia_p67_owner'::regrole,'aia_p67_create'::regrole,'aia_p67_read'::regrole)")->fetchColumn()===0,'MEMBERSHIP_RESTORED_FROM_BACKUP');
}

try {
    p68Need(PHP_SAPI==='cli' && is_file('/.dockerenv') && getenv('GITHUB_ACTIONS')==='true'
        && getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        && getenv('AIA_P68_PROBE')==='isolated-p68-v1','CI_ONLY');
    $raw=stream_get_contents(STDIN,40001);
    p68Need(is_string($raw) && strlen($raw)<=40000,'INPUT_LIMIT');
    $input=$raw===''?[]:json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    p68Need(is_array($input),'INPUT_OBJECT');
    $password=@file_get_contents('/run/secrets/p25-password');
    p68Need(is_string($password) && preg_match('/\A[a-f0-9]{64}\z/D',$password)===1,'ADMIN_PASSWORD');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    p68Need($db->query('SELECT current_database()')->fetchColumn()==='imagine_agents_ci','DATABASE');
    $db->exec("SET statement_timeout='5s';SET lock_timeout='1s';SET search_path=pg_catalog");
    $mode=$argv[1]??'';
    if ($mode==='capture') {
        $db->exec('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        if (isset($input['snapshot'])) {
            p68Need(is_string($input['snapshot']) && preg_match('/\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{8}-[0-9]+\z/D',$input['snapshot'])===1,'SNAPSHOT_ID');
            $db->exec('SET TRANSACTION SNAPSHOT '.$db->quote($input['snapshot']));
        }
        $result=p68Capture($db);$db->rollBack();
    } elseif ($mode==='authority') {
        $result=p68Authority($db);
    } elseif ($mode==='quarantine') {
        p68Inert($db);
        $db->exec('REVOKE EXECUTE ON FUNCTION aia_p67_owner.create_draft(text,jsonb) FROM aia_p67_create;'
            .'REVOKE EXECUTE ON FUNCTION aia_p67_owner.read_draft(text,jsonb) FROM aia_p67_read');
        foreach (['create','read'] as $operation) {
            p68Denied($db,'aia_p67_'.$operation,"SELECT aia_p67_owner.{$operation}_draft(repeat('a',64),'{}'::jsonb)");
        }
        $result=['restored_roles_nologin'=>true,'quarantined_functions_denied'=>true];
    } elseif ($mode==='apply-current') {
        p68Inert($db);
        $current=p68Sorted($input['authority']??null);
        p68Need(is_array($current) && array_keys($current)===['companies','grants','keys'],'CURRENT_AUTHORITY_SHAPE');
        foreach (['create','read'] as $operation) {
            $secret=$input[$operation]??null;
            p68Need(is_string($secret) && preg_match('/\A[a-f0-9]{64}\z/D',$secret)===1,'FRESH_PASSWORD');
        }
        $old=p68Authority($db);
        $db->beginTransaction();
        foreach (['companies','grants','keys'] as $kind) {
            p68Need(count($current[$kind])===count($old[$kind]),'NO_NEW_AUTHORITY_ROW');
            foreach ($current[$kind] as $i=>$item) {
                $prior=$old[$kind][$i];
                $mutable=$kind==='companies'?['active','revision']:['active','revision','expires_at'];
                p68Need(array_keys($item)===array_keys($prior),'CURRENT_AUTHORITY_FIELDS');
                foreach ($prior as $field=>$value) {
                    if (!in_array($field,$mutable,true)) p68Need($item[$field]===$value,'NO_SCOPE_OR_KEY_REPLACEMENT');
                }
                p68Need(is_bool($item['active']) && (!$item['active'] || $prior['active'])
                    && is_int($item['revision']) && $item['revision']>=$prior['revision'],'MONOTONIC_AUTHORITY');
                if ($kind!=='companies') p68Need(is_int($item['expires_at']) && $item['expires_at']<=$prior['expires_at'],'NO_EXPIRY_EXTENSION');
                if ($kind==='companies') {
                    $stmt=$db->prepare('UPDATE aia_p67_owner.companies SET active=?::boolean,revision=? WHERE company_id=?::uuid');
                    $args=[$item['active']?'true':'false',$item['revision'],$item['company_id']];
                } elseif ($kind==='keys') {
                    $stmt=$db->prepare('UPDATE aia_p67_owner.mac_keys SET active=?::boolean,revision=?,expires_at=? WHERE key_id=?');
                    $args=[$item['active']?'true':'false',$item['revision'],$item['expires_at'],$item['key_id']];
                } else {
                    $stmt=$db->prepare('UPDATE aia_p67_owner.service_grants SET active=?::boolean,revision=?,expires_at=? WHERE company_id=?::uuid AND fingerprint=? AND operation=?');
                    $args=[$item['active']?'true':'false',$item['revision'],$item['expires_at'],$item['company_id'],$item['fingerprint'],$item['operation']];
                }
                $stmt->execute($args);p68Need($stmt->rowCount()===1,'CURRENT_AUTHORITY_ROW');
            }
        }
        p68Need(p68Authority($db)===$current,'CURRENT_AUTHORITY_EXACT');
        $db->exec('GRANT EXECUTE ON FUNCTION aia_p67_owner.create_draft(text,jsonb) TO aia_p67_create;'
            .'GRANT EXECUTE ON FUNCTION aia_p67_owner.read_draft(text,jsonb) TO aia_p67_read');
        // No standalone login mode: current authority and fresh login creation
        // commit together. Any missing/invalid metadata leaves the quarantine.
        foreach (['create','read'] as $operation) {
            $login='aia_p67_'.$operation.'_login';
            $db->exec('CREATE ROLE '.$login.' LOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '.$db->quote($input[$operation]));
            $db->exec('GRANT aia_p67_'.$operation.' TO '.$login.' WITH INHERIT FALSE, SET TRUE');
        }
        $db->commit();
        $result=['current_authority_applied'=>true,'no_scope_added'=>true,'fresh_logins_provisioned'=>true];
    } elseif ($mode==='old-passwords') {
        foreach (['create','read'] as $operation) {
            $secret=$input[$operation]??null;
            p68Need(is_string($secret) && preg_match('/\A[a-f0-9]{64}\z/D',$secret)===1,'OLD_PASSWORD_INPUT');
            $rejected=false;
            try {new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','aia_p67_'.$operation.'_login',$secret);}
            catch (PDOException) {$rejected=true;}
            p68Need($rejected,'SOURCE_PASSWORD_REUSED');
        }
        $result=['source_passwords_rejected'=>2];
    } else {throw new RuntimeException('P68_MODE');}
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $message=$error instanceof RuntimeException && preg_match('/\AP68_[A-Z0-9_]+\z/D',$error->getMessage())===1
        ? $error->getMessage() : 'P68_RUNTIME';
    fwrite(STDERR,'P68_PROBE_DENIED_'.$message."\n");exit(1);
}
