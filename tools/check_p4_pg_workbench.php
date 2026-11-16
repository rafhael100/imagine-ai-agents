<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSalesWorkbenchLab.php';

use ImagineAI\Labs\PgSalesWorkbenchLab;

function checkP4(bool $ok,string $name): void
{
    if (!$ok) {
        fwrite(STDERR,"P4_PG_WORKBENCH_DENIED:".$name."\n");
        exit(1);
    }
}
function dbP4(): PDO
{
    return new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
}

if (getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    || getenv('AIA_P4_PG_LAB')!=='true'
    || getenv('DB_DATABASE')!=='imagine_agents_ci'
    || getenv('DB_HOST')!=='127.0.0.1') {
    throw new RuntimeException('P4_CI_ONLY');
}
$db=dbP4();
$lab=new PgSalesWorkbenchLab($db);
$lab->createTables();
try {
    checkP4($lab->run('MALICIOUS','SYNTHETIC_REQ_A','PRESET_SALES','SYNTHETIC_LEAD_A','next')===null,'hostile tenant');
    checkP4($lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_A','PRESET_SALES','../../CRM','next')===null,'hostile lead');
    checkP4($lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_A','PRESET_SALES','SYNTHETIC_LEAD_A','write')===null,'write operation');
    checkP4($lab->stats('SYNTHETIC_TENANT_A')===null,'invalid attempts create no quota');

    $first=$lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_FIRST',
        'PRESET_SALES','SYNTHETIC_LEAD_A','next');
    checkP4($first===['state'=>'SIMULATED','used'=>1,'approval_ref'=>'SYNTHETIC_REVIEW_01'],'initial approval');
    $another=new PgSalesWorkbenchLab(dbP4());
    $repeat=$another->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_FIRST',
        'PRESET_SALES','SYNTHETIC_LEAD_A','next');
    checkP4($repeat!==null && $repeat['state']==='REPLAY' && $repeat['used']===1,'replay across connections');
    checkP4($another->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_FIRST',
        'PRESET_SALES','SYNTHETIC_LEAD_A','summary')===null,'same id with new payload');
    checkP4($lab->decide('SYNTHETIC_TENANT_B','SYNTHETIC_REVIEW_01',
        'APROVADO_EM_ENSAIO')===false,'cross-tenant review');
    checkP4($lab->decide('SYNTHETIC_TENANT_A','SYNTHETIC_REVIEW_01',
        'APROVADO_EM_ENSAIO')===true,'first terminal decision');
    checkP4($another->decide('SYNTHETIC_TENANT_A','SYNTHETIC_REVIEW_01',
        'REJEITADO_EM_ENSAIO')===false,'second terminal decision denied');
    checkP4($lab->stats('SYNTHETIC_TENANT_A')===[
        'used'=>1,'events'=>1,'pending'=>0,'approved'=>1,'rejected'=>0
    ],'initial stats correct');

    // Create 12 distinct concurrent PHP processes. Only 7 more may commit
    // under the same real PostgreSQL row lock; 5 must be denied.
    $running=[];
    for ($i=1;$i<=12;$i++) {
        $request='SYNTHETIC_REQ_PARALLEL_'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $cmd=[PHP_BINARY,__DIR__.'/p4_pg_workbench_worker.php',
            'run','SYNTHETIC_TENANT_A',$request,
            'PRESET_SALES','SYNTHETIC_LEAD_B','summary'];
        $proc=proc_open($cmd,[
            0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']
        ],$pipes,__DIR__);
        checkP4(is_resource($proc),'process created');
        fclose($pipes[0]);
        $running[]=[$proc,$pipes,$request];
    }
    $accepted=0;
    $denied=0;
    $seen=[];
    foreach ($running as [$proc,$pipes,$request]) {
        $stdout=trim(stream_get_contents($pipes[1]));
        $stderr=trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($proc);
        checkP4($exit===0 && $stderr==='','worker completed without error');
        $result=json_decode($stdout,true,5,JSON_THROW_ON_ERROR);
        if ($result===null) {
            $denied++;
        } else {
            checkP4($result['state']==='SIMULATED','only new executions counted');
            checkP4(!in_array($result['used'],$seen,true),'unique run number');
            $seen[]=$result['used'];
            $accepted++;
        }
    }
    checkP4($accepted===7 && $denied===5,'bounded 7 successes and 5 denials');
    sort($seen);
    checkP4($seen===[2,3,4,5,6,7,8],'serial numeric execution range');
    checkP4($lab->stats('SYNTHETIC_TENANT_A')===[
        'used'=>8,'events'=>8,'pending'=>0,'approved'=>1,'rejected'=>0
    ],'exactly-once quota and audit counts');
    checkP4($lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_OVER',
        'PRESET_SALES','SYNTHETIC_LEAD_A','next')===null,'ninth denied');
    checkP4($lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_FIRST',
        'PRESET_SALES','SYNTHETIC_LEAD_A','next')['state']==='REPLAY',
        'old idempotency survives exhaustion');
    checkP4($lab->decide('SYNTHETIC_TENANT_A','SYNTHETIC_REVIEW_99',
        'APROVADO_EM_ENSAIO')===false,'unknown review denied');

    // Same synthetic request ref in an independent tenant is distinct.
    $beta=$another->run('SYNTHETIC_TENANT_B','SYNTHETIC_REQ_FIRST',
        'PRESET_SALES','SYNTHETIC_LEAD_B','next');
    checkP4($beta!==null && $beta['used']===1
        && $beta['approval_ref']==='SYNTHETIC_REVIEW_01','isolated company');
    checkP4($lab->stats('SYNTHETIC_TENANT_B')['pending']===1,'isolated pending review');

    // Force a missing DB table: no in-memory fallback and no green result.
    $lab->dropTables();
    checkP4($lab->run('SYNTHETIC_TENANT_A','SYNTHETIC_REQ_OUTAGE',
        'PRESET_SALES','SYNTHETIC_LEAD_A','next')===null,'missing db denies');
    checkP4($lab->stats('SYNTHETIC_TENANT_A')===null,'missing db no fake stats');
    echo "P4_PG16_WORKBENCH_PASS concurrent=12 committed=7 denied=5 quota=8 replay=durable approvals=terminal tenants=2 NO_GO\n";
} finally {
    $lab->dropTables();
}
