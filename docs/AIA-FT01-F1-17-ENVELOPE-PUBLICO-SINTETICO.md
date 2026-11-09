# FT01/F1-17 — Envelope público uniforme de leitura CRM, apenas sintético

**Data:** 09/11/2026. **Estado inicial:** proposta de implementação offline em branch filha. **Não existe rota HTTP, cliente real, endpoint CRM nem mecanismo de autorização operacional.**

## Objetivo
Um consumidor externo não deve distinguir, pela **estrutura e conteúdo lógico** da resposta, entre: recurso inexistente, recurso pertencente a outra empresa, campo não permitido, revogação, ausência de autorização anterior ou indisponibilidade da política proprietária. No piloto real, as camadas HTTP, tempo de resposta e observabilidade também precisam ser testadas: **esta etapa não homologa comportamento temporal nem status de rede**.

## Projeto e responsabilidades
- `src/MockBridge/MockCrmReadPublicFacade.php`: objeto-fachada **apenas local** com construtor que exige `MockTrustGateway`, `MockCrmReader`, `MockCrmOwnerPolicy` (F1-16) e `MockCrmReadAuditTrail` (F1-13). Um consumidor não pode omitir acidentalmente o mock de revalidação proprietária.
- Monta a fronteira F1-12 e o auditor F1-13 existentes; não recria autenticação, policy engine ou a base de leads. A requisição de teste é um **array PHP**, nunca HTTP/JSON recebido de clientes.
- Resposta de sucesso: `contract=SYNTHETIC_CRM_AIA_PUBLIC_V1`, `state=READ_SYNTHETIC_ONLY`, e somente projeção fictícia permitida `ref/stage`. Valida que nenhuma chave/campo extra e nenhum valor não sintético consiga sair.
- Resposta de qualquer falha: **mesmo** `state=DENIED_OR_NOT_FOUND`, `projection=null`, sem `request_ref`, tenant, motivo técnico, exceção, stack trace, usuário, autorização ou payload de lead. Ambas as respostas incluem `real_authorization=false` e `external_calls=0`.
- `tests/Unit/public_read_facade.php`: 16 cenários U17, com suspensão/revogação, recursos cruzados, provas ausentes/expiradas, replay, schema manipulado, campos pessoais, indisponibilidade do dono, falha de trilha e minimização.
- Runner e validador SHA-fixado passam a exigir **214 testes PHP e 70 arquivos PHP lint**, sujeitos à CI antes de integrar à dev.

## Riscos e decisões ainda abertos
- **Não há garantia de indistinguibilidade temporal**: sem HTTP real, banco, distribuição, carga, latência, status codes, cache ou roteamento. O envelope apenas previne diferenças de **conteúdo** em falhas simuladas.
- Erro ocorrido antes de construir `MockCrmReadRequest` não alcança o auditor F1-13; em serviço real, a borda deve contar tentativas inválidas de forma segura sem registrar payloads ou identificadores brutos.
- A captura genérica de erros só produz um valor de retorno em memória. Não substitui log interno de incidentes, detecção de corrupção de dados, telemetria ou alertas reais.
- A política F1-16 e a evidência F1-11 são **mocks** sem autenticação ou ACL CRM. Os gates **R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos**; CRM PR #57 é proposta documental draft.
- Nenhum uso de dados pessoais, APIs, credenciais, VPS, `main`, produção, LLM ou fornecedores pagos.

## Critério de integração exclusivamente dev
Confirmar escopo do diff, branch base/head e logs de `EXPECTED_SHA=<HEAD> bash tools/validate_ft01_offline.sh` com **214/214 testes e 70/70 lint**. Nenhuma mudança de gate operacional, lançamento de recurso ou implementação no CRM está autorizada.
