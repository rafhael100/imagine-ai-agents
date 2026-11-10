# R01/EVID-29 — SEC-44 integrada, CI pós-merge verificada

**Data de verificação:** 10/11/2026. **Decisão operacional: NO-GO.** Toda a evidência descreve somente o Imagine AI Agents em modo PHP sintético offline.

## Evidências do GitHub

- [PR #218 — SEC-44](https://github.com/rafhael100/imagine-ai-agents/pull/218): squash integrado exclusivamente na branch `dev/ft01-aia-core-mocks-2026-11-06`, commit `ded000871ad5f34a8e3697f91c6ba00b81941a70`.
- [CI pós-merge #42906719897](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42906719897): `completed/success` no mesmo commit, com **575 testes PHP**, lint dos **83 arquivos PHP**, regressões Python, testes de contratos e verificações de isolamento.
- `MockToolDispatcher::readThroughBoundary()` passou a usar `MockReadProjectionInvariant::isCanonical`, já aplicado pela borda do leitor, fachada pública e telemetria.
- Dez regressões U66-01..10 percorrem ferramenta → edge → fachada → leitor, cobrindo resultado mínimo, ordem da projeção, revogação durante leitura, suspensão de empresa/membro, falha de memória compartilhada, auditoria sem vazamento, replay bloqueado e preflight negado sem consumir nonce.

## Limites e não-autorização

O código e todos os IDs continuam fictícios. Esses testes **não** executam uma requisição real ao CRM, autenticação serviço-serviço, RLS/ACL de produção, transação de autorização, cache distribuído, concorrência entre instâncias ou revisão independente. Os 13 cenários reais continuam `NAO_EXECUTADO`; os gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS/NO-GO; o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT.

Nenhum merge na `main`, VPS, deploy, cliente real, segredo ou nova despesa. A CI do mock não deve ser convertida em autorização de piloto ou produção.

## Continuidade

Avançar somente com preparação/testes offline sem inventar aceites. A integração operacional depende de contrato bilateral, autenticação, checagem de permissão dentro do backend CRM, cenários reais positivos/negativos e aprovação independente.
