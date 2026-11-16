# AUTO-IA-07 — Laboratório PostgreSQL 16: atomicidade e segregação por empresa

**Contexto:** [DEC-AUTO-IA-001 #339](https://github.com/rafhael100/imagine-ai-agents/issues/339) e [AUTO-IA-RC01 #346](https://github.com/rafhael100/imagine-ai-agents/pull/346).

**Apenas em PostgreSQL descartável do GitHub Actions. Não instalar no CRM, Portal ou VPS.**

Inclui três tabelas \`auto_ia_lab.budgets\`, \`allowed_ops\`, \`requests\`, com RLS habilitado e FORCE, papéis independentes para duas empresas fictícias, acesso SELECT restrito por papel, e nenhuma permissão de INSERT/UPDATE direto aos papéis de tenant.

A função de laboratório \`reserve\` executa validação do catálogo de oito operações interpretativas, converte a role testada na identidade de empresa **apenas sintética**, bloqueia a linha do orçamento em transação PostgreSQL \`FOR UPDATE\` e executa reserva idempotente. Oito sessões independentes devem respeitar orçamento de 12 unidades técnicas e 33 tokens, aceitando apenas 3 reservas de custo 4 / 11 tokens. \`mark_unknown\` congela novas reservas após incerteza, sem tratar timeout como zero.

**Limitação relevante de segurança:** \`SET ROLE\` de teste e \`current_setting('role')\` não constituem IAM operacional. Esses papéis não devem ser expostos a clientes e o runtime de produção nunca poderá aceitar identidade autodeclarada; identidade S2S confiável, secrets, grants, revogação, auditoria, anti-replay e revisão DBA/AppSec ainda são bloqueios. As funções retornam \`LAB_RESERVED_NO_AUTHORIZATION\` e jamais fazem chamadas a APIs de IA ou debitam créditos.

O ensaio demonstra apenas preservação de limites **estimados**. Não possui observação oficial de custo, cobrança, política comercial ou homologação de falhas após restauração.

## Evidência e próximos gates
- CI na HEAD exata: RLS/SELECT intertenant/negação de INSERT, operações NO_AI, replay conflituoso, congelamento por UNKNOWN, concorrência e baseline RC01.
- Reconciliar com IAM e persistência oficial CRM/Portal sem vincular diretamente banco externo.
- Preparar contrato autenticado serviço-serviço para eventos internos (n8n apenas executará fluxo autorizado; jamais acessará SQL alheio).
- Review independente e staging isolado antes de qualquer conexão real.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-AI-API / NO-CUSTOMERS.**
