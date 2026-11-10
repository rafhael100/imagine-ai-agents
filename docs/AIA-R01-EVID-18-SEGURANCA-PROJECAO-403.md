# R01/EVID-18 — Validação de projeção antes da trilha de sucesso

**Data:** 10/11/2026. **Estado:** execução offline verificada, integração real CRM **NO-GO**.

## Mudança técnica verificada

O [PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) foi integrado **somente à dev** com SHA `c064a640fea07f6f353e9fe4329380c5a0354242`. Corrigiu uma divergência de auditoria: a borda interna de leitura passou a verificar **todos** os valores da projeção antes de marcar leitura permitida. Um retorno sintético com referência a outro recurso, estágio falso, campo extra, valor com aparência de informação pessoal ou campo ausente recebe `BRIDGE_PROJECTION_DENIED` e auditoria `DENIED/PROJECTION`. Somente o contrato sintético correto registra `READ_SYNTHETIC_ONLY/NONE`.

**Evidências:**
- [CI de revisão #42759147342](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759147342) — success no HEAD da revisão.
- [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) — success no SHA integrado.
- **403 testes PHP, lint de 82 arquivos PHP, verificações Python de contrato e isolamento.**
- Regressões **U41-01 a U41-06**, com leitor de laboratório alternativo que gera respostas malformadas; não usa rede ou dados reais.

A interface `CrmReader` já existente foi utilizada como contrato de teste e não habilita conexão ao CRM, SQL, APIs externas ou privilégios. Os valores `ref/stage` e `SYNTHETIC_NEW` continuam exclusivamente fictícios.

## Dependências reais não atendidas

A proposta documental [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT sem merge**. [EVID-17](AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md) fixa seu conteúdo para inspeção offline, mas não representa autorização bilateral. O CRM precisa confirmar identidade serviço-serviço, ACL por empresa/principal/recurso/campo em seu backend, dados e estados reais, privacidade/LGPD, replay distribuído, logs, homologação isolada e rollback. Direção e donos competentes devem aprovar piloto e limites de custo separadamente.

**R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO.** Não modificar `main`, VPS, produção, clientes, credenciais ou fornecedores pagos por causa de testes sintéticos.

## Higiene documental da rodada

O [README](../README.md) foi condensado para apresentar somente comandos/estado vigentes. Sua versão anterior, de cerca de 84 KB, foi preservada integralmente em [histórico](historico/README-ANTES-CONSOLIDACAO-2026-11-10.md). O teste de status canônico deve garantir que a versão histórica permaneça íntegra e que o README atual permaneça curto, sem substituir evidências.
