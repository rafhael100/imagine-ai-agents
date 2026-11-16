# AIA-P16 — PostgreSQL16 replay / idempotência (CI descartável)

Data 16/11/2026. Candidata empilhada sobre P15 (#368).

Implementação de laboratório `PgSyntheticReplayGate`: compõe P15 com a proposta canônica P7/P12, com persistência estritamente sintética e atômica em PostgreSQL16 descartável. Guarda apenas `tenant_ref`, `event_ref`, `request_ref`, `nonce_ref`, `payload_hash` e hora técnica. Restrições únicas por empresa para evento, request e nonce. Uma disputa de dez processos deve admitir um único registro; demais são negados. Replay em processo novo permanece bloqueado; outra empresa usa namespace de unicidade separado. Inconsistência/falha SQL encerra em negação.

Critérios: nenhuma mensagem real, nenhum dado pessoal, nenhum n8n/CRM conectado, nenhuma chamada de IA ou débito, nenhuma autorização operacional. A aceitação `LAB_ATOMIC_CLAIM_NO_EFFECT` marca apenas a **reserva de uma referência de evento** num banco CI — não é entrega, ACK ou permissão do serviço proprietário.

**Antes de produção:** revisão independente da modelagem de retenção/particionamento, IAM operacional, vínculo com transação de negócio real, credenciais isoladas, revogação, teste de rollback/backup, TTL seguro, autorização da empresa e n8n autenticado. Sem merge nem deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
