# AIA-P56 — Tela local conectada ao PostgreSQL real de laboratório

16/11/2026. Candidata empilhada sobre P55 (#439), sem deploy nem conexão com usuários reais.

## Entrega

\`examples/p56-live-pg-review-local.php\` apresenta uma central de propostas responsiva com a identidade Imagine (laranja #F9941D). Diferentemente da P54, que usa fixtures em PHP, esta página **consulta linhas gravadas de fato no PostgreSQL16 descartável da CI**, por meio do \`PgScopedReviewQueueBridgeLab\` P55 e do modelo P53. A consulta é uma transação \`REPEATABLE READ, READ ONLY\`, com validação de contagem, fingerprint e escopo de empresa.

A origem da conexão é uma identidade sintética A com permissão SELECT apenas e política FORCE ROW LEVEL SECURITY; a empresa B possui rascunhos próprios e não aparece na tela de A. Toda senha é gerada apenas no runner e guardada num arquivo temporário de permissão restrita. O PHP usa esse arquivo somente ao conectar, não o imprime nem aceita credenciais pelo HTTP.

A interface é ativada **apenas durante GitHub Actions** e com flags de laboratório, servidor PHP embutido no loopback, hostname confiável e GET com único parâmetro opcional \`kind\`. POST/PUT/DELETE, outros parâmetros e hosts inesperados são recusados. Não existem botões ou formulários de aprovação, scripts, recursos de terceiros, acesso real ao CRM/Portal ou envio de dados.

## CI

A workflow P56 conserva as regressões P13–P55 e o job n8n PG17 isolado. Adiciona um terceiro job PostgreSQL16 novo, em que:
1. Um script CLI cria apenas as duas empresas sintéticas e quatro rascunhos, mais o usuário SELECT-only com RLS.
2. Um teste inicia \`php -S 127.0.0.1\` numa porta efêmera, confirma tela, filtros, CSP, ausência de ativos externos e segregação A/B.
3. Um processo de teste independente altera um valor no banco descartável: a tela retorna HTTP 503 genérico, sem apresentar dados incoerentes; restaurado o valor, volta a 200.
4. Uma exclusão de rascunho provoca o mesmo bloqueio, comprovando que o dashboard não apresenta filas parciais.
5. A senha efêmera é eliminada no final do job.

## Restrições

Esta página não pode ser aberta como URL do Portal ou na VPS, nem deve ser disponibilizada a clientes. Os testes de autorização são **fixtures de banco**, não login real, consentimento ou permissão CRM. Para uma tela operacional: IAM e identidade reais, migrações revisadas, auditoria de acesso, MFA conforme política, LGPD, logging e monitoramento, CSRF nas ações futuras, homologação da VPS e aprovação humana antes de produção. P56 não implementa votação, aprovação, execução, n8n S2S ou IA paga.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
