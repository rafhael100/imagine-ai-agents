# Roadmap proposto — Imagine AI Agents

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
