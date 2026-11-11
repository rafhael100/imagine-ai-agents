# FT01/F195 — Delta seguro dos estados PAUSED/OPEN na prévia F193

**11/11/2026 — Simulação offline. Não é operação de CRM, autorização, aprovação ou produção.**

## Incremento

A prévia estática F193 apresentava as duas fotografias da fila fictícia, mas exigia comparar manualmente dez métricas em cada estado. O novo resumo de transição calcula somente a diferença de seis contagens agregadas da fila **depois** da validação estrita F191/F192. No ensaio canônico, pendentes caem em um, concluídos sobem em um, e indeterminados permanecem em um. Nenhuma transição de revisão converte incerteza em autorização.

O painel foi incluído como elemento HTML `aside` acessível e continua sem JavaScript, rede, formulário, identidade ou armazenamento. Os números são convertidos somente de inteiros já validados, com sinal legível e valores zero explícitos. Não acrescenta ações de fila nem autorizações reais.

Seis regressões Python conferem os sinais -1/+1/0, demais zeros, invariantes NO-GO, recusa de contagem maliciosa e determinismo. O novo teste compõe o quality gate; a base PHP permanece em 835 casos e lint 103.

## Execução

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f195_transition_delta.py' -v
python3 tools/render_f190_supervision.py
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

CRM PR #57 continua OPEN/DRAFT, 13 casos reais OP-01 NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. Sem main, VPS, deploy, produção, clientes, segredos, IAM real ou novos custos.
