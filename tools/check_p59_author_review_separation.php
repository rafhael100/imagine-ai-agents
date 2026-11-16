<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgDraftAuthorProvenanceLab.php';
require_once __DIR__.'/labs/PgSignedDraftReviewLab.php';
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgDraftAuthorProvenanceLab;
use ImagineAI\Convergence\PgAuthorBoundSignedReviewLab;
use ImagineAI\Convergence\PgSignedDraftReviewLab;
use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\P18Fixture;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('AIA_P57_REVIEW_LAB')!=='true'
    ||getenv('AIA_P59_AUTHOR_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P59_DISPOSABLE_CI_ONLY');
}
$db=new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);
$registry=new PgDeterministicDraftRegistryLab($db);
$registry->createTables();
$review=new PgSignedDraftReviewLab($db);
$review->createTables();
$provenance=new PgDraftAuthorProvenanceLab($db);
$provenance->createTables();
$bound=new PgAuthorBoundSignedReviewLab($db);

$checks=0;
function p59Check(bool $ok,string $name):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('AIA_P59_FAIL_'.$name);
}
function p59Stage(PgDeterministicDraftRegistryLab $registry,
    string $tenant,string $request):void{
    $command=[
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,'request_ref'=>$request,
        'weekly_visits'=>120,'lead_rate_bp'=>1000,'client_rate_bp'=>2000
    ];
    $rules=[
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P59_RULESET',
        'revision'=>1,
        'rules'=>[[
            'rule_ref'=>'SYNTHETIC_P59_RULE',
            'metric'=>'projected_leads',
            'operator'=>'gte',
            'threshold'=>12,
            'draft_kind'=>'HUMAN_REVIEW_DRAFT'
        ]]
    ];
    $r=$registry->stage($command,$rules);
    p59Check($r['decision']==='LAB_DRAFTS_STAGED_NO_ACTION'
        &&$r['proposal_count']===1,'stage_'.$request);
}
function p59Proposal(PDO $db,string $tenant,string $request):array{
    $q=$db->prepare(<<<'SQL'
SELECT proposal_ref,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant AND request_ref=:request
SQL);
    $q->execute([':tenant'=>$tenant,':request'=>$request]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r))throw new RuntimeException('P59_DRAFT_NOT_FOUND');
    return $r;
}
function p59Signed(string $secret,string $tenant,array $draft,
    string $reviewer,string $role,int $now):array{
    $fields=[
        'schema'=>'AIA_SIGNED_DRAFT_REVIEW_V1',
        'issuer'=>'SYNTHETIC_INDEPENDENT_REVIEWER',
        'audience'=>'imagine-ai-agents',
        'tenant_ref'=>$tenant,
        'proposal_ref'=>$draft['proposal_ref'],
        'reviewer_ref'=>$reviewer,
        'role'=>$role,
        'decision'=>'ACKNOWLEDGE',
        'draft_sha256'=>$draft['draft_sha256'],
        'key_revision'=>1,
        'issued_at'=>$now-5,
        'expires_at'=>$now+60
    ];
    $raw=json_encode($fields,JSON_THROW_ON_ERROR
        |JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
try{
    $tenant='SYNTHETIC_TENANT_D';
    $now=P18Fixture::NOW;
    $reqA='SYNTHETIC_P59_REQUEST_A';
    $reqB='SYNTHETIC_P59_REQUEST_B';
    p59Stage($registry,$tenant,$reqA);
    p59Stage($registry,$tenant,$reqB);
    $draftA=p59Proposal($db,$tenant,$reqA);
    $draftB=p59Proposal($db,$tenant,$reqB);
    $self='SYNTHETIC_P59_AUTHOR_PRODUCT';
    $product='SYNTHETIC_P59_PRODUCT_INDEPENDENT';
    $security='SYNTHETIC_P59_SECURITY_INDEPENDENT';
    $selfPair=sodium_crypto_sign_keypair();
    $pPair=sodium_crypto_sign_keypair();
    $sPair=sodium_crypto_sign_keypair();
    $selfSecret=sodium_crypto_sign_secretkey($selfPair);
    $pSecret=sodium_crypto_sign_secretkey($pPair);
    $sSecret=sodium_crypto_sign_secretkey($sPair);
    p59Check($review->registerReviewer($tenant,$self,'PRODUCT',
        sodium_crypto_sign_publickey($selfPair)),
        'self_reviewer_registered_but_not_authorized');
    p59Check($review->registerReviewer($tenant,$product,'PRODUCT',
        sodium_crypto_sign_publickey($pPair)),'product_independent');
    p59Check($review->registerReviewer($tenant,$security,'SECURITY',
        sodium_crypto_sign_publickey($sPair)),'security_independent');

    p59Check($provenance->seal($tenant,$draftA['proposal_ref'],$self)
        ==='LAB_AUTHOR_SEALED_NO_APPROVAL','author_sealed_to_A');
    p59Check($provenance->seal($tenant,$draftA['proposal_ref'],$self)
        ==='LAB_AUTHOR_SEAL_REPLAY_NO_ACTION','immutable_author_replay');
    p59Check($provenance->seal($tenant,$draftA['proposal_ref'],$product)
        ==='DENY_AUTHOR_CHANGE_OR_STALE_REPLAY',
        'cannot_reassign_author_to_proposed_reviewer');
    p59Check($provenance->seal('SYNTHETIC_TENANT_B',
        $draftA['proposal_ref'],$self)
        ==='DENY_AUTHOR_DRAFT_MISSING','cannot_seal_cross_tenant');

    [$selfRaw,$selfSig]=p59Signed(
        $selfSecret,$tenant,$draftA,$self,'PRODUCT',$now
    );
    $selfVote=$bound->submit($selfRaw,$selfSig,$now);
    p59Check($selfVote['reason']==='REVIEW_AUTHOR_SEPARATION_DENIED'
        &&$selfVote['can_execute']===false,'self_review_denied_in_same_tx');
    [$goodRaw,$goodSig]=p59Signed(
        $pSecret,$tenant,$draftA,$product,'PRODUCT',$now
    );
    p59Check($bound->submit($goodRaw,$goodSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'product_independent_observation');
    [$secRaw,$secSig]=p59Signed(
        $sSecret,$tenant,$draftA,$security,'SECURITY',$now
    );
    p59Check($bound->submit($secRaw,$secSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'security_independent_observation');
    $status=$bound->status($tenant,$draftA['proposal_ref'],$now);
    p59Check($status['decision']==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'
        &&$status['observations']===2
        &&$status['can_approve']===false
        &&$status['can_execute']===false
        &&$status['operational_authorization']===false,
        'author_independent_votes_still_no_real_approval');

    [$bVoteRaw,$bVoteSig]=p59Signed(
        $pSecret,$tenant,$draftB,$product,'PRODUCT',$now
    );
    p59Check($bound->submit($bVoteRaw,$bVoteSig,$now)['reason']
        ==='REVIEW_AUTHOR_SEPARATION_DENIED',
        'unsealed_draft_cannot_get_optin_authorized_vote');
    p59Check($bound->status($tenant,$draftB['proposal_ref'],$now)['reason']
        ==='DRAFT_AUTHOR_PROVENANCE_DENIED',
        'unsealed_draft_status_denied_even_without_votes');

    p59Check($provenance->seal($tenant,$draftB['proposal_ref'],$self)
        ==='LAB_AUTHOR_SEALED_NO_APPROVAL',
        'second_draft_sealed');
    p59Check($bound->submit($bVoteRaw,$bVoteSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'same_vote_valid_after_explicit_author_seal');
    p59Check($bound->status($tenant,$draftB['proposal_ref'],$now)['decision']
        ==='LAB_PARTIAL_REVIEW_NO_ACTION','one_independent_review');

    $update=$db->prepare(<<<'SQL'
UPDATE aia_p59_lab.draft_authors SET author_ref=:author
WHERE tenant_ref=:tenant AND proposal_ref=:proposal
SQL);
    $update->execute([
        ':author'=>$product,
        ':tenant'=>$tenant,
        ':proposal'=>$draftA['proposal_ref']
    ]);
    $tampered=$bound->status($tenant,$draftA['proposal_ref'],$now);
    p59Check($tampered['reason']
        ==='HISTORICAL_REVIEW_TAMPERED_EXPIRED_OR_REVOKED'
        &&$tampered['can_execute']===false,
        'read_only_status_detects_author_changed_to_reviewer');
    $update->execute([
        ':author'=>$self,
        ':tenant'=>$tenant,
        ':proposal'=>$draftA['proposal_ref']
    ]);
    p59Check($bound->status($tenant,$draftA['proposal_ref'],$now)['decision']
        ==='LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION',
        'restored_author_separation_revalidated');

    $db->exec("UPDATE aia_p59_lab.draft_authors
        SET draft_sha256='".str_repeat('a',64)."'
        WHERE proposal_ref='".$draftB['proposal_ref']."'");
    p59Check($bound->status($tenant,$draftB['proposal_ref'],$now)['reason']
        ==='DRAFT_AUTHOR_PROVENANCE_DENIED',
        'author_binding_digest_tamper_fails_closed');
    p59Check(PgAuthorBoundSignedReviewLab::operationalReadiness()['decision']
        ==='NO_GO','no_authenticated_CRM_author');
    echo "AIA_P59_AUTHOR_REVIEW_SEPARATION_PASS checks={$checks} same_author_vote=DENY two_independent=1 cross_tenant=DENY signed_author_real=false no_ai=1 NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
