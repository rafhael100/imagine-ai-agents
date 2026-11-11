# R01/EVID-41 — Cinco entregas técnicas verificadas de 11/11/2026

**Escopo:** `rafhael100/imagine-ai-agents`, branch `dev/ft01-aia-core-mocks-2026-11-06`. **Tudo sintético, local e sem integração CRM real. Operação: NO-GO.**

## Sequência de PRs realmente integrados

| Incremento | PR | SHA do squash na dev | CI pós-merge exata | Gate |
| --- | --- | --- | --- | --- |
| F184: segunda observação interativa, descartável e sem aprovação | [#255](https://github.com/rafhael100/imagine-ai-agents/pull/255) | `88a31ad0618f0a0051de88eb523cdcb062e3c29a` | [#43009103944](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43009103944) **success** | 776 PHP / lint 97 + Python/DOM |
| F185: configurador de rascunhos de três agentes fictícios | [#256](https://github.com/rafhael100/imagine-ai-agents/pull/256) | `bebc262f16aea653a6e615f314a277c103b6018c` | [#43010448505](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43010448505) **success** | 776 PHP / lint 97 + Python/DOM |
| F186: lista local de até cinco rascunhos, sem armazenamento | [#257](https://github.com/rafhael100/imagine-ai-agents/pull/257) | `c5eeedd441ff5c831be4b8309b8e42b7c1b1732b` | [#43011660965](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43011660965) **success** | 776 PHP / lint 97 + Python/DOM |
| SEC-58: relógio da fila só avança após mutação validada | [#258](https://github.com/rafhael100/imagine-ai-agents/pull/258) | `368b6158d5c4632c1da175c970012f5ddff814fd` | [#43012704139](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43012704139) **success** | 792 PHP / lint 98 + Python/DOM |
| SEC-59: impedir `new Step()` direto durante callback | [#259](https://github.com/rafhael100/imagine-ai-agents/pull/259) | `08b17e5139ab1d638410100771a424d83ad76481` | [#43013609438](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43013609438) **success** | **800 PHP / lint 99 + Python/DOM** |

A última CI pós-merge corresponde exatamente ao HEAD `08b17e5139ab1d638410100771a424d83ad76481`, não a um HEAD de PR anterior. O incremento foi feito por PRs supervisionados com aprovação automática **apenas do gate offline**; nenhum resultado equivale a revisão independente de segurança nem autoriza operação.

## Progresso técnico concreto

**UX de produto, sem simular publicação:** a aba de supervisão F183 permite explorar concordância/divergência de segunda observação hipotética sem apagar a incerteza; o catálogo F108 oferece três templates fechados com nomes validados e um vínculo para cenários já existentes; a coleção local aceita até 5 rascunhos, replay idempotente, exclusão e reabertura, sem usar banco, storage do navegador, prompt externo ou APIs. Os testes Python F184/F185/F186 são obrigatórios no gate; o smoke Node executa os fluxos de usuário quando o runtime já está disponível.

**Correções de segurança e confiabilidade do núcleo PHP:** SEC-58 impede negação de serviço por envenenamento do relógio com operações que não alteraram estado, mantendo a proteção contra rollback e a confirmação de expirações reais. São 16 regressões U89-01..16. SEC-59 exige guarda sticky até no construtor `Step` chamado diretamente com o mesmo Run, incluindo 8 regressões U90-01..08; inspeção read-only e novos Runs legítimos continuam possíveis. Nenhum código de agente real, ferramenta de CRM, permissão operacional ou deploy foi ativado.

**Limites:** `SYNTHETIC_QUEUE_SUPERVISION_V1` e a UI F108 ainda trabalham com cenários ficcionais. O relógio da fila recebe um `now` de teste, não fonte confiável. O bloqueio de callbacks vale só no mesmo processo PHP, não é sandbox nem lock distribuído. A coleção de rascunhos não é persistente, multiusuário ou segura para dados pessoais reais.

## Gate e critérios de liberação pendentes

- **800 testes PHP**, **99 arquivos PHP com lint**, verificadores Python offline e smoke DOM Node aprovados na CI #43013609438, sem instalação de novas dependências ou acesso de rede ao CRM.
- `main` permaneceu no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; somente a branch `dev` recebeu as integrações.
- O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT e não mesclado**. Os **13 cenários reais de OP-01** continuam `NAO_EXECUTADO`; R01-G01..G11 e CRM CORE/SEG/AUT permanecem **ABERTOS/NO-GO**. Não é aceitável inferir GO com base em testes sintéticos.
- Antes de integração real: fonte de identidade autenticada serviço-serviço, autorização do backend por empresa/recurso/propósito/campo, decisão de dados LGPD, provas de revogação/replay durável, segregação de revisores, homologação bilateral, revisão independente de AppSec e infraestrutura, mecanismos de auditoria/rollback e autorização explícita de piloto com limites de despesas.

Esta evidência complementa a [EVID-40](AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md) e não a substitui. Os resultados não afirmam que o produto SaaS esteja finalizado, disponível a clientes ou apto a receber dados reais.
