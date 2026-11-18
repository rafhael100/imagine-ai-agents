<?php
declare(strict_types=1);
/** P63 disposable fixture/probe. No production route, identity or writer. */
require_once __DIR__.'/labs/PgDeterministicDraftRegistryLab.php';
require_once __DIR__.'/labs/PgDraftAuthorProvenanceLab.php';
require_once __DIR__.'/labs/PgSignedReviewQueueBridgeLab.php';

use ImagineAI\Automation\DeterministicRuleEngine;
use ImagineAI\Convergence\PgDeterministicDraftRegistryLab;
use ImagineAI\Convergence\PgDraftAuthorProvenanceLab;
use ImagineAI\Convergence\PgDraftFingerprintLab;
use ImagineAI\Convergence\PgSignedReviewQueueBridgeLab;

const P63_TENANT='SYNTHETIC_TENANT_A';
const P63_REQUEST='SYNTHETIC_P63_COMPARE';
const P63_JSON=JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
function p63Must(bool $value,string $code):void
{
    if(!$value)throw new RuntimeException('P63_PROBE_DENIED_'.$code);
}
function p63Db(string $scope='a'):PDO
{
    p63Must(in_array($scope,['admin','a','b'],true),'DB_SCOPE');
    $password=trim((string)file_get_contents($scope==='admin'
        ?'/run/secrets/p25-password':'/run/p62/reader-'.$scope));
    return new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        $scope==='admin'?'postgres':'aia_p55_ci_read_'.$scope,$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
}
function p63Inputs():array
{
    $command=[
        'schema'=>'AIA_DETERMINISTIC_COMMAND_V1','operation'=>'funnel.calculate',
        'tenant_ref'=>P63_TENANT,'request_ref'=>P63_REQUEST,
        'weekly_visits'=>120,'lead_rate_bp'=>1000,'client_rate_bp'=>2000,
    ];
    $rules=[];
    foreach(['gte','lte','eq'] as $op){
        $rules[]=[
            'rule_ref'=>'SYNTHETIC_P63_'.strtoupper($op),'metric'=>'projected_leads',
            'operator'=>$op,'threshold'=>12,'draft_kind'=>'HUMAN_REVIEW_DRAFT',
        ];
    }
    return [$command,[
        'schema'=>'AIA_RULESET_V1','tenant_ref'=>P63_TENANT,
        'ruleset_ref'=>'SYNTHETIC_P63_RULESET','revision'=>1,'rules'=>$rules,
    ]];
}
function p63RowsDigest(PDO $db):string
{
    $rows=[];
    foreach(['aia_p51_lab.preview_batches','aia_p51_lab.review_drafts',
        'aia_p57_lab.reviewers','aia_p57_lab.signed_observations',
        'aia_p59_lab.draft_authors'] as $table){
        $rows[$table]=$db->query("SELECT row_to_json(t)::text AS row
            FROM {$table} t ORDER BY row")->fetchAll(PDO::FETCH_COLUMN);
    }
    return hash('sha256',json_encode($rows,P63_JSON));
}
function p63Seed():array
{
    $db=p63Db('admin');
    $registry=new PgDeterministicDraftRegistryLab($db);
    $old=$db->query("SELECT command_canonical,ruleset_canonical
        FROM aia_p51_lab.preview_batches WHERE tenant_ref='SYNTHETIC_TENANT_A'
        AND request_ref='SYNTHETIC_P62_WAIT'")->fetch(PDO::FETCH_ASSOC);
    p63Must(is_array($old),'P62_SEED_REQUIRED');
    // Restore the actual pre-P63 column shape, retaining P51/P59/P57 rows.
    // Upgrade must preserve them as legacy, not fabricate stored inputs.
    $db->exec('ALTER TABLE aia_p51_lab.preview_batches
        DROP COLUMN command_canonical,DROP COLUMN ruleset_canonical,
        DROP COLUMN inputs_version');
    $registry->createTables();
    p63Must($db->query('SELECT count(*) FROM aia_p51_lab.preview_batches
        WHERE inputs_version=0 AND command_canonical IS NULL
        AND ruleset_canonical IS NULL')->fetchColumn()===5,'LEGACY_SCHEMA_UPGRADED');
    $before=p63RowsDigest($db);
    $replay=$registry->stage(json_decode($old['command_canonical'],true,12,JSON_THROW_ON_ERROR),
        json_decode($old['ruleset_canonical'],true,12,JSON_THROW_ON_ERROR));
    p63Must($replay['decision']==='LAB_DRAFTS_ALREADY_STAGED'
        &&p63RowsDigest($db)===$before,'LEGACY_REPLAY_NO_BACKFILL');
    [$command,$ruleset]=p63Inputs();
    $stage=$registry->stage($command,$ruleset);
    p63Must($stage['decision']==='LAB_DRAFTS_STAGED_NO_ACTION'
        &&$stage['proposal_count']===3,'THREE_CONDITIONS_STAGED');
    $authors=new PgDraftAuthorProvenanceLab($db);
    $proposals=[];
    foreach((new DeterministicRuleEngine())->evaluate($command,$ruleset)['proposals'] as $p){
        $proposals[$p['operator']]=$p['proposal_ref'];
        p63Must($authors->seal(P63_TENANT,$p['proposal_ref'],'SYNTHETIC_P62_AUTHOR_A')
            ==='LAB_AUTHOR_SEALED_NO_APPROVAL','AUTHOR_SEALED');
    }
    p63Must(count(array_unique($proposals))===3,'DIFFERENT_PROPOSAL_IDENTITIES');
    $before=p63RowsDigest($db);
    p63Must($registry->stage($command,$ruleset)['decision']==='LAB_DRAFTS_ALREADY_STAGED'
        &&p63RowsDigest($db)===$before,'CAPTURED_REPLAY_UNCHANGED');
    $zeroCommand=$command;$zeroCommand['request_ref']='SYNTHETIC_P63_ZERO';
    $zeroRules=$ruleset;$zeroRules['rules']=[$ruleset['rules'][0]];
    $zeroRules['rules'][0]['threshold']=13;
    $zero=$registry->stage($zeroCommand,$zeroRules);
    p63Must($zero['decision']==='LAB_DRAFTS_STAGED_NO_ACTION'
        &&$zero['proposal_count']===0
        &&$db->query("SELECT count(*) FROM aia_p51_lab.preview_batches
            WHERE request_ref='SYNTHETIC_P63_ZERO' AND inputs_version=1
              AND command_canonical IS NOT NULL AND ruleset_canonical IS NOT NULL")
            ->fetchColumn()===1,'ZERO_PROPOSALS_STILL_CAPTURE_INPUTS');
    // Fail on the second row to prove canonical batch + all drafts roll back.
    $db->exec(<<<'SQL'
CREATE FUNCTION aia_p51_lab.p63_fail_fixture() RETURNS trigger
 LANGUAGE plpgsql AS $f$ BEGIN
 IF NEW.request_ref='SYNTHETIC_P63_ROLLBACK' AND NEW.rule_ref='SYNTHETIC_P63_LTE'
 THEN RAISE EXCEPTION 'P63_SYNTHETIC_WRITE_FAILURE'; END IF;
 RETURN NEW; END; $f$;
CREATE TRIGGER p63_fail_fixture BEFORE INSERT ON aia_p51_lab.review_drafts
 FOR EACH ROW EXECUTE FUNCTION aia_p51_lab.p63_fail_fixture();
SQL);
    $failedCommand=$command;$failedCommand['request_ref']='SYNTHETIC_P63_ROLLBACK';
    $failed=$registry->stage($failedCommand,$ruleset);
    $db->exec('DROP TRIGGER p63_fail_fixture ON aia_p51_lab.review_drafts;
        DROP FUNCTION aia_p51_lab.p63_fail_fixture()');
    p63Must($failed['decision']==='DENY'
        &&$db->query("SELECT count(*) FROM aia_p51_lab.preview_batches
            WHERE request_ref='SYNTHETIC_P63_ROLLBACK'")->fetchColumn()===0
        &&$db->query("SELECT count(*) FROM aia_p51_lab.review_drafts
            WHERE request_ref='SYNTHETIC_P63_ROLLBACK'")->fetchColumn()===0,
        'INPUTS_AND_DRAFTS_ATOMIC');
    return ['schema'=>'P63_SEEDED_V1','proposals'=>$proposals,
        'upgraded_legacy_batches'=>5,'legacy_replay_no_backfill'=>true,
        'captured_replay_no_change'=>true,'zero_proposals_inputs_preserved'=>true,
        'failed_write_left_no_batch_or_drafts'=>true];
}
function p63View(string $proposal,string $scope='a',?PDO $db=null):array
{
    $tenant=$scope==='b'?'SYNTHETIC_TENANT_B':P63_TENANT;
    return (new PgSignedReviewQueueBridgeLab($db??p63Db($scope)))->read($tenant,$proposal);
}
function p63Checks(array $input):array
{
    $admin=p63Db('admin');$before=p63RowsDigest($admin);
    foreach(['gte','lte','eq'] as $op){
        // Each call opens a fresh reader connection after the seed committed.
        $view=p63View($input['proposals'][$op]);
        $detail=$view['detail']??[];
        p63Must($view['decision']==='LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
            &&($detail['state']??null)==='VERIFIED'
            &&$detail['explanation']['rule']['operator']===$op
            &&$detail['explanation']['observed']===12
            &&$detail['explanation']['rule']['threshold']===12
            &&$detail['explanation']['matched_rules']===3
            &&$detail['explanation']['inputs']===[
                'weekly_visits'=>120,'lead_rate_bp'=>1000,'client_rate_bp'=>2000,
            ]&&$detail['author_provenance_verified']===true
            &&$view['can_approve']===false&&$view['can_execute']===false,
            'FRESH_CONNECTION_EXACT_CONDITION');
    }
    $legacy=p63View($input['legacy']);
    p63Must(($legacy['detail']['state']??null)==='ORIGINAL_INPUTS_UNAVAILABLE'
        &&$legacy['detail']['explanation']===null
        &&$legacy['detail']['inputs_available']===false,'LEGACY_NOT_RECONSTRUCTED');
    $foreign=p63View($input['foreign']);
    $missing=p63View('SYNTHETIC_PROPOSAL_'.str_repeat('0',24));
    p63Must($foreign===$missing&&$foreign['decision']==='DENY','UNIFORM_FOREIGN_OR_MISSING');
    p63Must((p63View($input['foreign'],'b')['detail']['state']??null)
        ==='ORIGINAL_INPUTS_UNAVAILABLE','TENANT_B_OWN_LEGACY');
    foreach([
        'UPDATE aia_p51_lab.preview_batches SET command_canonical=NULL
            WHERE inputs_version=1',
        "UPDATE aia_p51_lab.preview_batches SET ruleset_canonical=repeat('x',8193)
            WHERE inputs_version=1",
    ] as $sql){
        try{$admin->exec($sql);throw new RuntimeException('P63_PROBE_DENIED_SQL_BOUND_ALLOWED');}
        catch(PDOException $e){p63Must($e->getCode()==='23514','SQL_BOUND_CHECK');}
    }
    p63Must(p63RowsDigest($admin)===$before,'READS_AND_REJECTED_MUTATIONS_UNCHANGED');
    return ['schema'=>'P63_DOMAIN_VERIFIED_V1','operators'=>['gte','lte','eq'],
        'fresh_connection_reads'=>3,'legacy_not_reconstructed'=>true,
        'uniform_tenant_isolation'=>true,'bounded_paired_storage'=>true,
        'read_only_rows_preserved'=>true];
}
function p63Tamper(PDO $db,bool $restore=false):void
{
    [$command]=p63Inputs();
    if(!$restore)$command['weekly_visits']=121;
    $q=$db->prepare('UPDATE aia_p51_lab.preview_batches SET command_canonical=?
        WHERE tenant_ref=? AND request_ref=?');
    $q->execute([json_encode($command,P63_JSON),P63_TENANT,P63_REQUEST]);
    p63Must($q->rowCount()===1,'TAMPER_FIXTURE_ROW');
}
function p63Sibling(PDO $db,bool $restore=false):void
{
    $q=$db->query("SELECT * FROM aia_p51_lab.review_drafts
        WHERE tenant_ref='SYNTHETIC_TENANT_A' AND request_ref='SYNTHETIC_P63_COMPARE'
          AND rule_ref='SYNTHETIC_P63_LTE'");
    $row=$q->fetch(PDO::FETCH_ASSOC);p63Must(is_array($row),'SIBLING_FIXTURE');
    $threshold=$restore?12:13;
    $hash=PgDraftFingerprintLab::sha256(P63_TENANT,$row['request_ref'],$row['ruleset_ref'],
        (int)$row['revision'],$row['proposal_ref'],$row['rule_ref'],$row['draft_kind'],
        $row['metric'],(int)$row['observed'],$threshold,$row['state']);
    $db->beginTransaction();
    $q=$db->prepare('UPDATE aia_p51_lab.review_drafts SET threshold=?,draft_sha256=?
        WHERE tenant_ref=? AND proposal_ref=?');
    $q->execute([$threshold,$hash,P63_TENANT,$row['proposal_ref']]);
    $q=$db->prepare('UPDATE aia_p59_lab.draft_authors SET draft_sha256=?
        WHERE tenant_ref=? AND proposal_ref=?');
    $q->execute([$hash,P63_TENANT,$row['proposal_ref']]);$db->commit();
}
final class P63ConcurrentFixturePDO extends PDO
{
    public bool $injected=false;
    public function __construct(private PDO $writer)
    {
        parent::__construct('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
            'aia_p55_ci_read_a',trim((string)file_get_contents('/run/p62/reader-a')),[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        if(!$this->injected&&str_contains($query,'P63_DETAIL_INPUTS')){
            p63Must($this->inTransaction(),'CONCURRENT_SNAPSHOT_ACTIVE');
            $this->injected=true;p63Tamper($this->writer);
        }
        return parent::prepare($query,$options);
    }
}
function p63Concurrent(string $proposal):array
{
    $admin=p63Db('admin');$before=p63RowsDigest($admin);
    $old=p63View($proposal);
    $reader=new P63ConcurrentFixturePDO($admin);
    try{
        $during=p63View($proposal,'a',$reader);
        p63Must($reader->injected&&($old['detail']['state']??null)==='VERIFIED'
            &&($during['detail']??null)===$old['detail']
            &&($during['queue']??null)===$old['queue'],'DETAIL_KEEPS_WHOLE_OLD_SNAPSHOT');
        p63Must(p63View($proposal)['decision']==='DENY','NEXT_SNAPSHOT_SEES_CHANGED_INPUTS');
    }finally{p63Tamper($admin,true);}
    p63Must(p63RowsDigest($admin)===$before,'CONCURRENT_FIXTURE_RESTORED');
    return ['schema'=>'P63_SNAPSHOT_VERIFIED_V1',
        'whole_old_snapshot_preserved'=>true,'next_snapshot_denied'=>true];
}

try{
    p63Must(PHP_SAPI==='cli'&&getenv('GITHUB_ACTIONS')==='true'
        &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
        &&getenv('AIA_P63_PROPOSAL_DETAIL_LAB')==='true'
        &&getenv('DB_HOST')==='127.0.0.1'
        &&getenv('DB_DATABASE')==='imagine_agents_ci','CI_ONLY');
    $raw=file_get_contents('php://stdin');
    p63Must(is_string($raw)&&strlen($raw)<=8192,'INPUT_BOUND');
    $input=json_decode($raw===''?'{}':$raw,true,8,JSON_THROW_ON_ERROR);
    p63Must(is_array($input),'INPUT_OBJECT');
    $mode=$argv[1]??'';
    if($mode==='seed')$output=p63Seed();
    elseif($mode==='checks')$output=p63Checks($input);
    elseif($mode==='view')$output=p63View($input['proposal']??'',$input['scope']??'a');
    elseif($mode==='concurrent')$output=p63Concurrent($input['proposal']??'');
    elseif(in_array($mode,['tamper','restore','sibling','restore-sibling'],true)){
        $admin=p63Db('admin');
        if(in_array($mode,['tamper','restore'],true))p63Tamper($admin,$mode==='restore');
        else p63Sibling($admin,$mode==='restore-sibling');
        if($mode==='tamper'){
            $before=p63RowsDigest($admin);[$command,$ruleset]=p63Inputs();
            $replay=(new PgDeterministicDraftRegistryLab($admin))->stage($command,$ruleset);
            p63Must($replay['decision']==='DENY'
                &&$replay['reason']==='STORED_CANONICAL_INPUTS_CHANGED'
                &&p63RowsDigest($admin)===$before,'REPLAY_CHANGED_INPUTS_NO_REPAIR');
        }
        $output=['schema'=>'P63_FIXTURE_CHANGED_V1','fixture_only'=>true];
    }else throw new RuntimeException('P63_PROBE_DENIED_MODE');
    echo json_encode($output,P63_JSON)."\n";
}catch(Throwable $error){
    $code=preg_match('/\AP63_PROBE_DENIED_[A-Z0-9_]+\z/D',$error->getMessage())
        ?$error->getMessage():'P63_PROBE_DENIED_UNEXPECTED';
    fwrite(STDERR,$code."\n");exit(1);
}
