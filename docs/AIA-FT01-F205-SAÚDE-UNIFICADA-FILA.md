# F205 — Resumo unificado de saúde da fila sintética

**11/11/2026. Exclusivamente laboratório offline, sem autorização operacional.**

A classe `MockQueueHealthSnapshot` reúne a triagem F200 e o diagnóstico de capacidade F204 a partir **da mesma instância de fila**, acessada pelo quadro de revisão. O consumidor não pode fornecer uma segunda fila para forçar uma visão contraditória. O método `assess()` retorna um snapshot agregado `SYNTHETIC_QUEUE_HEALTH_V1` com classificação, sinais ordenados, triagem e capacidade, sempre `operational_decision=NO_GO`, `real_authorization=false`, zero chamadas externas e zero escritas.

## Classificações e precedência

`PAUSED` prevalece sobre `REVIEW_REQUIRED`, depois `CAPACITY_EXHAUSTED`, `CAPACITY_NEAR_LIMIT`, `LEASE_IN_PROGRESS`, `PENDING` e `QUIET`. O conjunto de sinais preserva **todos** os motivos simultâneos, inclusive uma fila pausada com resultados indeterminados e falta de capacidade. O rótulo `QUIET` significa somente que o mock não tem trabalho ativo nem pendente; **não** significa aptidão para produção.

O código exige contexto correto, flags NO-GO em ambos os componentes, contagem de trabalhos igual à soma dos seis estados da fila, contagem de revisões igual à quantidade indeterminada e um mesmo estado de admissão. Qualquer divergência falha fechada.

## Testes

14 casos PHP U102-01..14 cobrem fila vazia, pendência, reserva, timeout, pausa, retomada, incerteza mantida, limites de 60/64 trabalhos, prioridade de pausa, tenant inválido, ligação incorreta ao tenant da fila, ausência de identificadores e 100 observações repetidas sem mutação.

O baseline proposto sobe a **879 testes PHP e lint 109 arquivos**, preservando os verificadores Python/DOM. O resultado de testes offline não libera CRM nem IAM. O PR CRM #57 continua OPEN/DRAFT, 13 testes operacionais OP-01 continuam NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT permanecem NO-GO. Sem alteração da main, VPS, produção, dados de clientes, segredos ou custos.
