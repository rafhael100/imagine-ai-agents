# Imagine AI Agents P7 — revisão técnica consolidada de P4 a P6

**16/11/2026 — candidato DRAFT/NO-GO sem implantação.**

Este candidato foi criado em cima da [PR #338](https://github.com/rafhael100/imagine-ai-agents/pull/338) (MVP demonstrável Docker) e incorpora as entregas do mesmo ramo:

- **[P4 #347](https://github.com/rafhael100/imagine-ai-agents/pull/347)** — PostgreSQL16 efêmero com cota transacional, idempotência por request e aprovação terminal; ensaio com 12 processos concorrentes. Evidência: CI [#43809021858](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43809021858).
- **[P5 #348](https://github.com/rafhael100/imagine-ai-agents/pull/348)** — política pura de seleção de automação versus potencial chamada a IA, cinco rotas gratuitas, 30 negativas, sempre 0 créditos cobrados e 0 chamadas externas. Evidência: CI [#43809423148](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43809423148).
- **[P6 #350](https://github.com/rafhael100/imagine-ai-agents/pull/350)** — contrato de evento estritamente canônico, outbox PG16 idempotente, leasing com `SKIP LOCKED`, confirmação e dead-letter após 3 tentativas. Evidência: CI [#43809862594](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43809862594).

O novo workflow `ci-p7-product-integrated.yml` executa **P4 + P5 + P6 + regressões da UI P0, P1 e domínio PHP P2** no mesmo commit, com PostgreSQL16 descartável no GitHub Actions.

## Qualidade integral R01

O workflow R01 da árvore original é executado também nesta branch, sem modificar seus 879 casos oficiais. A contagem de lint dos arquivos em `src`, `tests/Unit`, `examples` deve aumentar de **117 para 119**, porque uma classe de domínio P2 e duas classes PHP puras P5/P6 foram adicionadas em `src/MockOrchestration`. O adaptador PG16 permanece em `tools/labs` para cumprir a regra de isolamento. Atualizações correspondentes foram feitas na validação e nos dois contratos Python que exigiam o número anterior.

**Aprovação de CI não equivale a revisão independente.** Não transportar os adaptadores `tools/labs` para serviços, CRM ou VPS. O outbox não possui receptor n8n e o roteador IA não reserva créditos nem aciona modelos. São candidatos de arquitetura e testes, não funcionalidades operacionais vendáveis.

## Dependências para produção

Acesso via Portal com login independente, identidade S2S segura, tenant e permissões derivados de fatos CRM persistidos, aprovação IAM, RBAC/ABAC e campo/lead, contratos e estimativa de consumo de IA com reserva transacional e conciliação de fornecedor, worker autenticado do n8n, política de egress, retenção/auditoria LGPD e revisão CORE/SEG/DBA/IAM. A PR CRM OP-01 [#57](https://github.com/rafhael100/imagine-crm/pull/57) ainda exige 14 gates, 7 decisões e 13 cenários de homologação.

Nenhum merge, deploy, publicação, chave privada, cliente real ou cobrança é autorizado pela CI.
