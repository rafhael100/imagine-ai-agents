# R01/EVID-20 — Fronteira canônica de ferramentas e limites de identificadores

**Data:** 10/11/2026. **Estado:** laboratório offline de dados sintéticos aprovado nos testes; **NO-GO** para integração e produção.

## Evidências de código e CI

| Entrega | Comportamento comprovado | SHA de integração | CI de revisão / pós-merge |
|---|---|---|---|
| [SEC-21 / PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) | `MockToolDispatcher` exige `Run` e `Step` criados e registrados pelo mesmo `SupervisedCoordinator`. Objetos clonados com mesmo ID e aprovação sintética forjada não executam ferramentas | `79fa9bb4cadae3a2ee82b334c9c40b32ab905032` | [#42836003077](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836003077) / [#42836105212](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836105212), ambas success |
| [SEC-22 / PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) | Limita a 80 bytes o ID de Run e 96 bytes o ID de Step no coordenador; recusa limites excedidos antes de criar estado ou evento | `581cdd9b382f4b5bd0493eca380cfee946dfe1dd` | [#42836558010](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836558010) / [#42836672922](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836672922), ambas success |

**Baseline da branch dev:** 414 testes PHP e lint de 82 arquivos PHP, mais verificadores Python do contrato OP-01, inventário de gates, isolamento e pacote de revisão bilateral. Estas verificações não representam percentual do produto real concluído.

## Onde a proteção é aplicada

A interface de ferramentas exposta pelo mock exige identidades de objetos canônicos e um histórico auditável. O coordenador interno conserva compatibilidade com fixtures históricas que utilizam Runs isoladas, **mas não deve ser exposto diretamente a chamadas externas**. O limite de tamanho de referência impede abuso de memória local no registro de estado, sem resolver concorrência distribuída nem replay em banco.

Os testes incluem tentativa de `SUGGEST` sem execução registrada, tentativa de `MUTATE` com aprovação sintética fabricada, clonagem de IDs, Step desanexado, operação legítima, recuperação após negação, limites exatos e IDs excessivos.

## Dependências independentes e próximas decisões

O [PR #57 do CRM](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT** e nenhuma API operacional, IAM, ACL ou token foi implementado por estas entregas. O [pacote bilateral OP-01 EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) continua com sete decisões em aberto, 14 gates e 13 cenários ainda não homologados.

Antes de construir adaptador CRM real, validar por responsáveis independentes: identidade serviço-serviço e pessoa delegada, autorização **no backend CRM** por empresa/principal/recurso/campo, antirreplay durável, LGPD, auditoria, rollback, homologação isolada e limites financeiros de piloto. **R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO.** `main`, VPS, produção, dados reais e custos permanecem fora do escopo.
