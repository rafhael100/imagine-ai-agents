# R01/EVID-44 — Verificação, supervisão visual e transparência do laboratório

**11/11/2026. PHP/Python/HTML offline. CRM real, piloto e produção: NO-GO.**

## Cinco entregas integradas com CI pós-merge verificada

| Entrega | PR | SHA squash na dev | CI pós-merge |
| --- | --- | --- | --- |
| F191 — verificador determinístico de pausa/retomada | [#268](https://github.com/rafhael100/imagine-ai-agents/pull/268) | `7fd30ac8ec3b5b5dc7885daa0802e583c15964d1` | [#43047660682](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43047660682) success |
| F192 — fechar todas as contagens e indicadores de segurança | [#269](https://github.com/rafhael100/imagine-ai-agents/pull/269) | `6867b8a2ffb534451c2f4dd1b979eb9a9e9d661f` | [#43048120334](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43048120334) success |
| F193 — prévia HTML estática dos dois snapshots | [#270](https://github.com/rafhael100/imagine-ai-agents/pull/270) | `2e386ef62078da610e3c146fb54ab64a93dee068` | [#43049412790](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43049412790) success |
| F194 — métricas F108 sincronizadas ao status canônico | [#271](https://github.com/rafhael100/imagine-ai-agents/pull/271) | `f4cbb06013ab03610bdf609ed629923db88a2053` | [#43049695153](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43049695153) success |
| F195 — diferenças agregadas de PAUSED/OPEN na prévia | [#272](https://github.com/rafhael100/imagine-ai-agents/pull/272) | `e100631b047f5153d5c3a912bf4fd298b619f32a` | [#43050336317](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43050336317) success |

Cada CI pós-merge acima foi conferida como `completed/success` sobre o SHA squash exato na branch de desenvolvimento, não apenas na branch do PR. Não foram declarados aprovados testes que ainda estivessem pendentes.

## Resultado técnico

Baseline PHP inalterado: **835 testes PHP, lint 103 arquivos + Python/DOM**. Os incrementos acrescentam **37 testes Python** de contratos, esquema fechado, renderização e proteção contra deriva. F191/F192 reforçam verificação fail-closed; F193 exibe comparação estática sem APIs; F194 elimina métricas históricas enganosas; F195 mostra variações seguras, sem resolver artificialmente `INDETERMINADO`.

## Barreiras que permanecem

CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) verificado **OPEN/DRAFT/merged:false**; 13 casos reais OP-01 continuam `NAO_EXECUTADO` nos registros e gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. Toda esta rodada usou fixtures locais e não constitui autenticação, IAM, homologação bilateral, worker ou auditoria operacional.

`main` permaneceu em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` na verificação. Nenhuma mudança de VPS, deploy, produção, clientes, credenciais, dados reais ou novos custos. GO requer pareceres independentes, contrato CRM aprovado e testes reais com evidência.
