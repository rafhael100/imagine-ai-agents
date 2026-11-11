# FT01/SEC-60 — bloqueio de mutações cross-Run durante sugestão

**11/11/2026. PHP sintético, mesmo processo, sem CRM real. Operação/piloto/produção: NO-GO.**

## Lacuna corrigida

As SEC-53 e SEC-59 impediam que um `AiSuggestionProvider` modificasse diretamente o `Run` recebido pelo callback e criasse `Step` por construtor. A SEC-52 impedia reentradas pelo `SupervisedCoordinator`. Porém, um provedor pode manter uma **referência a outro `Run` canônico já iniciado pelo mesmo coordenador** e chamar `$otherRun->transition('CONCLUIDA')`, `$otherRun->step(...)` ou `new Step(..., $otherRun)` **diretamente**, contornando a auditoria, sem passar pelo coordenador e sem disparar o bloqueio local do Run ativo.

A nova proteção é de **escopo do coordenador**: antes de chamar o provedor, `SupervisedCoordinator::suggest()` cria um token por objeto, não entregue ao provedor, e invoca `beginCoordinatorSuggestionScope($token)` em **todos os Runs canônicos já registrados**. Os construtores de Step e as transições passam por `Run::assertSuggestionWritable()` e negam tanto a guarda do próprio Run quanto a do coordenador.

- Um acesso direto de escrita a qualquer Run protegido recebe `PROVIDER_CALLBACK_DENIED`, **sem modificar seu estado**.
- A tentativa fica marcada em `coordinatorMutationAttempt`; se o provedor captura a exceção e retorna uma sugestão aparentemente válida, a checagem pós-callback ainda nega o resultado externo.
- Tentar entrar no mesmo escopo outra vez ou encerrá-lo com token falso também nega e marca a tentativa como suspeita.
- Em `finally`, o coordenador encerra os escopos de todos os Runs registrados, **inclusive sob exceção do provedor**. Uma nova sugestão saudável e a criação legítima de Step após a falha continuam funcionando.
- Consultar `state()`, `assertActive()` ou ler uma Step previamente criada continua permitido. Não são criados eventos `SUGGESTED` ou `STEP_CREATED` pelas tentativas proibidas.

**Importante:** este guard protege Runs *canônicos* pertencentes ao **mesmo coordenador**, no **mesmo processo**. Não é proteção contra código PHP arbitrário, Reflection, objetos não registrados, efeitos externos ou outros coordenadores/servidores. O token só dá consistência ao mock; não é identidade, ACL, sandbox ou transação distribuída.

## Regressões adversariais U91-01..10

1. Mutação direta capturada de outro Run invalida sugestão;
2. Criação via `Run::step()` em outro Run também nega;
3. Construção `new Step(..., $otherRun)` nega;
4. Mutação não capturada não produz sugestão bem-sucedida;
5. Tentativa de encerramento com token falso nega e invalida callback;
6. Tentativa de substituir token da guarda também nega;
7. Inspeção somente leitura de outro Run e de Step existente permanece válida;
8. O escopo é limpo e permite uso legítimo posterior de outro Run;
9. Dois outros Runs, ambos protegidos simultaneamente;
10. Sugestão chamada diretamente pelo coordenador (sem ferramenta) também nega.

**Gate solicitado:** 810 testes PHP, lint 100 arquivos PHP, contratos Python e smoke de DOM Node, verificados em SHA exato de PR e pós-merge.

## Pendências de autorização real

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT, 13 cenários reais OP-01 `NAO_EXECUTADO`, gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. Sem deploy, IAM, SQL real, conexão CRM, rede externa, clientes, segredos, VPS, custos ou alteração da `main`.
