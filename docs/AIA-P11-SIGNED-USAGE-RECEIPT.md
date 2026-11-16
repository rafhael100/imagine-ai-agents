# Agents P11 — prova criptográfica de medição antes de conciliar crédito

**16/11/2026 — ensaio de assinatura Ed25519 em PostgreSQL16 descartável. Não é integração com um provedor real.**

## Risco fechado

A P10 impediu cobranças sem a etapa de despacho, mas a conciliação ainda aceitava um número `observed` fornecido diretamente pelo chamador. Isso **não comprova** que a informação de uso veio do provedor. Agora a função pública `reconcileSigned()` aceita somente **recibo sintético, canônico e assinado Ed25519**, com uma chave pública de verificação injetada pelo harness confiável. A função bruta anterior foi tornada privada: não existe mais API pública de conciliação por número sem prova.

O recibo contém exatamente oito chaves ordenadas: `contract`, `issuer`, `proof_ref`, `tenant_ref`, `request_ref`, `observed`, `issued_at`, `expires_at`. Os campos são estritos; o payload deve estar byte-a-byte em JSON canônico (sem duplicidades, mudanças de whitespace ou escape) e ter validade de no máximo 60 segundos no relógio do teste. A assinatura detached Ed25519 deve verificar sob o public key registrado no objeto **antes** da operação financeira.

O banco de teste armazena `proof_hash` associado ao estado `FINALIZED`, protegendo também a identidade do recibo: repetir o mesmo comprovante é idempotente e não cobra duas vezes; uma segunda medição incompatível ou novo recibo contraditório congela o saldo até revisão.

## Validações previstas

- Assinatura válida gerada em memória durante o job contra chave pública registrada no laboratório.
- Assinatura de outra chave, tampering de bytes, intervalo vencido ou futuro, empresa/requisição divergente e ausência de âncora de confiança são recusados.
- `DISPATCHED` obrigatório antes de medição, `UNCERTAIN` mantém reserva após timeout.
- Reserva física PostgreSQL16 concorre em dez processos, com cinco admissões e cinco negativas; a conclusão verifica estado/contabilização idempotente.
- Regressões de n8n P9 (ACK com fencing) e P4 (cotas de 12 workers) continuam verdes.

**Riscos pendentes, fundamentais:** a chave do teste é gerada localmente no GitHub Actions e **não atesta nenhum fornecedor de IA real**. Em produção seria necessário registro de chaves do fornecedor, política de rotação/revogação, prova de identidade S2S, autorização do tenant, replay durable, relógio confiável, validação dos valores cobrados contra extratos e auditoria independente. O usuário nunca deve fornecer a própria chave pública de verificação; ela tem de vir de canal de confiança. Este recorte ainda está em `tools/labs`, com flag CI explícita, sem rotas, provedor, CRM, n8n, cobrança ou VPS.

**NO-GO / DRAFT / NO-MERGE / NO-DEPLOY.**
