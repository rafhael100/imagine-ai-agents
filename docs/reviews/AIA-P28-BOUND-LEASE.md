# AIA-P28 — Só reservar evento com prova íntegra no ledger

16/11/2026. Complemento de P17/P23/P25/P27, empilhado sobre #386.

**Problema:** o método `PgN8nOutboxLab::claim()` da P6 não consulta a tabela antirrepetição P16. Uma linha de fila sintética órfã ou adulterada poderia receber lease mesmo se P23 detectasse inconsistência posteriormente.

**Entrega:** `PgBoundOutboxLeaseLab` fornece caminho **separado, opt-in e exclusivamente CI** de lease que bloqueia e verifica a linha do outbox e a linha P16 na mesma transação PostgreSQL. Faz hash dos bytes canônicos, confronta tenant/evento, hash, request e nonce derivados pelo adaptador CONV-02 imutável, e só então incrementa attempts e grava fencing do worker. As falhas retornam DENY e rollback. Uma fila órfã permanece pendente para triagem humana em vez de ser despachada.

A saída é `LAB_LEASE_RESERVED_NO_DELIVERY` sem corpo do evento, sem URL, sem credencial, sem autorização operacional e sem efeito externo. O método P6 original é preservado, logo **a proteção não é global nem entrou em produção**. Testes adversariais cobrem órfãos, hashes, request, nonce, dupla reserva e isolamento de tenant.

**Gates:** IAM por empresa e serviço real, autorização do proprietário do evento, ACK autenticado, processamento externo, revogação, revisão independente de segurança e integração deliberada do novo método. Sem merge, VPS, n8n real ou IA paga. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
