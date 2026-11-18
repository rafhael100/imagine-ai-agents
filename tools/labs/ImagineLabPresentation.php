<?php
declare(strict_types=1);

namespace ImagineAI\Presentation;

/** Fixed local presentation only: no session, database, network or authority. */
final class ImagineLabPresentation
{
    public static function logo(string $png): string
    {
        // Original Portal PNG, Git blob 678adeceb3a8ecdaeb6b576b9ee18d1bf0396f77.
        if (!hash_equals('009e7096173ec354acc5e702f5f0b2609a8b2e34a860d31b252fbb3dc6d0db58', hash('sha256', $png))) {
            throw new \InvalidArgumentException('IMAGINE_LOGO_DENIED');
        }

        return '<img class="brand-image" data-imagine-brand="original" alt="Imagine" width="200" height="55" src="data:image/png;base64,'.base64_encode($png).'">';
    }

    public static function navigation(string $active, bool $canCreate = false): string
    {
        if (!in_array($active, ['create', 'review'], true)) {
            throw new \InvalidArgumentException('IMAGINE_NAVIGATION_DENIED');
        }
        $links = '';
        if ($active === 'create' || $canCreate) {
            $links .= $active === 'create'
                ? '<a href="/p64-create-drafts-local.php" data-imagine-nav="create" aria-current="page">Criar rascunhos</a>'
                : '<a href="http://127.0.0.1:8787/p64-create-drafts-local.php" data-imagine-nav="create">Criar rascunhos</a>';
        }
        $links .= $active === 'review'
            ? '<a href="?kind=all" data-imagine-nav="review" aria-current="page">Fila de revisão</a>'
            : '<a href="http://127.0.0.1:8786/p56-live-pg-review-local.php" data-imagine-nav="review">Fila de revisão</a>';

        return '<nav class="lab-nav" data-imagine-lab-nav="true" aria-label="Navegação de propostas"><span class="nav-label">Propostas</span>'.$links.'</nav>';
    }

