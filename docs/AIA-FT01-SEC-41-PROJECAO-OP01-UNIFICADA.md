# FT01/SEC-41 — Invariante compartilhada da projeção OP-01

**Data:** 10/11/2026. **Natureza:** correção e regressão de laboratório, somente PHP sintético e sem rede. **Decisão operacional: NO-GO.**

## Problema

A borda OP-01 validava a projeção **antes** do registro de sucesso, mas também havia checagens semelhantes, não idênticas, na fachada pública e no contador de telemetria. Uma mudança futura em `CrmReader` poderia produzir valores excessivamente longos, dados de outro recurso ou respostas com campos que uma camada trataria de modo diferente. A regex era aplicada a strings sem um limite de bytes explícito.

## Mudança

- `MockReadProjectionInvariant` implementa um contrato único, **sem concessão de permissão**: no máximo dois campos `ref`/`stage`, conjunto exato de chaves, strings com até **90 bytes** no formato `SYNTHETIC_[A-Z0-9_]{1,80}`, `ref` vinculada ao recurso do contexto já confiável e `stage` igual a `SYNTHETIC_NEW`.
- `canonicalize()` rejeita omissão/campo extra antes de percorrer a projeção e devolve os campos somente na ordem solicitada. Isso preserva a resposta existente mesmo quando um leitor fictício retorna `ref, stage` e o cliente pede `stage, ref`.
- `isCanonical()` é usada pela **fachada pública** e pela **borda de telemetria**, impedindo aceitar projeções fora de ordem ou inconsistentes se alguma camada mudar.
- `MockCrmReadBoundary` chama a regra **antes da auditoria de sucesso**; leitores fictícios com dados inválidos falham com `BRIDGE_PROJECTION_DENIED`, são auditados apenas como `DENIED / PROJECTION` e não divulgam conteúdo. A prova local de nonce continua consumida e um replay é negado.

## Regressões U64-01..09

Nove casos cobrem ordenação, estrutura incompleta/extra, valores fictícios de **1 MiB**, texto binário e pessoal, vínculo de empresa/recurso, tipos diferentes de string, auditoria de negação por leitor inconsistente, replay após resposta inválida e leitura mínima legítima em ordem alternativa.

**Gate da revisão:** **555 testes PHP, lint dos 83 arquivos PHP** (a classe compartilhada é nova), verificadores Python, CI no SHA do PR e novamente pós-merge.

## Bloqueios

Isso **não** substitui identidade serviço-serviço, ACL do backend CRM, assinatura, RLS, Redis/nonce distribuído, API Laravel ou revisão independente. A matriz de 13 testes reais ainda registra `NAO_EXECUTADO`, CRM PR #57 segue OPEN/DRAFT, e OP01-R01..R07, R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO. Sem deploy, produção, `main`, VPS, clientes, segredos ou custos.
