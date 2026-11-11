# FT01/SEC-58 — Relógio monotônico transacional da fila read-only

**11/11/2026. Laboratório PHP sintético e offline. CRM real/piloto/produção: NO-GO.**

## Defeito de integridade corrigido

O scheduler `MockReadOnlyRunQueue` preserva um relógio monotônico de laboratório para evitar regressões de lease/estado. Antes desta correção, `enqueue`, `claim`, `finish` e `cancel` chamavam `clock(now)` **antes de validar todas as condições de negócio**. O método validava o horário e imediatamente gravava `observedAt`. Assim, um **replay idempotente ou chamada recusada** com horário sintético muito distante poderia avançar silenciosamente o relógio, deixando os jobs intactos, mas causando `QUEUE_TIME_DENIED` nas operações legítimas seguintes.

Exemplos antes da correção: `enqueue` com `request_ref` duplicada de outro Run a `now=1000000` era recusado, mas um `claim` legítimo a `now=11` também falhava. `cancel` de job inexistente, `finish` com claim falso ou estado inválido e polling sem trabalho tinham efeito semelhante sobre o relógio.

## Nova regra

`assertClock(now)` **somente valida**: tempo não negativo, não inferior ao último horário efetivamente confirmado. `commitClock(now)` grava o relógio apenas quando a operação já passou por validação de identidade, contexto, estado, capacidade de auditoria e está prestes a modificar o job.

- **Enqueue:** commit depois da checagem de par idempotente, unicidade de `request_ref`, capacidade e espaço de auditoria; replays exatos e conflitos não avançam o relógio.
- **Claim:** calcula de antemão todas as expirações e eventual nova reserva. Somente se existe evento de alteração, com espaço de auditoria, confirma o horário. Poll sem reserva e sem expiração não altera o relógio.
- **Finish:** confirma após verificar claim, estado e espaço de auditoria. Se lease já expirou, a operação **muda para `INDETERMINADO` e também confirma o horário**, mesmo devolvendo `QUEUE_CLAIM_EXPIRED` para o chamador; não é falsa ausência de trabalho.
- **Cancel:** confirma somente antes da transição legítima de `PENDENTE` para `CANCELADA`, não em cancelamento inexistente, conflitante ou idempotente.
- **Consultas read-only:** seguem sem modificar relógio, jobs ou trilha.

A mudança é apenas para a fila simulada em um processo PHP. O `now` é fornecido pelo teste, não relógio criptográfico ou autoridade operacional confiável.

## Regressões U89-01..16

1. Replay de enqueue em horário distante não bloqueia novo job;
2. Duplicação de `request_ref` negada não envenena o relógio;
3. Conflito de `run_ref` não envenena o relógio;
4. Tentativa da 65ª tarefa, negada por capacidade, não bloqueia claim legítimo;
5. Cancelamento inexistente negado não altera o relógio;
6. Cancelamento de reserva ativa negado não invalida lease;
7. Replay de cancelamento terminal não altera relógio;
8. Resultado inválido de `finish` não altera relógio;
9. Claim formalmente válido, mas incorreto, não altera relógio;
10. Replay de conclusão terminal não altera relógio;
11. Poll de fila vazia a horário extremo não envenena novas tarefas;
12. Poll com lease ativo sem expiração não impede finish posterior;
13. `finish` expirado **deve** alterar estado e confirmar relógio;
14. `claim` que expira reservas **deve** confirmar relógio;
15. Enqueue, cancel e finish válidos preservam bloqueio contra rollback;
16. Contexto falso, lease inválido e referência excessiva são negados sem efeitos.

**Gate proposto: 792 testes PHP, lint 98 arquivos PHP, validadores Python/Node offline**. CI de PR e pós-merge no SHA exato obrigatórias. Não há deploy, API ou assinatura de tempo.

## Bloqueios

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT; 13 casos OP-01 reais `NAO_EXECUTADO`, todos os gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. É necessário projeto futuro de consistência/lease durável entre processos, autenticação de worker e teste de concorrência real. Nenhuma alteração em `main`, VPS, produção, clientes, segredos ou custos.
