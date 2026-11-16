# AIA-P55 — Fila real de rascunhos do PostgreSQL em modo somente leitura (laboratório)

16/11/2026. Incremento empilhado sobre P54 (#437), sem alterar CRM/Portal ou abrir endpoint HTTP real.

A P51 já persistia rascunhos sintéticos no PostgreSQL16 de laboratório; P52 os auditava, P53 criava um modelo de leitura, e P54 demonstrava a tela com fixtures locais. A P55 conecta essa trilha pela primeira vez a uma consulta **real no banco**, sob uma única transação \`REPEATABLE READ, READ ONLY\`.

**Implementado:** \`PgScopedReviewQueueBridgeLab\` verifica uma vinculação CI-only de usuário de banco a tenant esperado, lê lotes e propostas do tenant num snapshot único, limita a 128 lotes e 128 rascunhos, revalida o fingerprint de cada proposta P52 e o número de propostas de cada lote P51, e chama o modelo de apresentação P53 sem liberar ações. Em caso de perda de linha, hash alterado, esquema indisponível, excesso, estado inesperado, role errada ou tentativa de transação aninhada, responde DENY, sem entrega parcial da fila nem dados do banco nos erros.

**Segurança testada no PostgreSQL real:** a CI cria dois usuários específicos (aia_p55_ci_read_a / aia_p55_ci_read_b) com senhas geradas apenas no runner, privilégios \`SELECT\` nas tabelas P51 e políticas \`FORCE ROW LEVEL SECURITY\` por tenant. A leitura A não vê B nem usando SQL sem filtro. UPDATE é negado pelo banco. O facade rejeita também a troca explícita da empresa e uso do administrador postgres como leitor. São contas sintéticas de CI, NÃO a autenticação ou a política RLS definitiva do SaaS.

A bateria P55 cobre armazenamento de duas empresas, lote com zero propostas, escopo bancário, alteração e perda de rascunhos, comportamento diante de corrupção, transação aninhada e ausência de permissões de escrita. A CI preserva toda a regressão P13–P54 e o job n8n PG17 separado.

**Importante:** P55 é uma classe de laboratório e não uma rota de usuário. O fato de funcionar com roles sintéticas em PostgreSQL não significa que o CRM tenha autorizado leitura. Antes de expor a interface são necessários IAM operacional, política e migrações de bancos próprias, supervisão AppSec, LGPD, logs de acesso, controle de privacidade e aceite humano. Nenhum merge, deploy ou custo.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
