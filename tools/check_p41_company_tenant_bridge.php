<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticCompanyTenantBridgeLab.php';

use ImagineAI\Convergence\PgSyntheticCompanyTenantBridgeLab;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('AIA_P16_PG_LAB')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci')
    throw new RuntimeException('P41_DISPOSABLE_CI_ONLY');

$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$bridge=new PgSyntheticCompanyTenantBridgeLab($db);
$bridge->createTable();
$db->exec('TRUNCATE TABLE aia_p41_lab.company_tenant_links');
$checks=0;
function p41Check(bool $ok,string $reason):void{
    global $checks;$checks++;
    if(!$ok)throw new RuntimeException('P41_FAIL_'.$reason);
}
function p41Decision(PgSyntheticCompanyTenantBridgeLab $bridge,
    string $company,string $tenant,array $proof,int $revision,int $now):array{
    $r=$bridge->inspect(
        $company,$tenant,$proof['request_id'],$proof['nonce'],
        $proof['request_digest'],$revision,$now
    );
    p41Check($r['ai_calls']===0 &&$r['service_effects']===0,
        'no_external_calls');
    return $r;
}
$a='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$b='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$tenA='SYNTHETIC_TENANT_A';
$tenB='SYNTHETIC_TENANT_B';
$now=time();

try{
    $ledger=$db->query(<<<'SQL'
SELECT request_id,nonce,request_digest,issued_at
 FROM central_auto_lab.mtls_nonce_ledger
 WHERE company_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'::uuid
   AND issuer='portal-automation-admin'
   AND audience='crm-automation-state'
 ORDER BY issued_at DESC LIMIT 1
SQL)->fetch(PDO::FETCH_ASSOC);
    p41Check(is_array($ledger),'real_p7_mtls_fixture_ledger_available');
    p41Check((int)$ledger['issued_at']>=$now-120,'fresh_mtls_test_admission');

    p41Check($bridge->seedLabBinding($a,$tenA),'mapped_A');
    p41Check($bridge->seedLabBinding($b,$tenB),'mapped_B_fixture');
    p41Check(!$bridge->seedLabBinding($a,$tenB),
        'cannot_remap_existing_company');
    p41Check(!$bridge->seedLabBinding('bad-company',$tenA),'malformed_company_denied');
    p41Check($bridge->snapshot($a)===[
        'active'=>true,'tenant_ref'=>$tenA,'revision'=>1
    ],'initial_mapping_A');

    p41Check(p41Decision($bridge,$a,$tenA,$ledger,1,$now)
        ['decision']==='LAB_MATCHED_BOUNDARY_NO_EXECUTION',
        'admitted_mtls_company_matches_tenant');
    p41Check(p41Decision($bridge,$a,$tenB,$ledger,1,$now)
        ['decision']==='DENY','cross_tenant_remap_denied');
    p41Check(p41Decision($bridge,$b,$tenB,$ledger,1,$now)
        ['decision']==='DENY','unadmitted_company_B_denied');

    $wrong=$ledger;
    $wrong['request_digest']=str_repeat('a',64);
    p41Check(p41Decision($bridge,$a,$tenA,$wrong,1,$now)
        ['decision']==='DENY','forged_mtls_digest_denied');
    $wrong=$ledger;
    $wrong['nonce']='SYNTHETIC_UNKNOWN_NONCE';
    p41Check(p41Decision($bridge,$a,$tenA,$wrong,1,$now)
        ['decision']==='DENY','unknown_mtls_nonce_denied');
    p41Check(p41Decision($bridge,$a,$tenA,$ledger,2,$now)
        ['decision']==='DENY','wrong_revision_denied');
    p41Check(p41Decision($bridge,$a,$tenA,$ledger,1,$now+121)
        ['decision']==='DENY','stale_mtls_record_denied');

    // A separate transaction holding FOR SHARE prevents revocation from
    // completing until the preflight transaction ends. This is not a grant
    // to act after inspect() returns; a future P42 must bind to stage atomically.
    $db->beginTransaction();
    $hold=$db->prepare('SELECT revision FROM aia_p41_lab.company_tenant_links
        WHERE company_id=CAST(:id AS uuid) FOR SHARE');
    $hold->execute([':id'=>$a]);
    p41Check((int)$hold->fetchColumn()===1,'company_mapping_share_lock');
    $db2=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db2->exec('SET statement_timeout=250');
    $other=new PgSyntheticCompanyTenantBridgeLab($db2);
    p41Check($other->revoke($a)===null,'mapping_revocation_waits_for_lock');
    $db->commit();
    $db2->exec('SET statement_timeout=0');
    p41Check($other->revoke($a)===2,'mapping_revokes_after_commit');
    p41Check($bridge->snapshot($a)===[
        'active'=>false,'tenant_ref'=>$tenA,'revision'=>2
    ],'revocation_persists');
    p41Check(p41Decision($bridge,$a,$tenA,$ledger,1,$now)
        ['decision']==='DENY','revoked_mapping_rejects_previous_proof');
    p41Check($bridge->seedLabBinding($a,$tenA)===false,
        'revoked_link_cannot_be_recreated_with_seed');

    echo "AIA_P41_COMPANY_TENANT_BRIDGE_PASS checks={$checks} real_loopback_ledger=1 mapping_revision=1 zero_ai=1 atomic_stage=false NO_GO\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
