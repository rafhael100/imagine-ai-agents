<?php
declare(strict_types=1);

/**
 * P56: private, localhost-only CI browser view of *real synthetic*
 * PostgreSQL review rows, with a role-scoped P55 transaction.
 * No business tenant, public API, logins, approvals or side effects.
 */
require_once __DIR__.'/../tools/labs/PgScopedReviewQueueBridgeLab.php';
require_once __DIR__.'/../tools/labs/PgSignedReviewQueueBridgeLab.php';

use ImagineAI\Convergence\PgScopedReviewQueueBridgeLab;
use ImagineAI\Convergence\PgSignedReviewQueueBridgeLab;

function p56Deny(int $status):never
{
    http_response_code($status);
    header('Cache-Control: no-store, private');
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 'P56_READONLY_LAB_DENIED';
    exit;
}

$ci=getenv('GITHUB_ACTIONS')==='true'
    &&getenv('GITHUB_REPOSITORY')==='rafhael100/imagine-ai-agents'
    &&getenv('AIA_P55_SCOPED_LAB')==='true'
    &&getenv('AIA_P56_BROWSER_LAB')==='true'
    &&getenv('DB_HOST')==='127.0.0.1'
    &&getenv('DB_DATABASE')==='imagine_agents_ci';
$local=PHP_SAPI==='cli-server'
    &&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)
    &&in_array($_SERVER['SERVER_NAME']??'',['127.0.0.1','::1'],true)
    &&preg_match(
        '/\A(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]{1,5})?\z/D',
        $_SERVER['HTTP_HOST']??''
    )===1;
if(!$ci||!$local)p56Deny(403);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')p56Deny(405);
$signedMode=getenv('AIA_P62_SIGNED_REVIEW_LAB')==='true';
$detailMode=$signedMode&&getenv('AIA_P63_PROPOSAL_DETAIL_LAB')==='true';
$allowed=$detailMode?['kind','proposal']:['kind'];
if(array_diff(array_keys($_GET),$allowed)!==[])p56Deny(400);
$kind=$_GET['kind']??'all';
if(!is_string($kind)||!in_array($kind,['all','human','task','notice'],true)){
    p56Deny(400);
}
$selected=$_GET['proposal']??null;
if($selected!==null&&(!is_string($selected)
    ||preg_match('/\ASYNTHETIC_PROPOSAL_[A-F0-9]{24}\z/D',$selected)!==1)){
    p56Deny(400);
}

$file=getenv('AIA_P56_READER_PASSWORD_FILE');
$temp=getenv('RUNNER_TEMP');
$resolved=is_string($file)?realpath($file):false;
$root=is_string($temp)?realpath($temp):false;
if(!is_string($resolved)||!is_string($root)
    ||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)
    ||!is_file($resolved)||filesize($resolved)>100){
    p56Deny(403);
}
$password=trim((string)file_get_contents($resolved));
if(preg_match('/\A[a-f0-9]{36}\z/D',$password)!==1)p56Deny(403);

try{
    $pdo=new PDO(
        'pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci',
        'aia_p55_ci_read_a',$password,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]
    );
    $snapshot=null;
    $detail=null;
    $signedItems=[];
    if($signedMode){
        $snapshot=(new PgSignedReviewQueueBridgeLab($pdo))->read(
            'SYNTHETIC_TENANT_A',$selected
        );
        if(($snapshot['decision']??null)!==
                'LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO'
            ||!is_array($snapshot['queue']??null)
            ||$snapshot['can_approve']!==false
            ||$snapshot['can_execute']!==false){
            p56Deny(503);
        }
        $model=$snapshot['queue'];
        if($selected!==null){
            $detail=$snapshot['detail']??null;
            if(!is_array($detail)
                ||$detail['schema']!=='AIA_P63_VERIFIABLE_PROPOSAL_DETAIL_V1'
                ||$detail['decision']!=='LAB_PROPOSAL_DETAIL_NO_GO'
                ||$detail['proposal']['proposal_ref']!==$selected
                ||$detail['author_provenance_verified']!==true){
                p56Deny(503);
            }
        }
        if($snapshot['current_review_available']){
            foreach($snapshot['signed_review']['items'] as $item){
                $signedItems[$item['proposal_ref']]=$item;
            }
        }
    }else{
        $result=(new PgScopedReviewQueueBridgeLab($pdo))->read(
            'SYNTHETIC_TENANT_A'
        );
        if(($result['decision']??null)!=='LAB_READONLY_PG_REVIEW_NO_GO'
            ||!is_array($result['presentation']??null)
            ||$result['presentation']['can_approve']!==false
            ||$result['presentation']['can_execute']!==false){
            p56Deny(503);
        }
        $model=$result['presentation'];
    }
}catch(Throwable){
    p56Deny(503);
}

