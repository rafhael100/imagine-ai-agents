# FT01/F1-28 — Pré-verificação sem consumo antes da leitura mínima

**Data:** 09/11/2026. **Escopo:** laboratório PHP totalmente sintético. **Operação real: NO-GO**.

## Risco corrigido
O fluxo F1-26 validava o formato do ID da execução antes de consultar a borda de leitura, mas só descobria **colisão de ID de Run** ou **capacidade insuficiente de auditoria** depois de a borda ter consumido um nonce, registrado a tentativa e lido campos fictícios. Uma divergência entre o `TenantContext` do coordenador e o da borda também podia ser identificada tarde. Isso não criava operação de escrita, mas podia desperdiçar uma autorização/requisição e prejudicar reconciliação e disponibilidade em uma futura implementação.

## Comportamento novo
- `SupervisedCoordinator::assertPilotRunReady` realiza checagem **sem efeitos colaterais** do contexto de tenant/principal/recurso/finalidade, formato do ID, duplicação de Run já iniciada e **reserva lógica de quatro eventos** exigidos pela sequência `STARTED → STEP_CREATED → SUGGESTED → RUN_CHANGED`.
- `MockReadOnlyPilot::suggest` chama essa verificação **antes de invocar a borda de leitura**. Uma falha retorna `DENIED_OR_NOT_FOUND` uniforme, sem acesso a dados, auditoria, consumo de nonce, criação de run ou chamadas ao gateway de efeitos.
- A checagem não cria um lock nem agenda trabalho assíncrono. Vale **somente para a instância PHP atual**. Em integração real, concorrência, idempotência, entrega única e transações distribuídas precisam de solução própria.

## Testes U31-01..04
- Uma segunda tentativa com o mesmo ID de Run e **nonce novo** é negada antes do acesso; o nonce novo continua usável com ID válido.
- Coordenador configurado para contexto diferente do contexto ligado à borda é recusado antes da leitura.
- Trilha já com 1.021 dos 1.024 eventos nega uma Run que exigiria quatro eventos, sem consumir nonce ou slot de auditoria.
- Trilha com 1.020 eventos permite **exatamente** uma Run de quatro eventos, sem escrita; outro pedido é negado antes de consultar a borda.

**Gate esperado:** 355 testes PHP e lint dos 82 arquivos PHP, além dos testes de contrato Python. A execução da CI no SHA revisado é obrigatória antes de qualquer merge.

## O que não muda
A identidade não é autenticada; o CRM não está conectado; os recursos são artificiais; não existem API, SQL, LLM externo, persistência, transação ou token. O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece `OPEN/DRAFT` e os gates R01-G01..G11 / CORE-SEG-AUT ainda precisam de aceites independentes. A `main`, a VPS, o CRM real, os dados de clientes e a produção não são alterados.
