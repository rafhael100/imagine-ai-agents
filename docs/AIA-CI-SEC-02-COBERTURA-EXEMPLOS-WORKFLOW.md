# CI-SEC-02 — Garantir validação de mudanças em exemplos

**Data:** 09/11/2026. **Escopo:** workflow de desenvolvimento somente leitura, no repositório público. **NO-GO operacional**.

## Lacuna corrigida
A CI `.github/workflows/r01-dev-integrity.yml` cobria `src/**`, `tests/**`, `tools/**`, contratos e um padrão específico de HTML na pasta `examples/`. A demonstração F1-27 adicionou `examples/f127-read-only-pilot-demo.php` e a CI de revisão passou porque **outros arquivos cobertos também mudaram**. Uma alteração futura exclusivamente nesse exemplo PHP não iniciaria automaticamente o workflow.

## Mudança
Os gatilhos de `push` e `pull_request` agora incluem `examples/**`. Um teste negativo em `tests/Contracts/test_ci_guardrails.py` exige o novo padrão **nas duas seções** e rejeita o filtro estreito anterior. Permissões `contents: read`, checkout por SHA fixado, `persist-credentials: false`, limite de 6 minutos, verificação de ambiente público e gate offline unificado foram preservados.

**Gate mantido:** 351 testes PHP, 82 arquivos PHP com lint e regressões Python de contrato/demo. O CI verde apenas demonstra o comportamento do checkout testado; não concede acesso real nem licença para integrar produção.

## Limites
O workflow é deliberadamente condicionado a `github.event.repository.private == false`; se o repositório tornar-se privado, a execução será ignorada. **Nenhuma ausência de CI pode ser tratada como aprovação**. Uma eventual migração para privado precisa definir de antemão os custos e a alternativa para testes e branch protection, sem contratação automática. `main`, CRM real, VPS, credenciais, clientes e produção inalterados. R01/CORE/SEG/AUT permanecem **ABERTOS / NO-GO**.