$kinds=[
    'all'=>null,
    'human'=>'HUMAN_REVIEW_DRAFT',
    'task'=>'CRM_TASK_DRAFT',
    'notice'=>'OPERATOR_NOTICE_DRAFT',
];
$filter=$kinds[$kind];
$visible=array_values(array_filter($model['items'],
    static fn($row)=>$filter===null||$row['kind']===$filter));
$names=[
    'HUMAN_REVIEW_DRAFT'=>'Revisão humana',
    'CRM_TASK_DRAFT'=>'Rascunho de tarefa CRM',
    'OPERATOR_NOTICE_DRAFT'=>'Aviso ao operador'
];
$metricNames=[
    'visits'=>'Visitas simuladas',
    'projected_leads'=>'Leads projetados',
    'projected_customers'=>'Clientes projetados'
];
$reviewNames=[
    'LAB_AWAITING_REVIEWS_NO_ACTION'=>'AGUARDANDO PARECERES',
    'LAB_PARTIAL_REVIEW_NO_ACTION'=>'UM PARECER ASSINADO',
    'LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION'=>'DUAS OBSERVAÇÕES ASSINADAS',
    'LAB_REVIEW_REJECTED_NO_ACTION'=>'PARECER DE RECUSA REGISTRADO',
];
$consultationTime=$signedMode
    ?(new DateTimeImmutable('@'.$snapshot['snapshot_observed_at']))
        ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
    :null;
