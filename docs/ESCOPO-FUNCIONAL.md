# Escopo funcional proposto — Imagine AI Agents

**Atenção:** os módulos abaixo são candidatos em planejamento; incluí-los neste arquivo não autoriza programação.

| ID | Módulo | Funções previstas |
|---|---|---|
| AIA-01 | Catálogo de agentes | Modelos de agentes, versões, publicação e desativação |
| AIA-02 | Instruções e regras | Papéis, objetivos, políticas, exemplos, limites e precedência |
| AIA-03 | Modelos e provedores | Configuração, roteamento, cotas, fallback e contingência |
| AIA-04 | Ferramentas e integrações | Ações autorizadas, contratos, escopos, credenciais e validação |
| AIA-05 | Base de conhecimento | Fontes aprovadas, indexação, recuperação, atualização e exclusão |
| AIA-06 | Contexto e memória | Contexto de execução, histórico e memória persistente autorizada |
| AIA-07 | Execuções e orquestração | Tarefas, filas, agentes individuais e colaboração especializada |
| AIA-08 | Autonomia e aprovações | Risco, alçadas, autorização, expiração, recusa e rastreabilidade |
| AIA-09 | Laboratório e avaliações | Cenários, datasets, métricas, regressão e promoção de versões |
| AIA-10 | Observabilidade e custos | Logs, consumo, falhas, SLAs, alertas e créditos de IA |
| AIA-11 | Governança e auditoria | Isolamento multiempresa, retenção, versões e auditorias |
| AIA-12 | SDK e contratos de produtos | APIs, eventos e adaptadores para CRM, Portal, Performance e Universidade |

## Interfaces propostas

- **Central Imagine (administração interna):** agentes oficiais, modelos, ferramentas homologadas, custos, auditoria, incidentes, permissões e versões.
- **Interfaces locais nos produtos:** agentes permitidos por plano, configurações do cliente, conhecimento privado e aprovações.
- **Automações visuais:** permanecem no produto correspondente, podendo chamar capacidades IA por APIs versionadas.

## Níveis de capacidade (classificação proposta)

- N1: consultas e respostas informativas.
- N2: análises, resumos e recomendações.
- N3: operações permitidas, com validação de backend e aprovações conforme risco.
- N4: coordenação entre agentes especialistas.
- N5: melhoria supervisionada, com testes e publicação controlada.

**Importante:** nível de capacidade não concede privilégio automaticamente. Toda ação exige autorização específica para ferramenta, empresa, produto e operação.