    public static function styles(): string
    {
        // Local aliases of Portal tokens b1fc205e272b5fb79b774741c38e7928aadc0446.
        // The darker support-text alias also meets AA on the grey/orange surfaces.
        return <<<'CSS'
:root{--imagine-orange:#F9941D;--imagine-ink:#171B19;--imagine-white:#FFFFFF;--imagine-surface:#F7F7F8;--imagine-muted:#737373;--imagine-border:#E8E8E8;--imagine-orange-soft:#FFF1E0;--imagine-orange-text:#9B4D00;--imagine-focus:#B75C00;--imagine-radius-control:6px;--imagine-radius-card:9px;--imagine-radius-panel:14px;--imagine-radius-navigation:10px;--imagine-sidebar-width:240px;--imagine-content-max:1380px;--imagine-touch-target:44px;--imagine-font-ui:Arial,Helvetica,sans-serif;--imagine-shadow-panel:0 6px 24px rgba(23,27,25,.035);--imagine-space-1:4px;--imagine-space-2:8px;--imagine-space-3:12px;--imagine-space-4:16px;--imagine-space-6:24px;--imagine-space-8:32px;--imagine-space-12:48px;--orange:var(--imagine-orange);--ink:var(--imagine-ink);--white:var(--imagine-white);--bg:var(--imagine-surface);--line:var(--imagine-border);--muted:#666666}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 var(--imagine-font-ui)}
a{color:var(--imagine-orange-text);text-underline-offset:4px}
a:focus-visible,button:focus-visible,input:focus-visible,[tabindex]:focus-visible{outline:3px solid var(--imagine-focus);outline-offset:4px}
.skip-link{position:absolute;z-index:10;top:12px;left:12px;transform:translateY(-160%);display:inline-flex;align-items:center;min-height:44px;padding:12px 16px;background:var(--white);border:2px solid var(--ink);border-radius:6px;color:var(--ink);font-weight:700}
.skip-link:focus{transform:translateY(0)}
.app-shell{display:grid;grid-template-columns:var(--imagine-sidebar-width) minmax(0,1fr);max-width:var(--imagine-content-max);min-height:100vh;margin:0 auto}
.sidebar{padding:32px 20px;border-right:1px solid var(--line);background:var(--white);display:flex;flex-direction:column;gap:32px;min-width:0}
.brand-image{display:block;max-width:100%;height:auto}
.product-name{margin:12px 0 0;font-size:14px;font-weight:700;color:var(--ink)}
.lab-nav{display:flex;flex-direction:column;gap:8px}
.nav-label{padding:0 12px 4px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.lab-nav a{display:flex;align-items:center;min-height:44px;padding:12px;border-radius:var(--imagine-radius-navigation);color:var(--ink);text-decoration:none;font-size:15px}
.lab-nav a:hover{background:var(--bg)}
.lab-nav a[aria-current="page"]{background:var(--imagine-orange-soft);box-shadow:inset 3px 0 var(--orange);color:var(--imagine-orange-text);font-weight:700}
.sidebar-note{margin:0;font-size:13px;color:var(--muted)}
.shell{min-width:0;padding:40px 40px 48px}
.workspace-header,.heading{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.workspace-header{margin-bottom:24px}
.eyebrow{margin:0;font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted)}
.flag{max-width:100%;padding:6px 12px;border-radius:20px;background:var(--imagine-orange-soft);color:var(--imagine-orange-text);font-size:12px;font-weight:700}
h1{font-size:clamp(28px,3.3vw,40px);line-height:1.2;letter-spacing:-.035em;margin:0 0 16px}
h2{font-size:21px;line-height:1.3;letter-spacing:-.02em;margin:0 0 12px}
h3{font-size:17px;line-height:1.4;margin:16px 0 8px}
p{margin:0 0 16px}.muted{color:var(--muted)}
.intro,.subtitle{max-width:740px;margin:0 0 32px;color:var(--muted)}
.layout{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:24px;align-items:start}
.card,.draft{min-width:0;padding:24px;border:1px solid var(--line);border-radius:var(--imagine-radius-panel);background:var(--white);box-shadow:var(--imagine-shadow-panel)}
.step{margin-bottom:8px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--imagine-orange-text)}
label{display:block;margin:20px 0 8px;font-size:14px;line-height:1.5;font-weight:700}
input:not([type=hidden]){width:100%;min-width:0;min-height:44px;padding:12px;border:1px solid #999999;border-radius:var(--imagine-radius-control);font:inherit;color:var(--ink);background:var(--white)}
.rates{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:16px}
.help{margin:8px 0 0;font-size:13px;color:var(--muted)}
button{min-height:44px;max-width:100%;margin-top:24px;padding:12px 20px;border:1px solid transparent;border-radius:var(--imagine-radius-control);font:inherit;font-weight:700;line-height:1.5;color:var(--ink);background:var(--orange);cursor:pointer}
button:hover{background:#F08A10}.wide{width:100%}
.rule{padding:16px 0;border-top:1px solid var(--line)}.rule strong{display:block}.rule:last-child{padding-bottom:0}
.notice,.detail-notice{padding:16px;border:1px solid #E5C292;border-radius:var(--imagine-radius-card);background:var(--imagine-orange-soft);color:var(--ink)}
.notice{margin-bottom:24px}.preview,.proposal-detail{margin-top:32px}
.metrics,.cards,.inputs{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr));gap:16px}
.metrics{margin:24px 0}.cards{margin-bottom:32px}
.metric{min-width:0;overflow-wrap:anywhere}.metrics .metric{padding:16px;background:var(--bg);border-radius:var(--imagine-radius-card)}
.metric b,.card>strong{display:block;font-size:clamp(24px,2.5vw,32px);line-height:1.3;font-variant-numeric:tabular-nums;letter-spacing:-.025em;overflow-wrap:anywhere}
.metric span,.card>span,small{font-size:13px;color:var(--muted)}
.preview .draft{padding:16px 0;border:0;border-top:1px solid var(--line);border-radius:0;box-shadow:none}.draft strong{display:block}
.pill,.state{display:inline-block;max-width:100%;padding:6px 10px;border-radius:6px;background:var(--imagine-orange-soft);color:var(--imagine-orange-text);font-size:12px;font-weight:700}
.pill{margin-top:8px}.state{margin-top:12px}
.review-status{margin-bottom:32px}.review-status strong{display:inline;font-size:inherit}
.filters{display:flex;flex-wrap:wrap;gap:8px}.filters a{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:8px 12px;border:1px solid var(--line);border-radius:var(--imagine-radius-navigation);color:var(--ink);background:var(--white);font-size:14px;text-decoration:none}
.filters a[aria-current="page"]{border-color:var(--orange);background:var(--imagine-orange-soft);color:var(--imagine-orange-text);font-weight:700}
.queue{display:grid;gap:16px;margin-top:16px}.queue .draft{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:center}.draft h3{margin:4px 0;font-size:18px}.queue .metric{text-align:right}.queue .metric strong{font-size:24px;line-height:1.3}
.mono{font:12px/1.6 ui-monospace,Consolas,monospace;color:var(--muted);overflow-wrap:anywhere}
.detail-link{display:inline-flex;align-items:center;min-height:44px;margin-top:8px;padding:8px 0;font-weight:700}
.condition{font-size:clamp(22px,3vw,28px);font-weight:700;letter-spacing:-.025em}
.inputs>div{min-width:0}.inputs dt{color:var(--muted);font-size:14px}.inputs dd{margin:8px 0 0;font-size:21px;font-weight:700;overflow-wrap:anywhere}
.footer{margin-top:32px;padding-top:24px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}
@media(max-width:1100px){.shell{padding:32px 24px}.layout{grid-template-columns:1fr}}
@media(max-width:900px){.app-shell{display:block}.sidebar{padding:24px;border-right:0;border-bottom:1px solid var(--line);gap:20px}.brand{display:flex;align-items:center;gap:24px;flex-wrap:wrap}.product-name{margin:0}.lab-nav{flex-direction:row;flex-wrap:wrap}.nav-label{flex-basis:100%;padding-left:0}.lab-nav a{flex:1;justify-content:center}.sidebar-note{display:none}}
@media(max-width:640px){.sidebar,.shell{padding:24px 16px}.workspace-header{gap:12px}.card,.draft{padding:20px}.cards,.inputs,.metrics,.rates{grid-template-columns:1fr}.queue .draft{grid-template-columns:1fr;gap:16px}.queue .metric{text-align:left}.heading{align-items:flex-start}.heading h2{margin-bottom:4px}.flag{font-size:12px}.intro,.subtitle{margin-bottom:24px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important}}
CSS;
    }
}
