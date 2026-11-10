# FT01 / SEC-26 — Estado de transporte faz parte da identidade da tentativa de uso

**Data:** 10/11/2026 · **Escopo:** laboratório PHP offline e totalmente sintético · **Operação real: NO-GO**

## Risco corrigido

O medidor `MockUsageLedger` agrupava uma tentativa pelo seu ID, assinatura de intenção e contexto, mas **não pelo seu estado de transporte**. Dois objetos `Attempt` com o mesmo ID e estados distintos, por exemplo `TIMEOUT` e `ENVIADA`, poderiam passar como a mesma tentativa em caminhos de gravação e reconciliação. A impressão digital de eventos também não incluía o estado, permitindo que um evento idempotente com mesmo ID e dados fosse reaproveitado por um objeto com estado diferente.

## Controles

- `record()` inclui `Attempt.state` no fingerprint de idempotência. Um mesmo ID de evento com estado de transporte diferente retorna `USAGE_EVENT_CONFLICT`, sem nova medição.
- `sample()` preserva `transport_state` ao criar a primeira medição e exige coincidência em `record()`, `markUnknown()` e `reconcile()`; colisões geram `USAGE_ATTEMPT_CONFLICT`.
- `reconcile()` só aceita evidência fictícia `MockUsageProof` se o estado da tentativa na evidência coincidir com o da tentativa a conciliar. Divergência mantém `INDETERMINADO/PROOF_CONFLICT` e não autoriza consumo.
- Objetos equivalentes criados separadamente, com **mesmo ID, mesma intenção, contexto e estado**, continuam idempotentes.
- Nenhuma modificação nos limites SEC-25 (512 eventos, 256 tentativas, 1.024 entradas de auditoria), nem inclusão de dados reais, banco ou rede.

## Regressões U48-01..06

Rejeição de ID de evento duplicado com estado alterado, nova medição com mesmo ID de tentativa e estado diferente, TIMEOUT forjado após observação ENVIADA, prova com estado diferente, repetição genuinamente idêntica e medição tardia consistente após TIMEOUT. O teste de falha assegura que não sejam criados novos eventos indevidos.

**Gate da revisão:** **439 casos PHP, 82 arquivos PHP com lint e testes Python de contratos, isolamento e prontidão.** Merge somente na `dev` após CI verde no HEAD exato.

## Limites e próximos bloqueios

A tentativa ainda é fictícia e seus estados não são observações autenticadas de rede. Para uso operacional são necessários contratos versionados, identidade serviço-serviço, ACL por tenant/recurso/campo dentro do CRM, idempotência durável, telemetria e auditoria transacionais. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT** e R01-G01..G11 / CORE-SEG-AUT permanecem **ABERTOS/NO-GO**. Sem `main`, VPS, clientes, credenciais, produção ou custos.
