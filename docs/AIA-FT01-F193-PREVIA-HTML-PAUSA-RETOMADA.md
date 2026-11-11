# FT01/F193 — Prévia HTML comparativa do ensaio F190

**11/11/2026. Relatório estático local sem JavaScript, servidor, armazenamento ou rede. Operação e produção: NO-GO.**

## Entrega

O utilitário `tools/render_f190_supervision.py` invoca duas vezes o cenário PHP F190, exige igualdade exata e executa a validação F191/F192 antes de gerar uma página HTML estática em stdout. O documento compara os estados PAUSADA e ABERTA (somente simulados) com todas as seis contagens de fila e as quatro contagens de revisão por fotografia.

A saída é um documento acessível em português, responsivo e sem botões, links externos, iframe, formulários, JavaScript, controle de cliente ou armazenamento. O rótulo NO-GO e a natureza fictícia permanecem visíveis. O processo não salva arquivo: o operador poderá redirecionar manualmente stdout em um ambiente de desenvolvimento, se desejar.

Dados externos não são aceitos; a fonte única é o CLI PHP offline existente. O HTML é gerado somente após rejeitar chaves extras, tipos divergentes, falsa conclusão ou autorização real. Nove testes Python cobrem acessibilidade, comparação, determinismo, ausência de rede e scripts, HTML hostil recusado, dados não autorizados e o caminho PHP real do laboratório.

## Como testar

```bash
python3 tools/render_f190_supervision.py
python3 -m unittest discover -s tests/Contracts -p 'test_f193_supervision_report.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

O quality gate executa o relatório mas descarta o HTML (sem persistência automática). Mantém baseline 835 PHP / lint 103. CRM PR #57 OPEN/DRAFT, 13 cenários reais NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT NO-GO. Sem mudança em main, VPS, credenciais, custo, cliente, produção ou deploy.
