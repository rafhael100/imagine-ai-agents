<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAiCreditReservationLab.php';
require_once __DIR__.'/../src/MockOrchestration/MockAiOrAutomationRouter.php';

use ImagineAI\Labs\PgAiCreditReservationLab;


/**
 * P11 test-only trusted signer: generated exclusively within ephemeral CI.
 * Never a real provider key or authorization. The verifier is pinned into
 * the lab object; signatures cannot specify or substitute their verifier.
 */
$fixturePair=sodium_crypto_sign_keypair();
$fixtureSecret=sodium_crypto_sign_secretkey($fixturePair);
$fixturePublic=sodium_crypto_sign_publickey($fixturePair);

function signedP11(
    string $tenant,string $request,int $observed,string $secret,
    int $issued=1794690010,int $expires=1794690070,
): array {
    $proof=[
        'contract'=>'SYNTHETIC_AIA_USAGE_RECEIPT_V1',
        'issuer'=>'SYNTHETIC_PROVIDER_FIXTURE',
        'proof_ref'=>'SYNTHETIC_PROOF_'.strtoupper(
            substr(hash('sha256',$tenant."\n".$request."\n".$observed),0,20)
        ),
        'tenant_ref'=>$tenant,
        'request_ref'=>$request,
        'observed'=>$observed,
        'issued_at'=>$issued,
        'expires_at'=>$expires,
    ];
    $bytes=json_encode(
        $proof,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    return [$bytes,base64_encode(sodium_crypto_sign_detached($bytes,$secret))];
}
function reconcileFixture(
    PgAiCreditReservationLab $lab,string $tenant,string $request,int $observed
): bool {
    global $fixtureSecret;
    [$body,$sig]=signedP11($tenant,$request,$observed,$fixtureSecret);
    return $lab->reconcileSigned($tenant,$request,$body,$sig,1794690020);
}

function checkP8(bool $ok,string $label): void
{
    if (!$ok) throw new RuntimeException('P8_CREDIT_LAB_DENIED_'.$label);
}
function connectP8(): PDO
{
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
}
if (getenv('AIA_P8_PG_LAB')!=='true'
    || getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('DB_HOST')!=='127.0.0.1'
    || getenv('DB_DATABASE')!=='imagine_agents_ci') {
    throw new RuntimeException('P8_CI_ONLY');
}
$db=connectP8();
$lab=new PgAiCreditReservationLab($db,$fixturePublic);
$lab->createTables();
try {
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_A',100),'seed A');
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_B',30),'seed B');

    checkP8(!method_exists($lab,'reconcile'),'no unsigned accounting API');
    [$validBody,$validSig]=signedP11(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NOT_DISPATCHED',10,$fixtureSecret
    );
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NOT_DISPATCHED',
        $validBody,$validSig,1794690020
    ),'signed proof cannot charge a missing request');

    $wrongPair=sodium_crypto_sign_keypair();
    $forgedSecret=sodium_crypto_sign_secretkey($wrongPair);
    [$forgedBody,$forgedSig]=signedP11(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NONEXISTENT',10,$forgedSecret
    );
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NONEXISTENT',
        $forgedBody,$forgedSig,1794690020
    ),'untrusted signing key denied');
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NONEXISTENT',
        $forgedBody,$validSig,1794690020
    ),'signature not interchangeable');
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NONEXISTENT',
        $validBody." ",$validSig,1794690020
    ),'noncanonical trailing bytes denied');
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_A','SYNTHETIC_NOT_DISPATCHED',
        $validBody,$validSig,1794690020
    ),'cross tenant signed proof denied');
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NOT_DISPATCHED',
        $validBody,$validSig,1794690080
    ),'expired provider fixture denied');
    checkP8(!$lab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NOT_DISPATCHED',
        $validBody,$validSig,1794690000
    ),'future dated fixture denied');
    $untrustedLab=new PgAiCreditReservationLab(connectP8());
    checkP8(!$untrustedLab->reconcileSigned(
        'SYNTHETIC_TENANT_B','SYNTHETIC_NOT_DISPATCHED',
        $validBody,$validSig,1794690020
    ),'no trusted verifier means no reconciliation');


    // P5 -> P8 integration contract: deterministic tasks never even
    // request a credit hold; semantic tasks require all eligibility checks.
    checkP8($lab->seedSynthetic('SYNTHETIC_TENANT_C',40),'seed model test tenant');
    $intent=[
        'tenant_ref'=>'SYNTHETIC_TENANT_C',
        'run_ref'=>'SYNTHETIC_RUN_01',
        'operation'=>'mock.funnel.estimate',
        'content_class'=>'SYNTHETIC_ONLY',
        'requires_external_effect'=>false,
        'estimated_model_units'=>0,
    ];
    $policy=[
        'tenant_ref'=>'SYNTHETIC_TENANT_C',
        'ai_opt_in'=>false,'consent_verified'=>false,
        'approved_for_model'=>false,'available_units'=>40,
        'per_call_limit'=>0,'provider_configured'=>false,'ledger_certain'=>false,
    ];
    $route=\ImagineAI\MockOrchestration\MockAiOrAutomationRouter::inspect($intent,$policy);
    checkP8($route['decision']==='DETERMINISTIC_ONLY'
        && $route['proposed_units']===0
        && $route['credits_consumed']===0,'deterministic preflight is zero-credit');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')['available']===40,
        'preflight must not touch money or PG quota');
    $intent['operation']='mock.copy.draft';
    $intent['estimated_model_units']=15;
    $policy=array_replace($policy,[
        'ai_opt_in'=>true,'consent_verified'=>true,
        'approved_for_model'=>true,'per_call_limit'=>20,
        'provider_configured'=>true,'ledger_certain'=>true,
    ]);
    $model=\ImagineAI\MockOrchestration\MockAiOrAutomationRouter::inspect($intent,$policy);
    checkP8($model['decision']==='MODEL_ELIGIBLE_NOT_EXECUTED'
        && $model['proposed_units']===15 && $model['credits_consumed']===0
        && $model['external_calls']===0,'model only eligible no API');
    checkP8($lab->reserve('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL',
        'SYNTHETIC_PURPOSE_MARKETING',$model['proposed_units'])===
        ['state'=>'RESERVED','estimated'=>15],'reserve separately after preflight');
    checkP8(!$lab->markUnknown('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL'),
        'cannot declare timeout before dispatch');
    checkP8(!reconcileFixture($lab,'SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL',12),
        'undispatched reservation cannot become charge');
    checkP8($lab->markDispatched('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL'),
        'explicit synthetic outbound handoff marker');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL'),
        'cannot cancel a dispatched operation');
    checkP8($lab->markUnknown('SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL'),
        'uncertain future provider response keeps hold');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')['held']===15,
        'no automatic refund of unknown credit');
    checkP8(reconcileFixture($lab,'SYNTHETIC_TENANT_C','SYNTHETIC_REQUEST_MODEL',12),
        'verified synthetic observed measure');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_C')===[
        'allowance'=>40,'spent'=>12,'held'=>0,'available'=>28,'frozen'=>false,
    ],'only observed usage charged after reconciliation');

    checkP8(!$lab->seedSynthetic('SYNTHETIC_TENANT_A',100),'reject double funding');
    checkP8(!$lab->seedSynthetic('REAL_CUSTOMER',100),'reject real customer');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','INVALID_ID',
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,'reject invalid request');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_REQUEST_ZERO',
        'SYNTHETIC_PURPOSE_MARKETING',0)===null,'zero estimate denied');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_REQUEST_OVER',
        'SYNTHETIC_PURPOSE_MARKETING',5001)===null,'oversize estimate denied');

    // Concurrent physical PG16 row locks. No overspend and no double hold.
    $processes=[];
    for($i=1;$i<=10;$i++){
        $request='SYNTHETIC_REQUEST_PARALLEL_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $cmd=[PHP_BINARY,__DIR__.'/p8_credit_worker.php','reserve',$request,'20'];
        $proc=proc_open($cmd,[
            0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],
        ],$pipes,__DIR__);
        checkP8(is_resource($proc),'worker spawned');
        fclose($pipes[0]);
        $processes[]=[$proc,$pipes,$request];
    }
    $accepted=[];$denied=0;
    foreach($processes as [$proc,$pipes,$request]){
        $stdout=trim(stream_get_contents($pipes[1]));
        $stderr=trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        checkP8(proc_close($proc)===0 && $stderr==='','worker success');
        $result=json_decode($stdout,true,5,JSON_THROW_ON_ERROR);
        if($result===null){$denied++;continue;}
        checkP8($result===['state'=>'RESERVED','estimated'=>20],'unique hold');
        $accepted[]=$request;
    }
    checkP8(count($accepted)===5 && $denied===5,'five accepted five denied');
    sort($accepted);
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>0,'held'=>100,'available'=>0,'frozen'=>false,
    ],'zero overbooking');

    [$first,$second,$third]=$accepted;
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===['state'=>'REPLAY','estimated'=>20],
        'idempotent replay same credit');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_OTHER',20)===null,'conflicting id denied');

    checkP8(!$lab->markUnknown('SYNTHETIC_TENANT_A',$first),
        'cannot time out reserved non-dispatched operation');
    checkP8(!reconcileFixture($lab,'SYNTHETIC_TENANT_A',$first,19),
        'must not charge a non-dispatched reservation');
    checkP8($lab->markDispatched('SYNTHETIC_TENANT_A',$first),
        'mark first as dispatched in lab');
    checkP8(!$lab->markDispatched('SYNTHETIC_TENANT_A',$first),
        'second dispatch marker denied');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$first),
        'dispatched reservation must not be canceled');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,
        'dispatched request cannot be replayed as a new authorization');
    checkP8($lab->markUnknown('SYNTHETIC_TENANT_A',$first),'unknown holds credit');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,
        'uncertain request cannot be replayed');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$first),
        'cannot refund unknown provider result');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')['available']===0,
        'timeout reserves whole amount');
    checkP8(!$lab->markUnknown('SYNTHETIC_TENANT_A',$first),
        'second timeout signal cannot alter state');

    checkP8($lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$second),
        'verified cancellation before dispatch');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$second),
        'cannot double refund');
    checkP8(!$lab->markDispatched('SYNTHETIC_TENANT_A',$second),
        'canceled hold cannot be marked dispatched');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$second,
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,
        'canceled request cannot be replayed and dispatched');
    checkP8(!reconcileFixture($lab,'SYNTHETIC_TENANT_A',$second,0),
        'cancelled cannot become provider charge');

    checkP8($lab->markDispatched('SYNTHETIC_TENANT_A',$third),
        'third execution explicitly marked dispatched');
    checkP8(reconcileFixture($lab,'SYNTHETIC_TENANT_A',$third,12),
        'verified observed 12');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>12,'held'=>60,'available'=>28,'frozen'=>false,
    ],'net after cancellation and 12 actual');
    checkP8(reconcileFixture($lab,'SYNTHETIC_TENANT_A',$first,19),
        'late observed provider proof frees uncertain hold');
    checkP8(reconcileFixture($lab,'SYNTHETIC_TENANT_A',$first,19),
        'same measured proof idempotent');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===null,
        'finalized request never reauthorizes model dispatch');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')===[
        'allowance'=>100,'spent'=>31,'held'=>40,'available'=>29,'frozen'=>false,
    ],'late proof only counted once');
    checkP8(!reconcileFixture($lab,'SYNTHETIC_TENANT_B',$first,19),
        'cross tenant cannot reconcile');

    // Conflicting late proof must freeze the account rather than guessing
    // which charge to trust.
    checkP8(!reconcileFixture($lab,'SYNTHETIC_TENANT_A',$first,18),
        'contradictory proof denied');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_A')['frozen']===true,
        'ledger frozen on contradiction');
    checkP8($lab->reserve('SYNTHETIC_TENANT_A','SYNTHETIC_NEW_REQUEST',
        'SYNTHETIC_PURPOSE_MARKETING',10)===null,'frozen cannot spend');
    checkP8(!$lab->cancelBeforeDispatch('SYNTHETIC_TENANT_A',$accepted[3]),
        'frozen cannot refund blindly');

    // Tenant B remains independent, even with the same idempotency ref.
    checkP8($lab->reserve('SYNTHETIC_TENANT_B',$first,
        'SYNTHETIC_PURPOSE_MARKETING',20)===['state'=>'RESERVED','estimated'=>20],
        'tenant B independent request');
    checkP8($lab->markDispatched('SYNTHETIC_TENANT_B',$first),
        'B dispatch state separated by tenant');
    checkP8(reconcileFixture($lab,'SYNTHETIC_TENANT_B',$first,20),
        'B exact observation');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_B')===[
        'allowance'=>30,'spent'=>20,'held'=>0,'available'=>10,'frozen'=>false,
    ],'B ledger independent');

    $lab->dropTables();
    checkP8($lab->reserve('SYNTHETIC_TENANT_B','SYNTHETIC_OUTAGE',
        'SYNTHETIC_PURPOSE_MARKETING',5)===null,
        'database outage fail closed');
    checkP8($lab->snapshot('SYNTHETIC_TENANT_B')===null,
        'database outage no fictitious fallback');
    echo "P8_PG16_CREDIT_RESERVATION_PASS workers=10 accepted=5 rejected=5 timeout_hold=true duplicate_charge=none provider_calls=0 NO_GO\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    $lab->dropTables();
}
