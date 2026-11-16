<?php
declare(strict_types=1);

/** P4 independent PHP process: never run outside disposable CI PG16. */
require_once __DIR__.'/labs/PgSalesWorkbenchLab.php';

use ImagineAI\Labs\PgSalesWorkbenchLab;

if (count($argv)!==7 || ($argv[1]??'')!=='run') {
    fwrite(STDERR,"P4_WORKER_ARGUMENTS_DENIED\n");
    exit(3);
}
try {
    $pdo=new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
    $lab=new PgSalesWorkbenchLab($pdo);
    $result=$lab->run($argv[2],$argv[3],$argv[4],$argv[5],$argv[6]);
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable) {
    fwrite(STDERR,"P4_WORKER_DENIED\n");
    exit(4);
}
