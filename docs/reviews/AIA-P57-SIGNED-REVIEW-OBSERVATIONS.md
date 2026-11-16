# AIA-P57 — Pareceres independentes assinados para propostas sintéticas

16/11/2026. Candidata de laboratório empilhada sobre a P56 (#440); nenhuma aprovação operacional ou publicação.

## Núcleo implementado

O \`PgSignedDraftReviewLab\` permite registrar **pareceres** sintéticos sobre rascunhos persistidos da P51 com fingerprint da P52. Há dois papéis fechados: PRODUCT e SECURITY. Cada revisor de laboratório é vinculado a uma única empresa, possui chave pública Ed25519, papel, versão e status de revogação.

Um parecer traz os campos canônicos da empresa, proposta, papel, revisor, decisão \`ACKNOWLEDGE\` ou \`REJECT\`, hash do rascunho, versão da chave e validade. A assinatura Ed25519 é verificada dentro de uma transação PostgreSQL com locks \`FOR SHARE\` na proposta e na chave do revisor. A unicidade de tenant/proposta/papel e de tenant/proposta/revisor impede duplicidade de votos por papel e atuação do mesmo revisor nos dois papéis.

Repetir exatamente um parecer gera replay idempotente. Tentar substituir parecer já gravado, atuar em outro tenant, usar papel indevido, chave revogada, assinatura inválida, proposta modificada ou reenviar parecer expirado produz DENY. Uma recusa gravada impede novos ACKs sobre a mesma proposta. A consulta de estado é \`REPEATABLE READ, READ ONLY\` e revalida criptograficamente todos os pareceres presentes.

**Regra inegociável:** dois ACKs assinados retornam \`LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION\` com \`can_approve=false\`, \`can_execute=false\` e \`operational_authorization=false\`. São duas observações independentes de laboratório, **não uma aprovação empresarial**, não concedem permissão para n8n, CRM, cobrança, mídia ou IA. O mesmo vale para o estado rejeitado.

## Testes

CI: dupla assinatura Ed25519 de papéis diferentes, replay exato, recusa de segundo produto, tentativa de produto agir como segurança, adulteração de assinatura persistida, restauração de prova histórica, recusa de proposta, revogação de chave, expiração e isolamento entre empresas. A workflow ainda executa o motor P13–P56, navegador PostgreSQL de CI e n8n oficial isolado.

## Pendências operacionais

Não há usuários humanos autenticados nem integração com IAM do CRM/Portal. As referências e chaves são sintéticas; não há registro de autor real, segregação real entre autores e revisores, auditoria inviolável, MFA, consentimento, retenção de LGPD ou estágio de execução. A implantação e qualquer mutação real permanecem bloqueadas até revisão independente e aceite explícito.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
