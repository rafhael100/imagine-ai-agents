<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/MockPilot/MockSalesWorkbenchSession.php';

use ImagineAI\MockPilot\MockSalesWorkbenchSession;

function check(bool $condition,string $label): void
{
    if (!$condition) {
        fwrite(STDERR,"P2_SALES_DOMAIN_DENIED ".$label."\n");
        exit(1);
    }
}

function fixtures(): array
{
    $lab=new MockSalesWorkbenchSession('SYNTHETIC_TENANT_A');
    $set=[];
    foreach (['next','summary'] as $goal) {
        foreach (['A','B','C'] as $lead) {
            $value=$lab->execute('PRESET_SALES',$lead,$goal);
            check($value!==null,'fixture generated');
            $set[$goal][$lead]=[
                'lead_ref'=>$value['lead_ref'],
                'stage'=>$value['stage'],
                'text'=>$value['text'],
                'requires_review'=>$value['requires_review'],
            ];
        }
    }
    return $set;
}

if (($argv[1]??null)==='--fixtures') {
    echo json_encode(fixtures(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit(0);
}

$session=new MockSalesWorkbenchSession('SYNTHETIC_TENANT_A');
check($session->snapshot()['authority']==='NONE','no authority');
check($session->snapshot()['external_calls']===0,'no external calls');
foreach (['TENANT_A','SYNTHETIC_A;B','SYNTHETIC_'.str_repeat('A',99)] as $bad) {
    try {
        new MockSalesWorkbenchSession($bad);
        check(false,'invalid tenant accepted');
    } catch (InvalidArgumentException $expected) {
        check($expected->getMessage()==='LAB_SCOPE_DENIED','generic scope denial');
    }
}
check($session->createDraft('<script>alert(1)</script>','sales')===null,'html name denied');
check($session->createDraft('__proto__','sales')===null,'prototype key denied');
check($session->createDraft('AB','sales')===null,'short name denied');
check($session->createDraft(str_repeat('A',41),'sales')===null,'oversized name denied');
check($session->createDraft('Consultor Comercial','webhook')===null,'foreign template denied');

$created=$session->createDraft('Consultor Comercial','sales');
check(($created['id']??'')==='SYNTHETIC_AGENT_01','first draft id');
check($session->createDraft('consultor comercial','sales')===null,'case folded duplicate denied');
$second=$session->createDraft('Analista de Leads','analysis');
check(($second['id']??'')==='SYNTHETIC_AGENT_02','second draft id');
for($i=3;$i<=5;$i++){
    check($session->createDraft('Consultor '.$i,'sales')!==null,'capacity baseline');
}
check($session->createDraft('Consultor 6','sales')===null,'capacity exhausted');
check(count($session->snapshot()['drafts'])===5,'draft array bounded');

check($session->execute('SYNTHETIC_AGENT_99','A','next')===null,'unknown agent denied');
check($session->execute('__proto__','A','next')===null,'fake id denied');
check($session->execute('PRESET_SALES','__proto__','next')===null,'lead inherited key denied');
check($session->execute('PRESET_SALES','A','delete')===null,'unapproved goal denied');
check($session->snapshot()['run_count']===0,'invalid runs do not spend quota');

$read=$session->execute('SYNTHETIC_AGENT_02','C','next');
check($read!==null && $read['requires_review']===false,'analysis cannot queue review');
check($read['approval_ref']===null,'analysis no approval');
check(str_contains($read['text'],'Estágio atual: proposta'),'analysis summary');
check($session->snapshot()['approvals']===[],'no pending approval');
$next=$session->execute('SYNTHETIC_AGENT_01','A','next');
check($next!==null && $next['requires_review']===true,'sales draft produces review');
check($next['approval_ref']==='SYNTHETIC_APPROVAL_02','approval ref stable');
check($session->decide('SYNTHETIC_APPROVAL_02','APPROVED')===false,'wrong decision denied');
check($session->decide('SYNTHETIC_APPROVAL_02','APROVADO_EM_ENSAIO')===true,'review accepted locally');
check($session->decide('SYNTHETIC_APPROVAL_02','REJEITADO_EM_ENSAIO')===false,'no second decision');
check($session->decide('SYNTHETIC_APPROVAL_999','APROVADO_EM_ENSAIO')===false,'unknown review denied');

for($i=0;$i<6;$i++){
    check($session->execute('PRESET_SALES','B','summary')!==null,'fill bounded run capacity');
}
check($session->snapshot()['run_count']===8,'run limit reached');
check($session->execute('PRESET_SALES','A','next')===null,'ninth run denied');
check($session->snapshot()['run_count']===8,'denied run does not mutate budget');
check($session->snapshot()['reviewed_count']===1,'one approved review');
check($session->snapshot()['persistent_writes']===0,'no persistent operations');
check($session->snapshot()['operational_decision']==='NO_GO','no go immutable');

$foreign=new MockSalesWorkbenchSession('SYNTHETIC_TENANT_B');
check($foreign->snapshot()['run_count']===0,'tenants have separated session instances');
check(count($foreign->snapshot()['drafts'])===0,'no cross-tenant draft sharing');
check(count(fixtures()['next'])===3 && count(fixtures()['summary'])===3,'all fixtures');
echo "P2_SALES_WORKBENCH_PHP_PASS drafts=5 runs=8 approvals=1 tenants=2 external_calls=0 NO_GO\n";
