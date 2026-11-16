<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgAiCreditReservationLab.php';

use ImagineAI\Labs\PgAiCreditReservationLab;

if (count($argv)!==4 || ($argv[1]??null)!=='reserve') {
    fwrite(STDERR,"P8_WORKER_INVALID_ARGS\n");
    exit(3);
}
try {
    $pdo=new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'postgres','ci-only-password',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
    $lab=new PgAiCreditReservationLab($pdo);
    $answer=$lab->reserve('SYNTHETIC_TENANT_A',$argv[2],
        'SYNTHETIC_PURPOSE_MARKETING',(int)$argv[3]);
    echo json_encode($answer,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable) {
    fwrite(STDERR,"P8_WORKER_DENIED\n");
    exit(4);
}
