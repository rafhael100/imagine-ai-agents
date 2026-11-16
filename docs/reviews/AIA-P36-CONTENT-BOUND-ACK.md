# AIA-P36 — Recibo Ed25519 V2 vinculado ao hash canônico e à revisão

16/11/2026. Complemento de P35 (#400), sem merge/deploy.

O recibo V1 de laboratório assinava tenant/event/worker/attempt, mas não incluía o digest do conteúdo originalmente aceito nem a revisão do serviço. O recibo V2 inclui `payload_hash` SHA-256 e `authority_revision` na estrutura canônica Ed25519, além de exigir tempo limitado, domínio de assinatura e campos exatos.

**Resolução TOCTOU:** a API opt-in `PgContentBoundExecutorAckLab` valida o V2 contra chave pública de teste fornecida separadamente. Em seguida passa o hash verificado à classe P30/P34, que compara esse digest com a linha travada do outbox **dentro da mesma transação PostgreSQL em que marca ACKED**. A linha P16, hashes derivados e a política revogável continuam obrigatórios. Qualquer alteração falha com rollback. A API P35 V1 permanece existente para regressões, mas não tem o vínculo novo e não deve ser promovida a operacional.

A CI cobre assinatura válida com digest errado, adulteração após assinar, revisão de autorização divergente, assinatura ausente, chave desconhecida, campos extras, worker falso, confirmação repetida, revogação posterior ao lease e separação entre empresas.

**Limites:** os testes usam chaves aleatórias e uma autoridade fictícia. Assinatura técnica não prova identidade do n8n oficial ou execução externa. Antes da produção faltam verificação de identidades S2S reais, rotação de chaves, autoridade do serviço proprietário, recibos de provedor auditáveis, revisão independente e homologação. Nenhuma chamada a n8n, IA, pagamentos ou VPS. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
