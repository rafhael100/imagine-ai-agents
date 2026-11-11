# R01/EVID-48 — Diagnóstico de capacidade F204 com CI pós-merge

**11/11/2026. Operação real: NO-GO. Somente código PHP, testes e simulações offline.**

## Evidência verificável

**F204 / [PR #285](https://github.com/rafhael100/imagine-ai-agents/pull/285)** integrou a capacidade agregada de fila, SHA squash na branch `dev/ft01-aia-core-mocks-2026-11-06`:

- Commit integrado: `e6eee470f6b49d45488b2f93122cf5c452626695`.
- [CI pós-merge #43090143368](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43090143368): `completed/success` no SHA integrado.
- Contrato `SYNTHETIC_QUEUE_CAPACITY_V1` somente leitura, agregando tarefas, eventos de auditoria, decisões e orçamento protegido para pausa de emergência. Alertas NORMAL/NEAR_LIMIT/EXHAUSTED não equivalem a comandos de worker.
- Catorze regressões PHP U101-01..14, incluindo 64 vagas de trabalho, 256 de auditoria, 32 decisões, pausa e isolamento por tenant sintético.

O baseline integrado e validado é **865 testes PHP, lint de 107 arquivos, além de Python/DOM**. A nova contagem é oficial da branch de desenvolvimento; os indicadores anteriores 851/106 registrados na [EVID-47](AIA-R01-EVID-47-F200-F203-CI-POSTMERGE.md) permanecem evidências históricas, não status corrente.

## Bloqueios preservados

CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT/merged:false na última verificação. Os 13 cenários operacionais reais OP-01 continuam NAO_EXECUTADO e R01-G01..G11/CORE/SEG/AUT seguem **NO-GO**. Nenhum teste sintético substitui autorização final por empresa, identidade serviço-serviço, retenção/auditoria real, observabilidade segura, revisão independente ou aceite para produção.

A `main`, VPS, deploy, credenciais, dados pessoais reais, clientes e custos permaneceram intactos. O relatório novo é apenas diagnóstico da fila local; não permite novas admissões ou execução de agentes comerciais.
