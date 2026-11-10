# R01/EVID-22 — Medidor de uso protegido contra esgotamento de memória

**Data:** 10/11/2026. **Situação:** entrega de laboratório offline validada; **NO-GO** para CRM operacional, piloto e produção.

## Evidência verificável

- **Correção SEC-25:** [PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192) integrado somente na branch `dev/ft01-aia-core-mocks-2026-11-06`, commit `b19a769cdbf10e33491bc05231d389c621204526`.
- **CI de revisão:** [#42859060377](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859060377) **success**.
- **CI pós-merge:** [#42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) — conferir a conclusão no próprio GitHub; evidência adicional, não liberar operação por esse resultado.
- **Baseline de código:** **433 testes PHP**, lint de **82 arquivos PHP**, verificadores Python de contrato, isolamento, prontidão e pacote bilateral.
- **Arquivo alterado:** `src/MockUsage/MockUsageLedger.php`. **Testes:** U47-01..06 em `tests/Unit/usage_quota.php`.

## Comportamento entregue

A instância em memória registra no máximo 512 eventos materiais, 256 tentativas e 1.024 itens de auditoria, sem excluir registros antigos. Medições novas, timeout e conciliação que precisem alterar o estado fazem preflight da capacidade. O código retorna `USAGE_CAPACITY_DENIED` quando falta espaço para uma mutação; a consulta `budget` retorna `INDETERMINADO / MEASUREMENT_CAPACITY_EXCEEDED` e não simula disponibilidade de uma cota que não pode ser medida. Repetição de evento idêntico mantém idempotência.

O módulo continua **não transacional, não persistente, sem preço, sem faturamento e sem provedor de IA externo**. Os testes não validam créditos comerciais ou autorização real; em produção, cotas e rate limiting precisam de transações, persistência, trilha confiável e política por tenant.

## Critérios para a etapa seguinte

1. CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT** (proposta documental, não API homologada).
2. [Pacote bilateral OP-01](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md): sete decisões pendentes, 14 gates, 13 casos sem execução real.
3. CRM CORE/SEG/AUT e responsáveis de Privacidade/Infra/Segurança devem aprovar autenticação serviço-serviço, ACL backend por empresa/principal/lead/campo, antifraude/antirreplay, LGPD, observabilidade e rollback.
4. Somente após liberação específica criar uma homologação isolada com dados de teste; piloto e produção exigem decisões independentes.

**Estado de gates:** R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS/NO-GO**. Sem `main`, VPS, produção, clientes, credenciais, fornecedores pagos ou custos nesta rodada.
