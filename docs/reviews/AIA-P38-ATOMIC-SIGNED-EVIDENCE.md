# AIA-P38 — ACK e recibo assinado persistidos no mesmo commit

16/11/2026. Complemento da P37 (#404), sem merge/deploy.

**Falha anterior:** embora o ACK pudesse exigir assinatura válida, o estado `ACKED` era gravado sem preservar a comprovação criptográfica validada na mesma transação. Um crash entre confirmar e armazenar evidências posteriormente deixaria registro incompleto.

**Nova implementação:** um caminho opt-in `PgAtomicSignedEvidenceAckLab` usa o registro de chaves P37, autoridade revision-bound P34, recibo V2 vinculado ao hash P36 e a checagem da P30. Dentro da transação que mantém locks no tenant, chave, evento e ledger, o verificador V2 é executado *novamente* com a chave pública comparada ao fingerprint registrado. Só então a transação confirma ACK e INSERE uma linha na tabela `aia_p38_lab.signed_ack_evidence`, com texto canônico, assinatura sintética, hash, tenant/event/worker/tentativa/versão e ID da chave. Qualquer erro da tabela de evidência ocasiona rollback do ACK.

A CI testa recibo válido/duplicado, assinatura criptograficamente válida com hash de conteúdo incorreto, impossibilidade de persistir evidência, rollback de ACK, correção da tabela em laboratório, nova tentativa idempotente e isolamento de empresa.

**Importante:** os documentos de teste contêm somente IDs sintéticos. A prova é de verificação técnica local; não demonstra que n8n executou a ação ou que houve IAM operacional. Não guarda chave privada. A API anterior sem evidência continua disponível para regressões e não deve ser usada em produção. As tabelas do teste não são migrations de produção.

Pendências: revisão externa, mecanismo de confiança real de certificados/segredos, política de retenção e privacidade, auditoria read-only do recibo, backup/restore e revisão do fluxo CRM/Portal. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
