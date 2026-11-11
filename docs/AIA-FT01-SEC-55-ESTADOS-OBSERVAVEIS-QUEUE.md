# FT01/SEC-55 — estados observáveis de fila sem falso EMPTY

**11/11/2026.** Laboratório PHP sintético e offline. **CRM real, piloto operacional e produção: NO-GO**.

## Problema

No protótipo de fila OP-01, `claim()` devolvia `null` quando não havia um item **pendente**, mesmo que já houvesse:
- um item `RESERVADA` aguardando resposta até expirar o lease; ou
- um item `INDETERMINADO` por lease expirado, sem prova de que a leitura/sugestão não ocorreu.

O piloto convertia indiscriminadamente `null` em `EMPTY`. O executor em lote, por sua vez, poderia contar um estado incerto como nova leitura negada ou seguir consumindo pares de provas sem uma operação correspondente.

## Mudança

`MockReadOnlyRunQueue` fornece duas verificações de estado **somente leitura**, vinculadas ao contexto fictício da empresa, sem revelar IDs ou claims:
- `needsReview(context)` indica ao menos um resultado `INDETERMINADO`;
- `hasActiveReservation(context)` indica reserva ainda ativa.

Depois de `claim()` varrer os prazos expirados, `MockQueuedReadOnlyPilot::runNext()` sinaliza **`REVIEW_REQUIRED` em preferência a `IN_PROGRESS`** quando o retorno de `claim()` é nulo. Só devolve `EMPTY` quando não há pendente, reserva ativa ou incerteza. Nenhum desses estados concede permissão, executa leitura ou dispara retry.

`MockQueuedBatchRunner::drain()` reconhece `REVIEW_REQUIRED` e `IN_PROGRESS`, **interrompe o lote**, preserva contagens de jobs realmente processados e não consome os próximos pares como negativas fictícias. Um lote parcial ainda informa suas contagens de sucesso e negação, mas com estado global de pendência/revisão. A API pública desta simulação conserva `real_authorization=false`, `external_calls=0` e `write_calls=0`; estados de espera não incluem texto nem IDs.

As garantias são de processo único, sem persistência ou atomicidade de fila distribuída. Estado `INDETERMINADO` é deliberadamente irreversível neste laboratório: não se inventa conclusão nem se reenvia.

## Regressões U83-01..12

- Distinção entre reserva em curso e claim expirado;
- `IN_PROGRESS` sem segundo acesso ao leitor, audit ou Run;
- `REVIEW_REQUIRED` na fronteira exata do lease;
- Lote com reserva ativa contabiliza zero leituras;
- Lote com resultado indeterminado contabiliza zero leituras;
- Sucesso parcial preserva `REVIEW_REQUIRED` sem contagem fictícia;
- Cancelamento terminal é `EMPTY`;
- Falha terminal conhecida é `EMPTY`;
- Estados públicos sem IDs, claims ou recursos;
- Outra empresa não consulta o sinal de revisão;
- Polling repetido de reserva não consome jobs;
- Polling repetido de revisão não dispara retries.

O teste anterior `U79-09` passa a exigir `REVIEW_REQUIRED` no lugar de `EMPTY` em lease expirado. **Gate solicitado:** 733 testes PHP, lint de 91 arquivos PHP e todos os validadores Python offline, CI HEAD e CI pós-merge.

## Impedimentos reais

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT, sem autenticação bilateral, API ou autorização real; 13 cenários OP-01 `NAO_EXECUTADO`; gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Sem merge `main`, VPS, clientes, dados reais, segredos, deploy, despesas ou serviço operacional.
