# R01/EVID-27 — SEC-41 e SEC-42 integradas, paridade offline confirmada

**Verificação:** 10/11/2026. **Decisão operacional: NO-GO.** Esta evidência é exclusivamente do laboratório Imagine AI Agents, sem endpoints CRM ou autenticação real.

## Integração e gates verificáveis

- [PR #213 — SEC-41](https://github.com/rafhael100/imagine-ai-agents/pull/213), squash integrado à `dev/ft01-aia-core-mocks-2026-11-06` no commit `e77f0514526fbfc2f52a7f313203ab94c7ad69fb`; [CI pós-merge #42897813673](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42897813673), `completed/success`. Centraliza validação de saída fictícia OP-01 e adiciona nove regressões PHP; baseline **555 testes PHP / lint dos 83 arquivos**.
- [PR #214 — SEC-42](https://github.com/rafhael100/imagine-ai-agents/pull/214), squash integrado à mesma `dev` no commit `61a64f88e23fd0d880fa9a3f7bebfe9cf45141d3`; [CI pós-merge #42899017010](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42899017010), `completed/success`. Inclui matriz determinística adversarial do parser OP-01 no gate Python. O baseline de PHP permanece **555 testes / 83 lint**.
- Guardrails: `tests/Contracts/test_quality_gate_coverage.py` exige a execução do novo teste de mutações na CI; `tests/Contracts/test_canonical_project_status.py` mantém indicadores atuais, histórico e NO-GO.

## O que essas provas não significam

O teste de paridade é um **oráculo estrito do consumidor sintético**, não aprovação de esquema pelo backend Imagine CRM. Os **13 cenários de homologação real** no CSV permanecem `NAO_EXECUTADO`, as 7 decisões OP01-R01..R07 não têm aceite, e o [PR #57 do CRM](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT**. R01-G01..G11 e CRM CORE/SEG/AUT continuam **ABERTOS/NO-GO**. Não houve acesso à VPS, dados reais, credenciais, APIs externas, cobrança, deploy ou alteração na `main`.

## Próxima decisão técnica

Para sair do laboratório, o CRM precisará ratificar bilateralmente a versão e o contrato de respostas, implementar identidade serviço-serviço e ACL final por empresa/recurso/campo, testes de replay/revogação distribuídos, privacidade, observabilidade e rollback em ambiente isolado. Somente responsáveis habilitados poderão aceitar esses gates; sucesso de CI offline não os aprova.
