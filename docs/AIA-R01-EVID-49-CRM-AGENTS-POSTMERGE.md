# AIA R01 / EVID-49 — F208 e F209 em código CRM ↔ AI Agents

**Data:** 14/11/2026. **Estado:** desenvolvimento sintético validado; operação e produção **NO-GO**.

## Evidência verificada

| Entrega | PR | HEAD testada | Commit squash na DEV | CI PR / CI pós-merge |
| --- | --- | --- | --- | --- |
| F208, parser/resposta recíprocos PHP | [#293](https://github.com/rafhael100/imagine-ai-agents/pull/293) | `d2d62cd4a3913e6094dcf3b1dc25ec0aebd627ea` | `54478ea25193bbe3a61fb87d9056ff4baace9149` | [43605107022](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43605107022) / [43605185307](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43605185307) — success |
| F209, matriz dos 13 cenários em código sintético | [#294](https://github.com/rafhael100/imagine-ai-agents/pull/294) | `0b2df8c70cfbd75b3237e7981813cb954841a809` | `8461480179461710ca74b3641a4503e2a9cdc182` | [43605536868](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43605536868) / [43605619299](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43605619299) — success |

Todos os quatro workflows da tabela foram conferidos como `completed/success` nos commits indicados. O CI também confere a identidade visual F108 original e a suíte offline: **879 casos PHP, 110 arquivos PHP em lint, contratos Python/Node**. A branch `main` permaneceu em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` durante estas entregas.

## O que o código demonstra e o que não demonstra

- O workflow `r01-dev-integrity.yml` executa primeiro o quality gate offline local e depois faz checkout **read-only** do CRM no SHA imutável `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad`, conferido por `git rev-parse HEAD` antes de chamar PHP. Sem persistir credenciais.
- F208 compara **classes PHP efetivas** `AiaReadOnlyMockBoundary`, `AgentsReadResponseInspector` (CRM) com `MockCrmReadRequest` e `MockCrmPublicResponseContract` (Agents). Requisições sintáticas válidas ou inválidas nunca autorizam leitura real; tentativa de outro tenant tem a mesma resposta negativa.
- F209 percorre **OP01-N01..N11/P01..P02** com código sintético dos dois repositórios e compara os identificadores ao pacote bilateral vigente. O resultado é **13 cenários ensaiados em fonte, 0 homologados**, `NAO_EXECUTADO` em cada caso, `NO_GO` operacional. O verificador Python fecha esquema e recusa falsos GO.
- Não se testaram autenticação serviço-serviço, ACL no backend CRM, RLS real, replay distribuído, falha/retomada real, provedor de IA, API operacional, credenciais, tenant real, persistência durável, staging da VPS ou autorização de piloto. A prova de código **não substitui** aceites bilaterais independentes.

**Gates e pendências:** [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT, Privacidade/LGPD e Infra continuam **NO-GO**. O pacote de 14 gates e 13 cenários registra 13 `NAO_EXECUTADO` na homologação real. Sem merge em `main`, deploy, dados de clientes ou custos.

**Critério de saída da fase:** aceite formal do contrato/segurança pela equipe CRM, decisões documentadas e testes reais independentes no ambiente isolado antes de qualquer integração operacional. Nenhum teste F208/F209 tem autorização para alterar gates, estados do pacote ou credenciais.
