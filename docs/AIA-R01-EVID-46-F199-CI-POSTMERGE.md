# R01/EVID-46 — Escopos do configurador F199 e continuidade documental

**11/11/2026. Exclusivamente laboratório offline. Operação real: NO-GO.**

## Evidência GitHub verificada

| Entrega | PR integrado | SHA squash | CI pós-merge |
| --- | --- | --- | --- |
| EVID-45 — consolidação F196/F197/F198 | [#277](https://github.com/rafhael100/imagine-ai-agents/pull/277) | `c6fd19ee9b4fa9b847cd8b0801959624534aeeac` | [#43077639745](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43077639745) **success** |
| F199 — matriz segura de fonte, negação e gate nos modelos CRM/Performance/Universidade | [#278](https://github.com/rafhael100/imagine-ai-agents/pull/278) | `c355bd5ae7044ee17db90ffffdcb5006f64cb2a9` | [#43078227186](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43078227186) **success** |

Ambas as CIs pós-merge foram conferidas como `completed/success` no SHA exato da branch `dev/ft01-aia-core-mocks-2026-11-06`.

## Funcionalidade entregue

F199 permite visualizar o alcance de três perfis fictícios de agentes sem lhes conceder permissões. O perfil de CRM oferece demonstração de campos mínimos, sem alterar leads; Performance recusa anúncios e saldos reais; Universidade declara documentos não confiáveis sem execução de ferramentas. O rascunho continua não publicado, o catálogo é fechado e o JSON transferível F187 mantém apenas `name` e `template`.

O incremento agregou cinco testes Python e expandiu o smoke DOM Node. O baseline PHP segue **835 casos, lint 103 arquivos**, com contratos Python e DOM. Nada foi validado em produção.

## Bloqueios que continuam

O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) foi conferido OPEN/DRAFT/merged:false. As treze homologações reais OP-01 permanecem `NAO_EXECUTADO` nos registros e gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. Nenhum código desta rodada oferece autenticação, IAM, acesso a dados de clientes, integração de serviço, log durável ou aprovação operacional. A `main` não foi tocada; VPS, deploy, produção, credenciais e custos permanecem inalterados.
