# Central AUTO P0 — validade da chave até commit

Base: AUTO-IA-RC03 #371, `b408fa97b3b23df4763556e76ad854ea4e2fa5d6`. CRM #632/#633, Portal #903. Autorização da direção 16/11/2026. DRAFT / NO-MERGE / NO-DEPLOY.

Achado da revisão de integração: o receiver PostgreSQL lia `enabled` e o segredo sem row lock. Uma revogação podia confirmar depois dessa leitura e antes da admissão/retorno de duplicidade. A verificação sequencial de replay após revogação não demonstra ausência dessa janela.

Correção limitada: `FOR SHARE` na leitura da chave vigente. Revogação/rotação por UPDATE precisa ordenar com a transação de ingresso. Admissão que já detém a chave termina antes da revogação; toda tentativa posterior à revogação confirmada é negada, incluindo replay exato. Não se promete cancelar retroativamente um commit legítimo anterior. O bloqueio deve durar a transação, por isso chamadas operacionais futuras precisam de timeout/limites transacionais.

Teste: duas sessões PostgreSQL reais; a primeira admite e mantém transação aberta; a segunda tenta revogar com lock_timeout e comprova bloqueio. Após commit, revoga e repete o mesmo envelope, que deve retornar LAB_DENY_KEY. Exige exatamente uma inbox. Reexecuta casos assinados existentes. SQL/identidades/chaves continuam exclusivamente sintéticos e descartáveis.

Não é revisão independente externa. Não fecha autoridade por empresa/ação, identidade mTLS, n8n, Gateway operacional ou qualquer gate R01/CORE/SEG/AUT. Não altera outras linhagens de outbox nem presume integração de PRs DRAFT. Consumidor CRM deve atualizar pin somente após verificar CI desta SHA.
