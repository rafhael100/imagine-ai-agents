<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgSignedDraftReviewLab.php';
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgSignedDraftReviewLab;
use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\P18Fixture;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('AIA_P57_REVIEW_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P57_DISPOSABLE_CI_ONLY');
}
$db=new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$drafts=new PgDeterministicDraftRegistryLab($db);
$drafts->createTables();
$db->exec('TRUNCATE TABLE aia_p51_lab.review_drafts,aia_p51_lab.preview_batches');
$review=new PgSignedDraftReviewLab($db);
$review->createTables();
$db->exec('TRUNCATE TABLE aia_p57_lab.signed_observations,aia_p57_lab.reviewers');

$checks=0;
function p57Check(bool $ok,string $label):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('AIA_P57_FAIL_'.$label);
}
function p57Command(string $tenant,string $request):array{
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,'request_ref'=>$request,
        'weekly_visits'=>120,'lead_rate_bp'=>1000,'client_rate_bp'=>2000
    ];
}
function p57Rules(string $tenant):array{
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P57_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P57_RULE_LEADS',
                'metric'=>'projected_leads',
                'operator'=>'gte','threshold'=>12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT',
            ],
            [
                'rule_ref'=>'SYNTHETIC_P57_RULE_CUSTOMERS',
                'metric'=>'projected_customers',
                'operator'=>'eq','threshold'=>2,
                'draft_kind'=>'CRM_TASK_DRAFT',
            ]
        ],
    ];
}
function p57Draft(PDO $db,string $tenant,string $rule):array{
    $q=$db->prepare(<<<'SQL'
SELECT proposal_ref,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND rule_ref=:rule
SQL);
    $q->execute([':tenant'=>$tenant,':rule'=>$rule]);
    $v=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($v))throw new RuntimeException('P57_DRAFT_NOT_FOUND');
    return $v;
}
function p57Signed(string $secret,string $tenant,array $draft,
    string $reviewer,string $role,string $decision,int $now,
    array $changes=[]):array{
    $data=[
        'schema'=>'AIA_SIGNED_DRAFT_REVIEW_V1',
        'issuer'=>'SYNTHETIC_INDEPENDENT_REVIEWER',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,
        'proposal_ref'=>$draft['proposal_ref'],
        'reviewer_ref'=>$reviewer,
        'role'=>$role,
        'decision'=>$decision,
        'draft_sha256'=>$draft['draft_sha256'],
        'key_revision'=>1,
        'issued_at'=>$now-5,
        'expires_at'=>$now+60
    ];
    $data=array_replace($data,$changes);
    $raw=json_encode($data,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
function p57NoAction(array $result,string $label):void {
    p57Check($result['can_approve']===false
        &&$result['can_execute']===false
        &&$result['operational_authorization']===false
        &&$result['ai_calls']===0
        &&$result['service_effects']===0,
        'no_operational_action_'.$label);
}
try{
    $a='SYNTHETIC_TENANT_A';
    $b='SYNTHETIC_TENANT_B';
    $now=P18Fixture::NOW;
    p57Check($drafts->stage(p57Command($a,'SYNTHETIC_P57_REQUEST_A'),
        p57Rules($a))['decision']==='LAB_DRAFTS_STAGED_NO_ACTION',
        'stage_A');
    p57Check($drafts->stage(p57Command($b,'SYNTHETIC_P57_REQUEST_B'),
        p57Rules($b))['decision']==='LAB_DRAFTS_STAGED_NO_ACTION',
        'stage_B');
    $lead=p57Draft($db,$a,'SYNTHETIC_P57_RULE_LEADS');
    $customer=p57Draft($db,$a,'SYNTHETIC_P57_RULE_CUSTOMERS');
    p57Check($review->status($a,$lead['proposal_ref'],$now)['decision']
        ==='LAB_AWAITING_REVIEWS_NO_ACTION','zero_votes_pending');
    p57Check($review->status($b,$lead['proposal_ref'],$now)['decision']
        ==='DENY','tenantB_cannot_read_A_review');

    $productPair=sodium_crypto_sign_keypair();
    $productSecret=sodium_crypto_sign_secretkey($productPair);
    $productKey=sodium_crypto_sign_publickey($productPair);
    $secPair=sodium_crypto_sign_keypair();
    $secSecret=sodium_crypto_sign_secretkey($secPair);
    $secKey=sodium_crypto_sign_publickey($secPair);
    $backupPair=sodium_crypto_sign_keypair();
    $backupSecret=sodium_crypto_sign_secretkey($backupPair);
    $backupKey=sodium_crypto_sign_publickey($backupPair);
    $product='SYNTHETIC_REVIEWER_PRODUCT_A';
    $security='SYNTHETIC_REVIEWER_SECURITY_B';
    $backup='SYNTHETIC_REVIEWER_PRODUCT_C';
    p57Check($review->registerReviewer($a,$product,'PRODUCT',$productKey),
        'register_product');
    p57Check($review->registerReviewer($a,$security,'SECURITY',$secKey),
        'register_security');
    p57Check($review->registerReviewer($a,$backup,'PRODUCT',$backupKey),
        'register_alternative_product');
    p57Check(!$review->registerReviewer($a,$product,'SECURITY',$secKey),
        'same_reviewer_cannot_register_second_role');

    [$productRaw,$productSig]=p57Signed(
        $productSecret,$a,$lead,$product,'PRODUCT','ACKNOWLEDGE',$now
    );
    p57Check($review->submit($productRaw,$productSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED','first_signed_vote');
    $one=$review->status($a,$lead['proposal_ref'],$now);
    p57Check($one['decision']==='LAB_PARTIAL_REVIEW_NO_ACTION'
        &&$one['observations']===1,'partial_observations');
    p57NoAction($one,'partial');
    p57Check($review->submit($productRaw,$productSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_REPLAY','idempotent_vote_replay');

    [$duplicateRaw,$duplicateSig]=p57Signed(
        $backupSecret,$a,$lead,$backup,'PRODUCT','ACKNOWLEDGE',$now
    );
    p57Check($review->submit($duplicateRaw,$duplicateSig,$now)['reason']
        ==='REVIEW_ROLE_ALREADY_OBSERVED',
        'cannot_double_vote_product_role');

    [$wrongRoleRaw,$wrongRoleSig]=p57Signed(
        $productSecret,$a,$lead,$product,'SECURITY','ACKNOWLEDGE',$now
    );
    p57Check($review->submit($wrongRoleRaw,$wrongRoleSig,$now)['reason']
        ==='REVIEWER_NOT_TRUSTED_REVOKED_OR_INVALID',
        'single_reviewer_cannot_act_as_security');

    [$secRaw,$secSig]=p57Signed(
        $secSecret,$a,$lead,$security,'SECURITY','ACKNOWLEDGE',$now
    );
    p57Check($review->submit($secRaw,$secSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'independent_second_observation');
    $two=$review->status($a,$lead['proposal_ref'],$now);
    p57Check($two['decision']==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'
        &&$two['observations']===2
        &&in_array('PRODUCT',$two['roles_observed'],true)
        &&in_array('SECURITY',$two['roles_observed'],true),
        'two_votes_never_operational_approval');
    p57NoAction($two,'two_votes');

    [$tamperedRaw,$tamperedSig]=p57Signed(
        $productSecret,$a,$customer,$product,'PRODUCT','ACKNOWLEDGE',$now,
        ['draft_sha256'=>str_repeat('a',64)]
    );
    p57Check($review->submit($tamperedRaw,$tamperedSig,$now)['reason']
        ==='REVIEW_DRAFT_MISSING_CHANGED_OR_CROSS_TENANT',
        'draft_hash_bound_to_signed_vote');
    p57Check($review->submit($secRaw,'AAAA',$now)['decision']==='DENY',
        'malformed_signature_denied');

    $q=$db->prepare(<<<'SQL'
SELECT signature_b64 FROM aia_p57_lab.signed_observations
WHERE tenant_ref=:tenant AND proposal_ref=:proposal AND reviewer_role='SECURITY'
SQL);
    $q->execute([':tenant'=>$a,':proposal'=>$lead['proposal_ref']]);
    $saved=$q->fetchColumn();
    p57Check(is_string($saved),'saved_signature_fixture');
    $change=$db->prepare(<<<'SQL'
UPDATE aia_p57_lab.signed_observations SET signature_b64=:sig
WHERE tenant_ref=:tenant AND proposal_ref=:proposal AND reviewer_role='SECURITY'
SQL);
    $change->execute([
        ':sig'=>str_repeat('A',88),
        ':tenant'=>$a,':proposal'=>$lead['proposal_ref']
    ]);
    p57Check($review->status($a,$lead['proposal_ref'],$now)['reason']
        ==='HISTORICAL_REVIEW_TAMPERED_EXPIRED_OR_REVOKED',
        'tampered_stored_signature_detected');
    $change->execute([
        ':sig'=>$saved,
        ':tenant'=>$a,':proposal'=>$lead['proposal_ref']
    ]);
    p57Check($review->status($a,$lead['proposal_ref'],$now)['decision']
        ==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION',
        'signature_restore_restores_review_integrity');

    [$rejectRaw,$rejectSig]=p57Signed(
        $productSecret,$a,$customer,$product,'PRODUCT','REJECT',$now
    );
    p57Check($review->submit($rejectRaw,$rejectSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED','reject_recorded');
    p57Check($review->status($a,$customer['proposal_ref'],$now)['decision']
        ==='LAB_REVIEW_REJECTED_NO_ACTION','reject_never_approves');
    [$secCustomerRaw,$secCustomerSig]=p57Signed(
        $secSecret,$a,$customer,$security,'SECURITY','ACKNOWLEDGE',$now
    );
    p57Check($review->submit($secCustomerRaw,$secCustomerSig,$now)['reason']
        ==='REVIEW_SEPARATION_OR_REJECTION_DENIED',
        'reject_blocks_further_acknowledgments');

    p57Check($review->revokeReviewer($a,$security),
        'revoke_security_after_two_observations');
    $rejected=$review->status($a,$lead['proposal_ref'],$now);
    p57Check($rejected['reason']
        ==='HISTORICAL_REVIEW_TAMPERED_EXPIRED_OR_REVOKED',
        'revocation_invalidates_readiness');
    p57NoAction($rejected,'revoked_status');
    p57Check($review->status($a,$customer['proposal_ref'],$now+61)
        ['decision']==='DENY','signed_observation_expires');
    p57Check($review->submit($productRaw,$productSig,$now+61)['decision']
        ==='DENY','expired_replay_not_reauthorized');

    $rows=(int)$db->query(
        'SELECT COUNT(*) FROM aia_p57_lab.signed_observations'
    )->fetchColumn();
    p57Check($rows===3,'only_three_signed_observations_stored');
    echo "AIA_P57_SIGNED_REVIEW_LAB_PASS checks={$checks} roles=2 approvals=0 signed_votes=3 tamper_deny=1 revoke_deny=1 zero_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
