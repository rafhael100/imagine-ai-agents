# FT01/F1-17 — Envelope público uniforme de leitura CRM, apenas sintético

## Estado vigente — F1-17 integrado exclusivamente à dev (09/11/2026)

- **PR [#116](https://github.com/rafhael100/imagine-ai-agents/pull/116) MERGED por squash somente em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `69992f878c7b3d6e16b60ff4f446f47c3e23edba`. A `main` foi conferida no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deploy, VPS, CRM real, credenciais ou gastos de fornecedor.
- **Prova efetivamente executada:** [GitHub Actions #42583032672](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42583032672), HEAD testado `9e60e0574bc5dfe8f277e4822a3adf6b93777936`, PHP 8.3.6, **214 cases / 0 failures**, **70/70 PHP lint**, `VALIDATION_PASS` de SHA fixado.
- **Escopo entregue:** `MockCrmReadPublicFacade` exige mocks da política proprietária F1-16 e auditoria F1-13 para produzir conteúdo público uniforme `DENIED_OR_NOT_FOUND` em falhas simuladas, com 16 testes U17. Não há HTTP, dados privados ou garantia de indistinguibilidade de tempo/status de rede.
- **Gates ABERTOS:** R01-G01..G11, CRM CORE/SEG/AUT e aceite bilateral do CRM. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue apenas documental DRAFT. O laboratório não autoriza `ALLOW`, API real, IAM, SQL, operações com clientes nem produção.
- **Riscos seguintes:** validar futuramente observabilidade da borda para erros de parsing sem registrar payload, política de falhas HTTP/latência, identidade verificável, revogação persistente, autorização final por recurso/campo pelo CRM e fluxos de incidentes. Isso requer gates de integração específicos; testes de mock não os encerram.

**Precedência:** menções abaixo a F1-17 pendente/sem CI são históricas, anteriores ao merge #116.

---


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
