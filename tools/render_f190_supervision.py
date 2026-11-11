#!/usr/bin/env python3
"""F193: inert local HTML comparison of synthetic F190 pause/resume snapshots.

Never contacts a CRM, writes files or reads credentials/user data. The output
is a static example, not an administrative control or a production dashboard.
"""
import html
import subprocess
import sys

from verify_f190_rehearsal import run_fixture, verify


HEAD = """<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'">
<title>Imagine Agentes de IA | Ensaio offline F193</title>
<style>
:root { color-scheme: light; font-family: system-ui, sans-serif; background: #f4f6f9; color: #15243b; }
body { margin: 0; padding: 2rem 1rem; }
main { max-width: 820px; margin: auto; }
header, section, aside, footer { background: white; border: 1px solid #d8e0ea; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
h1 { margin: .25rem 0; font-size: 1.6rem; }
h2 { margin: 0 0 .75rem; font-size: 1.15rem; }
p { line-height: 1.5; }
.warn { font-weight: 700; color: #932627; }
.state { font-weight: 700; }
table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
th, td { padding: .65rem .4rem; border-bottom: 1px solid #e0e5ed; text-align: left; }
td { text-align: right; }
@media(max-width:480px) { body { padding: .65rem; } header, section, aside, footer { padding: .75rem; } }
</style>
</head>
<body><main>
<header><p class="warn">NO-GO | DADOS FICTÍCIOS | SEM OPERACÃO REAL</p>
<h1>Ensaio de pausa e retomada</h1>
<p>Visualização estática das duas fotografias verificadas da fila sintética.
Não existe botão operacional, rede, CRM, trabalhador ou autorização de cliente.</p></header>
"""
FOOT = """<footer><p>O resultado indeterminado continua indeterminado após dois pareceres.
As métricas são exclusivas da simulação F190. Nenhuma integração de produção foi validada.</p>
<p class="warn">OPERAÇÃO REAL: NO-GO</p></footer></main></body></html>
"""

METRICS = (
    ("Pendentes", "pending"), ("Reservados", "reserved"),
    ("Concluídos", "completed"), ("Falhas", "failed"),
    ("Cancelados", "cancelled"), ("Indeterminados", "indeterminate"),
)
REVIEWS = (
    ("Aguardando primeira revisão", "awaiting_first"),
    ("Aguardando segunda revisão", "awaiting_second"),
    ("Revisados sem conclusão", "reviewed_unresolved"),
    ("Pareceres conflitantes", "conflict"),
)


def render(payload):
    """Render only validated synthetic counters; never render arbitrary fields."""
    verify(payload)
    output = [HEAD]
    for stage, title, state in (
        ("paused", "Durante a pausa", "PAUSED"),
        ("resumed", "Após a retomada", "OPEN"),
    ):
        snapshot = payload[stage]
        label = "PAUSADA" if state == "PAUSED" else "ABERTA"
        output.append(
            '<section aria-labelledby="' + stage + '-title">'
            '<h2 id="' + stage + '-title">' + html.escape(title) + '</h2>'
            '<p class="state">Admissão: ' + label + ' — SOMENTE SIMULADA</p>'
            '<table><caption>Fila sintética</caption><thead><tr>'
            '<th scope="col">Estado</th><th scope="col">Quantidade</th>'
            '</tr></thead><tbody>'
        )
        for name, key in METRICS:
            output.append(
                '<tr><th scope="row">' + html.escape(name) +
                '</th><td>' + str(snapshot["queue"][key]) + '</td></tr>'
            )
        output.append('</tbody></table><table><caption>Revisão sintética</caption>'
                      '<thead><tr><th scope="col">Estado</th><th scope="col">Quantidade</th>'
                      '</tr></thead><tbody>')
        for name, key in REVIEWS:
            output.append(
                '<tr><th scope="row">' + html.escape(name) +
                '</th><td>' + str(snapshot["review"][key]) + '</td></tr>'
            )
        output.append('</tbody></table></section>')
    before = payload["paused"]["queue"]
    after = payload["resumed"]["queue"]
    output.append(
        '<aside aria-labelledby="transition-title"><h2 id="transition-title">'
        'Diferenças entre os dois estados</h2>'
        '<p>A retomada concluiu apenas o pedido ainda pendente. '
        'O resultado incerto permanece sem resolução.</p>'
        '<table><caption>Variação sintética</caption><thead><tr>'
        '<th scope="col">Estado</th><th scope="col">Variação</th>'
        '</tr></thead><tbody>'
    )
    for name, key in METRICS:
        delta = after[key] - before[key]
        change = "+" + str(delta) if delta > 0 else (
            "−" + str(-delta) if delta < 0 else "0"
        )
        output.append(
            '<tr><th scope="row">' + html.escape(name) +
            '</th><td>' + change + '</td></tr>'
        )
    output.append('</tbody></table><p class="warn">'
                  'INDETERMINADO: 1 antes e depois. Revisão não concede GO.'
                  '</p></aside>')
    output.append(FOOT)
    return "\n".join(output)


def main():
    try:
        first, second = run_fixture(), run_fixture()
        if first != second:
            raise ValueError("NON_DETERMINISTIC")
        page = render(first)
    except (ValueError, OSError, subprocess.TimeoutExpired):
        print("F193_STATIC_REPORT_FAILED operational_decision=NO_GO",
              file=sys.stderr)
        return 1
    sys.stdout.write(page)
    return 0


if __name__ == "__main__":
    sys.exit(main())
