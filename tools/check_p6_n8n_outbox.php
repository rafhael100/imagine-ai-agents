<?php
declare(strict_types=1);
require_once __DIR__.'/../src/MockOrchestration/MockN8nOutboxContract.php';
require_once __DIR__.'/labs/PgN8nOutboxLab.php';

use ImagineAI\MockOrchestration\MockN8nOutboxContract as Envelope;
use ImagineAI\Labs\PgN8nOutboxLab as Outbox;

function okay(bool $ok,string $reason): void
{
    if (!$ok) throw new RuntimeException('P6_OUTBOX_TEST_FAILED_'.$reason);
}
function event(string $id,string $tenant='SYNTHETIC_TENANT_A',int $time=1794690010): array
{
    return [
        'contract'=>'SYNTHETIC_AIA_AUTOMATION_V1',
        'event_ref'=>$id,
        'tenant_ref'=>$tenant,
        'run_ref'=>'SYNTHETIC_RUN_01',
        'kind'=>'mock.run.completed',
        'reference_ref'=>'SYNTHETIC_RESOURCE_01',
        'occurred_at'=>$time,
    ];
}
function connect(): PDO
{
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
}
$good=event('SYNTHETIC_EVENT_01');
$canonical=Envelope::encode($good);
okay(is_string($canonical),'canonical envelope created');
okay(Envelope::decode($canonical)===$good,'roundtrip exact');
foreach ([
    array_replace($good,['tenant_ref'=>'TENANT_REAL']),
    array_replace($good,['kind'=>'crm.leads.update']),
    array_replace($good,['kind'=>'mock.approval.requested','external_url'=>'https://example.org']),
    array_replace($good,['occurred_at'=>'1794690010']),
    array_replace($good,['event_ref'=>'SYNTHETIC_'.str_repeat('A',90)]),
    array_replace($good,['reference_ref'=>['private'=>'PII']]),
    array_replace($good,['payload'=>'secret']),
] as $unsafe) {
    okay(Envelope::encode($unsafe)===null,'unknown or malicious field denied');
}
foreach ([
    '',
    '{"contract":"SYNTHETIC_AIA_AUTOMATION_V1"}',
    str_replace(',"kind"',',"event_ref":"SYNTHETIC_FORGED","kind"',$canonical),
    $canonical."\n",
    str_replace(',"occurred_at":',',"unknown":true,"occurred_at":',$canonical),
] as $unsafeText) {
    okay(Envelope::decode($unsafeText)===null,'noncanonical or duplicate denied');
}
if (getenv('AIA_P6_PG_LAB')!=='true' || getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('DB_DATABASE')!=='imagine_agents_ci'
    || getenv('DB_HOST')!=='127.0.0.1') {
    throw new RuntimeException('P6_DISPOSABLE_CI_REQUIRED');
}
$db=connect();
$out=new Outbox($db);
$out->createTables();
try {
    $other=new Outbox(connect());
    okay($out->enqueue($canonical)==='CREATED','first insert');
    okay($other->enqueue($canonical)==='REPLAY','cross-process-like replay');
    okay($out->enqueue(Envelope::encode(event('SYNTHETIC_EVENT_01','SYNTHETIC_TENANT_A',1794690011)))===null,'id collision payload mismatch');
    okay($out->enqueue(Envelope::encode(event('SYNTHETIC_EVENT_01','SYNTHETIC_TENANT_B')))==='CREATED','same id different tenant');
    okay($out->enqueue(Envelope::encode(event('SYNTHETIC_EVENT_02')))==='CREATED','second event');

    $now=1794690020;
    // A transaction holding event 01 prevents another consumer from
    // blocking; SKIP LOCKED must return event 02 on a separate PG socket.
    $db->beginTransaction();
    $locked=$db->query("SELECT event_ref FROM public.aia_lab_n8n_outbox_p6
        WHERE tenant_ref='SYNTHETIC_TENANT_A'
        AND event_ref='SYNTHETIC_EVENT_01' FOR UPDATE")->fetchColumn();
    okay($locked==='SYNTHETIC_EVENT_01','locked first event');
    $second=$other->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_02',$now);
    okay(($second['event_ref']??null)==='SYNTHETIC_EVENT_02','skip locked chooses second');
    okay(($second['attempt']??null)===1,'first attempt');
    $db->commit();

    okay($out->acknowledge('SYNTHETIC_TENANT_B','SYNTHETIC_EVENT_02','SYNTHETIC_WORKER_02',$now)===false,'tenant swapped');
    okay($out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_EVENT_02','SYNTHETIC_WORKER_FORGED',$now)===false,'worker mismatch');
    okay($out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_EVENT_02','SYNTHETIC_WORKER_02',$now+60)===false,'expired lease');
    okay($other->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_EVENT_02','SYNTHETIC_WORKER_02',$now+1)===true,'valid ack');
    okay($other->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_EVENT_02','SYNTHETIC_WORKER_02',$now+1)===false,'duplicate ack');
    okay($out->stats('SYNTHETIC_TENANT_A')===[
        'pending'=>1,'leased'=>0,'acked'=>1,'dead'=>0
    ],'first two event states');

    $one=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_01',$now);
    okay(($one['event_ref']??null)==='SYNTHETIC_EVENT_01','first event claimed');
    okay($other->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_02',$now+10)===null,'live lease cannot be stolen');
    $two=$other->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_02',$now+60);
    okay(($two['attempt']??null)===2,'retry after expiry');
    okay($out->acknowledge('SYNTHETIC_TENANT_A','SYNTHETIC_EVENT_01','SYNTHETIC_WORKER_01',$now+61)===false,'old worker denied');
    $three=$out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_01',$now+120);
    okay(($three['attempt']??null)===3,'bounded third attempt');
    okay($out->claim('SYNTHETIC_TENANT_A','SYNTHETIC_WORKER_01',$now+180)===null,'dead letter after last timeout');
    okay($out->stats('SYNTHETIC_TENANT_A')===[
        'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>1
    ],'retries exhausted and retained');

    $beta=$other->claim('SYNTHETIC_TENANT_B','SYNTHETIC_WORKER_BETA',$now);
    okay(($beta['event_ref']??null)==='SYNTHETIC_EVENT_01','tenant B isolated');
    okay($other->acknowledge('SYNTHETIC_TENANT_B','SYNTHETIC_EVENT_01','SYNTHETIC_WORKER_BETA',$now+1)===true,'tenant B ack');
    okay($out->stats('SYNTHETIC_TENANT_B')===[
        'pending'=>0,'leased'=>0,'acked'=>1,'dead'=>0
    ],'B state independent');

    $out->dropTables();
    okay($out->enqueue($canonical)===null,'db outage denies enqueue');
    okay($out->stats('SYNTHETIC_TENANT_A')===null,'db outage denies stats');
    echo "P6_N8N_OUTBOX_PG16_PASS envelope=canonical tenant=isolated replay=durable skip_locked=true retries=3 ack=single dead_letter=retained network=none NO_GO\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    $out->dropTables();
}
