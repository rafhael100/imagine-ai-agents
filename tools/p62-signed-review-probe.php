<?php
declare(strict_types=1);
/** Disposable P62 fixture/probe. Never installed in the private receiver. */
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/PgDraftAuthorProvenanceLab.php';
require_once __DIR__.'/labs/PgSignedReviewQueueBridgeLab.php';

use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\PgDraftAuthorProvenanceLab;
use ImagineAI\Convergence\PgHistoricalSignedReviewAuditLab;
use ImagineAI\Convergence\PgScopedReviewQueueBridgeLab;
use ImagineAI\Convergence\PgSignedDraftReviewLab;
use ImagineAI\Convergence\PgSignedReviewQueueBridgeLab;

const P62_TABLES=[
    'aia_p51_lab.preview_batches','aia_p51_lab.review_drafts',
    'aia_p57_lab.reviewers','aia_p57_lab.signed_observations',
    'aia_p59_lab.draft_authors',
];
function p62Must(bool $value,string $code):void
{
    if(!$value)throw new RuntimeException('P62_PROBE_DENIED_'.$code);
}
function p62Db(string $connection):PDO
{
    p62Must(in_array($connection,['admin','a','b'],true),'CONNECTION');
    $role=$connection==='admin'?'postgres':'aia_p55_ci_read_'.$connection;
    $file=$connection==='admin'?'/run/secrets/p25-password':
        '/run/p62/reader-'.$connection;
    $password=file_get_contents($file);
    p62Must(is_string($password)&&strlen($password)>20,'PASSWORD_FILE');
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        $role,trim($password),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]
    );
}
function p62Seed(PDO $db,array $input):array
{
    foreach(['aia_p51_lab','aia_p57_lab','aia_p59_lab'] as $schema){
        p62Must($db->query("SELECT to_regnamespace('{$schema}')")->fetchColumn()===null,
            'PREEXISTING_SCHEMA');
    }
    foreach(['a','b'] as $name){
        p62Must(isset($input['reader_'.$name])
            &&is_string($input['reader_'.$name])
            &&preg_match('/\A[a-f0-9]{36}\z/D',$input['reader_'.$name])===1,
            'READER_PASSWORD_FORMAT');
        p62Must($db->query("SELECT count(*) FROM pg_roles
            WHERE rolname='aia_p55_ci_read_{$name}'")->fetchColumn()===0,
            'PREEXISTING_ROLE');
    }
    $registry=new PgDeterministicDraftRegistryLab($db);
    $registry->createTables();
    $reviews=new PgSignedDraftReviewLab($db);
    $reviews->createTables();
    $authors=new PgDraftAuthorProvenanceLab($db);
    $authors->createTables();
    $signers=[];
    foreach(['PRODUCT','SECURITY'] as $role){
        $pair=sodium_crypto_sign_keypair();
        $secret=sodium_crypto_sign_secretkey($pair);
        $signers[$role]=base64_encode($secret);
        p62Must($reviews->registerReviewer(
            'SYNTHETIC_TENANT_A','SYNTHETIC_P62_'.$role,$role,
            sodium_crypto_sign_publickey($pair)
        ),'REVIEWER_REGISTER');
    }
    $now=time();
    $drafts=[];
    foreach([
        ['A','WAIT','HUMAN_REVIEW_DRAFT',[]],
        ['A','PARTIAL','CRM_TASK_DRAFT',['PRODUCT'=>'ACKNOWLEDGE']],
        ['A','TWO','OPERATOR_NOTICE_DRAFT',[
            'PRODUCT'=>'ACKNOWLEDGE','SECURITY'=>'ACKNOWLEDGE'
        ]],
        ['A','REJECT','HUMAN_REVIEW_DRAFT',['SECURITY'=>'REJECT']],
        ['B','PRIVATE_B','HUMAN_REVIEW_DRAFT',[]],
    ] as [$company,$label,$kind,$votes]){
        $tenant='SYNTHETIC_TENANT_'.$company;
        $request='SYNTHETIC_P62_'.$label;
        $command=[
            'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
            'operation'=>'funnel.calculate','tenant_ref'=>$tenant,
            'request_ref'=>$request,'weekly_visits'=>120,
            'lead_rate_bp'=>1000,'client_rate_bp'=>2000,
        ];
        $rules=[
            'schema'=>'AIA_RULESET_V1','tenant_ref'=>$tenant,
            'ruleset_ref'=>'SYNTHETIC_P62_RULESET','revision'=>1,
            'rules'=>[[
                'rule_ref'=>'SYNTHETIC_P62_RULE','metric'=>'projected_leads',
                'operator'=>'gte','threshold'=>12,'draft_kind'=>$kind,
            ]],
        ];
        p62Must($registry->stage($command,$rules)['decision']===
            'LAB_DRAFTS_STAGED_NO_ACTION','DRAFT_STAGE');
        $q=$db->prepare('SELECT proposal_ref,draft_sha256
            FROM aia_p51_lab.review_drafts
            WHERE tenant_ref=:tenant AND request_ref=:request');
        $q->execute([':tenant'=>$tenant,':request'=>$request]);
        $draft=$q->fetch(PDO::FETCH_ASSOC);
        p62Must(is_array($draft),'DRAFT_READ');
        $drafts[$label]=$draft['proposal_ref'];
        p62Must($authors->seal(
            $tenant,$draft['proposal_ref'],'SYNTHETIC_P62_AUTHOR_'.$company
        )==='LAB_AUTHOR_SEALED_NO_APPROVAL','AUTHOR_SEAL');
        foreach($votes as $role=>$decision){
            $canonical=json_encode([
                'schema'=>'AIA_SIGNED_DRAFT_REVIEW_V1',
                'issuer'=>'SYNTHETIC_INDEPENDENT_REVIEWER',
                'audience'=>'imagine-ai-agents','tenant_ref'=>$tenant,
                'proposal_ref'=>$draft['proposal_ref'],
                'reviewer_ref'=>'SYNTHETIC_P62_'.$role,'role'=>$role,
                'decision'=>$decision,'draft_sha256'=>$draft['draft_sha256'],
                'key_revision'=>1,'issued_at'=>$now,'expires_at'=>$now+120,
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $signature=base64_encode(sodium_crypto_sign_detached(
                $canonical,base64_decode($signers[$role],true)
            ));
            p62Must($reviews->submit($canonical,$signature,$now,true)['decision']
                ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED','REVIEW_SUBMIT');
        }
    }
    foreach(['a','b'] as $name){
        $role='aia_p55_ci_read_'.$name;
        $password=$input['reader_'.$name];
        $tenant='SYNTHETIC_TENANT_'.strtoupper($name);
        $db->exec("CREATE ROLE {$role} LOGIN PASSWORD '{$password}'
            NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS");
        $db->exec("GRANT USAGE ON SCHEMA aia_p51_lab,aia_p57_lab,aia_p59_lab TO {$role}");
        foreach(P62_TABLES as $table){
            $db->exec("REVOKE ALL ON {$table} FROM PUBLIC");
            $db->exec("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            $db->exec("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            $db->exec("GRANT SELECT ON {$table} TO {$role}");
            $db->exec("CREATE POLICY p62_read_{$name} ON {$table}
                FOR SELECT TO {$role} USING(tenant_ref='{$tenant}')");
        }
    }
    // Captured only by the isolated driver in memory; never printed in reports.
    return ['schema'=>'P62_SEEDED_V1','drafts'=>$drafts,'signers'=>$signers];
}
function p62Stats(PDO $db):array
{
    $stats=[];
    foreach(P62_TABLES as $table){
        $rows=$db->query("SELECT row_to_json(t)::text AS row
            FROM {$table} t ORDER BY row_to_json(t)::text")->fetchAll(PDO::FETCH_COLUMN);
        $stats[$table]=[
            'count'=>count($rows),
            'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),
        ];
    }
    return $stats;
}
function p62Checks():array
{
    $a=p62Db('a');$b=p62Db('b');$admin=p62Db('admin');
    $tenant='SYNTHETIC_TENANT_A';
    $before=p62Stats($admin);
    $bridge=new PgSignedReviewQueueBridgeLab($a);
    $view=$bridge->read($tenant);
    p62Must($view['decision']==='LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
        &&$view['current_review_available']===true
        &&$view['signed_review']['review_summary']===[
            'awaiting'=>1,'partial'=>1,'two_observations'=>1,'rejected'=>1
        ]&&$view['historical']['checked_drafts']===4
        &&$view['historical']['checked_signed_observations']===4,'FOUR_STATES');
    p62Must($view['can_approve']===false&&$view['can_execute']===false
        &&$view['operational_authorization']===false,'NO_AUTHORIZATION');
    p62Must($bridge->read('SYNTHETIC_TENANT_B')['decision']==='DENY','CROSS_TENANT');
    p62Must((new PgSignedReviewQueueBridgeLab($admin))->read($tenant)['decision']
        ==='DENY','SUPERUSER_CANNOT_BE_READER');
    $other=(new PgSignedReviewQueueBridgeLab($b))->read('SYNTHETIC_TENANT_B');
    p62Must($other['decision']==='LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
        &&$other['queue']['total_pending']===1
        &&$other['historical']['checked_signed_observations']===0,'TENANT_B');
    foreach(['a'=>$a,'b'=>$b] as $name=>$db){
        $role=$db->query('SELECT rolsuper,rolbypassrls,rolcreaterole,
            rolcreatedb,rolinherit FROM pg_roles WHERE rolname=current_user')
            ->fetch(PDO::FETCH_NUM);
        p62Must($role===[false,false,false,false,false],'ROLE_PRIVILEGES');
        foreach(P62_TABLES as $table){
            $priv=$db->query("SELECT has_table_privilege(current_user,'{$table}','SELECT'),
                has_table_privilege(current_user,'{$table}','INSERT'),
                has_table_privilege(current_user,'{$table}','UPDATE'),
                has_table_privilege(current_user,'{$table}','DELETE'),
                has_table_privilege(current_user,'{$table}','TRUNCATE')")
                ->fetch(PDO::FETCH_NUM);
            p62Must($priv===[true,false,false,false,false],'SELECT_ONLY');
            $scope=$db->query("SELECT count(*) FROM {$table}
                WHERE tenant_ref<>'SYNTHETIC_TENANT_".strtoupper($name)."'")
                ->fetchColumn();
            p62Must($scope===0,'RLS_SCOPE');
            $flags=$admin->query("SELECT relrowsecurity,relforcerowsecurity
                FROM pg_class WHERE oid='{$table}'::regclass")->fetch(PDO::FETCH_NUM);
            p62Must($flags===[true,true],'FORCED_RLS');
        }
        try{
            $db->exec("UPDATE aia_p51_lab.review_drafts SET observed=observed");
            throw new RuntimeException('P62_PROBE_DENIED_WRITE_ALLOWED');
        }catch(PDOException $error){
            p62Must($error->getCode()==='42501','WRITE_DENIAL_CODE');
        }
    }
    $proposal=$view['queue']['items'][0]['proposal_ref'];
    p62Must((new PgScopedReviewQueueBridgeLab($a))
        ->readInsideReadOnlySnapshot($tenant)['decision']==='DENY','NO_TX_P55');
    p62Must((new PgSignedDraftReviewLab($a))
        ->statusInsideReadOnlySnapshot($tenant,$proposal,time())['decision']
        ==='DENY','NO_TX_P57');
    p62Must((new PgHistoricalSignedReviewAuditLab($a))
        ->scanTenantInsideReadOnlySnapshot($tenant)['decision']
        ==='INDETERMINATE','NO_TX_P61');
    foreach([
        'READ COMMITTED, READ ONLY',
        'REPEATABLE READ, READ WRITE',
    ] as $mode){
        $a->beginTransaction();
        $a->exec('SET TRANSACTION ISOLATION LEVEL '.$mode);
        p62Must((new PgScopedReviewQueueBridgeLab($a))
            ->readInsideReadOnlySnapshot($tenant)['decision']==='DENY','GUARD_P55');
        p62Must((new PgSignedDraftReviewLab($a))
            ->statusInsideReadOnlySnapshot($tenant,$proposal,time())['decision']
            ==='DENY','GUARD_P57');
        p62Must((new PgHistoricalSignedReviewAuditLab($a))
            ->scanTenantInsideReadOnlySnapshot($tenant)['decision']
            ==='INDETERMINATE','GUARD_P61');
        p62Must($a->inTransaction(),'CALLER_TX_PRESERVED');
        $a->rollBack();
    }
    $a->beginTransaction();
    $a->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    p62Must((new PgScopedReviewQueueBridgeLab($a))->read($tenant)['decision']
        ==='DENY','OLD_P55_NESTING_DENIED');
    p62Must((new PgSignedDraftReviewLab($a))->status($tenant,$proposal,time(),true)
        ['decision']==='DENY','OLD_P57_NESTING_DENIED');
    p62Must((new PgHistoricalSignedReviewAuditLab($a))->scan()['decision']
        ==='INDETERMINATE','OLD_P61_NESTING_DENIED');
    p62Must($a->inTransaction(),'OLD_CALLER_TX_PRESERVED');
    $a->commit();
    p62Must((new PgScopedReviewQueueBridgeLab($a))->read($tenant)['decision']
        ==='LAB_READONLY_PG_REVIEW_NO_GO','OLD_P55_WORKS');
    p62Must((new PgSignedDraftReviewLab($a))->status($tenant,$proposal,time(),true)
        ['decision']!=='DENY','OLD_P57_WORKS');
    p62Must((new PgHistoricalSignedReviewAuditLab($admin))->scan()['decision']
        ==='LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO','OLD_P61_WORKS');
    $serialized=json_encode($view,JSON_THROW_ON_ERROR);
    foreach(['canonical_vote','signature_b64','public_key_b64','reviewer_ref',
        'SYNTHETIC_P62_PRODUCT','SYNTHETIC_P62_SECURITY','SYNTHETIC_P62_PRIVATE_B',
        'SYNTHETIC_P62_AUTHOR'] as $hidden){
        p62Must(!str_contains($serialized,$hidden),'PRIVATE_FIELDS');
    }
    p62Must(p62Stats($admin)===$before,'READS_DID_NOT_MUTATE');
    return [
        'schema'=>'P62_DOMAIN_CHECKS_VERIFIED_V1','four_states'=>true,
        'tenant_isolation'=>true,'select_only_forced_rls'=>true,
        'caller_transaction_guards'=>true,'legacy_read_apis_preserved'=>true,
        'private_fields_hidden'=>true,'read_only_rows_preserved'=>true,
        'operational_authorization'=>false,
    ];
}

/**
 * Test-only PDO instrumentation, not a hook accepted by the product bridge.
 * A second real PG connection commits after P55 has read the complete queue
 * and before P61/P57 read authors, votes and keys in the same RR snapshot.
 */
final class P62ConcurrentFixturePDO extends PDO
{
    public bool $injected=false;

    public function __construct(private PDO $writer,private string $change)
    {
        parent::__construct(
            'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
            'aia_p55_ci_read_a',trim((string)file_get_contents('/run/p62/reader-a')),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES=>false]
        );
    }

    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        if(!$this->injected&&str_contains($query,'FROM aia_p51_lab.review_drafts d')){
            p62Must($this->inTransaction(),'CONCURRENT_TX_ACTIVE');
            $this->injected=true;
            if($this->change==='revoke'){
                p62Must((new PgSignedDraftReviewLab($this->writer))->revokeReviewer(
                    'SYNTHETIC_TENANT_A','SYNTHETIC_P62_SECURITY'
                ),'CONCURRENT_REVOKE');
            }elseif($this->change==='author'){
                $this->writer->exec("UPDATE aia_p59_lab.draft_authors
                    SET author_ref='SYNTHETIC_P62_PRODUCT'
                    WHERE tenant_ref='SYNTHETIC_TENANT_A'");
            }elseif($this->change==='signature'){
                $q=$this->writer->prepare("UPDATE aia_p57_lab.signed_observations
                    SET signature_b64=?
                    WHERE tenant_ref='SYNTHETIC_TENANT_A' AND reviewer_role='SECURITY'");
                $q->execute([str_repeat('A',88)]);
            }else{
                throw new RuntimeException('P62_PROBE_DENIED_CONCURRENT_CHANGE');
            }
        }
        return parent::prepare($query,$options);
    }
}

function p62Concurrent(string $change):array
{
    p62Must(in_array($change,['revoke','author','signature'],true),'CONCURRENT_KIND');
    $writer=p62Db('admin');
    $reader=new P62ConcurrentFixturePDO($writer,$change);
    $old=(new PgSignedReviewQueueBridgeLab($reader))->read('SYNTHETIC_TENANT_A');
    p62Must($reader->injected&&!$reader->inTransaction(),'CONCURRENT_INJECTED');
    p62Must($old['decision']==='LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
        &&$old['current_review_available']===true
        &&$old['signed_review']['review_summary']===[
            'awaiting'=>1,'partial'=>1,'two_observations'=>1,'rejected'=>1
        ]&&$old['historical']['checked_signed_observations']===4,'WHOLE_OLD_SNAPSHOT');
    $new=(new PgSignedReviewQueueBridgeLab(p62Db('a')))->read('SYNTHETIC_TENANT_A');
    if($change==='revoke'){
        p62Must($new['decision']==='LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
            &&$new['current_review_available']===false
            &&$new['signed_review']===null
            &&$new['historical']['checked_signed_observations']===4,
            'NEXT_SNAPSHOT_REVOKED');
    }else{
        p62Must($new['decision']==='DENY'&&$new['queue']===null
            &&$new['signed_review']===null&&$new['historical']===null,
            'NEXT_SNAPSHOT_CORRUPTION_DENIED');
    }
    return [
        'schema'=>'P62_CONCURRENT_SNAPSHOT_VERIFIED_V1','mutation'=>$change,
        'second_connection_committed'=>true,'whole_old_snapshot_preserved'=>true,
        'next_snapshot_observes_change'=>true,'operational_authorization'=>false,
    ];
}

/** A DENY after a timed-out lock is not evidence of post-wait policy checks. */
final class P62LockObservedPDO extends PDO
{
    public bool $lockCompleted=false;

    public function __construct()
    {
        parent::__construct(
            'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
            'aia_p55_ci_read_a',trim((string)file_get_contents('/run/p62/reader-a')),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES=>false]
        );
    }

    public function exec(string $statement):int|false
    {
        $result=parent::exec($statement);
        if(str_starts_with($statement,'LOCK TABLE aia_p51_lab.preview_batches,')){
            $this->lockCompleted=true;
        }
        return $result;
    }
}

function p62Http(array $input):array
{
    $path=$input['path']??'/p56-live-pg-review-local.php';
    $method=$input['method']??'GET';
    $host=$input['host']??'127.0.0.1:8786';
    p62Must(is_string($path)&&strlen($path)<512
        &&str_starts_with($path,'/p56-live-pg-review-local.php')
        &&!str_contains($path,"\r")&&!str_contains($path,"\n")
        &&in_array($method,['GET','POST','PUT','DELETE'],true)
        &&is_string($host)&&preg_match('/\A[a-z0-9.:-]{1,100}\z/D',$host)===1,
        'HTTP_INPUT');
    $context=stream_context_create(['http'=>[
        'method'=>$method,'header'=>"Host: {$host}\r\nConnection: close\r\n",
        'timeout'=>4,'ignore_errors'=>true,'follow_location'=>0,
    ]]);
    $body=@file_get_contents('http://127.0.0.1:8786'.$path,false,$context,0,524289);
    p62Must(is_string($body)&&strlen($body)<=524288,'HTTP_TRANSPORT');
    $headers=$http_response_header??[];
    p62Must(is_array($headers)&&isset($headers[0])
        &&preg_match('/\AHTTP\/1\.[01] ([0-9]{3}) /',$headers[0],$match)===1,
        'HTTP_STATUS');
    $fields=[];
    foreach(array_slice($headers,1) as $header){
        if(!str_contains($header,':'))continue;
        [$key,$value]=explode(':',$header,2);
        $fields[strtolower(trim($key))]=trim($value);
    }
    return ['status'=>(int)$match[1],'headers'=>$fields,'body_b64'=>base64_encode($body)];
}

try{
    p62Must(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('AIA_P62_SIGNED_REVIEW_LAB')==='true'
        &&getenv('DB_HOST')==='127.0.0.1'
        &&getenv('DB_DATABASE')==='imagine_agents_ci','CI_ONLY');
    $raw=file_get_contents('php://stdin');
    p62Must(is_string($raw)&&strlen($raw)<=16384,'INPUT_LIMIT');
    $input=$raw===''?[]:json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    p62Must(is_array($input),'INPUT_OBJECT');
    $mode=$argv[1]??'';
    if($mode==='seed'){
        $output=p62Seed(p62Db('admin'),$input);
    }elseif($mode==='checks'){
        $output=p62Checks();
    }elseif(in_array($mode,['view','view-lock-observed'],true)){
        $db=$mode==='view-lock-observed'?new P62LockObservedPDO():
            p62Db($input['connection']??'a');
        if(isset($input['application'])){
            p62Must(is_string($input['application'])
                &&preg_match('/\Ap62_[a-z0-9_]{1,40}\z/D',$input['application'])===1,
                'APPLICATION');
            $q=$db->prepare("SELECT set_config('application_name',?,false)");
            $q->execute([$input['application']]);
        }
        $output=(new PgSignedReviewQueueBridgeLab($db))
            ->read($input['tenant']??'SYNTHETIC_TENANT_A');
        if($db instanceof P62LockObservedPDO){
            $output=[
                'schema'=>'P62_LOCK_OBSERVED_V1',
                'lock_completed'=>$db->lockCompleted,'result'=>$output,
            ];
        }
    }elseif($mode==='stats'){
        $output=p62Stats(p62Db('admin'));
    }elseif($mode==='concurrent-snapshot'){
        $output=p62Concurrent($input['change']??'');
    }elseif($mode==='http'){
        $output=p62Http($input);
    }elseif($mode==='unauthenticated-db'){
        try{
            new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
                'aia_p55_ci_read_a','wrong-synthetic-password',[
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                ]);
            throw new RuntimeException('P62_PROBE_DENIED_DB_PASSWORD_NOT_REQUIRED');
        }catch(PDOException){
            $output=['schema'=>'P62_DB_PASSWORD_REQUIRED_V1','required'=>true];
        }
    }elseif($mode==='votes-time-fixture'){
        p62Must(in_array($input['when']??null,['expired','fresh'],true),'FIXTURE_TIME');
        $db=p62Db('admin');
        $now=time();
        foreach($db->query("SELECT proposal_ref,reviewer_role,canonical_vote
            FROM aia_p57_lab.signed_observations
            WHERE tenant_ref='SYNTHETIC_TENANT_A'")->fetchAll(PDO::FETCH_ASSOC) as $row){
            $secret=base64_decode($input['signers'][$row['reviewer_role']]??'',true);
            p62Must(is_string($secret)&&strlen($secret)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES,
                'FIXTURE_SIGNER');
            $vote=json_decode($row['canonical_vote'],true,5,JSON_THROW_ON_ERROR);
            $vote['issued_at']=$input['when']==='expired'?$now-180:$now;
            $vote['expires_at']=$vote['issued_at']+120;
            $canonical=json_encode($vote,
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $q=$db->prepare("UPDATE aia_p57_lab.signed_observations
                SET canonical_vote=?,signature_b64=?
                WHERE tenant_ref='SYNTHETIC_TENANT_A'
                AND proposal_ref=? AND reviewer_role=?");
            $q->execute([$canonical,base64_encode(sodium_crypto_sign_detached(
                $canonical,$secret
            )),$row['proposal_ref'],$row['reviewer_role']]);
        }
        $output=['schema'=>'P62_TIME_FIXTURE_REWRITTEN_V1','fixture_only'=>true];
    }else{
        throw new RuntimeException('P62_PROBE_DENIED_MODE');
    }
    echo json_encode($output,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $error){
    $code=preg_match('/\AP62_PROBE_DENIED_[A-Z0-9_]+\z/D',$error->getMessage())
        ?$error->getMessage():'P62_PROBE_DENIED_UNEXPECTED';
    fwrite(STDERR,$code."\n");
    exit(1);
}
