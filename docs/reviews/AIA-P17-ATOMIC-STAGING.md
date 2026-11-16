# AIA-P17 — Atomicidade entre antirrepetição e outbox (PostgreSQL CI)

16/11/2026. Descoberta crítica: o método antigo `PgN8nOutboxLab::enqueue()` inicia e confirma sua própria transação. Portanto, gravar P16 e o outbox em sequência poderia deixar uma marca de replay durável, mas **não entregar a gravação para a fila**, impedindo recuperação no mesmo pedido.

Nesta PR, o outbox P6 recebe `enqueueWithinTransaction`, restrito a uma transação já iniciada pelo chamador, preservando o método original para clientes P6. Um novo `PgSyntheticAtomicStager` utiliza **a mesma conexão PDO e uma única transação** para P16 + outbox P6, com commit conjunto e rollback conjunto. A memória sintética do nonce só avança após o commit completo. Regressões incluem conflito de conteúdo existente na fila, rollback do registro antirrepetição, nova tentativa e isolamento entre empresas.

**Limites:** a gravação em fila não é execução n8n, confirmação externa ou permissão para chamar IA. Não há transporte, IAM real, PostgreSQL de produção, faturamento ou provedores conectados. Revisão independente do refactor P6 e testes de concorrência do caminho P17 são obrigatórios antes de integrar qualquer produto. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY**.
