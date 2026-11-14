# EVID-50 — F210/F211: fachada PHP dos Agentes com Core CRM

**14/11/2026. Laboratório CI exclusivamente sintético; produção NO-GO.**

| Entrega | PR | HEAD exata | Commit DEV | CI PR e pós-merge |
| --- | --- | --- | --- | --- |
| F210, fachada PHP real fictícia + inspetor CRM, seis casos e dez mutações | [#296](https://github.com/rafhael100/imagine-ai-agents/pull/296) | `a7372f65a2b38668a41930807b24a34d0ce37f05` | `60444d42221f6fb2e78476a313a35fa44a25ec89` | [43606859038](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43606859038), [43606939495](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43606939495) |
| F211, 14 estados owner/trust e cinco revogações de recibo | [#297](https://github.com/rafhael100/imagine-ai-agents/pull/297) | `98bbe4fb12ac3c3565c98b0f13585a30d6d53b49` | `d69458d6a4488e7e762206d9e159cd9cd4ac44ea` | [43607201374](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43607201374), [43607272003](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43607272003) |

As quatro CIs foram conferidas como `completed/success`. O baseline original de **879 testes PHP / lint 110**, e suítes Python/Node, permanece. O workflow confere checkout somente leitura do CRM na SHA `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad` antes de testar código PHP real dos dois repositórios.

**O que foi comprovado:** respostas fictícias da `MockCrmReadPublicFacade` chegam ao inspetor PHP real do CRM sem conceder direitos; revogação/ABA, suspensões, replay, expiração e indisponibilidade nos mocks negam com saída uniforme. Nenhuma comunicação HTTP operacional, IAM real, ACL de cliente, persistência distribuída ou infraestrutura foi testada.

**Bloqueios:** [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) **OPEN/DRAFT**, 13 cenários reais `NAO_EXECUTADO`, **14 gates** abertos, **R01-G01..G11, CORE/SEG/AUT/LGPD NO-GO**. Nenhuma API, cliente, token, VPS, despesa, deploy ou acesso operacional autorizado. A `main` seguiu em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.

O próximo marco requer pareceres independentes sobre identidade, permissão backend, privacidade e homologação real; testes sintéticos verdes não significam GO.
