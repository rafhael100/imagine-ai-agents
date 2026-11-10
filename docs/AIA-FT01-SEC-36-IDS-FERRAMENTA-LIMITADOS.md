# FT01/SEC-36 — Limite de identificadores de ferramenta antes da auditoria

**Data:** 10/11/2026. **Escopo:** somente `MockToolDispatcher`, testes PHP offline e referências fictícias. **Decisão operacional: NO-GO.**

## Problema

Uma ferramenta inexistente já era negada pelo catálogo, mas a rota de erro ainda calculava `sha256` do identificador de tamanho arbitrário fornecido pelo chamador. O trabalho para registrar a negação podia crescer com uma string excessivamente longa, ainda que a ferramenta não tivesse permissão.

## Correção

- As duas interfaces sintéticas, `dispatch()` e `readThroughBoundary()`, verificam `strlen($toolId) <= 128` antes de consultar o catálogo, recursos ou adaptadores. Identificadores maiores são negados: `TOOL_DENIED` no dispatcher comum e `DENIED_OR_NOT_FOUND` na borda de leitura.
- O evento de auditoria continua sendo escrito quando houver capacidade, mas a referência de IDs grandes usa **um único hash de um marcador fixo**, nunca o conteúdo longo fornecido. Isso mantém o formato hash-only e evita cálculo proporcional à entrada rejeitada.
- Ferramentas conhecidas e identificadores inválidos de até 128 bytes preservam as verificações e a impressão digital habitual.
- Uma tentativa negada não consome nonce OP-01, auditoria de leitura ou chamada ao gateway. Uma solicitação posterior válida continua possível.

## Regressões U58-01..05

Negação de identificador de 1 MiB antes de qualquer provider; mesmo marcador para duas entradas grandes distintas; leitura negada sem consumo de nonce/audit; fronteira 128/129 bytes; e sugestão válida depois de entrada longa inválida.

**Gate esperado: 505 testes PHP, lint de 82 arquivos PHP e verificadores Python**, com CI no SHA exato e pós-merge.

## Restrições

A implementação não é um gateway de rede, firewall, IAM ou rate limit distribuído. O CRM PR #57 segue OPEN/DRAFT; R01-G01..G11 / CORE-SEG-AUT permanecem ABERTOS/NO-GO. Não altera `main`, VPS, produção, clientes, segredos nem gera custos.
