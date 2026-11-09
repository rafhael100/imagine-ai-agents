# FT01/F1-21 — Projeção somente leitura de eventos por execução

**Escopo:** API interna PHP com dados inteiramente sintéticos. Sem HTTP, CRM real, autorização de usuário, persistência, custos ou publicação.

## Problema

`SupervisedCoordinator` permite iniciar várias execuções (`Run`) no mesmo contexto, registrando seus eventos intercalados em `events()`. O revisor `ReadOnlyTraceReview::inspect`, entretanto, exige uma trilha de apenas uma execução quando recebe a intenção de destino. Passar o histórico global com eventos de outro `Run` gera corretamente `CONTEXT_DENIED`, mas não existia um modo seguro e reproduzível de obter a visão de uma execução específica.

## Solução

O novo método `SupervisedCoordinator::eventsForRun(Run $run): array` verifica primeiro a igualdade integral do `TenantContext` com o contexto do coordenador. Ele seleciona eventos cujo hash de `run` corresponde ao identificador fictício pedido e devolve **cópias** com números de sequência locais e contíguos, conforme esperado pelo revisor. O histórico global original, seus números e a ordem de emissão permanecem inalterados. A nova função é exclusivamente leitura e não chama gateways ou modelos.

Uma execução desconhecida retorna uma lista vazia e, se submetida ao revisor, não pode ser considerada confirmada. Uma requisição de outro contexto retorna `CONTEXT_DENIED`. A função também permite analisar uma execução cancelada sem reabri-la ou disparar efeitos. Não se trata de ACL real: os IDs são sintéticos e o método não autentica chamadores.

## Testes e limites

Oito casos U21-01 a U21-08 exercitam duas execuções intercaladas com operações independentes, projeção A/B, segregação de tenant, run ausente, imutabilidade do histórico, reconciliação posterior e cancelamento. A suíte oficial sobe para **288 testes PHP e 77 arquivos de lint**, condicionada à aprovação de CI em SHA exato.

**Atenção à semântica da auditoria:** os números de sequência retornados são relativos à projeção; não são os números históricos globais e não devem ser usados para atestar ordem absoluta entre execuções. Não há assinatura criptográfica de origem, armazenamento imutável, nem garantia de autenticação ou retenção. A própria `ReadOnlyTraceReview` reconhece apenas provas fictícias. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece DRAFT, R01-G01..G11 e CRM CORE/SEG/AUT continuam abertos. **NO-GO** para qualquer acesso de cliente, API real, `main`, VPS ou produção.
