# FT01/F1-14 — Coordenação de nonce/revogação entre gateways fictícios

**Data:** 09/11/2026. **Status inicial:** recorte em branch filha, sem integração à dev; laboratório 100% offline e sem credenciais ou dados reais.

## Objetivo
Demonstrar, por testes negativos, que duas instâncias **simuladas dentro de um único processo PHP** podem rejeitar o mesmo nonce, observar revogação fictícia e negar acesso quando a memória compartilhada está indisponível. Isso **não implementa** Redis, banco, mensageria, multi-host, identidade criptográfica, autenticação nem retenção durável.

## Mudanças propostas
- `src/MockTrust/MockSharedTrustState.php`: objeto de memória compartilhado por referência, consumo único de nonce indexado por par emissor/destinatário, revogação por digest sintético, teste de indisponibilidade fail-closed. O objeto não persiste nem replica.
- `src/MockTrust/MockTrustGateway.php`: parâmetro **opcional** `?MockSharedTrustState`; os testes antigos continuam com armazenamento privado por gateway (F1-11), a simulação F1-14 compartilha explicitamente o novo objeto entre gateways.
- `src/MockAudit/MockCrmReadAuditedBoundary.php`: categoriza uma falha de indisponibilidade sintética como `TRUST`, nunca expõe erro livre ou payload do CRM.
- `tests/Unit/shared_trust_state.php`: 12 novos testes U14 para replay, nonce trocado, multiempresa, revogação, falha/recovery, emissor incorreto e auditoria F1-13, além de toda regressão anterior.
- Runner e validador SHA-fixado passam a esperar **168 casos** e **65 arquivos PHP**. **Metas**, não evidências de execução até a conclusão do CI.

## Limites e riscos residuais
- Dois gateways em um processo **não** provam cross-process, distribuição, atomicidade real, corrida concorrente ou consistência de caches.
- `MockTrustEnvelope` continua um objeto fictício com digest SHA-256 **não assinado**; `MockOwnerScopeEvidence` não prova ACL real. Passar nesses testes não converte a etapa em identidade verificável.
- O mecanismo opcional preserva intencionalmente a lógica histórica local dos testes F1-11 e F1-12; **essa lógica não é segura entre instâncias reais e não deve ser usada em produção**.
- Não foram adicionados segredos, endpoints, SQL, conexões, LLM, fornecedores ou dados pessoais. Não se alterou CRM, Portal, `main` ou VPS.
- Gates R01-G01 a G11 e CRM CORE/SEG/AUT continuam **ABERTOS**; CRM PR #57 segue documentação em rascunho.

## Pré-condições para merge exclusivamente na dev
1. Diff isolado e revisão dos casos negativos, sem APIs reais.
2. Workflow com runner padrão Linux **somente no repositório público**, sem secrets e sem artefatos persistidos; se voltar ao modo privado, job não roda.
3. Validador `EXPECTED_SHA=<HEAD> bash tools/validate_ft01_offline.sh` com **168/168** testes e **65/65** PHP lint, sem falhas.
4. Verificação de base/head e mergeabilidade. Nenhum destes testes fecha os gates operacionais.
