# AIA-P42 — Uma admissão mTLS → Um stage P16/P6 em commit único

16/11/2026. Continuação da P41 (#411), mantendo fontes CRM/Portal/CENTRAL-AUTO-P7 em commits independentes e sem instalação operacional.

A P41 já verificava o mapeamento explícito de empresa e uma linha real de ledger do mTLS de laboratório, mas encerrava a transação antes do stage. Isso permitia revogação no intervalo e, pior, reutilização da mesma admissão de transporte para eventos diferentes.

**Mudanças:**
- Método `inspectWithinTransaction` reutiliza os mesmos critérios de P41, mas mantém FOR SHARE na associação company_id/tenant_ref e ledger do nonce até o commit do chamador; o pré-check antigo continua funcional.
- Método `PgSyntheticAtomicStager::stageWithinTransaction` usa as primitivas P16 e P6 na transação já aberta, sem commitar nem declarar sucesso operacional; o `seen` local só é publicado após commit.
- Nova classe `PgAtomicCompanyTenantStageLab` inicia transação única, valida associação e o nonce original P7, **consome a prova de transporte apenas uma vez** numa tabela com unicidade por empresa, request e nonce, grava ledger antirrepetição P16 e outbox P6, e confirma os três registros juntos. Qualquer falha faz rollback de todos.
- Testes: admissão mTLS real de loopback → stage protegido sintético, tentativa de usar a mesma admissão para evento diferente, falha de assinatura e nova tentativa sem prova perdida, troca de empresa, revogação e auditoria de integridade.

Como o fluxo P7 usa relógio real do runner e as fixtures P18 usam timestamp sintético fixo, o ensaio recebe explicitamente dois relógios independentes, **somente na CI**. Isso não é uma API operacional nem modelo válido de gestão temporal do produto.

**Limites essenciais:** saber a linha P7 já admitida não substitui autenticação do solicitante atual. Não existe integração do IAM do CRM ou Portal, nem traduz autoridade em autorização de operação real, consentimento ou cobrança. A tabela de consumo é sintética, não uma migration. Não transmite a n8n e mantém NO_AI sem crédito. Revisão de segurança e homologação operacional seguem bloqueadas.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
