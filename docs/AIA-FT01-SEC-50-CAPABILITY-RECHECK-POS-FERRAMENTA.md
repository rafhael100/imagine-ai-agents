# FT01/SEC-50 — Revalidar capability grant após ferramenta de leitura/sugestão

**Data:** 10/11/2026. **Escopo:** PHP fictício offline, sem integração CRM. **Estado operacional: NO-GO.**

## Lacuna

A interface `MockToolDispatcher` exigia uma concessão de capacidade (`MockCapabilityGrant`) **antes** da ferramenta de leitura ou do provedor de sugestão, mas ela podia ser revogada enquanto um adaptador local preparava sua resposta. Mesmo com as verificações OP-01 de trust e proprietário dentro da leitura, **a permissão independente da ferramenta** podia estar revogada na hora da entrega. Isso não é autorização real de serviço, mas é uma inconsistência testável no simulador.

## Correção

- `MockToolDispatcher::readThroughBoundary` revalida a **mesma** concessão de ferramenta após a resposta de borda OP-01 ser verificada e **antes** de publicar `SYNTHETIC_OK` e o evento `SYNTHETIC_READ`. Revogação intermediária devolve `DENIED_OR_NOT_FOUND` e registra `SYNTHETIC_DENIED`, sem publicar a projeção.
- Se a leitura realmente aconteceu e foi válida para a autorização **CRM-owner**, a auditoria interna e contadores OP-01 continuam registrando sua leitura como sucesso; a negação posterior da **ferramenta** não deve reescrever esse fato. Nenhum novo nonce nem chamada extra é introduzido.
- `MockToolDispatcher::dispatch(SUGGEST)` revalida a capacidade **depois** do provedor e da validação da sugestão. Revogação durante o provedor segue o caminho `TOOL_SUGGESTION_DENIED`, fecha o Run como `FALHOU` e impede o retorno de qualquer texto.
- Não se aplica um falso “undo” em `MUTATE`: uma chamada de escrita confirmada não pode ser desfeita por uma checagem pós-submit. Ela permanece explicitamente fora desse mecanismo, sem autorização para CRM real.

## Regressões U72-01..08

Leitura com grant revogada durante reader, segunda tentativa negada sem nova leitura, recuperação com novo grant/prova, leitura normal, sugestão com grant revogada, segunda tentativa sem nova chamada ao provedor, sugestão saudável e recuperação com novo Run/grant.

**Gate esperado:** **629 testes PHP / lint 83 arquivos**, verificações Python; CI verde no SHA exato da revisão e novamente pós-merge na `dev`.

## Limitações e bloqueios

Uma concessão é apenas um objeto PHP injetado, não IAM, ACL, token ou permissão real. O `now` é sintético; não há relógio confiável, revogação compartilhada entre processos, concorrência, autenticação serviço-serviço ou autorização transacional. A negação da ferramenta **não** é prova de que uma leitura interna não ocorreu; os registros devem permanecer fiéis à separação dessas etapas.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT e os 13 cenários reais OP-01 continuam `NAO_EXECUTADO`; decisões OP01-R01..R07 e gates R01-G01..G11/CRM CORE/SEG/AUT ABERTOS/NO-GO. Sem merge em `main`, VPS, produção, dados reais, credenciais ou gastos.
