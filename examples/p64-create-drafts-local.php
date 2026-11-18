<?php
declare(strict_types=1);

/** P64: isolated synthetic form; credentials and authority never come from HTTP. */
require_once __DIR__.'/../tools/labs/PgAuthoredDraftStagingLab.php';
require_once __DIR__.'/../tools/labs/ImagineLabPresentation.php';

use ImagineAI\Automation\DeterministicFunnelProjector;
use ImagineAI\Automation\DeterministicRuleEngine;
use ImagineAI\Convergence\PgAuthoredDraftStagingLab;
use ImagineAI\Convergence\PgScopedDraftWriterLab;
use ImagineAI\Presentation\ImagineLabPresentation;

const P64_ORIGIN='http://127.0.0.1:8787';
const P64_PAGE='/p64-create-drafts-local.php';
const P64_READER='http://127.0.0.1:8786/p56-live-pg-review-local.php';
const P64_PREVIEW_TTL=600;
const P64_MAX_PREVIEWS=5;

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'self' ".P64_READER."; base-uri 'none'; frame-ancestors 'none'");

function p64Deny(int $status):never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'P64_LOCAL_FORM_DENIED';
    exit;
}
function p64Escape(string|int $value):string
{
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
function p64FormBody():array
{
    if(preg_match('/\Aapplication\/x-www-form-urlencoded(?:;\s*charset=UTF-8)?\z/Di',
        $_SERVER['CONTENT_TYPE']??'')!==1)p64Deny(415);
    $length=$_SERVER['CONTENT_LENGTH']??'';
    if(!is_string($length)||preg_match('/\A[0-9]{1,5}\z/D',$length)!==1
        ||(int)$length<1||(int)$length>8192)p64Deny(413);
    $raw=file_get_contents('php://input',false,null,0,8193);
    if(!is_string($raw)||strlen($raw)!==(int)$length)p64Deny(400);
    $parts=explode('&',$raw);
    if(count($parts)>8)p64Deny(400);
    $values=[];
    foreach($parts as $part){
        if(substr_count($part,'=')!==1
            ||preg_match('/%(?![0-9a-fA-F]{2})/',$part)===1)p64Deny(400);
        [$key,$value]=array_map('urldecode',explode('=',$part,2));
        if(preg_match('/\A[a-z_]+\z/D',$key)!==1||array_key_exists($key,$values)
            ||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value)===1){
            p64Deny(400);
        }
        $values[$key]=$value;
    }
    $action=$values['action']??null;
    $expected=match($action){
        'preview'=>['action','client_rate','csrf','lead_rate','weekly_visits'],
        'save'=>['action','csrf','preview_id'],
        default=>[],
    };
    $keys=array_keys($values);
    sort($keys,SORT_STRING);
    if($expected===[]||$keys!==$expected)p64Deny(400);
    return $values;
}
function p64Visits(string $value):int
{
    if(preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D',$value)!==1
        ||(int)$value>100000000)throw new InvalidArgumentException('INPUT');
    return (int)$value;
}
function p64Rate(string $value):int
{
    if(preg_match('/\A(?:0|[1-9][0-9]?|100)(?:[.,][0-9]{1,2})?\z/D',$value)!==1){
        throw new InvalidArgumentException('INPUT');
    }
    $parts=explode('.',str_replace(',','.',$value));
    $bp=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    if($bp>10000)throw new InvalidArgumentException('INPUT');
    return $bp;
}

$local=PHP_SAPI==='cli-server'
    &&($_SERVER['REMOTE_ADDR']??'')==='127.0.0.1'
    &&($_SERVER['SERVER_NAME']??'')==='127.0.0.1'
    &&($_SERVER['HTTP_HOST']??'')==='127.0.0.1:8787';
$ci=getenv('GITHUB_ACTIONS')==='true'
    &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
    &&getenv('AIA_P64_CREATE_LAB')==='true'
    &&getenv('AIA_P64_FORM_LAB')==='true'
    &&getenv('DB_HOST')==='127.0.0.1'
    &&getenv('DB_DATABASE')==='imagine_agents_ci';
