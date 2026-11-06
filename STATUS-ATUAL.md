# STATUS-ATUAL — Imagine AI Agents

**Data de referência:** 2026-11-06  
**Fase:** AIA-01 aprovado funcionalmente (D01–D08); detalhamento técnico e implementação ainda bloqueados. AIA-02 aprovado funcionalmente (D01–D10); AIA-03 aprovado funcionalmente (D01–D10); AIA-04 será planejado em branch separada. Implementação permanece bloqueada.  
**GitHub:** `rafhael100/imagine-ai-agents`, branch padrão `main`.

## Estado confirmado

- Repositório privado criado e acesso administrativo/escrita verificado.
- Documentação inicial publicada; nenhuma implementação autorizada.
- AIA-01: diretrizes AIA01-D01 a D08 **aprovadas funcionalmente em 2026-11-06**; especificação em `docs/AIA-01-CATALOGO-AGENTES.md`. A implantação segue bloqueada.
- Nenhum deploy ou provisionamento de infraestrutura executado por este projeto.
- Nenhuma alteração autorizada nos repositórios CRM, Portal, Performance AI ou Universidade.

## Diretrizes existentes a respeitar

- CRM Imagine: SaaS multiempresa e motor nativo de automações configuráveis para clientes, com políticas próprias de permissões e créditos de IA.
- n8n: uso central em fluxos controlados pela Imagine, sujeito à validação de licenciamento comercial.
- Serviços, dados e autenticações de produtos devem permanecer segregados; integração por contratos e APIs.
- Decisões pendentes em outros projetos não são consideradas automaticamente aprovadas aqui.

- AIA-02: diretrizes AIA02-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-02-INSTRUCOES-PERSONALIDADE-REGRAS.md`; sem autorização de implementação.

- AIA-03: diretrizes AIA03-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-03-MODELOS-PROVEDORES-ROTEAMENTO.md`. Fornecedor, modelo, preço, contrato, orçamento, stack e implementação permanecem sem aprovação.

## Propostas complementares em avaliação

- Serviço compartilhado de agentes, com administração interna central e configuração limitada nos produtos.
- Catálogo de agentes, modelos, ferramentas, políticas, conhecimentos, memórias, execuções, testes e custos.
- Autonomia controlada, aprovação humana de operações sensíveis e melhoria supervisionada.
- Escopo modular AIA-01 a AIA-12, ainda **não aprovado para desenvolvimento**.

## Gates abertos

- [x] Criar e confirmar repositório privado no GitHub.
- [x] Aprovar funcionalmente AIA01-D01 a D08 (2026-11-06).
- [ ] Definir/validar os critérios técnicos detalhados de AIA-01 e integrações antes da implementação.
- [x] Aprovar funcionalmente AIA02-D01 a D10 (2026-11-06).
- [ ] Detalhar e validar aspectos técnicos de AIA-02, incluindo segurança, interfaces e testes.
- [x] Aprovar funcionalmente AIA03-D01 a D10 (2026-11-06).
- [ ] Detalhar AIA-03: provedores e modelos específicos, contratos, privacidade, custos e critérios técnicos.
- [ ] Revisar AIA-04 — Ferramentas e Integrações dos Agentes (ainda não aprovado).
- [ ] Aprovar escopo e fronteiras de responsabilidade entre produtos.
- [ ] Aprovar contratos de autenticação, autorização e isolamento.
- [ ] Definir provedores, modelos, abstração, contingência e custos.
- [ ] Definir stack técnica do núcleo, filas, armazenamento e observabilidade.
- [ ] Definir políticas de dados, memória, retenção e bases de conhecimento.
- [ ] Validar licenciamento comercial de ferramentas e n8n.
- [ ] Fechar MVP, orçamento, critérios de teste e aceite.
- [ ] Autorizar implementação em branch separada.

**Bloqueio:** sem programação, deploy, migração, mudanças em outros repositórios ou consumo pago de APIs sem autorização.
