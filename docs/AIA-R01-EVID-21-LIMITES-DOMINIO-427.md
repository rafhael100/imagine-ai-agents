# R01/EVID-21 — Referências, idempotência e aprovações com limites de memória

**Data:** 10/11/2026. **Estado operacional:** laboratório offline verificado; CRM real, piloto e produção **NO-GO**.

## Entregas técnicas verificadas

| Etapa | Resultado em código | CI de revisão e pós-merge |
|---|---|---|
| [SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) | `Intent.id`, chave de idempotência, material e `Approval.id/approver` limitados antes de assinatura/aprovação (128 bytes para refs e 4096 para material) | [#42847626974](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42847626974) e [#42847828963](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42847828963) success, merge `f72ebaccadec579083d9d091c9d7b2048402f91e` |
| [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) | Construtores `Run` e `Step` também exigem identificadores limitados, não só o coordenador; 80 e 96 bytes respectivamente | [#42848424558](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42848424558) e [#42848621884](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42848621884) success, merge `b8128f1619ebdceecb24a22cba958cc8e88a6f01` |

**Baseline atual da dev:** **427 testes PHP, lint em 82 arquivos PHP e verificadores Python** do contrato, isolamento e pacote bilateral OP-01. Os testes são sintéticos e não provam segurança ou integração de produção.

## O que permanece pendente e fora deste gate

- [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT**, sem contrato ou backend operacional homologado para o Agents.
- O [pacote de revisão EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) continua com **sete decisões**, **14 gates** e **13 cenários ainda não executados**. Nenhuma aprovação pode ser inferida de alterações de código, CI verde ou este documento.
- IAM real, autenticação entre serviços, autorização por tenant/principal/recurso/campo no backend CRM, replay entre instâncias, segregação de dados, LGPD, telemetria durável, rollback, revisão independente e liberação formal de piloto são requisitos de fases futuras.

**Decisão:** R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS/NO-GO**. Sem alterações em `main`, VPS, produção, credenciais, clientes, dados pessoais reais ou custos. Futuras integrações exigirão autenticação e aprovação específica, não apenas limite de memória no mock.
