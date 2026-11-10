# R01/EVID-16 — Encerramento seguro de Runs e erros públicos da ferramenta

**Data:** 10/11/2026. **Estado:** código de laboratório sintético aprovado; integração real e produção **NO-GO**.

| Etapa | Mudança de código | Evidência verificada |
| --- | --- | --- |
| [SEC-18 / PR #177](https://github.com/rafhael100/imagine-ai-agents/pull/177) | Sugestão rejeitada não deixa a Run `EXECUTANDO`. Após erro ou output fora do contrato, tenta registrar `FALHOU`, impedindo nova sugestão e execução de efeito na mesma Run; preflight reserva até dois eventos | [CI de revisão #42753013699](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753013699) e [CI pós-merge #42753075500](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753075500) success; merge dev `feb9fd8e004c2908a754f8dd535ca5286094454c` |
| [SEC-19 / PR #178](https://github.com/rafhael100/imagine-ai-agents/pull/178) | Gateway de status pode retornar `MockError` arbitrário; interface de ferramentas passa a aceitar só códigos públicos fechados, substituindo os demais por `TOOL_EXECUTION_DENIED` sem segredo/trilha bruta | [CI de revisão #42753478173](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753478173) e [CI pós-merge #42753536675](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753536675) success; merge dev `138a97df72ff93e7105e12ef73bae8a07eccdc65` |

**Gate atual da branch dev:** 397 casos PHP, lint de 82 arquivos PHP e testes Python de contrato, isolamento e demonstração. Evidência restrita ao código sintético, in-memory, sem CRM, rede, IAM, dados reais ou IA externa.

## O próximo trabalho não é mais adicionar casos fictícios

Antes da integração OP-01 real:
1. Revisar e aprovar bilateralmente a proposta do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (**OPEN/DRAFT**); definir versão do contrato, campos operacionais, ownership de dados e erros.
2. Projetar/validar autenticação real serviço-serviço, vínculo usuário/tenant e revogação/anti-replay distribuídos (R01-G01/G03, CRM AUT/SEG).
3. Autorizar cada leitura no **backend CRM** por tenant/principal/recurso/finalidade/campo e testar isolamento real multiempresa (G02, CRM CORE/SEG).
4. Fechar privacidade, base legal, minimização, retenção, observabilidade, rollback e revisão independente (G05/G07/G08/G10).
5. Provisionar homologação isolada e obter aceite específico de piloto, inclusive limites de custo e responsáveis (G11). Autorização de produção é etapa distinta.

**Estado formal:** R01-G01..G11 e CRM CORE/SEG/AUT seguem **ABERTOS / NO-GO**. Não presumir encerramento por PR documental, testes de mock ou autorização genérica de desenvolvimento. Nenhuma alteração na `main`, VPS, produção ou contratação.
