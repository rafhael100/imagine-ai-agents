# R01/EVID-37 — SEC-52 integrada, proteção de callback no coordenador compartilhado

**Verificado em 10/11/2026. Estado operacional: NO-GO.** Evidência apenas de PHP offline e dados inteiramente sintéticos.

## Prova de integração

- [SEC-52 / PR #235](https://github.com/rafhael100/imagine-ai-agents/pull/235), merge exclusivamente na `dev/ft01-aia-core-mocks-2026-11-06` no SHA `c74eabb82c0e69b9a1bbd5ed320a8ac5f247e7e0`.
- [CI do PR #42943048263](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42943048263) e [CI pós-merge #42943113650](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42943113650) concluídas **success**, a segunda no SHA exato `c74eabb82c0e69b9a1bbd5ed320a8ac5f247e7e0`. Gate: **640 testes PHP**, lint de **83 arquivos PHP** e verificadores Python offline.
- `SupervisedCoordinator` passa a negar reentrada do callback de sugestão mesmo através de outros despachantes ligados ao **mesmo coordenador**, além de bloquear invocação direta de `execute`, `suggest`, `start`, `transition` e demais métodos mutáveis durante o callback.
- **U74-01..07** exercitam sugestão reentrante, mutação fictícia já aprovada, leitura de segundo despachante, execução e transição diretas, negação mesmo com falha capturada e recuperação em Run novo. A sugestão externa falha sem registrar `SUGGESTED`, e o despachante encerra Run como `FALHOU`.
- Detalhamento em [SEC-52](AIA-FT01-SEC-52-GUARDA-COORDENADOR-CALLBACK.md). As evidências [EVID-36](AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md) e [EVID-35](AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md) permanecem históricas e verificáveis.

## O que não está comprovado

Essa guarda existe somente **em memória local, por coordenador/processo PHP**. Não impede chamadas em outro processo ou a outros serviços, não autentica serviço, não valida tenant em CRM real e não substitui revisão independente, IAM, ACL/RLS, logs operacionais nem anti-replay distribuído.

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT**, os **13 cenários reais OP-01 seguem `NAO_EXECUTADO`**, e R01-G01..G11 / CORE/SEG/AUT continuam **NO-GO**. Sem alteração de `main`, VPS, clientes, produção, segredos ou gastos. Revisão bilateral e homologação segregada permanecem indispensáveis antes de piloto real.
