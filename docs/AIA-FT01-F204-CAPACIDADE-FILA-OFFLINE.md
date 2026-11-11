# FT01/F204 — Diagnóstico de capacidade da fila offline

**11/11/2026. Novo método PHP somente de leitura. CRM real e produção: NO-GO.**

## Capacidade implementada

`MockReadOnlyRunQueue::capacityOverview(TenantContext)` expõe o contrato agregado e fechado `SYNTHETIC_QUEUE_CAPACITY_V1`, validando o contexto multiempresa fictício antes de responder. Sem IDs de execução, pedido, claim, tenant ou controle, mostra os recursos de armazenamento local com limites conhecidos:

| Recurso | Limite do mock | Contadores |
| --- | --- | --- |
| Trabalhos conhecidos, incluindo concluídos, cancelados e indeterminados | 64 | usados, limite, restantes |
| Eventos de auditoria locais | 256 | usados, limite, restantes |
| Decisões de pausa e retomada | 32 | usados, limite, restantes |

O estado agregado de pressão é `NORMAL`, `NEAR_LIMIT` (até 4 slots de trabalho, 16 de auditoria ou 2 de decisão restantes) ou `EXHAUSTED`. A propriedade `resume_budget_available` reflete **apenas espaço contábil**: com uma única decisão restante, o código preserva a reserva de pausa de emergência e não sugere retomada. Não é autorização para chamar `resumeAdmission()` ou liberar clientes.

O método não muta filas, não incrementa relógios, não gera eventos e nunca abre acesso externo. Todo retorno usa `operational_decision: NO_GO`, `real_authorization: false`, `external_calls: 0`, `write_calls: 0`.

## Evidências

A suíte PHP `tests/Unit/queue_capacity.php` adiciona **14 regressões U101-01..14**, para casos vazio, pendente, reservado, concluído, cancelado, expirado, pausa, retomada, tenant divergente, saturação de 64 slots, aviso com quatro slots disponíveis, decisão de controle nº31, leitura repetida sem efeitos e ausência de identificadores.

**Novo baseline: 865 casos PHP e lint de 107 arquivos**, mais os contratos Python/DOM existentes. Atualizados workflow, script de validação, testes canônicos, STATUS/README e métricas do laboratório F108. O resultado ainda exige CI verde no SHA exato.

```bash
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=107 EXPECTED_CASES=865 bash tools/run_r01_offline_quality_gate.sh
```

A `main`, VPS, produção, clientes, dados reais, IAM, credenciais e gastos permanecem inalterados. CRM PR #57 OPEN/DRAFT, treze testes reais OP-01 NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT NO-GO.
