<?php
declare(strict_types=1);

/** Genuine pinned Laravel code, own CRM login, and real mTLS transport. No Agents DB secret. */
use App\Homologation\S2S\AgentsEventAdapter;
use App\Homologation\S2S\AgentsExecutionReceipt;
use App\Homologation\S2S\AgentsExecutionReconciler;

function feedbackCrmNeed(bool $condition):void
{if(!$condition)throw new RuntimeException('FEEDBACK_CRM_PROBE_GUARD');}

function feedbackCrmDatabase(string $role):PDO
{
    feedbackCrmNeed(in_array($role,['crm_auto_sender','crm_auto_execution_reconciler'],true));
    $file=$role==='crm_auto_sender'?'/certs/sender.password':'/certs/execution-reader.password';
    $password=file_get_contents($file);
    feedbackCrmNeed(is_string($password)&&strlen(trim($password))>=32);
    $db=new PDO(getenv('CRM_AUTO_S2S_DSN'),$role,trim($password),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    feedbackCrmNeed($db->query('SELECT current_database()')->fetchColumn()==='imagine_crm_private_homologation'
        &&$db->query('SELECT current_user')->fetchColumn()===$role);
    $db->exec("SET statement_timeout='2000ms';SET lock_timeout='1000ms';SET idle_in_transaction_session_timeout='6000ms'");
    return $db;
}

try {
    feedbackCrmNeed(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('CRM_FEEDBACK_CI')==='synthetic-private-v1'
        &&getenv('APP_ENV')==='staging'
        &&preg_match('/\A[a-f0-9]{40}\z/D',getenv('CRM_FEEDBACK_SOURCE_SHA')?:'')===1
        &&getenv('CRM_AUTO_S2S_DSN')==='pgsql:host=127.0.0.1;port=5433;dbname=imagine_crm_private_homologation');
    foreach(['/run/secrets/p25-password','/certs/agents-db.password',
        '/run/secrets/transport-password','/run/secrets/execution-password'] as $forbidden)
        feedbackCrmNeed(!file_exists($forbidden));
    // The runtime image must execute the same public code as the exact archived checkout.
    foreach(['composer.lock','bootstrap/app.php','app/Homologation/S2S/StrictJson.php',
        'app/Homologation/S2S/AgentsAcknowledgement.php','app/Homologation/S2S/AgentsEventAdapter.php',
        'app/Homologation/S2S/AgentsExecutionReceipt.php',
        'app/Homologation/S2S/AgentsExecutionReconciler.php'] as $source){
        $archived='/crm-source/'.$source;$installed='/var/www/html/'.$source;
        feedbackCrmNeed(is_file($archived)&&!is_link($archived)&&is_file($installed)&&!is_link($installed)
            &&hash_equals(hash_file('sha256',$archived),hash_file('sha256',$installed)));
    }
    foreach(['/var/www/html/storage/framework/cache/data','/var/www/html/storage/framework/sessions',
        '/var/www/html/storage/framework/views','/var/www/html/storage/logs'] as $directory)
        if(!is_dir($directory))feedbackCrmNeed(mkdir($directory,0770,true));
    require '/var/www/html/vendor/autoload.php';
    $app=require '/var/www/html/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    feedbackCrmNeed(app()->environment('staging'));
    $mode=$argv[1]??'';
    feedbackCrmNeed(in_array($mode,['send','stage','reconcile','read','replay','validate','isolation'],true));
    $raw=file_get_contents('php://stdin',false,null,0,20001);
    feedbackCrmNeed(is_string($raw)&&strlen($raw)<=20000);
    $input=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
    feedbackCrmNeed(is_array($input));
    $company=$input['company_id']??null;$event=$input['event_id']??null;
    feedbackCrmNeed(in_array($company,['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],true)
        &&is_string($event)&&preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/D',$event)===1);
    if($mode==='send'){
        (new AgentsEventAdapter)->send($company,$event);
        $result=['admission_verified'=>true,'dispatch'=>'DISABLED'];
    }elseif($mode==='reconcile'){
        $result=(new AgentsExecutionReconciler)->reconcile($company,$event);
    }elseif($mode==='isolation'){
        $db=feedbackCrmDatabase('crm_auto_execution_reconciler');
        $wrongDatabaseDenied=false;
        try{
            new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci;connect_timeout=2',
                'crm_auto_execution_reconciler',trim(file_get_contents('/certs/execution-reader.password')),
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        }catch(PDOException){$wrongDatabaseDenied=true;}
        feedbackCrmNeed($wrongDatabaseDenied);
        $socket=@fsockopen('192.0.2.1',443,$errno,$error,0.5);
        feedbackCrmNeed(!is_resource($socket));
        $result=['laravel_environment'=>app()->environment(),'agents_database_denied'=>true,
            'runtime_egress_denied'=>true,'agents_database_secrets_mounted'=>false];
    }else{
        if(in_array($mode,['validate','replay'],true)){
            $wire=base64_decode($input['body_b64']??'',true);$query=$input['query']??null;
            feedbackCrmNeed(is_string($wire)&&strlen($wire)<=4096&&is_array($query));
            // Replays in the test are previously returned, genuinely signed HTTP bytes.
            // A caller-supplied "verified" flag can never reach the SQL projection.
            $verified=AgentsExecutionReceipt::validate($wire,$query,
                file_get_contents('/certs/agents-execution.key'),time());
        }
        if($mode==='validate')$result=['response_verified'=>true,'observation_hash'=>$verified['observation_hash']];
        else{
            $db=feedbackCrmDatabase($mode==='stage'?'crm_auto_sender':'crm_auto_execution_reconciler');
            $db->beginTransaction();
            if($mode==='replay'){
                $statement=$db->prepare('SELECT crm_auto_service.project_execution(CAST(? AS uuid),CAST(? AS uuid),CAST(? AS jsonb),?)');
                $statement->execute([$company,$event,json_encode($query,JSON_THROW_ON_ERROR),$wire]);
            }else{
                $function=$mode==='stage'?'stage_outbound':'read_execution_projection';
                $statement=$db->prepare('SELECT crm_auto_service.'.$function.'(CAST(? AS uuid),CAST(? AS uuid))');
                $statement->execute([$company,$event]);
            }
            $result=json_decode($statement->fetchColumn(),true,16,JSON_THROW_ON_ERROR);
            $db->commit();
        }
    }
    echo json_encode(['ok'=>true,'value'=>$result],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    $reason='CRM_FEEDBACK_RUNTIME_FAILED';
    if($error instanceof PDOException&&in_array((string)$error->getCode(),['57014','55P03'],true))
        $reason='CRM_FEEDBACK_DATABASE_TIMEOUT';
    elseif(preg_match('/\b(EXECUTION_[A-Z_]+|SENDER_DISABLED|AGENTS_TRANSPORT_DENIED|FEEDBACK_CI_PROJECTION_FAULT|FEEDBACK_CRM_PROBE_GUARD)\b/',
        $error->getMessage(),$match))$reason=$match[1];
    // Only a bounded domain code leaves this process; no SQL, credentials or trace.
    echo json_encode(['ok'=>false,'reason'=>$reason],JSON_THROW_ON_ERROR)."\n";
}
