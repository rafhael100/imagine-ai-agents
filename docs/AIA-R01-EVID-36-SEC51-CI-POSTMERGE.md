# R01/EVID-36 — SEC-51 integrada: bloqueio de ferramentas reentrantes

**Verificado em 10/11/2026. Decisão operacional: NO-GO.** Evidência de laboratório PHP offline, não homologação do produto real.

## Validação verificável

- [PR #233 — SEC-51](https://github.com/rafhael100/imagine-ai-agents/pull/233) integrado exclusivamente à branch `dev/ft01-aia-core-mocks-2026-11-06`, commit pós-merge `872597580bf93261cbb858a8c9eb6f2fe342e9d7`.
- [CI do PR #42940517445](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42940517445) e [CI pós-merge #42940604581](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42940604581) foram concluídas com **success**; a segunda verificou o SHA exato `872597580bf93261cbb858a8c9eb6f2fe342e9d7`, **633 testes PHP, lint de 83 arquivos e validadores Python offline**.
- `MockToolDispatcher` agora bloqueia reentrada READ/SUGGEST/MUTATE durante callback de sugestão na **mesma instância PHP**. Tentativa aninhada contamina o resultado externo, mesmo que o provedor capture a negação; o Run termina em `FALHOU`.
- **U73-01..04** confirmam negação de sugestão recursiva, bloqueio de mutação fictícia previamente aprovada, negação de READ aninhada e recuperação em novo Run. Consulte [SEC-51](AIA-FT01-SEC-51-REENTRADA-FERRAMENTAS-CALLBACK.md).

## Limitações e aceite pendente

Guarda apenas em memória de processo único, não um lock distribuído, identidade serviço-serviço, ACL/RLS, autorização real ou auditoria operacional. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT** e 13 cenários reais OP-01 estão **NAO_EXECUTADO**. R01-G01..G11 e CRM CORE/SEG/AUT continuam NO-GO.

Nenhuma alteração em main, VPS, produção, clientes, tokens, segredos ou despesas. Próximo desbloqueio de produto: revisão independente e aprovação do contrato CRM↔Agents, identidade/autorização no CRM, homologação segregada e validação dos 13 cenários reais antes de qualquer piloto.