if(!$local||!$ci)p64Deny(403);
if(($_SERVER['QUERY_STRING']??'')!=='')p64Deny(400);
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true))p64Deny(405);
if($method==='POST'&&($_SERVER['HTTP_ORIGIN']??'')!==P64_ORIGIN)p64Deny(403);
$body=$method==='POST'?p64FormBody():null;

$passwordPath=getenv('AIA_P64_WRITER_PASSWORD_FILE');
if($passwordPath!=='/run/p64/writer-a'
    ||realpath($passwordPath)!==$passwordPath||!is_file($passwordPath)
    ||filesize($passwordPath)!==36)p64Deny(403);
$password=file_get_contents($passwordPath);
if(!is_string($password)||preg_match('/\A[a-f0-9]{36}\z/D',$password)!==1)p64Deny(403);
$sessionDirectory='/run/p64-sessions';
if(realpath($sessionDirectory)!==$sessionDirectory||!is_dir($sessionDirectory)
    ||(fileperms($sessionDirectory)&0777)!==0700||!is_writable($sessionDirectory))p64Deny(403);

try{
    $db=new PDO('pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'aia_p64_ci_write_a',$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3,
        ]);
    $scope=PgScopedDraftWriterLab::fromDatabase($db);
}catch(Throwable){p64Deny(503);}
unset($password);

ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
ini_set('session.gc_maxlifetime',(string)P64_PREVIEW_TTL);
session_name('AIA_P64_LAB');
session_save_path($sessionDirectory);
session_cache_limiter('');
session_set_cookie_params([
    'lifetime'=>0,'path'=>P64_PAGE,'secure'=>false,
    'httponly'=>true,'samesite'=>'Strict',
]);
if(!session_start())p64Deny(503);
if(!isset($_SESSION['csrf'])){
    $_SESSION=['csrf'=>bin2hex(random_bytes(32)),'previews'=>[]];
}
if(!is_string($_SESSION['csrf']??null)||!is_array($_SESSION['previews']??null))p64Deny(503);
$csrf=$_SESSION['csrf'];
foreach($_SESSION['previews'] as $id=>$pending){
    if(!is_array($pending)||!is_int($pending['created_at']??null)
        ||time()-$pending['created_at']>P64_PREVIEW_TTL)unset($_SESSION['previews'][$id]);
}
if($body!==null&&(!is_string($body['csrf']??null)||!hash_equals($csrf,$body['csrf']))){
    p64Deny(403);
}

