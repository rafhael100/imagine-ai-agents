<?php
declare(strict_types=1);

/**
 * AIA-P54: local-only read-only browser mock. This is not a CRM/Portal
 * endpoint and it NEVER receives operational tenant data or approvals.
 *
 * Run only:
 *   php -S 127.0.0.1:18754 -t examples
 *   http://127.0.0.1:18754/p54-review-queue-local.php
 */
require_once __DIR__.'/../src/Automation/ReviewQueueReadModel.php';

use ImagineAI\Automation\ReviewQueueReadModel;

function p54Deny(int $status):never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'P54_LOCAL_DEMO_DENIED_NO_OPERATIONAL_ACCESS';
    exit;
}

if(PHP_SAPI!=='cli-server'
    ||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)
    ||!in_array($_SERVER['SERVER_NAME']??'',['127.0.0.1','::1'],true)
    ||preg_match('/\A(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]{1,5})?\z/D',
        $_SERVER['HTTP_HOST']??'')!==1){
    p54Deny(403);
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')p54Deny(405);
if(array_keys($_GET)!==[] &&array_keys($_GET)!==['kind'])p54Deny(400);
$kind=$_GET['kind']??'all';
if(!is_string($kind)
    ||!in_array($kind,['all','human','task','notice'],true))p54Deny(400);

function p54E(string|int $value):string
{
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
$tenant='SYNTHETIC_TENANT_DEMO';
$read=[
    'decision'=>'LAB_READONLY_DRAFTS_NO_APPROVAL',
    'tenant_ref'=>$tenant,
    'drafts'=>[
        [
            'request_ref'=>'SYNTHETIC_REQUEST_P54_001',
            'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.str_repeat('A',24),
            'rule_ref'=>'SYNTHETIC_RULE_LEAD_THRESHOLD',
            'draft_kind'=>'HUMAN_REVIEW_DRAFT',
            'metric'=>'projected_leads','observed'=>'12','threshold'=>'10',
            'state'=>'AWAITING_HUMAN_REVIEW_LAB'
        ],
        [
            'request_ref'=>'SYNTHETIC_REQUEST_P54_001',
            'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.str_repeat('B',24),
            'rule_ref'=>'SYNTHETIC_RULE_CRM_DRAFT',
            'draft_kind'=>'CRM_TASK_DRAFT',
            'metric'=>'projected_customers','observed'=>'2','threshold'=>'2',
            'state'=>'AWAITING_HUMAN_REVIEW_LAB'
        ],
        [
            'request_ref'=>'SYNTHETIC_REQUEST_P54_002',
            'proposal_ref'=>'SYNTHETIC_PROPOSAL_'.str_repeat('C',24),
            'rule_ref'=>'SYNTHETIC_RULE_NOTICE_DRAFT',
            'draft_kind'=>'OPERATOR_NOTICE_DRAFT',
            'metric'=>'visits','observed'=>'120','threshold'=>'100',
            'state'=>'AWAITING_HUMAN_REVIEW_LAB'
        ],
    ],
    'ai_calls'=>0,
    'service_effects'=>0
];
try{
    $model=(new ReviewQueueReadModel())->present($read,$tenant);
}catch(Throwable){
    p54Deny(503);
}
$kindKey=[
    'all'=>null,'human'=>'HUMAN_REVIEW_DRAFT',
    'task'=>'CRM_TASK_DRAFT','notice'=>'OPERATOR_NOTICE_DRAFT'
][$kind];
$visible=array_values(array_filter($model['items'],
    static fn($x)=>$kindKey===null || $x['kind']===$kindKey));
$kindTitles=[
    'HUMAN_REVIEW_DRAFT'=>'Revisão humana',
    'CRM_TASK_DRAFT'=>'Sugestão de tarefa no CRM',
    'OPERATOR_NOTICE_DRAFT'=>'Aviso ao operador (rascunho)',
];
$metricTitles=[
    'visits'=>'Visitas simuladas',
    'projected_leads'=>'Leads projetados',
    'projected_customers'=>'Clientes projetados'
];
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>Imagine · Fila de propostas (demonstração local)</title>
  <style>
    :root{color-scheme:light;--orange:#F9941D;--ink:#1c2635;--muted:#64748b;--line:#e5eaf0;--bg:#f6f8fb;--white:#fff}
    *,*::before,*::after{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.6 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
    .shell{max-width:1080px;margin:auto;padding:32px 24px 56px}
    .top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:46px}
    .logo{font-size:26px;font-weight:850;letter-spacing:-.05em}
    .logo b{color:var(--orange)}
    .topnote{font-size:12px;color:#8b5b0e;background:#fff1d7;border:1px solid #f8d58b;border-radius:50px;padding:6px 12px;font-weight:700}
    h1{font-size:clamp(28px,4vw,42px);line-height:1.13;letter-spacing:-.04em;margin:0 0 12px}
    .intro{color:var(--muted);max-width:650px;margin:0 0 30px}
    .kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:32px}
    .kpi,.item{background:var(--white);border:1px solid var(--line);border-radius:17px;padding:22px;box-shadow:0 4px 18px rgba(28,38,53,.035)}
    .kpi .n{font-size:34px;font-weight:800;line-height:1.2;font-variant-numeric:tabular-nums}
    .kpi .l{color:var(--muted);font-size:13px;margin-top:4px}
    .heading{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px}
    h2{font-size:19px;letter-spacing:-.02em;margin:0}
    .filters{display:flex;gap:6px;flex-wrap:wrap}
    .filters a{font-size:13px;color:#526072;text-decoration:none;border:1px solid var(--line);padding:7px 12px;border-radius:12px;background:#fff}
    .filters a[aria-current="page"]{color:#7a4400;background:#fff1dd;border-color:#f6cb91;font-weight:700}
    .queue{display:grid;gap:12px}
    .item{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center}
    .item small{font-size:12px;letter-spacing:.02em;color:var(--muted)}
    .item h3{font-size:16px;margin:6px 0 4px}
    .mono{font:11px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;overflow-wrap:anywhere;color:#718096}
    .measure{text-align:right}
    .measure strong{display:block;font-size:22px}
    .measure em{font-style:normal;color:var(--muted);font-size:12px}
    .state{color:#7f560a;background:#fff4e5;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:700;display:inline-block;margin-top:10px}
    .empty,.footer{color:var(--muted);font-size:13px}
    .footer{margin-top:40px;border-top:1px solid var(--line);padding-top:18px}
    @media(max-width:660px){.shell{padding:22px 15px}.top{margin-bottom:30px}.kpis{grid-template-columns:1fr}.item{grid-template-columns:1fr}.measure{text-align:left}.topnote{font-size:10px}}
  </style>
</head>
<body>
  <main class="shell">
    <header class="top">
      <div class="logo">imagine<b>.</b></div>
      <div class="topnote">LABORATÓRIO · SEM AÇÕES</div>
    </header>
    <h1>Fila de propostas</h1>
    <p class="intro">Visualização demonstrativa dos resultados das automações determinísticas. Todas as empresas, referências e métricas aqui são fictícias. Nenhuma proposta pode ser aprovada ou executada nesta tela.</p>

    <section class="kpis" aria-label="Indicadores sintéticos">
      <div class="kpi"><div class="n"><?= p54E($model['total_pending']) ?></div><div class="l">Propostas para revisão</div></div>
      <div class="kpi"><div class="n"><?= p54E($model['by_kind']['crm_task_draft']) ?></div><div class="l">Sugestões de tarefas CRM</div></div>
      <div class="kpi"><div class="n"><?= p54E($model['by_kind']['operator_notice_draft']) ?></div><div class="l">Rascunhos de avisos</div></div>
    </section>

    <section aria-labelledby="queue-title">
      <div class="heading">
        <h2 id="queue-title">Propostas aguardando revisão</h2>
        <nav class="filters" aria-label="Filtrar tipos de propostas">
          <?php foreach(['all'=>'Todas','human'=>'Revisão','task'=>'Tarefas CRM','notice'=>'Avisos'] as $key=>$label): ?>
            <a href="?kind=<?= p54E($key) ?>" <?= $kind===$key?'aria-current="page"':'' ?>><?= p54E($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="queue">
        <?php if($visible===[]): ?>
          <div class="item empty">Nenhuma proposta sintética nesta categoria.</div>
        <?php else: ?>
          <?php foreach($visible as $item): ?>
            <article class="item">
              <div>
                <small><?= p54E($kindTitles[$item['kind']]) ?></small>
                <h3><?= p54E($metricTitles[$item['metric']]) ?></h3>
                <div class="mono"><?= p54E($item['request_ref']) ?> · <?= p54E($item['rule_ref']) ?></div>
                <div class="state">AGUARDANDO REVISÃO · NÃO EXECUTADO</div>
              </div>
              <div class="measure">
                <strong><?= p54E($item['observed']) ?></strong>
                <em>Limiar configurado: <?= p54E($item['threshold']) ?></em>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
    <footer class="footer">
      <strong>NO-GO operacional.</strong> Esta interface lê uma fixture local, sem banco de clientes, login, integração com n8n, envio de mensagens, chamadas de IA ou botões de aprovação. A futura Central depende de IAM real e homologação.
    </footer>
  </main>
</body>
</html>
