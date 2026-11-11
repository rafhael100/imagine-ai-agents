# FT01/SEC-56 — request_ref não pode apontar para dois Runs

**Data 11/11/2026.** Apenas PHP offline com dados sintéticos. **NO-GO para CRM real, piloto e produção.**

## Defeito corrigido

A fila já era idempotente por `run_ref`: repetir o mesmo Run com o mesmo request funcionava como replay seguro. No entanto, o mesmo `request_ref` poderia ser cadastrado em dois Runs **diferentes**, inclusive depois de um deles ter sido concluído, cancelado, falhado ou se tornado `INDETERMINADO`. Isso quebra a unicidade do vínculo de correlação e abre margem a uma segunda tentativa com prova fictícia nova, sem que o segundo pedido seja reconhecido como reenvio.

`MockReadOnlyRunQueue::enqueue` agora verifica se a referência de requisição já consta de **qualquer** job, independentemente de estado. Somente a repetição do mesmo par `(run_ref, request_ref)` é idempotente. Reutilizar um mesmo `run_ref` com `request_ref` diferente continua `QUEUE_IDEMPOTENCY_CONFLICT`; reutilizar um `request_ref` com outro Run retorna `QUEUE_REQUEST_CONFLICT`.

O repositório mantém no máximo 64 jobs em memória e não os remove. A checagem varre essas até 64 entradas, não cria índice volátil extra e ocorre antes de reservar auditoria ou modificar jobs. O estado original nunca é reiniciado para permitir retry, especialmente quando a conclusão é incerta.

## U84-01..08

1. Duas Runs pendentes disputando o mesmo request: negação antes de auditoria;
2. Replay exato do mesmo par permanece idempotente;
3. Request concluído não pode ser reassociado;
4. Request cancelado permanece reservado para rastreabilidade;
5. Request em `INDETERMINADO` não vira novo job;
6. Requests distintos seguem ordem FIFO normal;
7. Com 64 vagas preenchidas, conflito de request é detectado sem evicção;
8. Pedido duplicado negado não consome prova, não provoca segunda leitura e não cria mutação.

**Gate solicitado:** 741 testes PHP, lint dos 91 arquivos PHP existentes, validadores Python offline, CI PR + pós-merge. Continuidade estritamente na branch de desenvolvimento.

## Limites

Esta é **idempotência de processo único**: não implementa deduplicação distribuída, persistência, fila real, assinatura de chamadas, autorização ou replay durável. O `request_ref` sintético não é token de identidade nem credencial. Não há integração CRM autenticada, API, IAM, ACL/RLS, execução de cliente ou homologação.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT, 13 cenários reais `NAO_EXECUTADO`, gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Sem `main`, VPS, segredos, produção, custos ou dados reais.
