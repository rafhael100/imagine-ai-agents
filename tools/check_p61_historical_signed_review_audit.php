<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgHistoricalSignedReviewAuditLab.php';
require_once __DIR__.'/labs/PgSignedDraftReviewLab.php';
require_once __DIR__.'/labs/PgDraftAuthorProvenanceLab.php';
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/P18Fixture.php';

use ImagineAI\Convergence\PgHistoricalSignedReviewAuditLab;
use ImagineAI\Convergence\PgSignedDraftReviewLab;
use ImagineAI\Convergence\PgDraftAuthorProvenanceLab;
use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\P18Fixture;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('AIA_P57_REVIEW_LAB')!=='true'
    ||getenv('AIA_P59_AUTHOR_LAB')!=='true'
    ||getenv('AIA_P61_HISTORY_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'){
    throw new RuntimeException('P61_DISPOSABLE_CI_ONLY');
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
$authors=new PgDraftAuthorProvenanceLab($db);
$authors->createTables();
$db->exec(<<<'SQL'
TRUNCATE TABLE aia_p57_lab.signed_observations,aia_p57_lab.reviewers,
aia_p59_lab.draft_authors,aia_p51_lab.review_drafts,aia_p51_lab.preview_batches
SQL);
$audit=new PgHistoricalSignedReviewAuditLab($db);
$checks=0;
function p61Check(bool $ok,string $name):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('AIA_P61_FAIL_'.$name);
}
function p61Contains(array $r,string $reason):bool {
    return in_array($reason,array_column($r['issues'],'reason'),true);
}
function p61Command(string $tenant,string $req):array {
    return [
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1',
        'operation'=>'funnel.calculate',
        'tenant_ref'=>$tenant,'request_ref'=>$req,
        'weekly_visits'=>120,'lead_rate_bp'=>1000,'client_rate_bp'=>2000
    ];
}
function p61Rules(string $tenant):array {
    return [
        'schema'=>'AIA_RULESET_V1',
        'tenant_ref'=>$tenant,
        'ruleset_ref'=>'SYNTHETIC_P61_RULESET',
        'revision'=>1,
        'rules'=>[[
            'rule_ref'=>'SYNTHETIC_P61_RULE',
            'metric'=>'projected_leads',
            'operator'=>'gte',
            'threshold'=>12,
            'draft_kind'=>'HUMAN_REVIEW_DRAFT'
        ]]
    ];
}
function p61Draft(PDO $db,string $tenant):array {
    $q=$db->prepare(<<<'SQL'
SELECT proposal_ref,draft_sha256 FROM aia_p51_lab.review_drafts
WHERE tenant_ref=:tenant
SQL);
    $q->execute([':tenant'=>$tenant]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r))throw new RuntimeException('P61_MISSING_DRAFT');
    return $r;
}
function p61Vote(string $secret,string $tenant,array $draft,
    string $reviewer,string $role,int $now):array{
    $raw=json_encode([
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
    ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    return [$raw,base64_encode(sodium_crypto_sign_detached($raw,$secret))];
}
try{
    $a='SYNTHETIC_TENANT_A';$b='SYNTHETIC_TENANT_B';
    $now=P18Fixture::NOW;
    foreach([
        [$a,'SYNTHETIC_P61_REQUEST_A'],
        [$b,'SYNTHETIC_P61_REQUEST_B']
    ] as [$tenant,$req]){
        p61Check($registry->stage(p61Command($tenant,$req),p61Rules($tenant))
            ['decision']==='LAB_DRAFTS_STAGED_NO_ACTION',
            'staged_'.$tenant);
    }
    $draftA=p61Draft($db,$a);
    $draftB=p61Draft($db,$b);
    $authorA='SYNTHETIC_P61_AUTHOR_A';
    $authorB='SYNTHETIC_P61_AUTHOR_B';
    p61Check($authors->seal($a,$draftA['proposal_ref'],$authorA)
        ==='LAB_AUTHOR_SEALED_NO_APPROVAL','seal_A');
    p61Check($authors->seal($b,$draftB['proposal_ref'],$authorB)
        ==='LAB_AUTHOR_SEALED_NO_APPROVAL','seal_B');
    $product='SYNTHETIC_P61_REVIEWER_PRODUCT';
    $security='SYNTHETIC_P61_REVIEWER_SECURITY';
    $productPair=sodium_crypto_sign_keypair();
    $productSecret=sodium_crypto_sign_secretkey($productPair);
    $securityPair=sodium_crypto_sign_keypair();
    $securitySecret=sodium_crypto_sign_secretkey($securityPair);
    $productPub=sodium_crypto_sign_publickey($productPair);
    p61Check($review->registerReviewer($a,$product,'PRODUCT',$productPub),
        'register_product_key');
    p61Check($review->registerReviewer($a,$security,'SECURITY',
        sodium_crypto_sign_publickey($securityPair)),
        'register_security_key');
    [$pRaw,$pSig]=p61Vote(
        $productSecret,$a,$draftA,$product,'PRODUCT',$now
    );
    [$sRaw,$sSig]=p61Vote(
        $securitySecret,$a,$draftA,$security,'SECURITY',$now
    );
    p61Check($review->submit($pRaw,$pSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'product_vote_stored');
    p61Check($review->submit($sRaw,$sSig,$now)['decision']
        ==='LAB_SIGNED_REVIEW_OBSERVATION_STORED',
        'security_vote_stored');
    $clean=$audit->scan();
    p61Check(
        $clean['decision']==='LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO'
        &&$clean['checked_drafts']===2
        &&$clean['checked_signed_observations']===2
        &&$clean['issues']===[],
        'two_tenants_one_signed_two_roles_plus_unsigned_synthetic_B_clean'
    );
    p61Check($clean['ai_calls']===0
        &&$clean['service_effects']===0
        &&$clean['repaired_rows']===0
        &&$clean['operational_authorization']===false,
        'historical_snapshot_never_changes_authority');
    p61Check($review->revokeReviewer($a,$security),
        'revoke_original_security_key_after_signed_vote');
    p61Check($audit->scan()['decision']
        ==='LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO',
        'historical_signature_still_verifies_after_revocation');

    $saved=$db->query(<<<'SQL'
SELECT signature_b64 FROM aia_p57_lab.signed_observations
WHERE reviewer_role='SECURITY'
SQL)->fetchColumn();
    p61Check(is_string($saved),'security_signature_fixture');
    $db->exec("UPDATE aia_p57_lab.signed_observations
      SET signature_b64='".str_repeat('A',88)."'
      WHERE reviewer_role='SECURITY'");
    $r=$audit->scan();
    p61Check($r['decision']==='DENY_INCONSISTENT'
        &&p61Contains($r,'SIGNED_REVIEW_HISTORICAL_SIGNATURE_INVALID'),
        'tampered_signature_flagged_readonly');
    $restore=$db->prepare(<<<'SQL'
UPDATE aia_p57_lab.signed_observations
SET signature_b64=:sig WHERE reviewer_role='SECURITY'
SQL);
    $restore->execute([':sig'=>$saved]);
    p61Check($audit->scan()['decision']
        ==='LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO',
        'signature_restore_reverifies');

    $db->exec("UPDATE aia_p59_lab.draft_authors
      SET author_ref='{$product}' WHERE tenant_ref='{$a}'");
    $r=$audit->scan();
    p61Check(p61Contains($r,'SELF_REVIEW_OR_AUTHOR_MISSING'),
        'later_author_switch_to_reviewer_detected');
    $db->exec("UPDATE aia_p59_lab.draft_authors
      SET author_ref='{$authorA}' WHERE tenant_ref='{$a}'");
    p61Check($audit->scan()['decision']
        ==='LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO',
        'author_restore_reverifies');

    $db->exec("UPDATE aia_p51_lab.review_drafts
      SET observed=31 WHERE tenant_ref='{$a}'");
    $r=$audit->scan();
    p61Check(p61Contains($r,'DRAFT_FINGERPRINT_OR_STATE_INVALID'),
        'changed_draft_metric_detected');
    $db->exec("UPDATE aia_p51_lab.review_drafts
      SET observed=12 WHERE tenant_ref='{$a}'");

    $db->exec("DELETE FROM aia_p59_lab.draft_authors WHERE tenant_ref='{$b}'");
    $r=$audit->scan();
    p61Check($r['decision']==='DENY_INCONSISTENT'
        &&p61Contains($r,'AUTHOR_PROVENANCE_MISSING_OR_CHANGED'),
        'missing_immutable_author_provenance_detected');
    p61Check($r['repaired_rows']===0,
        'auditor_does_not_seal_missing_authors');
    $serial=json_encode($r,JSON_THROW_ON_ERROR);
    p61Check(!str_contains($serial,$saved)
        &&!str_contains($serial,$pRaw)
        &&!str_contains($serial,base64_encode($productPub))
        &&!str_contains($serial,'public_key_b64'),
        'audit_report_contains_no_signatures_or_keys');

    $db->exec('DROP TABLE aia_p59_lab.draft_authors');
    p61Check($audit->scan()['decision']==='INDETERMINATE',
        'schema_missing_cannot_be_reported_as_clean');

    echo "AIA_P61_HISTORICAL_SIGNED_REVIEW_AUDIT_PASS checks={$checks} draft_integrity=1 revoked_historical_key=verified missing_author=detected self_review=detected readonly=true NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
