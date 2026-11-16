# F250 — verificação cruzada Agents ↔ CRM candidato funcional F249

**15/11/2026 — revisão sintética e de fonte, NO-GO / NO-MERGE / NO-DEPLOY.**

O CRM passou na [PR #628](https://github.com/rafhael100/imagine-crm/pull/628), commit `c67aaa0440ac4f9f1c607883d0c1347f5a067f92`, com três workflows aprovados para o mesmo commit. O Agents permanece na [PR #332](https://github.com/rafhael100/imagine-ai-agents/pull/332), commit `fbf50543e7c52e6c59c6ffec58b7f6a6d30564b5`, sem merge. A F250 fixa a fonte CRM #628 em CI somente leitura e verifica que os guardas de Ed25519, escopo, PG16/RLS, bloqueio de revogação, negativa HTTP uniforme e ausência de rotas Agents permanecem presentes.

É **auditoria estática de código original**, não uma nova execução de PostgreSQL nem homologação operacional. As provas físicas de banco foram executadas separadamente no CRM #628 ([Actions #43795878769](https://github.com/rafhael100/imagine-crm/actions/runs/43795878769)) e o Agents R01 na CI de #332. A F250 é um controle contra desvio de contrato entre repositórios; não substitui revisão de diff, não altera o algoritmo de assinatura, não lê empresas, clientes, credenciais nem executa HTTP.

O próximo gate real é SEG/CORE/IAM/DBA: identidade de peer segura, autorização efetiva do CRM por membership/recurso/campo, revogação com origem confiável, TLS/mTLS, auditoria e retenção LGPD, política de chaves e rollback. A PR CRM #57 mantém 14 gates, 7 decisões e 13 cenários operacionais sem aceite. Não há autorização para fazer merge, deploy ou operação com dados reais.
