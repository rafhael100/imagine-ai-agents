# FT01 — processamento supervisionado de lotes OP-01, somente leitura

**Data:** 10/11/2026. **Status operacional: NO-GO.** Laboratório PHP offline, sem cliente real, IAM, banco, filas distribuídas, HTTP ou modelo pago.

## Entrega de desenvolvimento

`MockQueuedBatchRunner` executa **até oito** chamadas sequenciais e explicitamente solicitadas ao já existente `MockQueuedReadOnlyPilot`. Cada entrada carrega um par de evidências `MockTrustEnvelope` e `MockOwnerScopeEvidence` sintéticas; não cria nem autentica essas provas. O construtor do pedido usa o mesmo contexto `TenantContext` vinculado, sem substituir claims nem inferir autorização do resultado de uma sugestão.

Antes de reservar qualquer job, o lote verifica vínculo de contexto, formato estrito de cada par (incluindo o oitavo), campos OP-01 e limites de clock/lease. Um par ou campo inválido faz **o lote inteiro ser negado sem consumir o primeiro job**. Depois, cada chamada é processada uma vez; a execução para ao encontrar fila vazia e nunca tenta reexecutar automaticamente item negado ou indeterminado.

A saída revela somente contagens agregadas `processed/succeeded/denied`, os estados `BATCH_SYNTHETIC_ONLY`, `EMPTY` ou `DENIED_OR_NOT_FOUND` e os invariantes `real_authorization=false`, `external_calls=0`, `write_calls=0`. Não contém `run_ref`, `request_ref`, tenant, principal, recurso, claims ou texto de cliente. Lotes não são transações atômicas: uma falha parcial continua parcial, e a fila local registra o resultado de cada item.

## Suíte U80-01..12

- Fila vazia sem leitura ou efeito.
- Dois jobs com duas provas independentes, ambos concluídos.
- Prova ausente no primeiro e válida no segundo não contamina resultado.
- Item cancelado é ignorado sem consumir prova.
- Replay da primeira prova nega o segundo job, sem retry.
- Campo privado falha antes do claim.
- Contexto de outra empresa falha sem tocar a fila.
- Nove entradas rejeitadas antes de qualquer ação.
- Prova malformada na última entrada impede consumo da primeira.
- Oito entradas com provas únicas concluem sem efeitos.
- Lease inválido e relógio negativo negados sem consumir job.
- Resposta agregada não revela IDs, claims ou segredos fictícios.

**Gate exigido:** 711 testes PHP, lint 88 arquivos e verificadores Python offline, CI do PR e pós-merge no SHA exato. Dados/regras/retorno são sintéticos.

## Barreiras e dependências reais

A fila e este executor rodam somente em um processo PHP e não são workers, serviços ou agendadores operacionais. Inexistem entregas persistentes/atômicas, retries distribuídos, ID de serviço, auditoria de produção, IAM, ACL/RLS, rotas, Laravel, Prisma/SQL, observabilidade de cliente ou autorização comercial. Cada prova é um objeto fictício já construído no teste.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; decisões OP01-R01..R07, gates R01-G01..G11 e CORE/SEG/AUT NO-GO; 13 homologações reais `NAO_EXECUTADO`. Nenhuma alteração de `main`, VPS, deploy, clientes, segredos, dados pessoais ou despesas.
