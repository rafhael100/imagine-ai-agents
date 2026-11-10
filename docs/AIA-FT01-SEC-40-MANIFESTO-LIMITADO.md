# FT01/SEC-40 — Validação limitada do manifesto e referências comuns

**Data:** 10/11/2026. **Escopo:** PHP sintético local, sem rede. **Decisão operacional: NO-GO.**

## Problema

`MockAgentVersionSnapshot` ordenava todos os nomes de campos de um manifesto **antes** de conferir a estrutura exata de cinco chaves. Um manifesto malformado com milhares de campos provocava alocação/ordenação desnecessária. Além disso, o validador comum `syntheticRef()` usava expressão regular diretamente em strings de tamanho arbitrário; a função atende não apenas o catálogo de modelos, mas também referências usadas por revisão, confiança, conhecimento e ferramentas fictícias.

## Correção

- O construtor exige **exatamente 5 chaves de topo** antes de criar/ordenar a lista de nomes. Os campos continuam exatamente `instruction_ref`, `policy_ref`, `risk`, `schema_version` e `tools`.
- O validador compartilhado recusa qualquer referência com mais de **110 bytes**, antes de aplicar `^SYNTHETIC_[A-Z0-9_]{1,100}$`. Mantém a fronteira anterior: 100 caracteres após o prefixo são aceitos, 101 são negados.
- Manifestos válidos em ordem diferente continuam equivalentes; limites de ferramentas, integridade de hashes e checks de elegibilidade continuam inalterados. Não foi criada nova autoridade de publicação.

## Regressões U62-01..07

Manifeste com 10 mil campos extras; strings de 1 MiB em IDs de snapshot/modelo/versão e em referências de instrução/política; fronteira 110/111 bytes; invariância de ordenação; lista de ferramentas enorme negada; e promoção sintética válida após uma tentativa de manifesto inválido.

**Gate requerido: 533 testes PHP, lint dos 82 arquivos PHP e validadores Python**, com CI no SHA do PR e pós-merge na `dev`.

## Restrições

Não há registro publicável, runtime de agentes, IA externa, IAM, conexão com CRM, tráfego de rede ou dados de cliente. O CRM PR #57 permanece OPEN/DRAFT e os gates R01-G01..G11 / CRM CORE/SEG/AUT continuam ABERTOS/NO-GO. Nenhuma mudança em `main`, VPS, produção, segredos ou despesas.
