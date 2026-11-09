# Roadmap proposto — Imagine AI Agents

## Estado vigente — SEC-01/SEC-02 com CI integrada (09/11/2026)

- **Proteção do núcleo sintético:** [PR #132](https://github.com/rafhael100/imagine-ai-agents/pull/132) integrado só à dev em `1c4cdbf87d8a7b003b5564e76784f9333dc5900c`; [CI integrada #42632148664](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632148664) PASS. Adicionado scanner estático limitado de operações de rede, processo e banco em `src/`, com dez testes Python.
- **Referências de contexto:** [PR #133](https://github.com/rafhael100/imagine-ai-agents/pull/133) integrado só à dev em `07088bb58c2e4f4a3d68e11091de50737f875368`; [CI integrada #42632952682](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632952682) PASS no SHA exato. `TenantContext` agora valida referências de 1 a 80 caracteres ASCII conforme OP-01, com 12 testes novos; suíte oficial **240/240 PHP e 74/74 lint**.
- **Fronteira de autorização:** scanner e testes **não** equivalem a isolamento de produção, IAM, ACL real ou aprovação do CRM. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT/sem merge, R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos. **NO-GO** para piloto operacional; `main`, VPS, clientes e provedores não foram tocados.

---

## Estado vigente — EVID-07 integrado, integridade do contrato reforçada (09/11/2026)

- [PR #130](https://github.com/rafhael100/imagine-ai-agents/pull/130) integrado **somente à dev**, squash `21300c5e518166e7ac51ad5020d645c17668edb0`. A [CI do PR #42630115145](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630115145) passou no SHA revisado `0277abf91012bfc12f51a2625fce296abab1a689`; a [CI integrada #42630193545](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630193545) passou no HEAD exato `21300c5e518166e7ac51ad5020d645c17668edb0`.
- EVID-07 acrescentou seis testes negativos à paridade OP-01: o snapshot CRM PR #57 agora exige **commit e blob exatos**, e o contrato JSON rejeita palavras-chave raiz adicionais e metadados falsos de produção. A regressão PHP **228/228** e **73/73 lint** permaneceu aprovada na CI.
- [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57): **OPEN/DRAFT, sem merge e sem aceite bilateral**. Os gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. **NO-GO operacional**: nenhum endpoint CRM, identidade real, autorização, dado de cliente, `main`, VPS, gasto ou deploy foi ativado.
- **Prioridade seguinte:** obter decisões formais de CORE/SEG/AUT/Privacidade e donos do piloto. Não ampliar o escopo do laboratório sintético enquanto a segurança contratual real segue pendente.

---

## Estado vigente — EVID-05/EVID-06 integrados e CI da dev comprovada (09/11/2026)

- **Painel gerado EVID-05:** [PR #127](https://github.com/rafhael100/imagine-ai-agents/pull/127), squash `44bab6e8a1c01bc6b30761018fe6271b94374bd6`, integrado apenas à dev. A [CI #42628509823](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42628509823) validou no SHA `5e3f0485d03a47d084332837d39f7fafe3169f75` o gerador determinístico, testes negativos, os validadores anteriores e a regressão PHP **228/228 testes e 73/73 lint** (mais o probe isolado).
- **CI consolidada EVID-06:** [PR #128](https://github.com/rafhael100/imagine-ai-agents/pull/128), squash `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c`, integrado somente à dev. A CI de revisão [#42629105845](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629105845) passou; sobretudo, a [CI #42629209058](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629209058) foi disparada **automaticamente pelo próprio merge na dev** no HEAD exato `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c` e concluiu `success` em todos os passos, inclusive a suíte PHP.
- **Governança e limites:** CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT/sem merge**. R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS; o painel e a CI retornam evidência de **laboratório, não autorização**. Estado operacional **NO-GO**, sem mudanças em `main`, CRM real, VPS, clientes, credenciais, contratos comerciais ou produção.

**Próximo passo que depende de terceiros:** pareceres bilaterais formais de CORE/SEG/AUT, Privacidade, Infra e direção sobre OP-01, com identidade, ACL, finalidade, revogação, topologia e custos. Até essas evidências, não iniciar piloto real.

---

## Estado vigente — paridade contratual e painel offline integrados à dev (09/11/2026)

- **EVID-03:** [PR #124](https://github.com/rafhael100/imagine-ai-agents/pull/124) integrado somente à dev no squash `1a0c41875b477101ff8617da4682f8c7b44889fd`; [CI #42624040384](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624040384) no SHA `1bca6d5fadf7e46429b81e8cb79699f1f6631066` concluiu `success` nos testes de snapshot do CRM PR #57, paridade do schema JSON/parser PHP e regressão **228/228 testes PHP, 73/73 lint oficiais + 1 lint do probe**. Isso **não verifica API CRM real**.
- **EVID-04:** [PR #125](https://github.com/rafhael100/imagine-ai-agents/pull/125) integrado somente à dev no squash `53fc200e48b1c05789317e85b9b143efc47b76f0`; [CI #42624860955](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624860955) no SHA `7ca16416914291f1a6fd7d9f47126228ff2f8526` concluiu `success` no painel offline `examples/r01-op01-readiness-offline.html`, incluindo teste de correspondência dos **14 gates e 13 casos**, testes negativos EVID-02/03/04 e regressão PHP completa.
- **Governança inalterada:** o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **DRAFT, OPEN, sem merge**; R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. Resultado operacional **NO-GO**. A `main`, a VPS, o CRM funcional, credenciais, clientes e produção não foram alterados.

**Próxima atividade que depende de aceite externo:** revisão bilateral formal de identidade, ACL, campos, privacidade, revogação, topologia, licenciamentos e escopo do piloto; não substituir assinaturas de responsáveis por simulações ou status de CI.

---

## Estado vigente — R01/EVID-02 e análise G09 integrados à dev (09/11/2026)

- **PR [#121](https://github.com/rafhael100/imagine-ai-agents/pull/121) integrado exclusivamente à dev** no squash `9bf82e93466903e42dbbdaf981736b50a6ec8638`. [GitHub Actions #42621128657](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42621128657) executou, no head exato `bf5fabe8d151bf6d9988017b4df0cf74a4ec0e1f`, **15/15 testes do inventário OP-01**, **228/228 PHP** e **73/73 lint PHP**, todos com conclusão `success`. O teste do inventário devolve sempre `NO_GO` para integração real, mesmo que o registro tenha integridade válida.
- **PR [#122](https://github.com/rafhael100/imagine-ai-agents/pull/122) documental integrado exclusivamente à dev** no squash `ab31c28afeac4cf75fa3e09a33cec9db37916865`. Estudo de cinco cenários de licenciamento n8n com fontes oficiais; possível exigência Enterprise para backend SaaS, sem concluir licença ou autorizar aquisição. **G09 ABERTO**.
- **Gates ainda ABERTOS:** R01-G01..G11 e CRM CORE/SEG/AUT; [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT. Sem `main`, servidores, clientes, APIs reais, credenciais, compras ou deploy.

---

## R01-G09 — investigação do licenciamento n8n (09/11/2026)

Fontes oficiais do n8n (FAQ atualizada em 06/10/2026, termos SUL e oferta OEM) foram confrontadas com o desenho multiempresa Imagine. O uso Community **não foi homologado** para backend SaaS; automações configuráveis do cliente permanecem responsabilidade do motor próprio do CRM. Ver `docs/AIA-R01-G09-ANALISE-LICENCIAMENTO-N8N-2026-11-09.md` e matriz `docs/evidencias/aia-g09-matriz-uso-n8n.csv`. **G09 ABERTO**, sem compras ou integração.

---

## Preparação de homologação bilateral CRM/AIA — EVID-01 (09/11/2026)

Foi produzido o dossiê `docs/AIA-R01-EVID-01-DOSSIE-ACEITE-PILOTO-CRM-OP01.md` e a matriz rastreável `docs/evidencias/aia-r01-gates-op01.csv`, confrontando o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (OPEN/DRAFT) com os gates do Agents. **Todos os R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS**, sem integração, credencial, custo, `main` ou deploy. Resultado: **NO-GO operacional; material pronto para revisão**.

---

## Atualização — F1-18 integrada apenas à dev (09/11/2026)

A [CI #42610138453](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42610138453) verificou **228/228 casos PHP e 73/73 arquivos com lint**, no SHA `210ff31ec365267f019aafbf2d4c1e48991b02a7`; o [PR #118](https://github.com/rafhael100/imagine-ai-agents/pull/118) foi integrado somente à dev (`2603c8da0a7ac7392cf5a27289249d776a095e81`). A contagem de falhas de schema antes da auditoria é apenas sintética. Os gates R01 e CRM continuam abertos. **Próxima prioridade:** revisão bilateral de confiança/contrato CRM e critérios formais para um piloto seguro — não criar mocks ilimitadamente. Sem `main` ou produção.

---

## Estado vigente em 09/11/2026 — protótipos FT01, sem produção

A fundação documental e os laboratórios **sintéticos F1-01 a F1-12** evoluíram e foram integrados **exclusivamente à dev** (último commit `562896763f900a8a264ea46aa35e2aeacd5e0903`). O runner PHP passou **144/144 testes**, e **60/60 arquivos PHP** tiveram sintaxe e blobs Git conferidos. Nenhum agente real, provedor, autenticação/ACL efetiva, CRM, banco, deploy, custo ou produção foi integrado. Os gates R01-G01..G11 e CORE/SEG/AUT CRM permanecem abertos.

**Prioridade real de conclusão, sem criar mock após mock indefinidamente:**
1. **Contratos de segurança e confiança serviço-serviço (offline)**: representação mockada de contexto, recurso, escopo, emissor/audience e replay **já implementada no F1-11**. O F1-12 entregou a primeira proposta de contrato versionado OP-01 somente leitura, com fixtures, parser e 17 testes adicionais. Próxima atividade: **revisão bilateral** do [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57), com aprovação da autoridade de recursos e de segurança. Não emitir tokens nem conectar servidores. Consultar [G01/G02 — proposta de confiança](AIA-R01-G01-G02-CONTRATO-CONFIANCA-PROPOSTA.md).
2. **Homologação transversal R01-G01..G11**: especificar responsáveis, evidências e aceites necessários antes de qualquer acesso real. Consultar [plano de transição FT01 → piloto](AIA-R01-TRANSICAO-FT01-PILOTO-CONTROLADO.md).
3. **Piloto CRM somente leitura em desenvolvimento**, condicionando credenciais, infraestrutura e acesso de teste à aprovação crítica de G01/G02/G05/G08/G10/G11 e dos gates do CRM. Nada de mutações/produção no primeiro piloto.
4. **Operação controlada**, após autenticação real, proteção de dados, testes de carga, observabilidade, idempotência, aprovação humana real, licenças, orçamento e rollback.

Os passos abaixo são o **roadmap documental de origem**; referências a fase 0 como “atual” e fase 1 como “apenas após aprovação” são históricos. A autorização ampla posterior permite **mocks offline com merge somente na dev**, mas não altera gates reais ou custos.

---


## Fase 0 — Fundação documental (atual)

- Repositório privado e arquivos `README.md`, `AGENTS.md`, `STATUS-ATUAL.md`.
- Delimitação inicial entre serviço central e consumidores.
- Levantamento de riscos, contratos, custos, licenciamento e backlog.

**Gate:** aprovação formal de arquitetura, responsabilidades e MVP.

## Fase 1 — Núcleo mínimo (apenas após aprovação)

- Cadastro e versionamento de agentes, provedores e instruções.
- Executor com autenticação, autorização e rastreamento.
- Ferramentas seguras e segregação multiempresa.
- Limites e cotas, registros de execução e custos.

**Gate:** testes automatizados, segurança, isolamento e critérios de aceite.

## Fase 2 — Caso-piloto integrado ao CRM

- Agente de qualificação de leads: buscar lead autorizado, recomendar classificação, produzir resumo e sugerir tarefa.
- Fluxos de aprovação conforme permissões e risco.
- Avaliações de qualidade, segurança, custos, auditabilidade e rollback.

**Gate:** aceite do CRM e evidências de testes.

## Fase 3 — Conhecimento, memória e expansão

- Fontes de conhecimento aprovadas, indexação e recuperação.
- Memória controlada e políticas de retenção.
- Integração progressiva a Performance AI, Portal e Universidade, respeitando os gates de cada produto.
- Painéis centrais de qualidade, consumo e incidentes.

## Fase 4 — Multiagentes e melhoria supervisionada

- Coordenação de especialistas.
- Análise de feedback, avaliação contínua e propostas de melhoria.
- Aprovação e publicação controlada de versões, sem autoalteração de regras críticas.

## Fora do escopo inicial

- Treinar modelo fundacional próprio.
- Acesso direto a bancos de outros produtos.
- Duplicar ou substituir o motor nativo de automações do CRM.
- Autonomia irrestrita ou autoalteração de privilégios.
- Implantar produção sem autorização expressa.
