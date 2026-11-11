# FT01/REV-02 / F182 — leitura agregada de filas e incertezas

**11/11/2026. Apenas PHP sintético e offline. Operação real: NO-GO.**

## Resultado de produto

`MockUncertaintyReviewBoard::overview(TenantContext)` reúne, numa estrutura **fechada, somente leitura e sem identificadores**, o estado da fila supervisionada e os casos de revisão:

- **Fila:** `pending`, `reserved`, `completed`, `failed`, `cancelled`, `indeterminate`.
- **Revisão:** `awaiting_first`, `awaiting_second`, `reviewed_unresolved`, `conflict`.

A visão é apropriada como contrato candidato para um **painel administrativo futuro**, mas não chama Portal/CRM, não autentica usuário e não é uma API. Valida o contexto de empresa do próprio quadro **e da fila**, sem confiar em IDs enviados por provedores. Confere que todos os registros de revisão continuam associados a jobs `INDETERMINADO`. Calcula `awaiting_first` pela diferença entre o total de incertezas e os casos que receberam votos; um desvio interno é negado antes de produzir um painel enganoso.

Todos os estados de revisão preservam a quantidade de jobs indeterminados: um caso `REVIEWED_UNRESOLVED` continua indeterminado, **sem requeue nem autorização implícita**. A resposta sempre traz `contract=SYNTHETIC_QUEUE_SUPERVISION_V1`, `operational_decision=NO_GO`, `real_authorization=false`, `external_calls=0`, `write_calls=0`, além de apenas contagens. Não transporta `run_ref`, `request_ref`, claim, empresa, revisor, motivos individuais, dados de lead ou texto da IA.

## Demonstração executável

```bash
php examples/f182-queue-supervision-demo.php
python3 -m unittest discover -s tests/Contracts -p 'test_f182_queue_supervision_demo.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=97 EXPECTED_CASES=776 bash tools/run_r01_offline_quality_gate.sh
```

O exemplo tem três jobs cujo prazo expirou, um job concluído no próprio laboratório e cinco observações sintéticas: um resultado revisado ainda não resolvido, um conflito entre revisores e um caso aguardando segunda revisão. JSON fixo esperado: `queue.completed=1`, `queue.indeterminate=3`, `review.awaiting_second=1`, `review.reviewed_unresolved=1`, `review.conflict=1`, `NO_GO`.

**Testes U87-01..10:** visão vazia, todos sem revisão, primeira observação, consenso não resolutivo, divergência, contagem mista, replay idempotente, contexto estrangeiro, erro de montagem entre quadro/fila e ausência de efeitos ou vazamento.

**Testes Python F182 (5):** saída JSON exata; determinismo mesmo sob stdin adversarial; nenhum ID/segredo; ausência de rede, banco e configurações externas; e invariante de que revisões não reduzem `indeterminate`.

**Gate proposto:** 776 testes PHP, lint 97 PHP, verificadores Python e isolamento sintético, CI HEAD e pós-merge. Nenhum teste aprova operação real.

## Limites de segurança e dependências

A visão usa objetos em um processo PHP, não assegura consistência distribuída, storage durável, IAM, segregação real de revisores, assinatura de auditoria, retenção de PII, API, billing, integração CRM, replay de rede ou autenticação. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) está OPEN/DRAFT, 13 cenários OP-01 reais `NAO_EXECUTADO`, gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Sem `main`, VPS, segredos, clientes, deploy ou custos.
