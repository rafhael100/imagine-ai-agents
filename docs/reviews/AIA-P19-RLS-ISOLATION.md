# AIA-P19 — Defesa em profundidade no banco: RLS por tenant

16/11/2026. Complemento da P18 (#373), não substituto da autorização no backend.

O ensaio P19 cria em PostgreSQL16 descartável duas identidades **LOGIN não privilegiadas**, com senhas aleatórias geradas apenas em memória na CI, somente para simulação de leituras, aplica Row-Level Security a `aia_p16_lab.event_replay` e `public.aia_lab_n8n_outbox_p6`, e garante que cada identidade veja apenas os registros da própria empresa. As políticas de SELECT vinculam empresa diretamente ao papel do banco — um campo arbitrário ou GUC de sessão fornecido pelo solicitante não é fonte de autoridade. Não são concedidos INSERT/UPDATE/DELETE aos papéis de leitura.

Negativos: solicitação de dados da outra empresa, GUC forjada, tentativa de INSERT e de SET ROLE na identidade da outra empresa. A CI apaga políticas/papéis ao final. Não instala papéis na VPS nem modifica nenhum banco operacional.

**Limitações e gates:** o banco CI utiliza um administrador para semear dados; as credenciais efêmeras de teste não reproduzem identidades de produção, e a política só cobre leitura (não regula todos os caminhos de escrita). IAM verdadeiro, segredos, rotação, revogação, segurança de conexão, privilégio mínimo do escritor, análise de migrations e revisão independente continuam necessários. Sem IA paga, deploy, merge ou dados reais. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
