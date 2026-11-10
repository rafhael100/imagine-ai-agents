# AIA-R01/EVID-15 — Handoff da ferramenta de leitura governada (10/11/2026)

**Decisão:** LABORATÓRIO OFFLINE VALIDADO; **NO-GO** para CRM real, piloto ou produção.

## Entregas verificáveis

| Marco | Resultado de código | Evidência |
|---|---|---|
| [F1-30 / PR #173](https://github.com/rafhael100/imagine-ai-agents/pull/173) | `MockToolDispatcher.readThroughBoundary` permite ferramenta READ **somente pela borda OP-01** com concessão de capacidade, contrato fechado, política de owner, trust, replay, auditoria e telemetria simulados; `dispatch(READ)` legado segue negado | [CI de revisão #42748809037](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42748809037), [pós-merge #42748908649](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42748908649) success; merge dev `f68b774ee69f1d1d5f9e3c88ddeea41676a56bc7` |
| [SEC-16 / PR #174](https://github.com/rafhael100/imagine-ai-agents/pull/174) | Teto local de 1.024 eventos na trilha do despachante; sem espaço, bloqueia antes de chamadas mutáveis ou consumo de nonce | [CI de revisão #42749218415](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749218415), [pós-merge #42749287014](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749287014) success; merge dev `35ac011be66f0efc575692fef10783579eae7aaa` |
| [SEC-17 / PR #175](https://github.com/rafhael100/imagine-ai-agents/pull/175) | Valida saída fechada de `SUGGEST`; não deixa texto forjado, campos extras nem erro bruto do provedor cruzarem a ferramenta | [CI de revisão #42749711492](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749711492), [pós-merge #42749779477](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749779477) success; merge dev `34cacc742837cabd8571668619a6116d96faba05` |

**Gate vigente:** **389 testes PHP / 82 arquivos PHP lint**, além dos testes Python de contrato, isolamento e demonstração no SHA revisado. Os testes foram executados com dados fictícios, sem rede nem autorização real.

## O que falta para produto operacional

A [proposta contratual CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT e não homologada**. Aia e CRM precisam concluir decisões sobre:

1. Serviço autenticado (emissor, audience, identidade delegada, expiração/revogação, anti-replay distribuído);
2. Política de autorização **executada dentro do CRM** por empresa, usuário, lead, operação e campo (deny-by-default; sem enumeração);
3. Campos operacionais, versões dos contratos, respostas/erros e bases de privacidade;
4. Ambiente isolado para testes de integração reais, observabilidade, retenção, rollback e revisão independente;
5. Responsáveis nomeados, escopo, teto financeiro e aceite específico do piloto (produção exige aceite posterior).

Esses controles correspondem aos gates **R01-G01..G11 e CRM CORE/SEG/AUT**, que seguem **ABERTOS**. Não presumir aprovação a partir de CI sintética ou de comentário no PR.

**Próximo marco efetivo:** contrato OP-01 homologado + serviço de autenticação e ACL real revisados; somente então implementar e testar adaptador CRM e piloto autorizado. Sem deploy, dados pessoais, alterações em `main`, VPS ou novos custos até liberação específica.
