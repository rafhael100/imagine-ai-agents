# FT01 — composição fila → piloto OP-01 → sugestão fictícia

**10/11/2026 — desenvolvimento offline, dados inteiramente sintéticos. NO-GO operacional.**

## Resultado funcional

`MockQueuedReadOnlyPilot` conecta três módulos já entregues:
1. `MockReadOnlyRunQueue` escolhe uma solicitação read-only pendente, com claim e lease local, mantendo FIFO, idempotência e ausência de retry automático;
2. `MockCrmReadRequestBuilder` constrói a requisição `crm.lead.read_minimal` **a partir do TenantContext vinculado**, sem aceitar tenant, recurso ou principal do modelo; campos solicitados são validados *antes* da reserva, para não queimar um item da fila com dados inválidos;
3. `MockReadOnlyPilot` executa a leitura e sugestão fictícias sobre a borda OP-01 do dono do recurso, com revalidação, nonce único, auditoria minimizada, saída de texto fixa e nenhum gateway de escrita.

Se o piloto concede `SUGGESTION_SYNTHETIC_ONLY`, a fila é concluída com `CONCLUIDA`. Se nega, o item passa a `FALHOU`. Se ocorre falha antes de regularizar o claim, a composição retorna **negação uniforme** e não reenvia, não cria nova autorização e não supõe conclusão em caso de lease indeterminado. Com fila vazia, devolve `EMPTY` sem ler CRM fictício.

O resultado público não expõe ids da empresa, principal, recurso, run, request, claim, nonce ou diagnóstico técnico; mostra apenas o estado e o texto ilustrativo fixo. `real_authorization=false`, `external_calls=0` e `write_calls=0` permanecem explícitos.

## Testes U79-01..12

- Sucesso completo com quatro eventos do coordenador e zero mutações;
- Fila vazia sem efeitos;
- Campo privado falha antes de claim, auditoria ou nonce;
- Enqueue duplicada não gera segunda leitura;
- Política CRM-owner ausente, trust envelope ausente: negação e falha local;
- Empresa divergente não toca fila nem leitor;
- Nonce consumido não permite segunda execução;
- Lease expirado permanece `INDETERMINADO`, sem requeue;
- Cancelamento antes de claim;
- Duas tarefas com provas fictícias independentes;
- Resposta pública sem identifiers, claim, segredos ou dados reais.

**Gate solicitado:** 699 testes PHP, sintaxe de 87 arquivos PHP e verificadores Python offline com CI no HEAD e pós-merge.

## Limites inalterados

Não são workers reais, tasks persistentes, API, mensagens, fila distribuída, IAM, tenant autenticado, LLM, CRM operacional ou execução sobre clientes. A queue e o piloto são objetos num único processo PHP. A abordagem ainda **não** implementa reserva atômica multiworker, persistência, reconciliação distribuída, rate limiting/quotas operacionais, billing nem assinatura de eventos.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; 13 homologações OP-01 reais `NAO_EXECUTADO`; R01-G01..G11/CORE/SEG/AUT NO-GO. Sem merge `main`, VPS, deploy, credenciais, clientes, dados reais ou despesas.
