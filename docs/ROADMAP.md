# Roadmap proposto — Imagine AI Agents

## Estado vigente em 09/11/2026 — protótipos FT01, sem produção

A fundação documental e os laboratórios **sintéticos F1-01 a F1-10** evoluíram e foram integrados **exclusivamente à dev** (último commit `70d81bf58d3e3e0b1e3ac87250538451a9c166e9`). O runner PHP passou **111/111 testes**, e **52/52 arquivos PHP** tiveram sintaxe e blobs Git conferidos. Nenhum agente real, provedor, autenticação/ACL efetiva, CRM, banco, deploy, custo ou produção foi integrado. Os gates R01-G01..G11 e CORE/SEG/AUT CRM permanecem abertos.

**Prioridade real de conclusão, sem criar mock após mock indefinidamente:**
1. **Contratos de segurança e confiança serviço-serviço (offline)**: identidade, contexto, recurso, esquema, revogação, autorização final e casos negativos em fixtures fictícias.
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
