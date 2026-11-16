<?php
declare(strict_types=1);

require_once __DIR__.'/labs/PgBoundOutboxLeaseLab.php';

use ImagineAI\Convergence\PgBoundOutboxLeaseLab;

if(getenv('AIA_P16_PG_LAB')!=='true' ||getenv('AIA_P6_PG_LAB')!=='true'
    ||getenv('GITHUB_ACTIONS')!=='true'
    ||getenv('DB_HOST')!=='127.0.0.1'
    ||getenv('DB_DATABASE')!=='imagine_agents_ci') throw new RuntimeException('P29_CI_ONLY');
$tenant=$argv[1]??'';
$worker=$argv[2]??'';
$now=$argv[3]??'';
if(!is_string($tenant)||!is_string($worker)||!ctype_digit($now)) throw new RuntimeException('BAD_P29_ARGS');
$db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
    'postgres','ci-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$result=(new PgBoundOutboxLeaseLab($db))->reserve($tenant,$worker,(int)$now);
if($result['ai_calls']!==0||$result['service_effects']!==0) throw new RuntimeException('P29_UNEXPECTED_EFFECT');
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
