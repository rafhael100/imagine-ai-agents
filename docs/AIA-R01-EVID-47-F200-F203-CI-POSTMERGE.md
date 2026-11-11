# R01/EVID-47 — Triagem PHP e jornada visual F200–F203, CIs pós-merge

**11/11/2026. Somente núcleo e laboratório offline. CRM real e produção: NO-GO.**

## Quatro entregas funcionalmente integradas na branch dev

| Entrega | PR | SHA squash pós-merge | GitHub Actions pós-merge |
| --- | --- | --- | --- |
| F200 — `MockQueueTriage` e precedência de risco em PHP | [#280](https://github.com/rafhael100/imagine-ai-agents/pull/280) | `99e0793c2b352794772d9f70db553c0728eacde1` | [#43086311234](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43086311234) **success** |
| F201 — ensaio PHP com seis estados e verificador de JSON | [#281](https://github.com/rafhael100/imagine-ai-agents/pull/281) | `3168601ddeeb8f98eae7a678c6de3fdda1038851` | [#43087249037](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43087249037) **success** |
| F202 — seleção dos seis estados no laboratório navegável | [#282](https://github.com/rafhael100/imagine-ai-agents/pull/282) | `d964fa0a0175ce2b1fd7030af47f2afead2a6df7` | [#43088119546](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43088119546) **success** |
| F203 — 24 ataques ao validador real do navegador | [#283](https://github.com/rafhael100/imagine-ai-agents/pull/283) | `c0cc7fa1de79c793d8bb48cb745ece50639c25d1` | [#43088648397](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43088648397) **success** |

As quatro CIs acima foram verificadas como `completed/success` nos commits exatos da branch `dev/ft01-aia-core-mocks-2026-11-06`; nenhuma falha foi omitida ou convertida em sucesso. O CI inicial de F202 detectou uma incompatibilidade do harness F198; ela foi corrigida no próprio PR antes da integração.

## Capacidade entregue, sem superestimar prontidão

- Triagem de fila de prioridade por PAUSED, REVIEW_REQUIRED, LEASE_IN_PROGRESS, PENDING e QUIET. As indicações não são comandos operacionais.
- CLI PHP demonstra seis transições, inclusive pausa e retomada que não apagam a incerteza. Verificador Python exige determinismo e esquema sem identificadores.
- Página F108 exibe a sequência visual com controles de leitura, paridade JSON PHP e preservação dos snapshots F182/F196.
- 24 cenários de ataque ao JavaScript real, rejeitados antes da renderização.
- **Baseline integrado: 851 testes PHP, lint de 106 arquivos + Python/DOM.** Acrescentados 16 cenários PHP U100, 12 contratos Python F201, 7 contratos Python F202 e 6 verificações Python F203 com 24 mutações adversariais Node.

## Impedimentos confirmados

O contrato interprojetos do CRM no [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) está **OPEN/DRAFT**, sem merge ou homologação. Os 13 cenários reais OP-01 continuam `NAO_EXECUTADO` nos registros; os gates R01-G01..G11/CORE/SEG/AUT permanecem **NO-GO**. CI sintético aprovada não substitui autenticação serviço-serviço, autorização final CRM, isolamento de tenant, evidência de execução, revisão independente nem liberação do piloto.

Esta rodada não alterou a `main`, VPS, infraestrutura, custos, credenciais, dados de clientes nem produção. Nenhum serviço comercial foi ativado.
