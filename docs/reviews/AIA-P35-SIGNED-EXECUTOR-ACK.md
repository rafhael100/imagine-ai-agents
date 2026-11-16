# AIA-P35 — Assinatura Ed25519 obrigatória para ACK em nova API de laboratório

16/11/2026, empilhada sobre P34 (#398).

**Risco anterior:** mesmo no fluxo protegido, a confirmação local P34 conhecia apenas identificadores de empresa, evento, worker, tentativa e a revisão de autorização. Não havia comprovação criptográfica de que um executor tinha produzido o recibo.

**Nova candidata:** `MockSignedN8nAckReceipt` verifica, sem efeitos externos, uma mensagem canônica fechada assinada com Ed25519. O verificador usa uma chave pública fornecida separadamente do conteúdo não confiável, exige tempo limitado, escopo exato de empresa/evento/worker, versão de tentativa, `audience`, `issuer` sintético e resultado `NO_AI_COMPLETED_LAB`. Uma nova API `PgSignedExecutorAckLab` só chama o ACK transacional de P30/P34 se o recibo sintético for validado; revogação posterior ao recebimento ainda provoca negação.

CI negativa cobre assinatura ausente, chave não reconhecida, adulteração de conteúdo, outra empresa, worker, tentativa, vencimento, chave pública ausente, campo extra que tenta introduzir autorização, confirmação repetida e recibo válido após revogação. Usa chaves aleatórias **geradas dentro da própria CI** e não armazena segredos.

**Crítico:** a chave de teste NÃO comprova que o n8n real gerou a mensagem. Ainda faltam identidade real S2S/mTLS ou canal autenticado, chave operacional gerenciada/rotacionada, política de revogação, prova de execução externa, outbox/inbox reais e revisão independente. A API P30 anterior continua disponível apenas em laboratório. Nenhuma implantação, chamada de IA, faturamento ou merge.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