$notice='';
$preview=null;
$previewId=null;
$inputs=null;
$values=['weekly_visits'=>'120','lead_rate'=>'10','client_rate'=>'20'];
if($body!==null&&$body['action']==='preview'){
    $values=array_intersect_key($body,$values);
    try{
        $visits=p64Visits($values['weekly_visits']);
        $leadRate=p64Rate($values['lead_rate']);
        $clientRate=p64Rate($values['client_rate']);
        if(count($_SESSION['previews'])>=P64_MAX_PREVIEWS){
            http_response_code(409);
            $notice='Há cinco prévias recentes nesta sessão. Aguarde dez minutos para preparar outra; as confirmações já abertas continuam disponíveis.';
        }else{
            $request='SYNTHETIC_P64_'.strtoupper(bin2hex(random_bytes(12)));
            $inputs=PgAuthoredDraftStagingLab::inputs(
                $scope->tenant(),$request,$visits,$leadRate,$clientRate
            );
            $preview=(new DeterministicRuleEngine())->evaluate($inputs['command'],$inputs['ruleset']);
            $previewId=bin2hex(random_bytes(24));
            $_SESSION['previews'][$previewId]=[
                'inputs'=>$inputs,'preview_hash'=>hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR)),
                'created_at'=>time(),
            ];
        }
    }catch(InvalidArgumentException){
        http_response_code(400);
        $notice='Informe visitas inteiras de 0 a 100.000.000 e taxas de 0 a 100%, com até duas casas decimais.';
    }
}elseif($body!==null){
    $previewId=$body['preview_id'];
    if(preg_match('/\A[a-f0-9]{48}\z/D',$previewId)!==1
        ||!isset($_SESSION['previews'][$previewId]))p64Deny(409);
    $pending=$_SESSION['previews'][$previewId];
    $inputs=$pending['inputs'];
    $values=['weekly_visits'=>(string)$inputs['command']['weekly_visits']];
    foreach(['lead_rate'=>'lead_rate_bp','client_rate'=>'client_rate_bp'] as $field=>$key){
        $bp=$inputs['command'][$key];
        $values[$field]=intdiv($bp,100).','.str_pad((string)($bp%100),2,'0',STR_PAD_LEFT);
    }
    $preview=(new DeterministicRuleEngine())->evaluate($inputs['command'],$inputs['ruleset']);
    if(!hash_equals($pending['preview_hash'],hash('sha256',json_encode($preview,JSON_THROW_ON_ERROR)))){
        p64Deny(409);
    }
    // Preserve the token/request for a retry after a lost response. Release
    // the session lock before DB work; idempotence belongs to PostgreSQL.
    session_write_close();
    try{
        $result=(new PgAuthoredDraftStagingLab($db,$scope))->stage($inputs['command'],$inputs['ruleset']);
    }catch(Throwable){p64Deny(503);}
    if(!in_array($result['decision'],[
        'LAB_AUTHORED_DRAFTS_STORED','LAB_AUTHORED_DRAFTS_REPLAY'
    ],true)){
        http_response_code(503);
        $notice='Não foi possível guardar o lote completo. Você pode tentar novamente com esta mesma prévia.';
    }else{
        header('Location: '.P64_READER.(count($result['proposal_refs'])===1
            ?'?proposal='.$result['proposal_refs'][0]:''),true,303);
        exit;
    }
}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
$projection=$inputs===null?null:DeterministicFunnelProjector::project(
    $inputs['command']['weekly_visits'],$inputs['command']['lead_rate_bp'],$inputs['command']['client_rate_bp']
);
try{
    $logo=ImagineLabPresentation::logo((string)@file_get_contents(__DIR__.'/assets/logo-imagine.png'));
}catch(Throwable){p64Deny(503);}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Imagine · Criar rascunhos</title>
<style><?= ImagineLabPresentation::styles() ?></style>
</head>
<body>
<a class="skip-link" data-imagine-skip="true" href="#main-content">Ir para o conteúdo</a>
<div class="app-shell">
<aside class="sidebar" aria-label="Imagine Agents">
<div class="brand"><?= $logo ?><p class="product-name">Agentes IA</p></div>
<?= ImagineLabPresentation::navigation('create') ?>
<p class="sidebar-note">Ambiente de homologação<br>Dados fictícios</p>
</aside>
<main class="shell" id="main-content" tabindex="-1" data-p64-local-form="true">
<header class="workspace-header"><p class="eyebrow">Criar rascunhos</p><span class="flag">Homologação · NO-GO operacional</span></header>
<h1>Da prévia ao rascunho.</h1>
<p class="intro">Simule o funil, confira as propostas e guarde os rascunhos para revisão. Use apenas números fictícios.</p>
<?php if($notice!==''): ?><div class="notice" role="status"><?= p64Escape($notice) ?></div><?php endif; ?>
<div class="layout"><section class="card"><div class="step">1 · Simular</div><h2>Defina o cenário</h2><p class="muted">Use números fictícios. As taxas são aplicadas em sequência.</p>
<form method="post" action="<?= P64_PAGE ?>">
<input type="hidden" name="action" value="preview"><input type="hidden" name="csrf" value="<?= p64Escape($csrf) ?>">
<label for="visits">Visitas por semana</label><input id="visits" name="weekly_visits" aria-describedby="visits-help" inputmode="numeric" pattern="[0-9]+" maxlength="9" required value="<?= p64Escape($values['weekly_visits']) ?>">
<p class="help" id="visits-help">Número inteiro, de 0 a 100.000.000.</p>
<div class="rates"><div><label for="lead-rate">Conversão em leads (%)</label><input id="lead-rate" name="lead_rate" aria-describedby="rates-help" inputmode="decimal" maxlength="6" required value="<?= p64Escape($values['lead_rate']) ?>"></div><div><label for="client-rate">Conversão em clientes (%)</label><input id="client-rate" name="client_rate" aria-describedby="rates-help" inputmode="decimal" maxlength="6" required value="<?= p64Escape($values['client_rate']) ?>"></div></div>
<p class="help" id="rates-help">Taxas de 0 a 100%, com até duas casas decimais. Projeções não representam clientes reais.</p><button class="wide" type="submit">Ver prévia</button>
</form></section>
<aside class="card"><div class="step">Regras desta simulação · revisão 1</div><h2>O que pode virar rascunho</h2><div class="rule"><strong>12 ou mais leads projetados</strong><span class="muted">Uma proposta para revisão humana.</span></div><div class="rule"><strong>2 ou mais clientes projetados</strong><span class="muted">Um rascunho de tarefa CRM.</span></div><div class="rule"><strong>Você confere antes de guardar</strong><span class="muted">As propostas só entram na fila quando o lote inteiro e sua autoria podem ser registrados juntos.</span></div></aside></div>
<?php if($preview!==null&&$projection!==null): ?>
<section class="card preview" data-p64-preview="<?= p64Escape($previewId) ?>" data-p64-request="<?= p64Escape($inputs['command']['request_ref']) ?>"><div class="step">2 · Conferir e guardar</div><h2>Sua prévia</h2>
<div class="metrics"><div class="metric"><b><?= number_format($projection['visits'],0,',','.') ?></b><span>visitas simuladas</span></div><div class="metric"><b><?= number_format($projection['projected_leads'],0,',','.') ?></b><span>leads projetados</span></div><div class="metric"><b><?= number_format($projection['projected_customers'],0,',','.') ?></b><span>clientes projetados</span></div></div>
<?php foreach($preview['proposals'] as $proposal): ?><div class="draft" data-p64-proposal="<?= p64Escape($proposal['proposal_ref']) ?>"><strong><?= $proposal['draft_kind']==='HUMAN_REVIEW_DRAFT'?'Proposta para revisão humana':'Rascunho de tarefa CRM' ?></strong><span class="muted"><?= p64Escape($proposal['observed']) ?> <?= $proposal['metric']==='projected_leads'?'leads':'clientes' ?> projetados · regra: pelo menos <?= p64Escape($proposal['threshold']) ?></span><br><span class="pill">Rascunho · sem execução</span></div><?php endforeach; ?>
<?php if($preview['matched_rules']>0): ?><form method="post" action="<?= P64_PAGE ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?= p64Escape($csrf) ?>"><input type="hidden" name="preview_id" value="<?= p64Escape($previewId) ?>"><button type="submit">Guardar <?= p64Escape($preview['matched_rules']) ?> <?= $preview['matched_rules']===1?'rascunho':'rascunhos' ?></button></form><p class="help">Esta prévia vale por dez minutos. Repetir a confirmação recupera o mesmo lote, sem duplicá-lo.</p>
<?php else: ?><p class="muted">Nenhuma regra deste cenário gerou um rascunho. Ajuste os números para preparar outra prévia.</p><?php endif; ?></section>
<?php endif; ?>
<footer class="footer">Autoria de laboratório, sem identidade humana autenticada. Guardar registra rascunhos sintéticos; não aprova propostas nem executa tarefas no CRM. Nenhum serviço externo é acionado.</footer>
</main></div></body></html>
