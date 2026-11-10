# R01/EVID-32 — SEC-47 integrada: recheck com tempo monotônico

**Verificado em:** 10/11/2026. **Situação operacional: NO-GO.** Dados inteiramente sintéticos, CI offline.

## Evidências do GitHub

- [PR #224 — SEC-47](https://github.com/rafhael100/imagine-ai-agents/pull/224): squash integrado à `dev/ft01-aia-core-mocks-2026-11-06`, commit `502be4fedc685c4e7965d5e77d3905264acac25a`.
- [CI pós-merge #42923158871](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42923158871): `completed/success` no mesmo SHA com **605 testes PHP, lint de 83 arquivos PHP** e verificações Python de contratos, isolamento e qualidade offline.
- A SEC-47 acrescentou a marca `observed_at` ao recibo local introduzido na SEC-46. O recheck permite horário sintético igual/mais avançado, nega retrocesso e invalida o recibo após expiração, revogação, erro ou quebra temporal.
- **U69-01..08**: testes de retrocesso, progressão válida, limites de validade do envelope e da prova do proprietário, entrada negativa, diferença no wire, request malformado e estado compartilhado de nonce. Não há segunda leitura ou consumo adicional por recheck.

## Limites e dependências

O `$now` é fornecido pelo laboratório, sem relógio externo autoritativo. A monotonicidade é **apenas por recibo de uma instância PHP**, não garante validade entre processos ou entre leituras novas. O CRM real ainda precisará validar autenticação, expiração e permissão **no próprio backend** sob as condições de concorrência exigidas; CI offline não autoriza piloto.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; os **13 cenários reais** seguem `NAO_EXECUTADO`, os gates R01-G01..G11 / CRM CORE/SEG/AUT e decisões OP01-R01..R07 permanecem ABERTOS/NO-GO. Sem alterações em `main`, deploy, VPS, credenciais, clientes ou despesas.
