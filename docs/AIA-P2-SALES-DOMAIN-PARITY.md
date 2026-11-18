# Imagine Agentes P2 — domínio PHP funcional, isolado e em paridade com a UI comercial

**16/11/2026 — recorte técnico sem autoridade. DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**

Avança a bancada P1 do Assistente Comercial com um **domínio PHP executável** `src/MockPilot/MockSalesWorkbenchSession.php` e seis verificações de paridade PHP/JavaScript da página. A entidade mantém somente estado em memória de um único contexto `SYNTHETIC_TENANT_...`; essa string é uma **etiqueta fictícia de partição, não identidade comprovada de empresa**.

## Capacidades já executáveis

- Cadastro de até cinco agentes rascunho (`sales` e `analysis`) com nome validado, nomes duplicados recusados, IDs sintéticos e sem persistência.
- Execução determinística de até oito recomendações com três leads fictícios. `analysis` gera somente resumo; `sales` pode criar item local de revisão quando o objetivo é `next`.
- Decisões `APROVADO_EM_ENSAIO` e `REJEITADO_EM_ENSAIO` são terminais e idempotência de negação bloqueia reversões ou duplicidade. Não alteram CRM, lead, tarefa, n8n, e-mail ou mensageria.
- `snapshot()` expõe o estado sintético limitado, contagens, rascunhos e aprovações locais, com `external_calls=0`, `persistent_writes=0`, `authority=NONE` e `operational_decision=NO_GO`.
- Nenhum provider de modelo é invocado, nenhuma cobrança é criada, nenhuma chave ou payload pessoal é processado.

## Regressões e correspondência

`php tools/check_p2_sales_domain.php` cobre IDs, nomes hostis, duplicações, limite de drafts/runs, tentativa de aprovação inválida, segundo voto, isolamento entre instâncias e ausência de efeito persistente.

`node tools/check_p2_sales_parity.js` executa seis casos de sugestão do PHP e compara com `advice()` e `simulatedRun()` **extraídos da página HTML real**, além de conferir rascunhos, modelo `analysis` sem aprovação e esgotamento de cota.

A CI mantém também os testes P0 de CSP e P1 de jornadas de DOM.

**Limites:** não há backend HTTP, workers, RLS, IAM de usuário, autorização CRM ou condição de produção. Não ligar esse domínio em endpoints antes de revisar autenticação, armazenamento durável, estado transacional, quota server-side, filas e aprovações por usuário e empresa. O CRM OP-01 [#57](https://github.com/rafhael100/imagine-crm/pull/57) e a prova PG16 [#630](https://github.com/rafhael100/imagine-crm/pull/630) permanecem independentes e sem leitura operacional.
