# AIA-P18 — Crash recovery e concorrência do staging P17

16/11/2026. A prova da P17 em um único processo não é suficiente para afirmar que a transação conjunta tolera concorrência ou morte abrupta do processo.

A P18 testa, em PostgreSQL16 **descartável de CI**:
1. término do processo após gravar P16 dentro de transação **sem commit**, seguido de prova de rollback no banco;
2. nova tentativa do mesmo evento após falha, com registro conjunto P16 + outbox;
3. dez processos disputando o mesmo evento/empresa, um vencedor e nove recusas, sem filas parciais;
4. proibição de gravar evento com política revogada e isolamento da empresa B;
5. ACK com fencing de tentativas por worker, recusando worker falso e ACK antigo na mesma identidade.

**Limites**: o encerramento sem commit simula interrupção limpa de conexão, não cobre corrupção, crash do host, falha de disco ou indisponibilidade prolongada. O ACK é uma confirmação fictícia, não prova de consumo n8n. Ainda faltam IAM verdadeiro, redes privadas, chaves reais, análise independente, restauração e carga em ambiente autorizado. Sem provider de IA, VPS, merge ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
