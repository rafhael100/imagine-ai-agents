# R01/EVID-42 — SEC-60 e F187 integradas e verificadas

**11/11/2026.** Repositório `rafhael100/imagine-ai-agents`; branch de desenvolvimento `dev/ft01-aia-core-mocks-2026-11-06`. **Somente laboratório offline e sintético. Decisão operacional: NO-GO.**

## Entregas verificadas

| Entrega | PR integrado | Commit squash na dev | CI de PR | CI pós-merge | Gate |
| --- | --- | --- | --- | --- | --- |
| SEC-60 — guarda cross-Run contra mutações diretas em callback de IA | [#261](https://github.com/rafhael100/imagine-ai-agents/pull/261) | `8a9a280bef3766c9d8b45e4b556ad3ad13ab2ffc` | [#43019763862](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43019763862) **success** | [#43019868566](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43019868566) **success** | **810 testes PHP / lint 100**, Python/DOM |
| F187 — exportação/importação manual de até cinco rascunhos em JSON | [#262](https://github.com/rafhael100/imagine-ai-agents/pull/262) | `6351c9e8ac657e9e72be7830b591679c33d63910` | [#43020876061](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43020876061) **success** | [#43020983307](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43020983307) **success** | **810 testes PHP / lint 100**, Python/DOM F187 |

O último SHA realmente verificado e integrado é `6351c9e8ac657e9e72be7830b591679c33d63910`, com CI pós-merge #43020983307 `success`. Evidências: [SEC-60](AIA-FT01-SEC-60-GUARDA-CROSS-RUN-CALLBACK.md) e [F187](AIA-FT01-F187-TRANSFERENCIA-MANUAL-RASCUNHOS-JSON.md). Não confundir sucesso do GitHub Actions **offline** com autorização de implantação, segurança de processo PHP arbitrário ou homologação de dados de clientes.

## O que progrediu de fato

**SEC-60:** anteriormente a guarda de sugestão protegia somente o Run passado ao provedor, enquanto um outro Run canônico capturado pelo mesmo provedor permanecia mutável diretamente, bypassando o registro do coordenador. Agora um escopo read-only com token interno é aplicado a todos os Runs canônicos registrados durante callbacks de sugestão. O escopo é limpo em `finally`, a tentativa capturada continua marcada e invalida a sugestão. Dez regressões adversariais U91-01..10 cobrem transições cross-Run, criação de Step, manipulação de token, múltiplos Runs, inspeção read-only e recuperação.

**F187:** no catálogo de prévia F108, um usuário pode gerar um JSON sintético para cópia manual e colar outro pacote para importá-lo. Esquema `SYNTHETIC_AGENT_DRAFTS_V1` com apenas `{name,template}`, máximo 5 itens / 4096 caracteres, nomes NFC e modelos fechados. O importador valida o pacote completo antes de substituir a lista na memória. Pacotes inválidos não alteram a coleção nem ecoam seu conteúdo; importação de lista vazia válida é uma ação explícita de limpeza. Sete contratos Python F187 e o smoke Node DOM exercitam roundtrip, schema, atomicidade, colisões, códigos hostis, capacidade e reset. **Não há armazenamento, API, dados de clientes, upload/download automático ou publicação de agente.**

## Limites e estado dos bloqueios

- Baseline atual: **810 testes PHP**, **100 arquivos PHP com lint** mais Python e smoke de DOM. Tests PHP sintéticos não validam carga distribuída, IAM, segregação real de empresas ou sandbox.
- `main` confirmada **inalterada** no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. VPS e produção não foram modificadas.
- O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) foi verificado **OPEN/DRAFT, merged:false**. Os 13 cenários OP-01 de homologação real permanecem `NAO_EXECUTADO` nas evidências do projeto; R01-G01..G11/CORE/SEG/AUT seguem **NO-GO**.
- Antes de produção: contratos bilaterais assinados, OAuth/IAM de serviço, autorização pelo proprietário do recurso, política LGPD, retenção e exclusão verificadas, revisão AppSec/Infra independente, segurança do worker, storage durável, estratégia de rollback, monitoramento operacional, homologação e liberação formal do piloto.

Históricos anteriores [EVID-40](AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md) e [EVID-41](AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md) preservados. Nada nesta evidência autoriza mudança em `main`, deploy, conexão com CRM, custos ou uso de dados pessoais reais.
