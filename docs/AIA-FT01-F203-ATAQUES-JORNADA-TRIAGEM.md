# FT01/F203 — Testes adversariais da jornada visual de triagem F202

**11/11/2026. Teste local offline. Produção e CRM real: NO-GO.**

## Entrega

O smoke Node `tests/Browser/f202_triage_tamper_smoke.cjs` extrai a implementação **real** de validação JavaScript do painel F202 e a executa, sem navegador, rede ou dependências externas, com um objeto DOM sintético. O pacote F201 válido precisa passar e renderizar a etapa Retomada mantendo uma execução indeterminada.

Depois, **24 cargas adversariais** são submetidas ao mesmo código de produção do laboratório. Incluem GO falso, autorização real inventada, chamada externa, escrita, campo extra no envelope, fase apagada, fase extra, referências individuais, HTML inserido em contador, booleano em lugar de inteiro, valor negativo, resolução indevida de incerteza, estado incorreto da admissão, passo que tenta disparar worker, contagem conflitante e chave obrigatória ausente.

Cada ataque precisa ser recusado antes de preencher `triage-view-state`. As falhas exibidas ao console de testes são genéricas e nunca transformam a simulação em ação externa ou autorização real.

## Regra de CI

`tests/Contracts/test_f203_triage_adversarial.py` inclui seis verificações sobre o harness, os 24 casos e a execução Node. O gate offline executa o smoke adicional se Node já estiver instalado. Não instala pacotes ou faz downloads. O baseline **851 testes PHP / lint 106** permanece inalterado.

```bash
node tests/Browser/f202_triage_tamper_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f203_triage_adversarial.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=106 EXPECTED_CASES=851 bash tools/run_r01_offline_quality_gate.sh
```

Não implementa IAM, domínio multiempresa real, deploy, CRM operacional, motor de agentes externo ou homologação. CRM PR #57 OPEN/DRAFT, 13 cenários OP-01 reais NAO_EXECUTADO e R01-G01..G11/CORE/SEG/AUT NO-GO. `main`, VPS, clientes, segredos e gastos inalterados.
