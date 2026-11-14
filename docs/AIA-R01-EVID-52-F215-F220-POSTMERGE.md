# EVID-52 — F215–F220: homologação preparada e robustez das mensagens CRM/Agents

**14/11/2026 · laboratório DEV, NO-GO operacional.** Esta revisão consolida HEADs finais, squash-merges e CIs concluídas com sucesso. Resultados negativos de tentativas anteriores foram corrigidos e revalidados nas HEADs indicadas.

| Lote | PR | HEAD validada | Squash DEV | CI PR aprovada | CI pós-merge aprovada |
| --- | --- | --- | --- | --- | --- |
| F215, caderno de 13 cenários | [#303](https://github.com/rafhael100/imagine-ai-agents/pull/303) | `edbb234aa34a7ac45d9fb40e25cde412248db6cf` | `380aae8075a5e1a2e8377567d0e08c3fe401ae09` | [43611836409](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43611836409) | [43611906504](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43611906504) |
| F216, recibo da borda | [#304](https://github.com/rafhael100/imagine-ai-agents/pull/304) | `2da78714f98c9ed0453a4c75368d28047f2cda60` | `c7ff83fd29714350aa0082359f94644c7f9dc35a` | [43614467875](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43614467875) | [43614546287](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43614546287) |
| F217, falhas do piloto | [#305](https://github.com/rafhael100/imagine-ai-agents/pull/305) | `7528c97139c7bc61a5f1a26fa88c652ec93d57bf` | `7cd78ffdf17f16912181f897dd60317e8bedd6be` | [43615043759](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43615043759) | [43615129946](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43615129946) |
| F218, fila e lotes | [#306](https://github.com/rafhael100/imagine-ai-agents/pull/306) | `90283bdfbca66fbe467c8943179ebc1fd8dc415d` | `fa850d43335565c0912d2da353222570ecb3c7e9` | [43615606851](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43615606851) | [43615686895](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43615686895) |
| F219, entrada JSON canônica mock | [#307](https://github.com/rafhael100/imagine-ai-agents/pull/307) | `326545f3a11c16332f4b4ace0b2cfdf408d9a323` | `4ac2de908b99683078cbc88b1f22237a669793d4` | [43618765292](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43618765292) | [43618848393](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43618848393) |
| F220, resposta JSON e CRM Core | [#308](https://github.com/rafhael100/imagine-ai-agents/pull/308) | `35c60f8d42785f2d7081772f4ebdcc5f730d3aee` | `8cc0f590632b3b308d34bb9c415750210cf54f72` | [43619223743](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43619223743) | [43619321460](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43619321460) |

## Incrementos técnicos com teste real de código PHP, mas somente dados sintéticos

- **F215:** caderno offline com 11 testes negativos e 2 positivos projetados, nenhum cenário operacional executado.
- **F216–218:** correção da duração e revogação do recibo de leitura em borda, piloto, fila e lotes, inclusive exceções intermediárias e confirmação de processamento negada.
- **F219:** parsing canônico de entrada JSON de até 1024 bytes, rejeição de duplicidade/ambiguidade com 15 mutações adversariais. API HTTP real não existe.
- **F220:** parsing canônico de resposta de até 512 bytes, 6 respostas produzidas pela fachada, 20 mutações adversariais, inspeção posterior com classes originais do **CRM Core fixadas ao commit** `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad`. O inspetor não concede direito real nem quando o formato é ficticiamente positivo.

A CI valida as classes PHP, contratos Python e baseline **879 testes PHP / lint 110**, sem adicionar dependências externas, custo, rede, segredo, banco real ou execução operacional.

## Bloqueadores que permanecem fora da autorização desta rodada

A [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**. O pacote bilateral mantém **14 gates ABERTO**, **7 decisões pendentes**, **13 cenários reais NAO_EXECUTADO** (N01–N11 e P01–P02). R01-G01..G11 e **CRM CORE/SEG/AUT/LGPD** continuam **NO-GO**. A conclusão exige autenticação entre serviços/identidades, ACL real no CRM por empresa/recurso/campo, privacidade e revisão independente, staging seguro, provas reais de todos os cenários e autorização expressa de piloto.

**Sem `main`, VPS, credenciais, dados de clientes, produção ou deploy.** Os códigos de retorno de CI apenas validam o laboratório e nunca equivalem a um parecer independente ou GO operacional.
