# R01/EVID-35 — SEC-50 integrada: revalidação da capability grant após ferramentas

**Verificação:** 10/11/2026. **Estado operacional: NO-GO.** Este registro documenta exclusivamente execução sintética offline em PHP.

## Evidência auditável

- [PR #230 — SEC-50](https://github.com/rafhael100/imagine-ai-agents/pull/230) foi integrado exclusivamente à `dev/ft01-aia-core-mocks-2026-11-06`, SHA pós-merge `efc6f26a57c3c6c631f4dddaecfc843bab7b72c7`.
- [CI pós-merge #42938703131](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42938703131) foi concluída com **status success no SHA exato `efc6f26a57c3c6c631f4dddaecfc843bab7b72c7`**. A rotina de desenvolvimento valida **629 testes PHP, sintaxe de 83 arquivos PHP** e verificadores Python offline. Nenhuma integração CRM↔Agents real foi executada.
- O [documento SEC-50](AIA-FT01-SEC-50-CAPABILITY-RECHECK-POS-FERRAMENTA.md) descreve a revalidação da mesma `MockCapabilityGrant` após READ e SUGGEST: revogação intermediária impede entregar projeção/texto.
- As **8 regressões U72-01..08** cobrem revogação durante leitor/provedor, recuperação com concessão/prova novas, não repetição de nonce e continuidade saudável.
- Auditoria CRM-owner permanece fiel à leitura que realmente ocorreu; uma negação posterior na ferramenta não reescreve a auditoria interna.

## Fronteiras não comprovadas

As grants, o trust, os recibos e as políticas são **objetos de memória local** em processo único. CI verde não constitui IAM de serviço, ACL/RLS transacional, privacidade operacional, replay distribuído, autenticação, autorização real ou homologação de cliente.

O [PR #57 do Imagine CRM](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT**; 13 cenários reais OP-01 continuam **NAO_EXECUTADO**. R01-G01..G11, CORE/SEG/AUT e decisões bilaterais pendem de revisão independente. **Sem merge em main, VPS, produção, dados reais, chaves ou novos gastos.**

**Próximo desbloqueio real:** revisão e aceite do contrato entre CRM e Agents, implementação autenticada na borda do CRM em ambiente segregado, execução e evidência dos 13 cenários reais, revisão independente e aprovação específica antes de qualquer piloto operacional.
