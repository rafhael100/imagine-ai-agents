# FT01/SEC-59 — proteção de construtor direto de Step durante callback de IA

**11/11/2026. Somente objetos PHP sintéticos em memória. Nenhum CRM real ou autorização de produção: NO-GO.**

## Lacuna corrigida

A SEC-53 já impedia `Run::transition` e `Run::step` durante o callback de um `AiSuggestionProvider`. Porém, o provedor recebe o objeto `Run` e poderia invocar **`new Step('SYNTHETIC_...', $run)` diretamente**, desviando a proteção de `Run::step`. O construtor `Step` consultava apenas `$run->assertActive()` (leitura permitida). A nova Step não era registrada como canônica no `SupervisedCoordinator`, por isso uma operação real via ferramenta continuava bloqueada, mas a garantia de **não criar etapas** dentro do callback era incompleta.

`Run::assertStepCreationReady()` executa a checagem `assertSuggestionWritable()` (com marca *sticky* de tentativa) e em seguida `assertActive()`. **O próprio construtor `Step::__construct` exige essa guarda antes de validar o ID**. `Run::step` também a chama antes da construção para manter um ponto de entrada consistente.

Se um provedor tentar construir uma Step durante o callback:
- recebe `PROVIDER_CALLBACK_DENIED`, mesmo com ID inválido ou extremamente grande;
- a tentativa fica registrada na memória do Run como bloqueio **sticky** até o fim desse callback;
- ainda que o provedor capture e oculte a exceção, a sugestão externa falha, não emite `SUGGESTED` ou `STEP_CREATED` por essa via;
- ferramentas supervisionadas fecham o Run como `FALHOU`; o coordenador direto, sem ferramenta, retorna a negação sem inventar sucesso;
- outro Run válido pode continuar posteriormente, e a inspeção somente leitura de uma Step previamente existente permanece permitida.

O construtor direto fora de callbacks ainda funciona para fixtures sintéticas; Runs não ativos continuam negados por `RUN_NOT_ACTIVE` e referências inválidas por `INVALID_SYNTHETIC_REF`. **A guarda não é sandbox PHP e não impede a execução de código arbitrário**: protege uma rota específica de criação em um objeto Run do mesmo processo.

## Regressões U90-01..08

1. Construtor direto capturado pelo provedor invalida a sugestão externa;
2. Construtor direto sem captura não emite sucesso nem STEP_CREATED;
3. ID malformado dentro do callback nega antes de validar referência;
4. ID de 1 MB recebe negação antecipada;
5. Chamado diretamente no coordenador (sem ferramenta) também nega;
6. Construtor fora do callback mantém comportamento ativo/terminal;
7. Provedor pode inspecionar Step canônica já existente, sem erro;
8. Outra execução saudável consegue criar Step e sugerir após o primeiro bloqueio.

**Gate proposto: 800 testes PHP / lint 99 arquivos PHP, validadores Python e smoke DOM Node**, com CI no SHA revisado e CI pós-merge obrigatórias. Não significa cobertura funcional completa do produto SaaS.

## Bloqueios externos mantidos

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT e documental, 13 cenários OP-01 reais `NAO_EXECUTADO`, R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Não houve integração autenticada, serviço de IA, ação real de CRM, revisão independente de AppSec, deploy, VPS, alteração de `main`, clientes, segredos ou despesas.
