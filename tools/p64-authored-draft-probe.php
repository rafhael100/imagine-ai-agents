<?php
declare(strict_types=1);
/** P64 isolated CI probe. No public route and no real identity. */
require_once __DIR__.'/labs/PgAuthoredDraftStagingLab.php';

use ImagineAI\Convergence\PgAuthoredDraftStagingLab;
use ImagineAI\Convergence\PgScopedDraftWriterLab;
use ImagineAI\Convergence\PgDraftAuthorProvenanceLab;
use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;

function p64Must(bool $value,string $code):void
{
    if(!$value)throw new RuntimeException('P64_PROBE_DENIED_'.$code);
}
function p64Db(string $name):PDO
{
    p64Must(in_array($name,['admin','a','b'],true),'CONNECTION');
    $password=file_get_contents($name==='admin'?'/run/secrets/p25-password':'/run/p64/writer-'.$name);
    p64Must(is_string($password)&&strlen(trim($password))>=36,'PASSWORD');
    return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        $name==='admin'?'postgres':'aia_p64_ci_write_'.$name,trim($password),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
}
function p64Input(array $data):array
{
    $name=$data['connection']??'a';
    p64Must(in_array($name,['a','b'],true),'WRITER');
    $request=$data['request']??'SYNTHETIC_P64_PROBE';
    p64Must(is_string($request)&&preg_match('/\ASYNTHETIC_P64_[A-Z0-9_]{1,34}\z/D',$request)===1,'REQUEST');
    $visits=$data['visits']??120;
    p64Must(is_int($visits),'VISITS');
    return PgAuthoredDraftStagingLab::inputs(
        $data['tenant']??'SYNTHETIC_TENANT_'.strtoupper($name),$request,$visits,1000,2000
    );
}
function p64Stats(PDO $db):array
{
    $rows=[];
    foreach(PgScopedDraftWriterLab::TABLES as $table){
        $rows[$table]=$db->query('SELECT row_to_json(t)::text FROM '.$table.
            ' t ORDER BY tenant_ref,'.($table==='aia_p51_lab.preview_batches'?'request_ref':'proposal_ref'))
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    return [
        'batches'=>count($rows['aia_p51_lab.preview_batches']),
        'drafts'=>count($rows['aia_p51_lab.review_drafts']),
        'authors'=>count($rows['aia_p59_lab.draft_authors']),
        'fingerprint'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),
        'votes'=>(int)$db->query('SELECT count(*) FROM aia_p57_lab.signed_observations')->fetchColumn(),
    ];
}
function p64Seed(PDO $db,array $input):array
{
    // ReviewLab prepared these exact synthetic fixtures in this fresh cluster.
    p64Must((int)$db->query('SELECT count(*) FROM aia_p51_lab.preview_batches')->fetchColumn()===5,'P62_FIXTURE');
    $db->beginTransaction();
    $db->exec('DELETE FROM aia_p57_lab.signed_observations; DELETE FROM aia_p57_lab.reviewers;
        DELETE FROM aia_p59_lab.draft_authors; DELETE FROM aia_p51_lab.review_drafts;
        DELETE FROM aia_p51_lab.preview_batches');
    foreach(['a','b'] as $name){
        $role='aia_p64_ci_write_'.$name;
        $tenant='SYNTHETIC_TENANT_'.strtoupper($name);
        $author='SYNTHETIC_P64_AUTHOR_'.strtoupper($name);
        $password=$input['writer_'.$name]??null;
        p64Must(is_string($password)&&preg_match('/\A[a-f0-9]{36}\z/D',$password)===1,'WRITER_PASSWORD');
        p64Must((int)$db->query("SELECT count(*) FROM pg_roles WHERE rolname='{$role}'")->fetchColumn()===0,'NEW_ROLE');
        $db->exec("CREATE ROLE {$role} LOGIN PASSWORD '{$password}'
            NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION");
        $db->exec("GRANT USAGE ON SCHEMA aia_p51_lab,aia_p59_lab TO {$role}");
        foreach(PgScopedDraftWriterLab::TABLES as $table){
            $db->exec("GRANT SELECT,INSERT ON {$table} TO {$role}");
            // This one-column privilege permits row locks. The UPDATE
            // policy's false check prevents every actual row update.
            $db->exec("GRANT UPDATE(tenant_ref) ON {$table} TO {$role}");
            $db->exec("CREATE POLICY p64_write_{$name}_select ON {$table}
                FOR SELECT TO {$role} USING(tenant_ref='{$tenant}')");
            $check="tenant_ref='{$tenant}'";
            if($table==='aia_p59_lab.draft_authors')$check.=" AND author_ref='{$author}'";
            $db->exec("CREATE POLICY p64_write_{$name}_insert ON {$table}
                FOR INSERT TO {$role} WITH CHECK({$check})");
            $db->exec("CREATE POLICY p64_write_{$name}_lock ON {$table}
                FOR UPDATE TO {$role} USING(tenant_ref='{$tenant}') WITH CHECK(false)");
        }
    }
    $db->commit();
    foreach(['a','b'] as $name){
        $scope=PgScopedDraftWriterLab::fromDatabase(p64Db($name));
        p64Must($scope->tenant()==='SYNTHETIC_TENANT_'.strtoupper($name),'SCOPE');
    }
    return ['schema'=>'P64_SEEDED_V1','empty'=>p64Stats($db)];
}
function p64Permissions():array
{
    $db=p64Db('a');
    $scope=PgScopedDraftWriterLab::fromDatabase($db);
    $db->beginTransaction();
    $scope->assertWriteTransaction($db);
    foreach(PgScopedDraftWriterLab::TABLES as $table){
        p64Must($db->query("SELECT tenant_ref FROM {$table} WHERE tenant_ref='SYNTHETIC_TENANT_A' FOR SHARE")
            ->fetchColumn()==='SYNTHETIC_TENANT_A','SHARE_LOCK');
        p64Must($db->query("SELECT tenant_ref FROM {$table} WHERE tenant_ref='SYNTHETIC_TENANT_A' FOR UPDATE")
            ->fetchColumn()==='SYNTHETIC_TENANT_A','UPDATE_LOCK');
        p64Must((int)$db->query("SELECT count(*) FROM {$table} WHERE tenant_ref='SYNTHETIC_TENANT_B'")
            ->fetchColumn()===0,'TENANT_HIDDEN');
    }
    $db->rollBack();
    foreach([
        "UPDATE aia_p51_lab.preview_batches SET tenant_ref=tenant_ref",
        "UPDATE aia_p51_lab.review_drafts SET tenant_ref=tenant_ref",
        "UPDATE aia_p59_lab.draft_authors SET tenant_ref=tenant_ref",
        "UPDATE aia_p59_lab.draft_authors SET author_ref='SYNTHETIC_OTHER'",
        'DELETE FROM aia_p51_lab.review_drafts',
        'SELECT * FROM aia_p57_lab.reviewers',
        'SELECT * FROM aia_p57_lab.signed_observations',
        'SET ROLE postgres',
    ] as $sql){
        $denied=false;
        try{$db->exec($sql);}catch(PDOException){$denied=true;}
        p64Must($denied,'FORBIDDEN_SQL');
    }
    try{$scope->assertConnection(p64Db('a'));$bound=false;}catch(RuntimeException){$bound=true;}
    p64Must($bound,'PDO_BINDING');
    try{PgScopedDraftWriterLab::fromDatabase(p64Db('admin'));$closed=false;}catch(RuntimeException){$closed=true;}
    p64Must($closed,'ADMIN_PROFILE_DENIED');
    return ['locks_work'=>true,'actual_updates_denied'=>true,'tenant_isolated'=>true,
        'no_keys_or_votes'=>true,'pdo_bound'=>true,'admin_profile_denied'=>true];
}
function p64Guards():array
{
    $db=p64Db('a');
    $scope=PgScopedDraftWriterLab::fromDatabase($db);
    $input=p64Input(['request'=>'SYNTHETIC_P64_GUARD']);
    $registry=PgDeterministicDraftRegistryLab::forScopedWriter($db,$scope);
    $authors=new PgDraftAuthorProvenanceLab($db);
    try{new PgDeterministicDraftRegistryLab($db);$defaultDenied=false;}
    catch(RuntimeException){$defaultDenied=true;}
    p64Must($defaultDenied,'LEGACY_POSTGRES_ONLY');
    p64Must($registry->stage($input['command'],$input['ruleset'])['reason']
        ==='SCOPED_WRITER_REQUIRES_COMPOSITION','SCOPED_STAGE_REQUIRES_COMPOSITION');
    $proposal='SYNTHETIC_PROPOSAL_'.str_repeat('A',24);
    p64Must($registry->stageInsideTransaction($input['command'],$input['ruleset'])['decision']==='DENY','NO_TX_STAGE');
    p64Must(str_starts_with($authors->sealInsideTransaction($scope->tenant(),$proposal,$scope->author()),'DENY'),'NO_TX_AUTHOR');
    foreach(['READ COMMITTED READ WRITE','REPEATABLE READ READ ONLY'] as $isolation){
        $db->beginTransaction();
        $db->exec('SET TRANSACTION ISOLATION LEVEL '.$isolation);
        p64Must((new PgAuthoredDraftStagingLab($db,$scope))->stage($input['command'],$input['ruleset'])['decision']==='DENY','CALLER_TX');
        p64Must($db->inTransaction(),'CALLER_TX_OWNERSHIP');
        if(str_contains($isolation,'READ ONLY')){
            p64Must($registry->stageInsideTransaction($input['command'],$input['ruleset'])['decision']==='DENY','RO_STAGE');
            p64Must(str_starts_with($authors->sealInsideTransaction($scope->tenant(),$proposal,$scope->author()),'DENY'),'RO_AUTHOR');
            p64Must($db->inTransaction(),'INSIDE_TX_OWNERSHIP');
        }
        $db->rollBack();
    }
    $admin=p64Db('admin');
    $legacy=new PgDeterministicDraftRegistryLab($admin);
    $legacyAuthor=new PgDraftAuthorProvenanceLab($admin);
    $admin->beginTransaction();
    p64Must($legacy->stage($input['command'],$input['ruleset'])['reason']==='NESTED_TRANSACTION'
        &&$legacyAuthor->seal($scope->tenant(),$proposal,$scope->author())==='DENY_AUTHOR_SEAL_INPUT'
        &&$admin->inTransaction(),'LEGACY_NESTED');
    $admin->rollBack();
    $admin->exec('SET default_transaction_read_only=on');
    p64Must($legacy->stage($input['command'],$input['ruleset'])['reason']
        ==='INVALID_INPUT_OR_DISPOSABLE_DB_FAILURE','LEGACY_READONLY_STAGE_REASON');
    p64Must($legacyAuthor->seal($scope->tenant(),$proposal,$scope->author())
        ==='DENY_AUTHOR_SEAL_DATABASE_FAILURE','LEGACY_READONLY_AUTHOR_REASON');
    $admin->exec('SET default_transaction_read_only=off');
    return ['caller_transaction_preserved'=>true,'inside_requires_write_transaction'=>true,
        'legacy_nested_and_readonly_reasons_preserved'=>true,'default_postgres_gate_preserved'=>true];
}
function p64CapacityFixture(bool $batch):array
{
    if(!$batch){
        $db=p64Db('a');
        $scope=PgScopedDraftWriterLab::fromDatabase($db);
        $facade=new PgAuthoredDraftStagingLab($db,$scope);
        $count=(int)$db->query('SELECT count(*) FROM aia_p51_lab.review_drafts')->fetchColumn();
        p64Must($count>0&&$count<127,'CAPACITY_START');
        for($i=$count;$i<127;$i++){
            $input=p64Input(['request'=>'SYNTHETIC_P64_FILL_'.$i,'visits'=>100]);
            p64Must($facade->stage($input['command'],$input['ruleset'])['decision']
                ==='LAB_AUTHORED_DRAFTS_STORED','CAPACITY_STAGE');
        }
        return ['tenant_drafts'=>(int)$db->query('SELECT count(*) FROM aia_p51_lab.review_drafts')->fetchColumn()];
    }
    $db=p64Db('admin');
    $db->beginTransaction();
    $request=$db->query("SELECT request_ref FROM aia_p51_lab.preview_batches
        WHERE tenant_ref='SYNTHETIC_TENANT_A' AND request_ref LIKE 'SYNTHETIC_P64_LAST_%'")->fetchAll(PDO::FETCH_COLUMN);
    p64Must(count($request)===1,'ONE_CAPACITY_WINNER');
    $remove=$db->prepare("DELETE FROM aia_p59_lab.draft_authors a USING aia_p51_lab.review_drafts d
        WHERE a.tenant_ref=d.tenant_ref AND a.proposal_ref=d.proposal_ref
        AND d.tenant_ref='SYNTHETIC_TENANT_A' AND d.request_ref=?");
    $remove->execute([$request[0]]);
    foreach(['aia_p51_lab.review_drafts','aia_p51_lab.preview_batches'] as $table){
        $q=$db->prepare("DELETE FROM {$table} WHERE tenant_ref='SYNTHETIC_TENANT_A' AND request_ref=?");
        $q->execute([$request[0]]);
        p64Must($q->rowCount()===1,'REMOVE_SINGLE_OWN_FIXTURE');
    }
    $db->commit();
    $count=(int)$db->query("SELECT count(*) FROM aia_p51_lab.preview_batches
        WHERE tenant_ref='SYNTHETIC_TENANT_A'")->fetchColumn();
    p64Must($count<128,'BATCH_CAPACITY_START');
    $legacy=new PgDeterministicDraftRegistryLab($db);
    // P51 still permits zero-proposal legacy batches. This isolates the
    // batch limit while the new P64 form deliberately never creates them.
    for($i=$count;$i<128;$i++){
        $input=p64Input(['request'=>'SYNTHETIC_P64_EMPTY_'.$i,'visits'=>0]);
        p64Must($legacy->stage($input['command'],$input['ruleset'])['decision']
            ==='LAB_DRAFTS_STAGED_NO_ACTION','LEGACY_EMPTY_BATCH');
    }
    return [
        'tenant_batches'=>(int)$db->query("SELECT count(*) FROM aia_p51_lab.preview_batches WHERE tenant_ref='SYNTHETIC_TENANT_A'")->fetchColumn(),
        'tenant_drafts'=>(int)$db->query("SELECT count(*) FROM aia_p51_lab.review_drafts WHERE tenant_ref='SYNTHETIC_TENANT_A'")->fetchColumn(),
    ];
}
function p64Http(array $input,bool $discard=false):array
{
    $port=$input['port']??8787;
    p64Must(in_array($port,[8786,8787],true),'HTTP_PORT');
    $path=$input['path']??'/p64-create-drafts-local.php';
    p64Must(is_string($path)&&strlen($path)<300
        &&preg_match('/\A\/(?:p64-create-drafts-local|p56-live-pg-review-local)\.php(?:\?[^\r\n ]*)?\z/D',$path)===1,'HTTP_PATH');
    $method=$input['method']??'GET';
    p64Must(in_array($method,['GET','POST','PUT'],true),'HTTP_METHOD');
    $body=base64_decode($input['body_b64']??'',true);
    p64Must(is_string($body)&&strlen($body)<=10000,'HTTP_BODY');
    $headers=['Host'=>$input['host']??'127.0.0.1:'.$port,'Connection'=>'close'];
    foreach(['origin'=>'Origin','cookie'=>'Cookie','content_type'=>'Content-Type'] as $key=>$name){
        if(isset($input[$key]))$headers[$name]=$input[$key];
    }
    if($method==='POST')$headers['Content-Length']=(string)strlen($body);
    $wire=$method.' '.$path." HTTP/1.1\r\n";
    foreach($headers as $name=>$value){
        p64Must(is_string($value)&&preg_match('/[\r\n]/',$value)!==1,'HTTP_HEADER');
        $wire.=$name.': '.$value."\r\n";
    }
    $wire.="\r\n".$body;
    $socket=stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$message,5);
    p64Must(is_resource($socket),'HTTP_CONNECT');
    stream_set_timeout($socket,8);
    $written=0;
    while($written<strlen($wire)){
        $count=fwrite($socket,substr($wire,$written));
        p64Must(is_int($count)&&$count>0,'HTTP_SEND');
        $written+=$count;
    }
    if($discard){
        // The application receives no HTTP response. The DB result is
        // observed separately before retrying the same confirmation.
        stream_socket_shutdown($socket,STREAM_SHUT_WR);
        fclose($socket);
        return ['request_sent'=>true,'response_read'=>false];
    }
    $response=stream_get_contents($socket,262145);
    $meta=stream_get_meta_data($socket);
    fclose($socket);
    p64Must(is_string($response)&&strlen($response)<=262144&&!$meta['timed_out'],'HTTP_READ');
    $split=explode("\r\n\r\n",$response,2);
    p64Must(count($split)===2,'HTTP_RESPONSE');
    $lines=explode("\r\n",$split[0]);
    p64Must(preg_match('/\AHTTP\/1\.[01] ([0-9]{3}) /',$lines[0],$match)===1,'HTTP_STATUS');
    $resultHeaders=[];
    foreach(array_slice($lines,1) as $line){
        [$name,$value]=explode(':',$line,2);
        $resultHeaders[strtolower(trim($name))]=trim($value);
    }
    return ['status'=>(int)$match[1],'headers'=>$resultHeaders,'body_b64'=>base64_encode($split[1])];
}

try{
    p64Must(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('AIA_P64_CREATE_LAB')==='true','CI_ONLY');
    $input=json_decode(stream_get_contents(STDIN),true,24,JSON_THROW_ON_ERROR);
    p64Must(is_array($input),'INPUT');
    $mode=$argv[1]??'';
    if($mode==='seed')$out=p64Seed(p64Db('admin'),$input);
    elseif($mode==='stats')$out=p64Stats(p64Db('admin'));
    elseif($mode==='permissions')$out=p64Permissions();
    elseif($mode==='guards')$out=p64Guards();
    elseif($mode==='capacity-fixture')$out=p64CapacityFixture(false);
    elseif($mode==='batch-capacity-fixture')$out=p64CapacityFixture(true);
    elseif($mode==='http'||$mode==='http-discard')$out=p64Http($input,$mode==='http-discard');
    elseif($mode==='profile'){
        try{PgScopedDraftWriterLab::fromDatabase(p64Db('a'));$accepted=true;}
        catch(Throwable){$accepted=false;}
        $out=['accepted'=>$accepted];
    }elseif($mode==='stage'){
        $data=p64Input($input);
        $db=p64Db($input['connection']??'a');
        if(isset($input['application'])){
            p64Must(is_string($input['application'])
                &&preg_match('/\Ap64_[a-z0-9_]{1,30}\z/D',$input['application'])===1,'APPLICATION');
            $q=$db->prepare("SELECT set_config('application_name',?,false)");
            $q->execute([$input['application']]);
        }
        $out=(new PgAuthoredDraftStagingLab($db,PgScopedDraftWriterLab::fromDatabase($db)))
            ->stage($data['command'],$data['ruleset']);
    }else{throw new RuntimeException('P64_PROBE_DENIED_MODE');}
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $error){
    if(preg_match('/\AP64_PROBE_DENIED_[A-Z0-9_]+\z/D',$error->getMessage())===1){
        $code=$error->getMessage();
    }elseif($error->getMessage()==='P64_WRITER_PROFILE_DENIED'){
        $code='P64_PROBE_DENIED_WRITER_PROFILE';
    }elseif($error instanceof PDOException
        &&preg_match('/\A[0-9A-Z]{5}\z/D',(string)$error->getCode())===1){
        $code='P64_PROBE_DENIED_SQLSTATE_'.$error->getCode();
    }else{$code='P64_PROBE_DENIED_UNEXPECTED';}
    fwrite(STDERR,$code."\n");
    exit(1);
}
