# AIA-P37 — Registro de assinantes Ed25519 com rotação e revogação

16/11/2026. Complemento da P36 (#402), **somente PostgreSQL16 descartável de CI**.

O verificador V2 da P36 recebia a chave pública por parâmetro. A P37 fornece um registro fictício de chaves públicas por empresa, com `key_id`, hash SHA-256 da chave, status ativo e revisão monotônica. Não armazena chaves privadas. Registro único impede reintroduzir a mesma chave revogada com outro nome, e rotação da chave antiga para nova ocorre em uma transação PostgreSQL.

**Proteção transacional obrigatória:** a nova API `PgRegistryBoundExecutorAckLab` recupera a chave pública, verifica o recibo V2, mas também passa `key_id` e fingerprint para a P30/P34. Esta última usa `SELECT ... FOR SHARE` na linha da chave na **mesma transação de ACK** e nega chave revogada, substituída ou com fingerprint divergente. A revogação concorrente espera até a transação de ACK confirmar, prevenindo uso da chave depois que a revogação concluiu.

Testes: nova chave confirma, chave antiga não confirma após rotação, assinatura antiga não é válida sob chave nova, revogação após o lease impede ACK mesmo com assinatura matematicamente correta, empresa B é independente, reutilização da chave antiga é proibida e lock FOR SHARE serializa revogação.

**Limites:** não é PKI/KMS nem identidade operacional de n8n; utiliza pares de teste gerados no processo. Recibo e chave válidos não substituem autorização CRM/Portal, canal TLS/mTLS nem prova de execução n8n. Os métodos antigos não são removidos e continuam apenas em laboratório. CI não instala servidores nem usa dinheiro, dados reais ou APIs de IA. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
