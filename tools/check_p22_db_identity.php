<?php
declare(strict_types=1);
require_once __DIR__.'/labs/PgSyntheticReplayGate.php';

use ImagineAI\Convergence\PgSyntheticReplayGate;

if(getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('GITHUB_REPOSITORY')!=='rafhael100/imagine-ai-agents'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci'
    ||getenv('AIA_P16_PG_LAB')!=='true') {
    throw new RuntimeException('P22_CI_ONLY');
}
$checks=0;
function p22Check(bool $condition,string $label):void {
    global $checks;
    $checks++;
    if(!$condition)throw new RuntimeException('P22_FAIL_'.$label);
}
function p22Denied(PDO $db,string $label):void {
    $rejected=false;
    try {
        new PgSyntheticReplayGate($db);
    }catch(RuntimeException $e) {
        $rejected=true;
    }
    p22Check($rejected,$label);
}
$good=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
p22Check(new PgSyntheticReplayGate($good) instanceof PgSyntheticReplayGate,'legitimate_ephemeral_db');

$wrongDb=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
p22Denied($wrongDb,'wrong_database_denied');

$wrongDriver=new PDO('sqlite::memory:');
p22Denied($wrongDriver,'non_postgresql_denied');

putenv('AIA_P16_PG_LAB=false');
try {
    p22Denied($good,'ci_environment_flag_required');
}finally{
    putenv('AIA_P16_PG_LAB=true');
}
p22Check(new PgSyntheticReplayGate($good) instanceof PgSyntheticReplayGate,'valid_restored');
echo "AIA_P22_DB_IDENTITY_PASS checks={$checks} accidental_production_db_denied=true ai_calls=0 NO_GO\n";
