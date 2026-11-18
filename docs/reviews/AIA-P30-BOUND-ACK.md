# AIA-P30 — Fencing e integridade obrigatórios antes de ACK

16/11/2026. Incremento da P29 (#390).

O P6 `acknowledge()` original valida worker/tentativa/prazo, mas não revalida a integridade do ledger de antirrepetição antes de marcar ACKED. A P30 introduz **API opt-in de laboratório**, `PgBoundOutboxAckLab::acknowledge`, sem alterar P6 legado. Confere em uma transação as linhas do outbox e do ledger, hashes canônicos, empresa, evento, request e nonce reconstruídos pelo adaptador imutável, além de token de fencing, proprietário da reserva e prazo. A transação mantém locks até a confirmação, e qualquer inconsistência resulta em rollback/negação.

Os testes provocam adulteração depois do lease, worker falso, tenant trocado, confirmação duplicada, expiração e ACK atrasado depois da segunda tentativa. São contadores sintéticos em PostgreSQL descartável.

**ACK aqui é apenas estado local:** não comprova entrega ao n8n, nem aceitação de serviço real, e nunca autoriza faturamento, uso de créditos de IA ou ações sobre clientes. Integração futura deve exigir recibo operacional autenticado e vínculo à autorização de negócio. Nenhum merge na main, VPS ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
