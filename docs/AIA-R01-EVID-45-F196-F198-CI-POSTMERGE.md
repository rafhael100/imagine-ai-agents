# R01/EVID-45 — Painel de transição F196/F197 e regressões F198

**11/11/2026 — Apenas laboratório PHP/HTML/JS/Python offline. Operação real: NO-GO.**

## Evidências GitHub verificadas no SHA pós-merge da branch dev

| Entrega | PR integrado | SHA squash | CI pós-merge |
| --- | --- | --- | --- |
| F196 — selecionar PAUSED/OPEN no laboratório F108, payload idêntico ao F190 | [#274](https://github.com/rafhael100/imagine-ai-agents/pull/274) | `dc9a0b71ff970f7594e210d157f480bdcc84587a` | [#43075578789](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43075578789) **success** |
| F197 — diferenças agregadas no painel visual | [#275](https://github.com/rafhael100/imagine-ai-agents/pull/275) | `808dd778c208a59220dad9c1897148d689ca3132` | [#43076103326](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43076103326) **success** |
| F198 — ataque ao validador real da página com 16 cargas adulteradas | [#276](https://github.com/rafhael100/imagine-ai-agents/pull/276) | `fa5046e0bce0e343e7f645f9b91de6ddb4467470` | [#43076603247](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43076603247) **success** |

As três execuções de CI acima foram conferidas como `completed/success` no respectivo SHA exato após a integração na branch `dev/ft01-aia-core-mocks-2026-11-06`.

## Progresso e alcance

- **F196:** a página F108 alterna PAUSED e OPEN, com contrato JSON fechado e paridade integral com o CLI PHP F190; preserva o snapshot F182 e a simulação de revisão F184.
- **F197:** a própria interface calcula diferenças verificadas de pendentes `−1`, concluídas `+1` e indeterminadas `0`; permanece sem chamadas de rede e sem autoridade operacional.
- **F198:** uma suíte Node executa o código de validação JavaScript real com 16 variações hostis; rejeições ocorrem antes do desenho da tela. O gate offline exige esse ensaio quando Node está disponível.
- **Testes incrementais:** 16 testes Python novos distribuídos pelos três incrementos, mais smoke DOM Node expandido e 16 casos adversariais Node. Baseline PHP permanece **835 testes / lint 103 arquivos**.

## Limites que não mudaram

CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT e não integrado conforme última verificação; treze testes operacionais OP-01 seguem `NAO_EXECUTADO` e gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. A interface não prova autenticação, identidade de operador, worker, CRM real, auditoria durável, backups, piloting ou segurança de produção.

A `main` não foi modificada, assim como VPS, deploy, clientes, segredos, uso de dados reais ou custos. Aprovação de CRM e revisão independente são pré-requisitos para passar à homologação real, não podem ser inferidos de green CI.
