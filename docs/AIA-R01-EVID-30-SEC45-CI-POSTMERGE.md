# R01/EVID-30 — SEC-45 integrada: recheck após sugestão OP-01

**Verificação em:** 10/11/2026. **Decisão operacional: NO-GO.** Somente PHP sintético offline, não um serviço real.

## Evidências verificadas

- [SEC-45 — PR #220](https://github.com/rafhael100/imagine-ai-agents/pull/220) integrado por squash exclusivamente à `dev/ft01-aia-core-mocks-2026-11-06`, commit `75c2c00dbcfad16b27f5bcef28f16c4fbcc8ec4f`.
- [CI pós-merge #42913437800](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42913437800) `completed/success` no mesmo SHA, baseline **585 testes PHP / lint de 83 arquivos PHP**, verificadores Python de contrato/isolamento e sem rede.
- `MockReadOnlyPilot` aplica a invariante de campos OP-01 antes do provedor e **revalida** a autorização depois da sugestão, antes de marcar o Run como concluído. A fachada/borda reutiliza o mesmo objeto `MockCrmReadBoundary` para checagem não consumidora de nonce.
- Dez regressões `U67-01..10` comprovam negação uniforme e Run `FALHOU` para grant/empresa/membro suspensos, falhas de owner/trust, revogação de envelope/owner/digest durante o provedor e recuperação com nova prova. Uma execução válida usa um só nonce, uma leitura, um contador e nenhuma escrita.

## Limites

A evidência retrata revogação **sincrônica dentro do mesmo processo**, não garante transação distribuída nem evita uma mudança de autorização entre a última verificação e a resposta externa. Não comprova identidade serviço-serviço, credenciais, API, ACL/RLS de banco do CRM, auditoria durável ou análise independente de segurança.

Os 13 cenários da homologação **real** continuam `NAO_EXECUTADO`; gates R01-G01..G11 / CRM CORE/SEG/AUT e as 7 decisões OP01-R01..R07 permanecem ABERTOS/NO-GO. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT. Sem `main`, VPS, deploy, dados de clientes, segredos ou novas despesas.

**Próximo marco:** aprovação bilateral do contrato OP-01, autorização dentro do backend CRM e ensaios de identidade/revogação/isolamento reais em ambiente segregado, seguidos por revisão independente. O laboratório não concede essas aprovações.
