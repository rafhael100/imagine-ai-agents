# FT01/SEC-09 — Integridade da identidade de Run e Step em memória

**Escopo:** coordenador PHP de laboratório, com referências e dados exclusivamente fictícios. Sem CRM real, identidade autenticada, bancos, rede, credenciais ou produção.

## Lacuna descoberta

Antes da correção, `SupervisedCoordinator::start` criava dois objetos `Run` distintos para o mesmo identificador. Entretanto, a trilha e a projeção `eventsForRun` utilizavam apenas o hash desse ID, permitindo confusão de eventos de execuções diferentes. O método também aceitava um `Run` externo com o mesmo ID registrado e, no caso de `Step`, podia criar ou receber instâncias distintas com o mesmo ID dentro da mesma execução. Essas colisões poderiam causar análise inconsistente mesmo com a assinatura lógica de `Intent` igual.

## Correção de consistência

- `start` mantém um registro somente em memória dos objetos `Run` criados. Reutilizar um ID conhecido resulta em `RUN_ID_CONFLICT`, sem novo evento.
- Em coordenadores que tenham execuções registradas, `step`, `suggest`, `transition`, `execute`, `afterTimeout` e `reconcile` exigem o objeto `Run` original. Referências externas incompatíveis geram `RUN_NOT_OWNED` antes de efeitos sintéticos.
- `step` impede recriar uma etapa registrada com o mesmo ID (`STEP_ID_CONFLICT`). Ao vincular uma intenção de uma execução registrada, exige a mesma instância `Step` criada pelo coordenador (`STEP_NOT_OWNED`).
- `eventsForRun` preserva o contrato de retornar lista vazia para uma execução desconhecida. Se o ID já pertence a outra instância, devolve `RUN_ID_CONFLICT` em vez de compartilhar a projeção. O histórico global permanece inalterado.
- O registro de identidade é feito **depois** de registrar os eventos `STARTED` e `STEP_CREATED`, evitando inserir estados que não tenham trilha correspondente.

## Compatibilidade explicitamente limitada

As fixtures legadas que constroem `Run` e `Step` fora do coordenador continuam aceitas quando **nenhuma execução foi iniciada** nele, para preservar os testes existentes. A projeção dedicada por execução permanece exclusiva para execuções iniciadas por `start`. Essa compatibilidade é uma concessão ao laboratório; não constitui autenticação, defesa contra criação de objetos arbitrários ou isolamento entre processos. Um protocolo de produção deverá eliminar a via legada com controle de migração, autenticação real e armazenamento transacional.

## Testes

Os 16 casos `U22-01` a `U22-16` verificam IDs duplicados de execução e etapa, instância diferente com ID igual em leitura, sugestão, transição, envio e status, tentativa de aprovar uma etapa recriada, fluxo válido, execução desconhecida, compatibilidade legada e isolamento por contexto. A suíte oficial prevista sobe para **304 casos PHP e 78 arquivos de sintaxe**; validar o resultado da CI do PR e do merge antes de afirmar integração concluída.

**Gates:** [Imagine CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT permanecem ABERTOS. Estado operacional **NO-GO**. Não alterar `main`, VPS, clientes, APIs, produção ou despesas.
