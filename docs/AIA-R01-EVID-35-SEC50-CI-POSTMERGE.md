# R01/EVID-35 — SEC-50 integrada: recheck da capability após a ferramenta

**Verificação:** 10/11/2026. **Decisão operacional: NO-GO.** Evidência somente do simulador PHP offline, sem integração real com Imagine CRM.

## GitHub e gate

- [PR #230 — SEC-50](https://github.com/rafhael100/imagine-ai-agents/pull/230): squash integrado apenas à `dev/ft01-aia-core-mocks-2026-11-06`, commit `efc6f26a57c3c6c631f4dddaecfc843bab7b72c7`.
- [CI pós-merge #42938703131](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42938703131) `completed/success` no mesmo SHA, com **629 testes PHP, lint de 83 arquivos PHP** e verificações Python de contratos, isolamento e qualidade offline.
- A ferramenta `READ` revalida a mesma `MockCapabilityGrant` após a validação OP-01 e antes de entregar o resultado. Revogação durante o leitor faz a ferramenta emitir `SYNTHETIC_DENIED`; auditoria interna continua refletindo a leitura que já ocorreu legitimamente na borda CRM-owner fictícia.
- A ferramenta `SUGGEST` revalida grant após a chamada do provedor e a checagem de saída. Se a capability foi revogada, nega o texto e encerra o Run fictício como `FALHOU`.
- **U72-01..08** cobrem revogação intermediária, recusa de nova tentativa revogada, preservação da auditoria/nonce, recuperação com novo grant/prova/Run e sucesso com grant preservada. Nenhuma escrita foi executada.

## Limites

`MockCapabilityGrant` é um objeto local, não um token/autorização real. O mecanismo **não** implementa identidade serviço-serviço, ACL do CRM, relógio autoritativo, revogação distribuída nem proteção transacional. Em ferramentas `MUTATE`, uma operação já submetida não pode ser desfeita por um recheck após o envio; esta correção não simula rollback nem libera operações de escrita reais.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; **13 cenários reais OP-01 continuam `NAO_EXECUTADO`**, decisões OP01-R01..R07 e gates R01-G01..G11/CRM CORE/SEG/AUT continuam ABERTOS/NO-GO. `main`, VPS, produção, dados reais, segredos e despesas permaneceram inalterados.

**Próximo marco:** contrato bilateral aprovado, autenticação e autorização reais no backend CRM, execução dos cenários em homologação segregada e revisão independente por responsáveis habilitados.
