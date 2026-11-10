# FT01/SEC-22 — Identificadores limitados no coordenador sintético

**Data:** 10/11/2026. **Escopo:** laboratório offline PHP em memória, sem CRM real, SQL ou rede. **Decisão operacional: NO-GO.**

## Risco corrigido

A estrutura `SupervisedCoordinator` armazenava `Run` e `Step` em mapas PHP e gravava hashes de identificadores na trilha. O domínio legado apenas verificava o prefixo `SYNTHETIC_`, sem limite de tamanho. Uma referência artificialmente extensa podia consumir memória e CPU desproporcionalmente antes de qualquer efeito, mesmo em um laboratório.

## Limites introduzidos antes da alocação

- **Run:** máximo de **80 bytes** por identificador, incluindo `SYNTHETIC_`.
- **Step:** máximo de **96 bytes** por identificador, incluindo `SYNTHETIC_`.
- Em ambos os casos, exige pelo menos um byte após o prefixo; referências vazias ou longas são recusadas com `INVALID_SYNTHETIC_REF` **antes** de criar objeto, gravar `STARTED`/`STEP_CREATED` ou alimentar os mapas.
- Mantém as convenções de fixtures históricas com sufixos em letras minúsculas; o contrato externo OP-01 já tem validação sintética mais restrita.
- Os limites de eventos (1.024) e as exigências SEC-21 de Run/Step canônicos na interface de ferramentas continuam ativos.

## Regressões U43-01..05

Rejeição de Run excessivamente longo, recusa de prefixo sem sufixo, borda de 80 bytes (válido) versus 81 (negado), Step com 97 bytes (negado antes da auditoria) e Step de 96 bytes (válido, ID duplicado negado). Os casos verificam que tentativas rejeitadas não consomem histórico nem bloqueiam novas execuções legítimas.

**Gate esperado:** 414 testes PHP, lint de 82 arquivos PHP e verificadores Python de contrato e prontidão. Merge somente na branch `dev` após CI verde no SHA exato.

## Restrições

Limites de identificadores em memória **não equivalem** a limites de recursos distribuídos, autenticação, rate limiting, ACL, assinatura criptográfica ou antirreplay de produção. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**, e R01-G01..G11 / CORE-SEG-AUT permanecem **ABERTOS/NO-GO**. Não mexer na `main`, VPS, clientes, credenciais, fornecedores pagos ou produção.
