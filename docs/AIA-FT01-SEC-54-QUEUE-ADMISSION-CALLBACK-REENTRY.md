# FT01/SEC-54 — negar reentrada na admissão de fila antes de consumir jobs

**Data: 11/11/2026.** Escopo: laboratório PHP offline/sintético. **Operação, integração real e produção: NO-GO**.

## Falha de segurança corrigida

Após o piloto em fila e o lote OP-01, um provedor de sugestão substituível poderia chamar `MockQueuedReadOnlyPilot::runNext()` ou `MockQueuedBatchRunner::drain()` **durante o próprio callback**. O coordenador já negava um segundo `start/suggest`, mas esta checagem ocorria somente **depois** de reservar outro item da fila. Um callback que capturasse e ocultasse a negação poderia queimar itens e gerar estados falsamente consumidos. Uma segunda instância de fila/piloto com o mesmo coordenador também poderia tentar o desvio.

A nova checagem `MockReadOnlyPilot::assertQueueAdmissionReady()` encaminha `SupervisedCoordinator::assertProviderCallbackIdle()` **antes de** conferir contexto, inspecionar campos, reservar o primeiro job ou invocar o leitor OP-01. O piloto em fila e o executor em lote realizam essa checagem. Como a guarda do coordenador é *sticky* durante o callback, mesmo que o provedor engula a negação, sua sugestão externa não é aceita; o Run já iniciado encerra `FALHOU`, e o job externo é marcado como falha, sem reenvio automático.

A alteração preserva comportamento normal fora do callback. A fila continua limitada a uma única empresa de teste em processo; nenhuma autenticação ou controle distribuído é introduzido.

## Regressões U82-01..10

1. Reentrada no mesmo piloto capturada pelo provedor não consome o próximo job.
2. Batch chamado recursivamente retorna negação com `processed=0` antes do claim.
3. Segunda instância de fila/piloto com o mesmo coordenador não burla a guarda.
4. Preflight explicitamente chamado de dentro do callback deixa a falha *sticky*.
5. Campos malformados ou enormes em tentativa aninhada não contornam a guarda.
6. Múltiplas reentradas engolidas não acumulam operações adicionais.
7. Exceção após reentrada deixa fila sem retry.
8. Um novo Run independente com prova nova funciona depois da falha.
9. Lote normal fora do callback continua funcional.
10. Tentativa aninhada com tenant estrangeiro também invalida o callback exterior.

**Gate proposto:** 721 testes PHP, lint 90 arquivos PHP e validadores Python; CI do PR e pós-merge sobre SHAs verificados. Não é auditoria de segurança independente.

## Limites e bloqueios reais

A proteção vale **somente dentro do mesmo objeto coordenador e processo PHP**. Não é mutex entre processos, lease distribuído, tratamento de concorrência real ou identidade de serviço. O risco de consumo em outra instância com outro coordenador deve ser resolvido na arquitetura operacional, com atomicidade e autenticação.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) ainda é OPEN/DRAFT; 13 cenários de homologação real `NAO_EXECUTADO`; gates R01-G01..G11, CORE/SEG/AUT **NO-GO**. Sem modificação de `main`, VPS, endpoints Laravel, dados reais, segredos, clientes, deploy ou despesas.
