# AIA-P24 — Row-Level Security também para escrita, com papéis não privilegiados

**16/11/2026 — revisão de laboratório, sem implantação.**

A P19 cobria somente leitura entre empresas. A P24 demonstra, no PostgreSQL16 descartável, que **dois usuários LOGIN não privilegiados** podem inserir eventos apenas do próprio tenant, quando a política de RLS usa o **papel autenticado**, e nunca um campo de tenant informado pelo chamador.

O teste reaproveita as tabelas de replay P16 e outbox P6 e os contratos já existentes. Semeia dois tenants pelo stager P17 e adiciona papéis efêmeros com senhas aleatórias geradas em memória. Aplica políticas SELECT e INSERT com tenant literal vinculado ao papel e FORCE ROW LEVEL SECURITY. Valida leitura limitada ao tenant, inserção permitida do próprio tenant, rejeição de INSERT cross-tenant, rejeição de UPDATE, DELETE, CREATE TABLE e SET ROLE contra a outra identidade, mesmo após alterar uma GUC de sessão.

**Importante:** os papéis de teste fazem INSERT diretamente e não representam o serviço proprietário. RLS limita o tenant, **não** comprova assinatura, consentimento, aprovação humana, integridade canônica dos campos, transação P17 ou IAM real. As classes de laboratório P16/P6 continuam exigindo a identidade administrativa específica da CI, e **não** foram convertidas para operar com os novos papéis. Um caminho operacional exigiria outro desenho de privilégio mínimo, validação de políticas no backend e autorização independente antes de movimentar eventos.

A CI não utiliza a VPS, dados reais, contas pagas, n8n instalado, migração de produção ou API de IA. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
