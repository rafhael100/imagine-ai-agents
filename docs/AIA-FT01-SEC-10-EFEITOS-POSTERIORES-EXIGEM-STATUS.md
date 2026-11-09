# FT01/SEC-10 — Efeitos posteriores exigem consulta de status

**Data:** 09/11/2026. **Ambiente:** laboratório PHP inteiramente sintético. **Decisão:** NO-GO para CRM real e produção.

## Lacuna e correção
O revisor `ReadOnlyTraceReview` verificava a existência de uma submissão, a identidade da intenção e a integridade das sequências. Entretanto, depois do primeiro resultado de uma submissão, podia aceitar outro código de efeito sem evento `STATUS_REQUESTED`. Isso permitia que uma trilha artificial apresentasse nova evidência sem mostrar sua origem.

A partir de SEC-10, **somente o primeiro resultado de envio** pode ser registrado sem consulta de status. Qualquer resultado posterior `CONFIRMADO`, `NEGADO`, `PENDENTE` ou `INDETERMINADO` precisa estar imediatamente associado a uma `STATUS_REQUESTED` ainda sem fechamento. A verificação rejeita atualizações soltas com `STATE_CONFLICT`. `STATUS_UNAVAILABLE` e `STATUS_REJECTED` continuam fechamentos sem prova adicional. Não há replay, rede ou autorização implícita.

## Regressões
- U26-01: confirmação duplicada sem consulta é rejeitada.
- U26-02: resultado pendente inserido após incerteza sem consulta é rejeitado.
- U26-03: falha de status não autoriza evento de efeito solto; trilha original continua verificável.
- U03-04 atualizado para esperar `STATE_CONFLICT`, refletindo a precedência da regra mais restritiva.

**Meta CI:** 329 casos PHP, 79 arquivos PHP com lint, no SHA revisado. Testes e hashes sintéticos não são comprovação criptográfica, identidade de serviço ou aceitação do CRM.

## Limites
Nenhum custo, API, credencial, SQL, LLC/VPS, `main`, dados reais ou publicação envolvidos. A integração OP-01 do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue condicionada a revisão bilateral. R01-G01..G11 e CORE/SEG/AUT **ABERTOS**.