function p56E(string|int $x):string
{
    return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Imagine · Revisão de propostas (PG de laboratório)</title>
<style>
:root{--orange:#F9941D;--ink:#1c2635;--muted:#64748b;--line:#e5eaf0;--bg:#f6f8fb;--white:#fff}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif}
.shell{max-width:1090px;margin:auto;padding:30px 22px 60px}
.top,.heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.top{margin-bottom:38px}
.logo{font-size:28px;font-weight:850;letter-spacing:-.055em}.logo b{color:var(--orange)}
.flag{font-size:12px;color:#804e0b;background:#fff3df;padding:6px 12px;border-radius:80px;font-weight:750}
h1{font-size:clamp(28px,4.5vw,42px);letter-spacing:-.045em;margin:0 0 8px;line-height:1.15}
.subtitle{color:var(--muted);max-width:740px;margin:0 0 28px}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:34px}
.card,.draft{border:1px solid var(--line);background:var(--white);border-radius:18px;padding:21px;box-shadow:0 4px 20px #1c263507}
.card strong{display:block;font-size:34px;font-variant-numeric:tabular-nums;line-height:1.3}
.card span,small{color:var(--muted);font-size:13px}
.review-status strong{display:inline;font-size:inherit}
h2{font-size:19px;letter-spacing:-.025em}
.filters{display:flex;flex-wrap:wrap;gap:6px}
.filters a{background:#fff;padding:8px 12px;color:#445569;text-decoration:none;font-size:13px;border:1px solid var(--line);border-radius:11px}
.filters a[aria-current="page"]{border-color:#f4c887;background:#fff2df;color:#804b06;font-weight:800}
.queue{display:grid;gap:12px;margin-top:12px}
.draft{display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center}
.draft h3{margin:4px 0;font-size:17px}.metric{text-align:right}
.metric strong{display:block;font-size:22px}
.mono{font:11px/1.6 ui-monospace,Consolas,monospace;color:#778499;overflow-wrap:anywhere}
.state{font-size:11px;color:#84510c;font-weight:700;background:#fff2df;display:inline-block;padding:5px 9px;border-radius:7px;margin-top:8px}
.detail-link{display:inline-block;margin-top:12px;color:#804b06;font-weight:700}
.proposal-detail{margin-top:24px}.condition{font-size:clamp(20px,3vw,28px);font-weight:800;letter-spacing:-.025em}
.inputs{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.inputs dt{color:var(--muted);font-size:13px}.inputs dd{margin:4px 0 0;font-size:20px;font-weight:700}
.detail-notice{background:#fff3df;border-radius:10px;padding:12px 16px}
.footer{border-top:1px solid var(--line);padding-top:18px;margin-top:36px;color:var(--muted);font-size:13px}
@media(max-width:650px){.shell{padding:18px 14px}.cards,.inputs{grid-template-columns:1fr}.draft{grid-template-columns:1fr}.metric{text-align:left}}
</style>
</head>
<body>
<main class="shell">
<header class="top">
<div class="logo">imagine<b>.</b></div>
<span class="flag">BANCO PG16 · CI · SOMENTE LEITURA</span>
</header>
<h1>Central de propostas</h1>
<p class="subtitle">Dados fictícios de rascunhos gravados no PostgreSQL de laboratório, lidos com isolamento por empresa e checagem de integridade. Nenhuma ação ou aprovação está disponível.</p>
<section class="cards" aria-label="Contadores de rascunhos">
<div class="card"><strong><?= p56E($model['total_pending']) ?></strong><span><?= $signedMode?'Propostas nesta consulta':'Propostas aguardando revisão' ?></span></div>
<div class="card"><strong><?= p56E($model['by_kind']['crm_task_draft']) ?></strong><span>Rascunhos de tarefas CRM</span></div>
<div class="card"><strong><?= p56E($model['by_kind']['operator_notice_draft']) ?></strong><span>Rascunhos de notificações</span></div>
</section>
<?php if($signedMode): ?>
<section class="card review-status" aria-labelledby="signed-review-title">
<h2 id="signed-review-title">Pareceres da proposta</h2>
<p data-review-history="verified">Histórico verificado: <?= p56E($snapshot['historical']['checked_signed_observations']) ?> observações assinadas em <?= p56E($snapshot['historical']['checked_drafts']) ?> propostas desta empresa.</p>
<?php if($snapshot['current_review_available']): ?>
<p data-review-current="available">Fila, autoria e pareceres foram lidos na mesma consulta. Os estados abaixo foram revalidados nesse momento; duas observações não constituem aprovação.</p>
<?php else: ?>
<p data-review-current="unavailable"><strong>Estado atual dos pareceres indisponível.</strong> A prova histórica continua verificável, mas não substitui pareceres válidos e chaves ativas no momento da consulta. Não há aprovação ou execução disponível.</p>
<?php endif; ?>
<small>Consulta: <?= p56E($consultationTime->format('d/m/Y H:i:s')) ?> (America/Sao_Paulo, UTC<?= p56E($consultationTime->format('P')) ?>). Uma alteração posterior aparecerá na próxima leitura. Esta consulta não concede autorização.</small>
</section>
<?php endif; ?>
<section aria-labelledby="queue-title">
<div class="heading">
<h2 id="queue-title">Fila de revisão</h2>
<nav class="filters" aria-label="Filtrar tipo">
<?php foreach(['all'=>'Todas','human'=>'Revisão','task'=>'Tarefas CRM','notice'=>'Avisos'] as $value=>$label): ?>
<a href="?kind=<?= p56E($value) ?>" <?= $kind===$value?'aria-current="page"':'' ?>><?= p56E($label) ?></a>
<?php endforeach; ?>
</nav>
</div>
<div class="queue">
<?php if($visible===[]): ?>
<article class="draft"><p>Nenhuma proposta sintética nessa categoria.</p></article>
<?php else: ?>
<?php foreach($visible as $proposal): ?>
<article class="draft">
<div>
<small><?= p56E($names[$proposal['kind']]) ?></small>
<h3><?= p56E($metricNames[$proposal['metric']]) ?></h3>
<div class="mono"><?= p56E($proposal['request_ref']) ?> · <?= p56E($proposal['rule_ref']) ?></div>
<?php if($signedMode&&$snapshot['current_review_available']): ?>
<?php $signed=$signedItems[$proposal['proposal_ref']]; ?>
<span class="state" data-review-state="<?= p56E($signed['review_state']) ?>"><?= p56E($reviewNames[$signed['review_state']]) ?> · NÃO EXECUTADO</span>
<p><small><?= p56E($signed['signed_observations']) ?> pareceres · <?= p56E(implode(' / ',$signed['review_roles'])?:'Nenhum papel observado') ?></small></p>
<?php elseif($signedMode): ?>
<span class="state" data-review-state="UNAVAILABLE">PARECER ATUAL INDISPONÍVEL · NÃO EXECUTADO</span>
<?php else: ?>
<span class="state">AGUARDANDO REVISÃO · NÃO EXECUTADO</span>
<?php endif; ?>
<?php if($detailMode): ?>
<div><a class="detail-link" data-proposal-detail="<?= p56E($proposal['proposal_ref']) ?>" href="?kind=<?= p56E($kind) ?>&amp;proposal=<?= p56E($proposal['proposal_ref']) ?>#proposal-detail">Ver condição e insumos</a></div>
<?php endif; ?>
</div>
<div class="metric">
<strong><?= p56E($proposal['observed']) ?></strong>
<small>Limiar: <?= p56E($proposal['threshold']) ?></small>
</div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>
<?php if($detail!==null): ?>
<section class="card proposal-detail" id="proposal-detail" aria-labelledby="detail-title" data-detail-state="<?= p56E($detail['state']) ?>" data-detail-proposal="<?= p56E($selected) ?>">
<h2 id="detail-title">Por que esta proposta foi criada?</h2>
<p class="mono"><?= p56E($detail['proposal']['request_ref']) ?> · <?= p56E($detail['proposal']['ruleset_ref']) ?> · revisão <?= p56E($detail['proposal']['revision']) ?></p>
<?php if($detail['state']==='VERIFIED'): ?>
<?php
$explanation=$detail['explanation'];
$rule=$explanation['rule'];
$input=$explanation['inputs'];
$operator=['gte'=>'≥','lte'=>'≤','eq'=>'='][$rule['operator']];
$comparison=['gte'=>'maior ou igual a','lte'=>'menor ou igual a','eq'=>'igual a'][$rule['operator']];
?>
<p>Esta proposta de <?= p56E(strtolower($names[$rule['draft_kind']])) ?> foi criada pela condição de <?= p56E(strtolower($metricNames[$rule['metric']])) ?>.</p>
<p class="condition" data-detail-operator="<?= p56E($rule['operator']) ?>"><?= p56E($explanation['observed']) ?> <?= p56E($operator) ?> <?= p56E($rule['threshold']) ?></p>
<p>O valor calculado foi <?= p56E($explanation['observed']) ?>. A regra exige um valor <?= p56E($comparison) ?> <?= p56E($rule['threshold']) ?>.</p>
<h3>Insumos originais da simulação</h3>
<dl class="inputs">
<div><dt>Visitas semanais</dt><dd><?= p56E($input['weekly_visits']) ?></dd></div>
<div><dt>Conversão em leads</dt><dd><?= p56E(number_format($input['lead_rate_bp']/100,2,',','.')) ?>%</dd></div>
<div><dt>Conversão em clientes</dt><dd><?= p56E(number_format($input['client_rate_bp']/100,2,',','.')) ?>%</dd></div>
</dl>
<p><small>Condição reavaliada com os insumos preservados do mesmo lote. <?= p56E($explanation['matched_rules']) ?> de <?= p56E($explanation['evaluated_rules']) ?> regras geraram rascunhos. Proposta não executada.</small></p>
<?php else: ?>
<p class="detail-notice"><strong>Entradas originais indisponíveis.</strong> Este lote foi gravado sem os insumos completos. Não é possível reconstruir a condição que o originou a partir dos valores ou dos identificadores.</p>
<?php endif; ?>
<p><small>Autoria sintética vinculada e verificada nesta consulta.</small></p>
<?php if($snapshot['current_review_available']): ?>
<?php $selectedReview=$signedItems[$selected]; ?>
<p><strong><?= p56E($reviewNames[$selectedReview['review_state']]) ?></strong> · <?= p56E($selectedReview['signed_observations']) ?> pareceres · <?= p56E(implode(' / ',$selectedReview['review_roles'])?:'Nenhum papel observado') ?></p>
<?php else: ?>
<p><strong>Parecer atual indisponível.</strong> O histórico foi verificado separadamente e não concede autorização.</p>
<?php endif; ?>
<p><small>Detalhe, fila, autoria e pareceres pertencem à mesma consulta. Não há aprovação ou execução disponível.</small></p>
</section>
<?php endif; ?>
<footer class="footer">
<strong>NO-GO operacional.</strong> Demonstração localhost em CI, com conexão PostgreSQL temporária de usuário somente leitura. Não há IAM de cliente, aprovação, execução, envio de mensagem, consumo de IA ou serviços externos.
</footer>
</main>
</body>
</html>
