# AIA R01 / EVID-51 — F212–F214: plano executável, painel e resumo de CI

**14/11/2026 — Desenvolvimento validado; homologação CRM real NÃO autorizada / NO-GO.**

| Entrega | PR | HEAD validada | Merge DEV | CI PR / pós-merge |
| --- | --- | --- | --- | --- |
| F212, plano executável de desbloqueio OP-01 em 5 frentes | [#299](https://github.com/rafhael100/imagine-ai-agents/pull/299) | `7b0ff512d76f9a2e48bd9c9f2b290228e527552a` | `21a95ce83e4ff28d827febdab77acd01e3dfb077` | [43609422027](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43609422027) / [43609511492](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43609511492) |
| F213, painel HTML navegável, paleta Imagine original, paridade inventários | [#300](https://github.com/rafhael100/imagine-ai-agents/pull/300) | `a100fec7cdec089651d6c8915cf6d399eb035e7b` | `0a4c393f249abcd8c6b3ec72cdebf286cd7d1458` | [43609926838](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43609926838) / [43610007078](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43610007078) |
| F214, relatório Markdown de bloqueios no job summary do GitHub Actions | [#301](https://github.com/rafhael100/imagine-ai-agents/pull/301) | `a1b0ea5a9690229e7dd369cb7afc89dc2f487762` | `986df65517a1ada86acc3626606195e653881289` | [43610163653](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43610163653) / [43610242488](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43610242488) |

As seis CIs foram conferidas como `completed/success` nas revisões indicadas. A suíte de desenvolvimento preserva **879 testes PHP / lint 110**, os contratos Python/Node e o branding F108. Sem custos ou serviços contratados.

## Entregas observáveis

- `python3 tools/plan_op01_release.py --check` falha se o pacote estiver alterado ou se um gate/caso real for artificialmente aprovado. `--format markdown` produz os cinco blocos com responsáveis, dependências, evidências e cenários.
- `examples/f213-op01-plano.html` apresenta uma navegação leve pelo caminho crítico no navegador, sem rede, scripts, formulários, credenciais, banco ou API.
- O GitHub Actions publica automaticamente o mesmo plano verificado no resumo do job, após gate offline, sem gravar em repositório ou liberar operação.

## Situação real e próximos passos para finalização

**O projeto NÃO está finalizado em produção.** A [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT**. O pacote registra **14 gates ABERTO, 7 decisões pendentes e 13 cenários reais NAO_EXECUTADO**. Os ensaios F208–F214 não constituem autenticação de serviço, ACL de empresa, RLS, LGPD ou homologação. Gates **R01-G01..G11, CORE/SEG/AUT/LGPD NO-GO**.

O caminho obrigatório é W01 identidade/autenticação e replay distribuído, W02 ACL no backend CRM por empresa/recurso/campo, W03 contrato bilateral e privacidade, W04 ambiente isolado e os cenários reais com revisões independentes, W05 autorização expressa de piloto e sua avaliação. Produção requer uma aprovação distinta posterior.

**Sem deploy, VPS, dados de clientes, pagamentos, tokens, novas permissões ou alteração de `main`.** A branch `main` permaneceu em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. CI verde significa apenas que o pacote, o painel e os testes locais estão consistentes, nunca GO.
