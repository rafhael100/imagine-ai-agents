# FT01/SEC-52 — bloquear callback recursivo no coordenador compartilhado

**Data:** 10/11/2026. **Laboratório sintético, offline e sem CRM real. Decisão operacional NO-GO.**

## Lacuna e cenário adversarial

A SEC-51 impede reentrada apenas no **mesmo** `MockToolDispatcher`. Um provedor de sugestão injetado pode obter uma segunda instância de `MockToolDispatcher` compartilhando o mesmo `SupervisedCoordinator`, ou chamar o coordenador diretamente. Isso permitiria tentar `MUTATE` aprovada, `SUGGEST` recursiva ou transições de Run durante a geração do texto sintético, burlando a guarda por instância.

## Mudança

- `SupervisedCoordinator` mantém `providerCallbackActive` e `providerCallbackReentryObserved` por instância, com limpeza em `finally`.
- Durante o callback, preflights e métodos de alteração de estado negam **antes** de traces, gateway ou mudança de Run. Se a tentativa for capturada pelo provedor, a flag persistente invalida o resultado externo e impede registrar `SUGGESTED`.
- `MockToolDispatcher` consulta o coordenador antes de `dispatch` e `readThroughBoundary`. Uma segunda instância não pode passar pela borda de leitura nem pela ferramenta de mutação enquanto o mesmo coordenador gera a sugestão.
- O despachante externo conserva o comportamento de **encerrar Run como `FALHOU`** em falha do provedor. A proteção vale apenas em um processo PHP e em um mesmo objeto coordenador.

## Regressões U74-01..07

1. `suggest` diretamente no coordenador durante callback é negado, mesmo quando a falha é engolida.
2. `SUGGEST` de outro despachante é negada, sem evento aninhado.
3. `MUTATE` com Intent/Approval/grant válidos em segundo despachante não invoca gateway.
4. `READ` de segundo despachante é negada sem entrar na borda.
5. `execute` direto com Approval válida não registra submissão.
6. Falha no callback não contamina novo Run, que pode sugerir normalmente.
7. `transition` direto durante callback não conclui nem altera Run indevidamente.

**Gate planejado antes de merge:** 640 testes PHP, lint 83, testes Python offline, validação GitHub CI no HEAD exato e pós-merge. **Não** alegar implementação produtiva ou substituição de auditoria independente.

## Bloqueios

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**, 13 cenários reais de homologação continuam `NAO_EXECUTADO`, gates R01-G01..G11 e CRM CORE/SEG/AUT permanecem NO-GO. Sem serviços externos, IAM, ACL/RLS, lock distribuído, clientes, segredos, acesso à VPS, deploy, merge em `main` ou despesa.
