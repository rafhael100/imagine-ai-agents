<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAtomicCompanyTenantStageLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgAtomicCompanyTenantStageLab;
use ImagineAI\Convergence\PgOwnerDecisionLab;
use ImagineAI\Convergence\PgOwnerDecisionBoundStageLab;
use ImagineAI\Convergence\P18Fixture;
use ImagineAI\Labs\PgN8nOutboxLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P6_PG_LAB')!=='true'||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P44_DISPOSABLE_CI_ONLY');
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stager=new PgAtomicCompanyTenantStageLab($db);
$stager->createTables();
$db->exec('TRUNCATE TABLE aia_p42_lab.consumed_transport_proofs');
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$db->exec('TRUNCATE TABLE aia_p16_lab.event_replay');
$outbox=new PgN8nOutboxLab($db);
$owner=new PgOwnerDecisionLab($db);
$owner->createTables();
$db->exec('TRUNCATE TABLE aia_p44_lab.consumed_owner_grants');
$db->exec('TRUNCATE TABLE aia_p44_lab.owner_keys');
$bound=new PgOwnerDecisionBoundStageLab($db);
$checks=0;
function p44Check(bool $ok,string $name):void {
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P44_FAIL_'.$name);
}
function p44Counts(PDO $db):array {
    return [
        (int)$db->query('SELECT COUNT(*) FROM aia_p44_lab.consumed_owner_grants')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM aia_p42_lab.consumed_transport_proofs')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM aia_p16_lab.event_replay')->fetchColumn(),
        (int)$db->query('SELECT COUNT(*) FROM public.aia_lab_n8n_outbox_p6')->fetchColumn(),
    ];
}
function p44Grant(string $priv,string $company,string $tenant,string $event,
    string $digest,string $grant,string $key,int $when,array $change=[]):array{
    $data=[
        'schema'=>'AIA_OWNER_DECISION_V1',
        'issuer'=>'SYNTHETIC_CRM_POLICY_OWNER',
        'audience'=>'imagine-ai-agents',
        'company_id'=>$company,'tenant_ref'=>$tenant,'event_ref'=>$event,
        'grant_ref'=>$grant,'operation'=>'run.status.project',
        'owner_revision'=>1,'payload_hash'=>$digest,
        'issued_at'=>$when-5,'expires_at'=>$when+60,
        'decision'=>'ALLOW_NO_AI_LAB','key_id'=>$key,
    ];
    $data=array_replace($data,$change);
    $raw=json_encode($data,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$priv))];
}
function p44Stage(
    PgOwnerDecisionBoundStageLab $bound,string $company,string $tenant,
    array $p7,string $event,int $transportNow,
    string $grantRaw,string $grantSig,string $key
):array {
    [$canonical,$signed,$policy]=P18Fixture::make($tenant,$event);
    $seen=[];
    $r=$bound->stage(
        $company,$tenant,$p7['request_id'],$p7['nonce'],
        $p7['request_digest'],1,$transportNow,
        $canonical,$signed,$policy,P18Fixture::NOW,
        ['SYNTHETIC_KEY_A'=>P18Fixture::KEY],$seen,
        $grantRaw,$grantSig,$key
    );
    p44Check($r['ai_calls']===0&&$r['service_effects']===0,
        'no_external_effect_'.$event);
    if($r['decision']==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY')
        p44Check(count($seen)===1,'commit_only_success_'.$event);
    else p44Check($seen===[],'no_seen_on_failure_'.$event);
    return $r;
}
try{
    $company='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $tenant='SYNTHETIC_TENANT_A';
    $now=time();
    $proofs=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest,issued_at
FROM central_auto_lab.mtls_nonce_ledger
WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
ORDER BY request_id LIMIT 3
SQL)->fetchAll(PDO::FETCH_ASSOC);
    p44Check(count($proofs)===3,'three_original_P7_mtls_proofs');
    p44Check($stager->bridge()->seedLabBinding($company,$tenant),'mapping_A');
    $pair=sodium_crypto_sign_keypair();
    $priv=sodium_crypto_sign_secretkey($pair);
    $pub=sodium_crypto_sign_publickey($pair);
    $key='SYNTHETIC_CRM_OWNER_KEY_A';
    p44Check($owner->registerKey($company,$key,$pub),'register_owner_key');

    $event1='SYNTHETIC_P44_EVENT_01';
    [$canonical1]=P18Fixture::make($tenant,$event1);
    [$grant1,$sig1]=p44Grant($priv,$company,$tenant,$event1,
        hash('sha256',$canonical1),'SYNTHETIC_P44_GRANT_01',
        $key,P18Fixture::NOW);
    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        '','','')['reason']==='OWNER_DECISION_REQUIRED',
        'transport_only_cannot_authorize_business');
    p44Check(p44Counts($db)===[0,0,0,0],'no_records_without_owner_decision');

    [$invalidOp,$invalidOpSig]=p44Grant($priv,$company,$tenant,$event1,
        hash('sha256',$canonical1),'SYNTHETIC_P44_GRANT_01',
        $key,P18Fixture::NOW,['operation'=>'campaign.publish']);
    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        $invalidOp,$invalidOpSig,$key)['reason']==='OWNER_SERVICE_DECISION_DENIED',
        'approved_key_not_enough_for_wrong_operation');
    p44Check(p44Counts($db)===[0,0,0,0],'operation_denied_atomic_rollback');

    [$invalidHash,$invalidHashSig]=p44Grant($priv,$company,$tenant,$event1,
        str_repeat('a',64),'SYNTHETIC_P44_GRANT_01',
        $key,P18Fixture::NOW);
    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        $invalidHash,$invalidHashSig,$key)['decision']==='DENY',
        'owner_grant_payload_digest_mismatch');
    p44Check(p44Counts($db)===[0,0,0,0],'bad_digest_keeps_no_rows');
    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        $grant1,'',$key)['decision']==='DENY',
        'unsigned_grant_denied');

    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        $grant1,$sig1,$key)['decision']
        ==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY','owner_grant_accepted');
    p44Check(p44Counts($db)===[1,1,1,1],'owner_mtls_replay_outbox_single_commit');
    p44Check(p44Stage($bound,$company,$tenant,$proofs[0],$event1,$now,
        $grant1,$sig1,$key)['decision']==='DENY','grant_cannot_replay');
    p44Check(p44Counts($db)===[1,1,1,1],'replay_does_not_duplicate_rows');

    $event2='SYNTHETIC_P44_EVENT_02';
    [$canonical2]=P18Fixture::make($tenant,$event2);
    [$grant2,$sig2]=p44Grant($priv,$company,$tenant,$event2,
        hash('sha256',$canonical2),'SYNTHETIC_P44_GRANT_02',
        $key,P18Fixture::NOW);
    p44Check($owner->revokeKey($company,$key)===2,'owner_key_revoked');
    p44Check(p44Stage($bound,$company,$tenant,$proofs[1],$event2,$now,
        $grant2,$sig2,$key)['reason']==='OWNER_SERVICE_DECISION_DENIED',
        'mathematically_valid_grant_rejected_after_revocation');
    p44Check(p44Counts($db)===[1,1,1,1],'revocation_prevents_partial_stage');

    $pairNew=sodium_crypto_sign_keypair();
    $privNew=sodium_crypto_sign_secretkey($pairNew);
    $pubNew=sodium_crypto_sign_publickey($pairNew);
    $newKey='SYNTHETIC_CRM_OWNER_KEY_B';
    p44Check($owner->registerKey($company,$newKey,$pubNew),
        'new_key_separate_registration');
    [$fresh,$freshSig]=p44Grant($privNew,$company,$tenant,$event2,
        hash('sha256',$canonical2),'SYNTHETIC_P44_GRANT_02',
        $newKey,P18Fixture::NOW);
    p44Check(p44Stage($bound,$company,$tenant,$proofs[1],$event2,$now,
        $fresh,$freshSig,$newKey)['decision']
        ==='LAB_ATOMIC_COMPANY_BOUND_STAGED_NO_DELIVERY',
        'new_owner_decision_allows_fresh_event');
    p44Check(p44Counts($db)===[2,2,2,2],'new_decision_atomic_commit');

    $event3='SYNTHETIC_P44_EVENT_03';
    [$canonical3]=P18Fixture::make($tenant,$event3);
    [$usedGrantAgain,$usedSigAgain]=p44Grant($privNew,$company,$tenant,$event3,
        hash('sha256',$canonical3),'SYNTHETIC_P44_GRANT_02',
        $newKey,P18Fixture::NOW);
    p44Check(p44Stage($bound,$company,$tenant,$proofs[2],$event3,$now,
        $usedGrantAgain,$usedSigAgain,$newKey)['decision']==='DENY',
        'same_owner_grant_ref_cannot_authorize_second_event');
    p44Check(p44Counts($db)===[2,2,2,2],'used_owner_grant_does_not_consume_mtls');
    p44Check(PgOwnerDecisionBoundStageLab::operationalReadiness()['decision']
        ==='NO_GO','not_real_owner_IAM');
    echo "AIA_P44_OWNER_DECISION_PASS checks={$checks} valid_events=2 owner_grants_consumed=2 transport_mtls_is_not_authority=true no_ai=1 NO_GO\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    $outbox->dropTables();
}
