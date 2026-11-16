# AIA-P39 — Auditoria histórica de ACK assinado, somente leitura

16/11/2026. Complemento da P38 (#406).

A P38 registra a prova assinada junto com ACK. A P39 introduz `PgSignedEvidenceAuditLab`, um scanner de consistência em transação PostgreSQL **REPEATABLE READ, READ ONLY**. A verificação combina o auditor P23/P25 do evento canônico e do ledger, a linha do outbox, o recibo persistido pela P38, a chave pública da empresa recuperada do registro P37 e a assinatura Ed25519 V2. Recalcula hash, verifica empresa/evento/worker/tentativa/revisão e detecta recibos ausentes para eventos ACKED.

**Auditoria histórica ≠ autorização atual:** uma chave revogada **depois do registro** ainda é usada para validar matematicamente uma assinatura histórica; ela **não é reativada**. A reconstrução da janela usa timestamp sintético assinado, não confirma instante real de entrega. O auditor só emite referências sintéticas e categorias de falha, nunca bytes do recibo, assinatura, chave privada ou payload.

Testes negativos em banco CI descartável: assinatura adulterada, hash de recibo alterado, fingerprint trocado, ACK com estado inconsistente, ausência de evidência, tabela indisponível e chave revogada posteriormente. Nenhuma reparação automática ou efeito externo; o resultado `LAB_HISTORICAL_EVIDENCE_CONSISTENT_NO_GO` significa apenas que registros de laboratório estão coerentes.

**Gates para operar:** IAM real, retenção e LGPD, chave e origem real do n8n, integridade de backup, revisão AppSec/DBA, assinatura/recibo transportados de modo autenticado, integração com serviços proprietários e aceites humanos. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
