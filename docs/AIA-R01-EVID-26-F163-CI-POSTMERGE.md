# R01/EVID-26 — Evidência pós-merge do ensaio sintético OP-01

**Verificado em:** 10/11/2026. **Decisão operacional: NO-GO.**

## Evidência no GitHub

- [PR #211](https://github.com/rafhael100/imagine-ai-agents/pull/211): `test(R01/F163): ensaiar os 13 cenários OP-01 sem declarar aceite do CRM`, merge por squash **somente na dev** `dev/ft01-aia-core-mocks-2026-11-06`.
- **Commit pós-merge:** `28086d76d2fe15cfc592e385d94569c78cc998e2`.
- **CI pós-merge verificada:** [Actions #42894696737](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42894696737), `completed/success`, com **546 testes PHP / 82 arquivos PHP no lint**, verificadores Python, matriz de contrato e demonstrações sem rede.
- As 13 novas regressões PHP (`U63-OP01-N01..N11`, `U63-OP01-P01..P02`) exercitam o consumidor **fictício** e usam a borda de leitura sintética.
- Novo verificador Python confere a correspondência 13:13 com `docs/evidencias/op01-casos-homologacao.csv` e rejeita tentativa de declarar cenário real executado no CSV.

## O que permanece pendente

Os 13 cenários reais continuam **NAO_EXECUTADO** no registro oficial, e as 7 decisões `OP01-R01..R07` seguem abertas. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT; não houve endpoint, chamada ao CRM, emissão de credencial, testes reais de ACL/RLS, verificação de service-to-service auth, prova distribuída ou parecer independente. R01-G01..G11 e CORE/SEG/AUT estão **ABERTOS/NO-GO**.

Uma CI verde do simulador **não deve** ser interpretada como homologação operacional, aprovação de segurança independente nem liberação do piloto. Sem `main`, VPS, produção, dados de clientes, segredos ou novos custos.
