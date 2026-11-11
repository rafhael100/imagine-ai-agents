# FT01/F189 — Admissão OPEN/PAUSED no contrato de supervisão

**11/11/2026. Somente PHP e HTML estático, sem CRM real, banco ou rede. Operação: NO-GO.**

## Problema resolvido

A SEC-61 acrescentou o controle de admissões da fila sintética, mas `MockUncertaintyReviewBoard::overview()` e a aba F183 do laboratório visual exibiam apenas contagens. Uma fila com trabalhos `PENDENTE` poderia estar **pausada**, e a interface não conseguia distinguir essa condição de um estado aberto com tarefas prontas para reserva. Interpretar a fila como simplesmente "vazia" ou "livre" seria incorreto.

## Nova integração offline

- `MockUncertaintyReviewBoard::overview(context)` continua sendo somente leitura e agora inclui **`admission: {state: OPEN|PAUSED}`**, extraído do `MockReadOnlyRunQueue::admissionState(context)`. É uma subestrutura **fechada com um único campo**; não transporta referências de decisão, usuários, chaves de claim, leads, IDs reais ou motivo administrativo. O restante de `SYNTHETIC_QUEUE_SUPERVISION_V1` permanece idêntico.
- Uma pausa é visível inclusive se a fila está vazia; contagens de `PENDENTE`, `RESERVADA`, `INDETERMINADO` e revisões continuam preservadas. `REVIEWED_UNRESOLVED` ainda não equivale a conclusão.
- O exemplo executável PHP `examples/f182-queue-supervision-demo.php` preserva seu cenário original, agora explicitamente **OPEN**. Os testes Python `test_f182_queue_supervision_demo.py` exigem igualdade exata do JSON com o campo novo.
- O mesmo JSON estático foi incorporado à aba F183 no `examples/f108-laboratorio-navegavel.html`. A tela mostra **ABERTA · SIMULADA** e valida rigorosamente a presença e os valores permitidos no JS; estado desconhecido ou esquema inesperado falha fechado. Não há polling, publicação ou comando remoto.
- Os testes Python F183 continuam comparando o JSON embarcado com a saída real do exemplo PHP; o smoke Node verifica que a indicação é exibida e permanece NO-GO.

O rótulo **ABERTA · SIMULADA** significa apenas que a fila **fictícia do snapshot** não foi pausada. Não significa permissão de envio, disponibilidade real de worker ou autorização de uso do CRM.

## Testes adversariais U93-01..08

1. OPEN em fila vazia, contrato redigido/fechado;
2. PAUSED com job pendente sem novas reservas;
3. retomada OPEN preserva pendentes e não confirma entregas;
4. contexto estrangeiro não lê status;
5. expiração em pausa mantém `INDETERMINADO` com revisão pendente;
6. duas observações concordantes continuam inconclusivas durante pausa;
7. nenhum ID, segredo ou decisão aparece na estrutura agregada;
8. fila vinculada ao piloto reporta PAUSED sem chamada CRM sintética.

```bash
php examples/f182-queue-supervision-demo.php
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=102 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

**Gate proposto:** 835 testes PHP, lint 102 arquivos PHP, Python e smoke Node offline. CI de PR e pós-merge em SHA exato são obrigatórias; não representam testes de navegador real.

## Limites bloqueantes

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT, os 13 cenários reais OP-01 `NAO_EXECUTADO`, R01-G01..G11/CORE/SEG/AUT **NO-GO**. Este contrato não implementa RBAC, SSE, API, auditoria durável, worker distribuído, autenticação de operador, aprovação humana, armazenamento ou integração real. `main`, VPS, clientes, credenciais, produção e despesas permanecem intocados.
