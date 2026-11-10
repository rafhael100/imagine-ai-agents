# R01/EVID-23 — Identidade de tentativas e limites de evidências de uso

**Data:** 10/11/2026. **Estado operacional:** laboratório PHP offline com dados sintéticos, sem CRM real; **NO-GO** para piloto e produção.

## Implementações validadas

| Alteração | Correção | Evidência da revisão e após o merge |
|---|---|---|
| [SEC-26 / PR #194](https://github.com/rafhael100/imagine-ai-agents/pull/194) | Medições, tentativas e provas do ledger passam a vincular o **estado de transporte** a ID, contexto e intenção, impedindo colisões entre `TIMEOUT` e `ENVIADA` com mesmo ID | [CI PR #42863560128](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863560128) e [CI pós-merge #42863662530](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863662530), ambas success; merge dev `dbb9a034744d0b6d1a725008eae7b548e367f513` |
| [SEC-27 / PR #195](https://github.com/rafhael100/imagine-ai-agents/pull/195) | IDs de `Attempt` e `Usage` limitados a 128 bytes com sufixo, `Usage.units` de 0 a 1.000.000 e prova sintética com sufixo obrigatório | [CI PR #42864200360](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864200360) e [CI pós-merge #42864322239](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864322239), ambas success; merge dev `3957bb3fe1ac22afc0c16820caee11c79ff5f480` |

**Gate atual:** **446 casos PHP**, lint de **82 arquivos PHP**, testes Python do contrato, prontidão, pacote bilateral e isolamento. Regressões U48-01..06 e U49-01..07, total de 13 casos novos comparado ao baseline de 433. Somente a branch `dev/ft01-aia-core-mocks-2026-11-06` foi atualizada. Os testes aprovados não significam que o produto esteja pronto para usuários.

## O que permanece bloqueado

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT** e não contém API operacional homologada para os Agentes. O [pacote bilateral OP-01 EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) mantém sete decisões técnicas, **14 gates** e **13 cenários ainda sem execução real**. Autenticação serviço-serviço, ACL por tenant/recurso/campo **no backend CRM**, revisão independente de segurança e privacidade, persistência, telemetria, antirreplay distribuído, infraestrutura de homologação e rollback são dependências reais.

**R01-G01..G11 e CRM CORE/SEG/AUT: ABERTOS/NO-GO.** Sem alterações na `main`, VPS, produção, clientes, dados pessoais reais, segredos, fornecedores pagos ou novos custos.
