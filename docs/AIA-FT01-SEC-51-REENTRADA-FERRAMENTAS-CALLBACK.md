# FT01/SEC-51 — bloquear reentrada de ferramentas durante sugestão

**Verificado para revisão:** 10/11/2026. **Somente PHP sintético offline; decisão operacional NO-GO.**

## Risco
Um provedor de sugestão injetado pode manter referência ao mesmo `MockToolDispatcher`. Durante o callback de SUGGEST, poderia tentar outra SUGGEST, uma READ OP-01 ou até MUTATE com Intent, Approval e grant válidos. Se capturasse a negação interna e retornasse texto válido, a sugestão externa ainda poderia parecer aceitável.

## Correção
- Guarda **local à instância PHP** nega `dispatch()` aninhado antes de catálogo, aprovação ou gateway e `readThroughBoundary()` aninhado antes de qualquer borda/nonce.
- A tentativa interna contamina o resultado externo **mesmo quando o provedor captura a exceção**. A sugestão é negada e seu Run passa a `FALHOU`.
- A tentativa não consome um evento de ferramenta separado; a negação externa é auditada com código fixo. `finally` limpa a guarda, permitindo próxima execução independente.
- A proteção se aplica somente à mesma instância; não implementa lock distribuído nem tenta desfazer efeitos previamente confirmados.

## Regressões
- **U73-01:** recursão SUGGEST engolida pelo provedor não permite sucesso externo.
- **U73-02:** MUTATE aprovado e com capability válida não chega ao gateway dentro do callback.
- **U73-03:** READ OP-01 aninhada é negada antes da borda.
- **U73-04:** Run novo funciona normalmente após limpar a guarda.

**Validação exigida antes de integrar:** 633 testes PHP, lint de 83 arquivos PHP e verificadores Python, CI no HEAD exato e novamente após merge na branch de desenvolvimento.

## Limitações permanentes
O simulador não tem IAM, autenticação serviço-serviço, ACL/RLS transacional, persistência, relógio confiável nem integração CRM real. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) OPEN/DRAFT; 13 cenários reais `NAO_EXECUTADO`, R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Sem main, VPS, deploy, clientes, segredos ou despesas.
