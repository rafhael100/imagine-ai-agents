# FT01/F196 — Visualizar pausa e retomada no laboratório F108

**11/11/2026. Somente dados sintéticos PHP/HTML; CRM real e produção: NO-GO.**

## Implementação
O laboratório navegável `examples/f108-laboratorio-navegavel.html` agora possui um painel independente de transição F190 dentro da página de supervisão. Dois controles acessíveis selecionam os snapshots PAUSED e OPEN, mostrando somente pendentes, concluídos, indeterminados e revisados sem conclusão. Nunca alteram o snapshot principal F182, os votos hipotéticos F184 ou a fila do exemplo PHP.

O pacote `SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1` é incorporado estaticamente no HTML. Antes de montar qualquer métrica no navegador, um verificador de contrato JavaScript valida rigorosamente chaves fechadas, todas as contagens e as flags NO_GO em cada snapshot. Falha em vez de exibir números caso o pacote seja malformado. Os botões são somente de visualização; não há servidor, identidade real, gravação ou permissão.

## Provas
- Teste Python `test_f196_transition_tab.py` compara **todo o JSON** incorporado com a saída real de `php examples/f190-queue-admission-demo.php` e verifica esquema, segurança e isolamento dos snapshots.
- Smoke DOM Node seleciona PAUSED → OPEN → PAUSED, exige invariância da incerteza e confirma que F182/F184 permanecem separados.
- O quality gate offline invoca esses novos contratos, preservando **835 testes PHP / lint 103**, mais testes Python/DOM.

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f196_transition_tab.py' -v
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

O CRM PR #57 permanece OPEN/DRAFT, 13 homologações reais OP-01 ainda NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. Não altera main, VPS, deploy, credenciais, dados reais, clientes ou custos.
