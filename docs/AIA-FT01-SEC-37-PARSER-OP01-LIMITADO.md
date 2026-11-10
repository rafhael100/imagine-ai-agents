# FT01/SEC-37 — Limites antes do parsing do contrato OP-01

**Data:** 10/11/2026. **Escopo:** somente `MockCrmReadRequest` e regressões PHP offline. **Decisão operacional: NO-GO.**

## Problema

O parser sintético do pedido `crm.lead.read_minimal` montava e ordenava todas as chaves de uma matriz recebida **antes de descobrir** que a estrutura tinha campos extras. Uma matriz muito grande causava trabalho e alocação proporcionais ao número de chaves, apesar de a resposta ser invariavelmente negada. Além disso, o teste por expressão regular das referências ocorria sem verificar primeiro o limite de bytes.

## Correção

- Verifica que a matriz tem **exatamente 8 chaves** antes de chamar `array_keys()` e `sort()`. Mantém a verificação exata dos nomes, a independência da ordem e a negação uniforme `BRIDGE_SCHEMA_DENIED`.
- Rejeita referências brutas com mais de **90 bytes** antes de executar `preg_match()`; mantém o padrão estrito `SYNTHETIC_[A-Z0-9_]{1,80}`.
- Conserva o contrato de projeção fechada (somente `ref` e `stage`), sem ampliar autorização.
- Na borda OP-01, parsing negado mantém a resposta pública `DENIED_OR_NOT_FOUND`, registra somente telemetria agregada `SCHEMA_DENIED` e **não consome nonce nem auditoria da leitura**. Uma solicitação posterior válida ainda funciona.

## Regressões U59-01..07

1. Matriz com 5.000 chaves extras recusada.
2. Referências fictícias de 1 MiB rejeitadas em cinco posições.
3. Fronteira de 90/91 bytes e prefixo incompleto.
4. Projeção grande, contratos gigantes e corpo com chaves reordenadas.
5. Entrada com 4.096 chaves extras recusada antes de nonce/auditoria, com leitura posterior válida.
6. Referência de recurso de 1 MiB não vazada na resposta pública ou telemetria.
7. Projeção com 1.024 campos recusada, seguida de leitura legítima ordenada.

**Gate esperado: 512 testes PHP; lint de 82 arquivos PHP e verificações Python**, CI no SHA exato e CI pós-merge.

## Limites e não-autorização

Todos os contextos, recursos, autorizações e dados são fictícios em memória. O parser não é um serviço HTTP, não implementa controle de tráfego de rede, IAM, privacidade operacional ou autenticação. CRM PR #57 permanece OPEN/DRAFT; R01-G01..G11 e CRM CORE/SEG/AUT ABERTOS/NO-GO. Nenhuma mudança em `main`, VPS, produção, dados de clientes, credenciais ou despesas.
