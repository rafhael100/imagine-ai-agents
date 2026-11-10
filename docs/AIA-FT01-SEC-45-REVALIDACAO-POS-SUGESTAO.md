# FT01/SEC-45 — Revalidação final da permissão antes da sugestão OP-01

**Data:** 10/11/2026. **Escopo:** simulação em um processo PHP, sem API, IAM, LLM ou CRM reais. **Decisão operacional: NO-GO.**

## Problema

A SEC-43 revalidou permissão após a execução de `CrmReader::lead` e a SEC-44 aplicou a mesma invariante a toda a borda de leitura por ferramenta. Entretanto, no `MockReadOnlyPilot`, após a leitura autorizada o provedor `AiSuggestionProvider` poderia revogar um grant sintético durante a preparação da sugestão. Sem uma checagem adicional, o piloto ainda terminaria o Run como `CONCLUIDA` e devolveria um texto fictício mesmo quando a autorização já não estava ativa.

## Correção

- `MockCrmReadBoundary::recheck()` centraliza a verificação atual do contexto, campos permitidos pelo proprietário e da prova trust. É reutilizado na validação após o leitor. `MockTrustGateway::recheck()` segue **não consumidor** e pode verificar revogação sem aceitar um replay como nova leitura.
- A `MockCrmReadPublicFacade` mantém a mesma instância de `MockCrmReadBoundary` tanto para leitura auditada quanto para `recheck()` (retorno booleano genérico e sem detalhes internos). A borda de telemetria expõe apenas a delegação recheck sem criar nova leitura, novo contador, novo registro de auditoria ou nonce.
- O `MockReadOnlyPilot` revalida a **projeção estrita OP-01** antes de chamar o provedor. Após a sugestão, antes da transição terminal para `CONCLUIDA`, a permissão sintética é verificada novamente com o mesmo wire/contexto/provas. Falha de permissão termina o Run como `FALHOU` com resposta pública uniforme `DENIED_OR_NOT_FOUND` e sem summary.
- A auditoria de **leitura** previamente autorizada permanece como sucesso de leitura: o Run falho e sua saída negada não devem falsificar um sucesso de **sugestão**. O recheck não altera auditoria nem telemetria histórica.

## Regressões U67-01..10

Os 10 casos simulam revogação de grant, suspensão de empresa/membro, indisponibilidade da política, envelope/owner revogados, digest compartilhado revogado, store indisponível, sucesso sem segundo nonce/contador, e recuperação com nova prova e Run. Nenhum efeito real ou sintético de escrita deve ser submetido.

**Gate esperado da SEC-45:** **585 testes PHP / lint de 83 arquivos**, verificações Python e CI aprovada antes e após squash na `dev`.

## Limitações

Os testes modelam revogação **durante uma chamada local ao provedor**; não garantem atomicidade entre a última checagem e o retorno público, nem substituem checagem transacional de ACL no backend CRM, identidade serviço-serviço, cache compartilhado real ou revisão independente. Os 13 casos de homologação real seguem `NAO_EXECUTADO`; CRM PR #57 OPEN/DRAFT e gates R01-G01..G11 / CRM CORE/SEG/AUT ABERTOS/NO-GO. `main`, VPS, produção, clientes, segredos e despesas inalterados.
