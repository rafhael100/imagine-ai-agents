#!/usr/bin/env python3
"""Offline HTML read-only report for the F206 queue-health rehearsal."""
from html import escape
import sys
from verify_f206_health import STAGES, fixture, verify


def render(data):
    verify(data)  # Fail before displaying anything if the JSON is not closed.
    rows = []
    for stage in STAGES:
        item = data["stages"][stage]
        q = item["triage"]["queue"]
        c = item["capacity"]
        cells = [
            stage, item["classification"], ", ".join(item["signals"]),
            item["triage"]["admission"]["state"], str(q["pending"]),
            str(q["reserved"]), str(q["indeterminate"]),
            str(q["completed"]), str(c["jobs"]["used"]),
            str(c["jobs"]["remaining"]), c["alert"],
        ]
        rows.append("<tr>" + "".join(
            "<td>" + escape(str(cell), quote=True) + "</td>" for cell in cells
        ) + "</tr>")
    th = ("Etapa", "Situação simulada", "Sinais agregados", "Admissão",
          "Pendentes", "Reservadas", "Indeterminadas", "Concluídas",
          "Slots usados", "Slots restantes", "Capacidade")
    headers = "".join("<th scope='col'>" + escape(x) + "</th>" for x in th)
    return """<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Security-Policy"
 content="default-src 'none'; connect-src 'none'; script-src 'none';
 img-src 'none'; font-src 'none'; object-src 'none'; base-uri 'none';
 form-action 'none'; style-src 'unsafe-inline'">
<title>Imagine Agents | Saúde da fila sintética — NO-GO</title>
<style>
:root{font-family:system-ui,Arial,sans-serif;color:#24314a;background:#f5f7fb}
body{max-width:1200px;margin:32px auto;padding:0 20px}
h1{font-size:1.75rem}p{line-height:1.55}table{border-collapse:collapse;width:100%;
background:white;min-width:980px}th,td{padding:12px;text-align:left;
border-bottom:1px solid #dce2ed;font-size:14px;vertical-align:top}
th{background:#eef2f8;font-weight:700}
.panel{overflow-x:auto;border:1px solid #dce2ed;border-radius:12px}
footer{font-size:13px;margin:25px 0}
</style></head><body>
<h1>Saúde agregada da fila — laboratório</h1>
<p>Oito fotografias reproduzíveis, somente de dados inventados.
Nenhum estado desta página autoriza uma fila real, CRM ou execução de agente.</p>
<p><strong>Decisão operacional: NO-GO.</strong> Zero chamadas externas;
zero escritas e nenhum identificador de cliente.</p>
<div class="panel"><table aria-label="Comparação de oito estados sintéticos">
<thead><tr>""" + headers + "</tr></thead><tbody>" + "".join(rows) + """</tbody>
</table></div>
<footer>Contrato SYNTHETIC_QUEUE_HEALTH_REHEARSAL_V1. Relatório
gerado offline. Os sinais de capacidade e de incerteza são demonstrativos.</footer>
</body></html>
"""


def main():
    try:
        markup = render(fixture())
    except (ValueError, OSError, KeyError, TypeError):
        print("F206_STATIC_REPORT_DENIED operational_decision=NO_GO",
              file=sys.stderr)
        return 1
    sys.stdout.write(markup)
    return 0


if __name__ == "__main__":
    sys.exit(main())
