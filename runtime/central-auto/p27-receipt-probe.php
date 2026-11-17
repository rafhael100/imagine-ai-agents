<?php
declare(strict_types=1);
/** Disposable P27 fixtures and TLS client. Never exposed by nginx. */
function p27_must(bool $ok,string $code):void {
    if(!$ok)throw new RuntimeException('P27_'.$code);
}
function p27_db():PDO {
    foreach(['GITHUB_ACTIONS'=>'true','GITHUB_REPOSITORY'=>'rafhael100/imagine-ai-agents',
        'DB_HOST'=>'127.0.0.1','DB_DATABASE'=>'imagine_agents_ci','DB_USERNAME'=>'postgres'] as $k=>$v)
        p27_must(getenv($k)===$v,'DISPOSABLE_CONTEXT');
    $password=file_get_contents('/run/secrets/p25-password');
    p27_must(is_string($password)&&strlen($password)===64,'PASSWORD_FILE');
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci','postgres',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET statement_timeout='2000ms'; SET lock_timeout='750ms'");
    return $db;
}
function p27_denied(PDO $db,string $role,string $sql):void {
    p27_must(in_array($role,['aia_private_receipt','aia_private_transport','aia_private_receipt_login'],true),'ROLE');
    $db->exec('SET ROLE '.$role);$denied=false;
    try{$db->query($sql);}catch(PDOException $e){$denied=$e->getCode()==='42501';}
    finally{$db->exec('RESET ROLE');}
    p27_must($denied,'DIRECT_SQL_WAS_NOT_DENIED');
}
try{
    p27_must(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents','CLI_CI_ONLY');
    $raw=stream_get_contents(STDIN,20001);
    p27_must(is_string($raw)&&strlen($raw)<=20000,'INPUT_LIMIT');
    $input=$raw===''?[]:json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    p27_must(is_array($input),'INPUT');$mode=$argv[1]??'';
    if($mode==='seed'){
        $db=p27_db();
        foreach(['fingerprint_a','fingerprint_b','transport_password','receipt_password'] as $field)
            p27_must(is_string($input[$field]??null)&&preg_match('/\A[a-f0-9]{64}\z/D',$input[$field])===1,'FIXTURE_INPUT');
        $db->exec('ALTER ROLE aia_private_transport LOGIN PASSWORD '.$db->quote($input['transport_password']));
        $db->exec('CREATE ROLE aia_private_receipt_login LOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS '
            .'NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '.$db->quote($input['receipt_password']));
        $db->exec('GRANT aia_private_receipt TO aia_private_receipt_login WITH INHERIT FALSE, SET TRUE');
        $expiry=time()+1800;
        foreach([
            ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','SYNTHETIC_TENANT_A',$input['fingerprint_a']],
            ['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','SYNTHETIC_TENANT_B',$input['fingerprint_b']],
        ] as [$company,$tenant,$fp]){
            $s=$db->prepare('INSERT INTO auto_ia_lab.company_links VALUES(CAST(? AS uuid),?,true,1)');
            $s->execute([$company,$tenant]);
            $s=$db->prepare("INSERT INTO auto_ia_lab.service_company_grants VALUES(CAST(? AS uuid),?,?,'lead.ingest',true,1,?)");
            $s->execute([$company,$fp,$tenant,$expiry]);
        }
        // A intentionally has only lead.ingest at first; B has independent read
        // authority to test that its valid certificate cannot read A's event.
        $s=$db->prepare("INSERT INTO auto_ia_lab.receipt_read_grants VALUES('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',?,'SYNTHETIC_TENANT_B','event.receipt.read',true,1,?)");
        $s->execute([$input['fingerprint_b'],$expiry]);
        $db->exec("UPDATE auto_ia_lab.mock_s2s_keys SET enabled=true WHERE key_id='SYNTHETIC_KEY_B'");
        $result=['schema'=>'P27_SEEDED_V1','read_role_nologin'=>true,'operational_grant_created'=>false];
    }elseif($mode==='stats'){
        $db=p27_db();$result=[];
        foreach(['inbox'=>'auto_ia_lab.event_inbox','ingress_audit'=>'auto_ia_lab.service_event_audit',
                 'ai_requests'=>'auto_ia_lab.requests'] as $name=>$table)
            $result[$name]=(int)$db->query('SELECT count(*) FROM '.$table)->fetchColumn();
    }elseif($mode==='verify-permissions'){
        $db=p27_db();
        p27_must($db->query("SELECT NOT(rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname='aia_private_receipt'")->fetchColumn()===true,'RECEIPT_ROLE');
        p27_must($db->query("SELECT rolcanlogin AND NOT(rolinherit OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname='aia_private_receipt_login'")->fetchColumn()===true,'LOGIN_ROLE');
        p27_must((int)$db->query("SELECT count(*) FROM pg_auth_members WHERE member='aia_private_receipt_login'::regrole AND roleid='aia_private_receipt'::regrole AND NOT admin_option AND NOT inherit_option AND set_option")->fetchColumn()===1,'LOGIN_MEMBERSHIP');
        p27_must((int)$db->query("SELECT count(*) FROM pg_auth_members WHERE member IN('aia_private_receipt_login'::regrole,'aia_private_receipt'::regrole)")->fetchColumn()===1,'EXCESS_MEMBERSHIP');
        p27_must($db->query("SELECT relrowsecurity AND relforcerowsecurity AND pg_get_userbyid(relowner)='aia_lab_owner' FROM pg_class WHERE oid='auto_ia_lab.receipt_read_grants'::regclass")->fetchColumn()===true,'GRANT_RLS_OWNER');
        p27_must($db->query("SELECT prosecdef AND pg_get_userbyid(proowner)='aia_lab_owner' AND proconfig @> ARRAY['search_path=pg_catalog, auto_ia_lab'] FROM pg_proc WHERE oid='auto_ia_lab.read_company_event_receipt(uuid,text,jsonb)'::regprocedure")->fetchColumn()===true,'DEFINER');
        foreach(['event_inbox','receipt_read_grants','service_company_grants','mock_s2s_keys'] as $table)
            p27_denied($db,'aia_private_receipt','SELECT * FROM auto_ia_lab.'.$table);
        p27_denied($db,'aia_private_receipt','UPDATE auto_ia_lab.receipt_read_grants SET active=true');
        p27_denied($db,'aia_private_receipt',"SELECT auto_ia_lab.receive_company_signed_no_ai('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',repeat('a',64),'{}'::jsonb)");
        foreach(['aia_private_transport','aia_private_receipt_login'] as $role)
            p27_denied($db,$role,"SELECT auto_ia_lab.read_company_event_receipt('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',repeat('a',64),'{}'::jsonb)");
        $result=['schema'=>'P27_SQL_PERMISSIONS_V1','roles_separate'=>true,'receipt_role_nologin'=>true,
            'execute_only'=>true,'rls_forced'=>true,'owner_preserved'=>true];
    }elseif($mode==='ready'){
        $result=[];
        foreach(['fpm'=>9000,'https'=>8443] as $name=>$port){
            $socket=@fsockopen('127.0.0.1',$port,$errno,$error,0.5);
            $result[$name]=is_resource($socket);if(is_resource($socket))fclose($socket);
        }
    }elseif($mode==='egress'){
        $socket=@fsockopen('192.0.2.1',443,$errno,$error,0.5);
        p27_must(!is_resource($socket),'EGRESS_NOT_BLOCKED');
        $result=['namespace_egress_denied'=>true];
    }elseif($mode==='unauthenticated-db'){
        $denied=false;
        try{
            new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci;connect_timeout=2',
                'aia_private_receipt_login','SYNTHETIC_WRONG_PASSWORD',
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        }catch(PDOException){$denied=true;}
        p27_must($denied,'DATABASE_ACCEPTED_WRONG_PASSWORD');
        $result=['db_password_required'=>true];
    }elseif($mode==='http'){
        p27_must(in_array($input['path']??null,['/private/events','/private/events/receipt','/private/events/receipt?x=1'],true),'HTTP_PATH');
        p27_must(in_array($input['method']??null,['POST','GET'],true),'HTTP_METHOD');
        $body=base64_decode($input['body_b64']??'',true);
        p27_must(is_string($body)&&strlen($body)<=9000,'HTTP_BODY');
        p27_must(in_array($input['content_type']??null,['application/json','text/plain'],true),'HTTP_CONTENT_TYPE');
        $headers=['Host: agents-ci.internal','Connection: close','Content-Type: '.$input['content_type']];
        p27_must(is_array($input['headers']??[]),'HTTP_HEADERS');
        foreach($input['headers']??[] as $header){
            p27_must(is_string($header)&&strlen($header)<=4096&&!str_contains($header,"\r")&&!str_contains($header,"\n"),'HTTP_HEADER');
            $headers[]=$header;
        }
        $ssl=['cafile'=>'/certs/ca.crt','verify_peer'=>true,'verify_peer_name'=>true,
            'peer_name'=>'agents-ci.internal','allow_self_signed'=>false,'SNI_enabled'=>true];
        if(($input['client_certificate']??null)===true){
            $ssl['local_cert']='/certs/client.crt';$ssl['local_pk']='/certs/client.key';
        }
        $context=stream_context_create(['ssl'=>$ssl,'http'=>[
            'method'=>$input['method'],'header'=>$headers,'content'=>$body,'timeout'=>4.0,
            'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0,'protocol_version'=>1.1]]);
        $http_response_header=[];
        $response=@file_get_contents('https://127.0.0.1:8443'.$input['path'],false,$context,0,4097);
        $status=0;$cache='';$type='';
        foreach($http_response_header as $header){
            if(preg_match('/\AHTTP\/1\.[01] ([0-9]{3})\b/',$header,$m))$status=(int)$m[1];
            if(str_starts_with(strtolower($header),'cache-control:'))$cache=trim(substr($header,14));
            if(str_starts_with(strtolower($header),'content-type:'))$type=trim(substr($header,13));
        }
        p27_must($response===false||strlen($response)<=4096,'HTTP_RESPONSE_LIMIT');
        $result=['transport_ok'=>is_string($response),'status'=>$status,'cache_control'=>$cache,
            'content_type'=>$type,'body_b64'=>base64_encode(is_string($response)?$response:'')];
    }else{throw new RuntimeException('P27_MODE_DENIED');}
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    $code=$error instanceof RuntimeException&&preg_match('/\AP27_[A-Z0-9_]+\z/D',$error->getMessage())
        ?$error->getMessage():strtoupper(str_replace('\\','_',get_class($error)));
    fwrite(STDERR,'P27_PROBE_DENIED_'.$code."\n");exit(1);
}
