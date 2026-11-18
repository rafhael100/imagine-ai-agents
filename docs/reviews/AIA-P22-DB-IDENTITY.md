# AIA-P22 — Falha fechada na identidade do banco de laboratório

16/11/2026. Endurecimento do componente P16, integrado à P17–P21.

A classe `PgSyntheticReplayGate` agora confirma, no próprio PDO, se o driver é PostgreSQL e se `current_database()` e `current_user` correspondem ao laboratório CI autorizado. Variáveis de ambiente isoladamente não são suficientes para decidir que o banco acessado é o de testes.

Testes rejeitam conexão a outro banco na mesma instância, driver SQLite e a flag de CI desligada, além de reexecutarem concorrência P16 e commit conjunto P17. Isso reduz o risco de **execução acidental do SQL de laboratório contra um banco errado**, mas não representa autenticação operacional: um administrador malicioso ainda pode reproduzir um laboratório, e o projeto real exige segredos e privilégios próprios.

Não muda nenhum banco em produção; o PostgreSQL usado é descartável no GitHub Actions. Sem n8n, API paga, merge ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
