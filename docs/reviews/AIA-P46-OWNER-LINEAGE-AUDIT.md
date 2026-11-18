# AIA-P46 — Auditoria histórica de proveniência dos eventos

16/11/2026. Continuação empilhada sobre P45 (#420).

**Objetivo:** detectar qualquer registro de automação que possua uma admissão mTLS mas não tenha a decisão explícita assinada do produto proprietário, bem como adulterações que tenham ocorrido depois do stage. A autorização operacional continua **NO-GO**.

`PgTransportOwnerLineageAuditLab` executa uma transação PostgreSQL `REPEATABLE READ, READ ONLY`, cruzando a ledger mTLS original CENTRAL-AUTO-P7, o mapeamento P41, o consumo exclusivo da prova P42, a autorização Ed25519 do owner P44/P45, a ledger antirrepetição P16 e o outbox P6. Recalcula hashes, compara escopo `company_id/tenant_ref/event_ref` e assinatura histórica. Uma chave de owner revogada depois da criação **não deixa de ser verificável historicamente**, mas tampouco é reativada.

A CI gera dois eventos sintéticos autorizados e confirma a proveniência completa. Em seguida provoca adulteração da assinatura, alteração do digest do transporte, exclusão da decisão do owner e perda do registro de nonce mTLS original. Todos devem produzir `DENY_INCONSISTENT` com causas específicas e sem qualquer reparo automático. A ausência da tabela de outbox retorna `INDETERMINATE`, nunca um falso resultado limpo. Os relatórios contêm somente IDs sintéticos e categorias; não expõem corpo assinado ou chaves.

**Escopo propositalmente limitado:** o auditor examina eventos vinculados às tabelas P42/P44. Outros caminhos de laboratório P17/P31 podem existir sem esse vínculo; não se deve interpretar esse relatório como certificação de toda a aplicação, nem como identidade ou consentimento verdadeiros. A implementação do produto proprietário é a fonte de autoridade futura, e a política de retenção/imutabilidade dos recibos requer projeto e revisão próprios.

Sem merge, VPS, n8n real, credenciais de clientes, IA paga ou cobrança. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
