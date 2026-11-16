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
    ||getenv('AIA_P58_REJECTION_RACE_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P58_DISPOSABLE_CI_ONLY');
}
$dsn='pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci';
$db=new PDO($dsn,'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$otherDb=new PDO($dsn,'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$drafts=new PgDeterministicDraftRegistryLab($db);
$drafts->createTables();
$review=new PgSignedDraftReviewLab($db);
$review->createTables();
$otherReview=new PgSignedDraftReviewLab($otherDb);
$checks=0;
function p58Check(bool $yes,string $label):void {
    global $checks;$checks++;
    if(!$yes)throw new RuntimeException('P58_FAIL_'.$label);
}
function p58Stage(PgDeterministicDraftRegistryLab $registry,
    string $tenant,string $request):void{
    $command=[
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,
        'request_ref'=>$request,
        'weekly_visits'=>120,
        'lead_rate_bp'=>1000,
        'client_rate_bp'=>2000
    ];
    $rules=[
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P58_RULESET',
        'revision'=>1,
        'rules'=>[
            [
                'rule_ref'=>'SYNTHETIC_P58_RULE_LEAD',
                'metric'=>'projected_leads',
                'operator'=>'gte',
                'threshold'=>12,
                'draft_kind'=>'HUMAN_REVIEW_DRAFT'
            ]
        ]
    ];
    $r=$registry->stage($command,$rules);
    p58Check($r['decision']==='LAB_DRAFTS_STAGED_NO_ACTION'
        &&$r['proposal_count']===1,'staged_'.$request);
}
function p58Draft(PDO $db,string $tenant,string $request):array{
    $q=$db->prepare(<<<'SQL'
SELECT proposal_ref,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND request_ref=:request
SQL);
    $q->execute([':tenant'=>$tenant,':request'=>$request]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r))throw new RuntimeException('P58_MISSING_DRAFT');
    return $r;
}
function p58Vote(
    string $secret,string $tenant,array $draft,string $reviewer,
    string $role,string $decision,int $now
):array{
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
    $raw=json_encode($data,
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
function p58Hold(PDO $db,string $tenant,string $proposal):void{
    $db->beginTransaction();
    $q=$db->prepare(<<<'SQL'
SELECT draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND proposal_ref=:proposal FOR UPDATE
SQL);
    $q->execute([':tenant'=>$tenant,':proposal'=>$proposal]);
    p58Check(is_string($q->fetchColumn()),'held_lock_'.$proposal);
}
try{
    $tenant='SYNTHETIC_TENANT_C';
    $now=P18Fixture::NOW;
    $productRef='SYNTHETIC_REVIEWER_P58_PRODUCT';
    $securityRef='SYNTHETIC_REVIEWER_P58_SECURITY';
    $pkey=sodium_crypto_sign_keypair();
    $skey=sodium_crypto_sign_keypair();
    $productSecret=sodium_crypto_sign_secretkey($pkey);
    $securitySecret=sodium_crypto_sign_secretkey($skey);
    p58Check($review->registerReviewer($tenant,$productRef,'PRODUCT',
        sodium_crypto_sign_publickey($pkey)),'product_key_registered');
    p58Check($review->registerReviewer($tenant,$securityRef,'SECURITY',
        sodium_crypto_sign_publickey($skey)),'security_key_registered');

    $requestReject='SYNTHETIC_P58_REQUEST_REJECT';
    p58Stage($drafts,$tenant,$requestReject);
    $target=p58Draft($db,$tenant,$requestReject);
    [$rejectRaw,$rejectSig]=p58Vote(
        $productSecret,$tenant,$target,$productRef,
        'PRODUCT','REJECT',$now
    );
    [$ackRaw,$ackSig]=p58Vote(
        $securitySecret,$tenant,$target,$securityRef,
        'SECURITY','ACKNOWLEDGE',$now
    );
    // If an ACK races with a REJECT, the draft-level write lock
    // serializes both transactions before the votes are inspected.
    p58Hold($db,$tenant,$target['proposal_ref']);
    $otherDb->exec('SET statement_timeout=250');
    $stalled=$otherReview->submit($ackRaw,$ackSig,$now);
    p58Check($stalled['decision']==='DENY'
        &&$stalled['can_execute']===false
        &&$stalled['ai_calls']===0,
        'no_ack_insert_while_draft_locked');
    $db->commit();
    $otherDb->exec('SET statement_timeout=0');
    p58Check($review->submit($rejectRaw,$rejectSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'rejection_commits_first');
    $tooLate=$otherReview->submit($ackRaw,$ackSig,$now);
    p58Check($tooLate['reason']==='REVIEW_SEPARATION_OR_REJECTION_DENIED',
        'ack_after_rejection_cannot_commit');
    $status=$review->status($tenant,$target['proposal_ref'],$now);
    p58Check($status['decision']==='LAB_REVIEW_REJECTED_NO_ACTION'
        &&$status['observations']===1
        &&$status['can_execute']===false
        &&$status['operational_authorization']===false,
        'rejection_terminal_no_action');

    $requestAck='SYNTHETIC_P58_REQUEST_ACK';
    p58Stage($drafts,$tenant,$requestAck);
    $ok=p58Draft($db,$tenant,$requestAck);
    [$rawProduct,$sigProduct]=p58Vote(
        $productSecret,$tenant,$ok,$productRef,
        'PRODUCT','ACKNOWLEDGE',$now
    );
    [$rawSecurity,$sigSecurity]=p58Vote(
        $securitySecret,$tenant,$ok,$securityRef,
        'SECURITY','ACKNOWLEDGE',$now
    );
    p58Hold($db,$tenant,$ok['proposal_ref']);
    $otherDb->exec('SET statement_timeout=250');
    $blocked=$otherReview->submit($rawProduct,$sigProduct,$now);
    p58Check($blocked['decision']==='DENY'
        &&$blocked['service_effects']===0,
        'would_be_ack_does_not_bypass_lock');
    $db->commit();
    $otherDb->exec('SET statement_timeout=0');
    p58Check($otherReview->submit($rawProduct,$sigProduct,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'valid_ack_after_lock_released');
    p58Check($review->submit($rawSecurity,$sigSecurity,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'independent_security_ack_after_product');
    $full=$otherReview->status($tenant,$ok['proposal_ref'],$now);
    p58Check($full['decision']==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'
        &&$full['can_approve']===false
        &&$full['can_execute']===false
        &&$full['operational_authorization']===false,
        'two_votes_still_no_live_authorization');

    // A key revocation UPDATE must wait for a FOR SHARE lock held
    // by a concurrent review verification transaction.
    $db->beginTransaction();
    $lock=$db->prepare(<<<'SQL'
SELECT revision FROM aia_p57_lab.reviewers
WHERE tenant_ref=:tenant AND reviewer_ref=:reviewer FOR SHARE
SQL);
    $lock->execute([':tenant'=>$tenant,':reviewer'=>$securityRef]);
    p58Check((int)$lock->fetchColumn()===1,
        'held_reviewer_key_share_lock');
    $otherDb->exec('SET statement_timeout=250');
    p58Check(!$otherReview->revokeReviewer($tenant,$securityRef),
        'revocation_cannot_commit_while_key_is_locked');
    $db->commit();
    $otherDb->exec('SET statement_timeout=0');
    p58Check($otherReview->revokeReviewer($tenant,$securityRef),
        'revocation_succeeds_after_lock_release');
    $revoked=$review->status($tenant,$ok['proposal_ref'],$now);
    p58Check($revoked['decision']==='DENY'
        &&$revoked['can_execute']===false,
        'revoked_key_invalidates_live_review_status');
    $rows=(int)$db->query(<<<'SQL'
SELECT COUNT(*) FROM aia_p57_lab.signed_observations
WHERE tenant_ref='SYNTHETIC_TENANT_C'
SQL)->fetchColumn();
    p58Check($rows===3,'one_rejection_plus_two_ack_observations');
    echo "AIA_P58_SERIALIZED_SIGNED_REVIEW_PASS checks={$checks} reject_race=DENY concurrent_ack_fenced=1 reviewer_revocation_lock=1 human_approval=false ai_calls=0 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if($otherDb->inTransaction())$otherDb->rollBack();
}
