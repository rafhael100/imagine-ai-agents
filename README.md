# Imagine AI Agents

**Status:** projeto criado no GitHub; fundação documental inicial (05/11/2026).  
**Repositório:** `rafhael100/imagine-ai-agents` (privado).  
**Execução:** sem código de produto, sem infraestrutura provisionada e sem deploy.

## Propósito

Construir a camada compartilhada de **configuração, execução supervisionada e governança de agentes de IA** do ecossistema Imagine.

**Produtos consumidores previstos:** Imagine CRM, Portal Imagine, Imagine Performance AI e Universidade Imagine.

## Princípios

1. **Núcleo comum, domínios independentes:** cada produto mantém suas APIs, identidades, dados e regras de negócio. Não há acesso direto e irrestrito aos bancos dos produtos.
2. **Isolamento multiempresa:** contexto, memória, documentos, ferramentas e custos segregados por empresa e produto, com autorização obrigatória no backend.
3. **A IA propõe, o sistema valida:** regras críticas (acesso, finanças, exclusão, publicações, cobranças) não dependem somente de prompts.
4. **Autonomia graduada:** informar, sugerir, solicitar aprovação e executar operações explicitamente permitidas conforme risco.
5. **Observabilidade:** versões, execuções, erros, custos, testes, avaliações e trilhas de auditoria.
6. **Melhoria controlada:** feedback e testes antes de promover versões.
7. **Integração por contratos técnicos:** APIs autenticadas, eventos, filas e ferramentas versionadas.
8. **Não duplicação:** o CRM mantém o motor nativo de automações; n8n pode atender fluxos internos controlados pela Imagine, mediante validação do licenciamento comercial.

## Documentos

- [AGENTS.md](AGENTS.md) — diretrizes para Codex e agentes de desenvolvimento.
- [STATUS-ATUAL.md](STATUS-ATUAL.md) — fatos, pendências e bloqueios.
- [docs/ARQUITETURA-INICIAL.md](docs/ARQUITETURA-INICIAL.md) — limites e componentes.
- [docs/ESCOPO-FUNCIONAL.md](docs/ESCOPO-FUNCIONAL.md) — 12 módulos candidatos.
- [docs/ROADMAP.md](docs/ROADMAP.md) — etapas e critérios para avançar.

## Próximas decisões

Revisar/aprovar limites do serviço, contratos entre produtos, proteção de dados, estratégias de modelos/provedores, custos, licenciamento, MVP e critérios de aceite.

**Regra:** a publicação documental neste repositório não autoriza programação, alterações nos demais repositórios, despesas, infraestrutura ou deploy.
