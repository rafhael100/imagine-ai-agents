# FT01/SEC-57 — vincular fila local ao coordenador e bloquear mutações diretas no callback

**11/11/2026. Apenas laboratório PHP sintético, offline. Decisão de produção: NO-GO.**

## Vulnerabilidade corrigida

A SEC-54 bloqueia `MockQueuedReadOnlyPilot::runNext` e `MockQueuedBatchRunner::drain` quando um provedor de sugestões tenta realizar outro trabalho durante seu callback, mas não protegia métodos públicos da própria fila. Um callback injetado com referência ao objeto `MockReadOnlyRunQueue` poderia chamar diretamente `claim`, `enqueue`, `finish` ou `cancel`, alterando jobs sem passar pelo piloto e sem invalidar a sugestão original.

Agora, ao construir `MockQueuedReadOnlyPilot`, o piloto vincula **uma vez** a fila ao `SupervisedCoordinator` que o serve. `MockReadOnlyRunQueue::bindAdmissionCoordinator()` recusa troca do coordenador por `QUEUE_COORDINATOR_CONFLICT`, aceita repetição do mesmo vínculo e verifica o estado do callback antes de vincular. Cada mutação de fila (`enqueue`, `claim`, `finish`, `cancel`) chama `assertAdmissionIdle` **antes** de conferir contexto, tamanho, tempo ou modificar jobs. A negação marca a guarda sticky do coordenador; ainda que o provedor engula o erro, a sugestão externa falha e o Run pai é encerrado como `FALHOU`.

As leituras locais `status`, `counts`, `events`, `needsReview` e `hasActiveReservation` não são mutações e conservam seus contratos de teste. Filas independentes usadas isoladamente em testes podem ficar desvinculadas; a garantia aplica-se às filas associadas a um piloto supervisionado. Nunca confundir com isolamento de um provedor que pode executar código arbitrário em PHP.

## Regressões U85-01..12

1. Claim direto dentro do callback não reserva o segundo job.
2. Enqueue direto não injeta um job.
3. Cancel direto não altera outro pendente.
4. Finish direto não conclui outra reserva.
5. Tentativa com tenant falso continua bloqueada antes do contexto.
6. Após callback rejeitado, uma execução nova e legítima funciona.
7. Vinculação repetida ao mesmo coordenador não altera identidade.
8. Um coordenador diferente não substitui o primeiro.
9. Tentativa capturada de vinculação durante callback invalida a sugestão.
10. Chamadas malformadas, inválidas e extensas não escapam pela validação.
11. Segunda fila com mesmo coordenador também nega mutação direta.
12. A fila desacoplada permanece operável como fixture sintética isolada.

**Gate planejado:** 753 testes PHP, lint 93 arquivos PHP e validadores Python. CI no SHA exato do PR e pós-merge exigidas antes de registrar evidência final.

## Limites e bloqueios

A proteção é **por instância, em memória, num processo PHP**: não há lock distribuído, sandbox, persistência transacional, autenticação real, API, IAM, ACL/RLS ou autorização CRM. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; 13 cenários reais `NAO_EXECUTADO`; R01-G01..G11 / CORE/SEG/AUT **NO-GO**. Sem `main`, VPS, produção, credenciais, clientes, dados pessoais nem despesas.
