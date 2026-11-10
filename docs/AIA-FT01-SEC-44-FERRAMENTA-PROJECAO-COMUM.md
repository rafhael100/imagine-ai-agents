# FT01/SEC-44 — Validação comum da projeção também na ferramenta OP-01

**Data:** 10/11/2026. **Escopo:** somente PHP sintético, sem tráfego, credenciais ou CRM real. **Decisão operacional: NO-GO.**

## Problema

Mesmo após SEC-41 unificar a verificação das projeções de leitura no boundary, fachada pública e telemetria, `MockToolDispatcher::readThroughBoundary()` mantinha sua **própria expressão regular** e validava campos independentes da regra `MockReadProjectionInvariant`. Essa duplicação permitiria divergências futuras no vínculo da referência ao recurso, no tamanho ou no conteúdo de `stage`. A SEC-43 passou a revalidar autorização após o leitor, mas faltava um ensaio completo de **ferramenta → edge → fachada → leitor → recheck** com revogações no meio do caminho.

## Implementação

- A ferramenta usa `MockReadProjectionInvariant::isCanonical($projection, $request->fields, $context)` para a mesma validação das outras três camadas, sem percorrer nem executar regex própria sobre valores potencialmente incoerentes.
- Permanece estrito o contrato de resposta: formato exato, `state=READ_SYNTHETIC_ONLY`, `real_authorization=false`, `external_calls=0`, apenas `ref` e `stage`, na ordem solicitada, com vínculo ao recurso do contexto. Nenhum caminho direto para o leitor foi introduzido.
- O novo conjunto de regressões cria um leitor reentrante fictício e atravessa toda a borda via `MockToolDispatcher`, exercitando o recheck **não consumidor de segundo nonce** implementado na SEC-43.

## Regressões U66-01..10

1. Leitura mínima correta com uma só autorização fictícia, auditoria e evento da ferramenta.
2. Ordem alternativa `stage/ref` preservada após a revalidação.
3. Grant do recurso revogado durante a leitura: negação uniforme, sem projeção.
4. Tenant suspenso durante a leitura: negação na ferramenta.
5. Membro suspenso: negação na ferramenta.
6. Revogação de digest na memória compartilhada: negação, nonce consumido só uma vez.
7. Prova owner revogada durante a leitura: negação.
8. Indisponibilidade da memória de revogações durante a leitura: negação.
9. Replay após sucesso: sem segundo acesso ao leitor, com auditoria e telemetria coerentes.
10. Corpo de requisição inválido: negado antes de edge/auditoria/nonce, sem impedir tentativa válida posterior.

**Gate desta revisão: 575 testes PHP, lint de 83 arquivos PHP e verificadores Python**, CI no SHA exato antes de integrar e novamente pós-merge na `dev`.

## Limites operacionais

São apenas ensaios reentrantes dentro de um processo PHP, não concorrência verdadeira, autorização transacional no CRM, identidade serviço-serviço, IAM, Redis/nonce distribuído ou revisão de segurança independente. Os 13 casos reais do contrato OP-01 seguem `NAO_EXECUTADO`; o CRM PR #57 continua OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT continuam ABERTOS/NO-GO. Sem `main`, VPS, produção, dados reais, segredos ou despesas.
