# AIA-P45 — Revogação/expiração do grant CRM bloqueia execução posterior

16/11/2026. Continuação empilhada sobre P44 (#418). Não há merge, deploy ou credenciais reais.

**Risco:** P44 exigia decisão explícita assinada pelo serviço proprietário ao registrar o evento, mas essa decisão não era revalidada quando o worker solicitava lease ou enviava ACK. Um grant revogado ou expirado após o stage poderia continuar sendo tratado como autorização.

**Mudanças:** o registro durável de laboratório `consumed_owner_grants` conserva agora o corpo canônico completo, a assinatura Ed25519, o hash, a revisão da chave de owner, escopo e operação, para verificação posterior. `PgOwnerExecutionGuardLab` bloqueia `FOR SHARE` tanto a linha do grant como a chave pública de owner durante a própria transação PostgreSQL de lease/ACK, recalcula hashes, confere os campos e escopos fechados, validade temporal, versão, estado ativo e assinatura. Revogação concorrente espera a transação de decisão terminar. Se o grant expirou, a chave foi revogada ou a evidência foi alterada, a transação falha fechada.

`PgOwnerBoundSignedDispatchLab` obriga simultaneamente a associação de empresa P41/P43, evento P42, grant P44 ativo, isolamento P34 e recibo de executor Ed25519 V2 com persistência P38. Métodos de laboratórios anteriores não são removidos: a proteção P45 aplica-se apenas ao facade novo, sem criar um endpoint real de produção.

**Testes:** grant owner válido permite lease e ACK sintético; adulterar assinatura armazenada impede lease; expiração da autorização impede nova reserva; revogar chave do proprietário após stage bloqueia evento pendente; revogar após lease impede ACK mesmo com recibo de executor válido e não gera evidência falsa.

**Limites críticos:** o owner é uma fixture fictícia, não a autorização real do CRM; a temporização do P18 é sintética. Requer integração operacional com o serviço proprietário e seus estados de consentimento e privilégio, revogação real de contas, aprovação AppSec, credenciais em vault/KMS, n8n privado, backup e rollback. Nenhuma IA ou despesa paga.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
