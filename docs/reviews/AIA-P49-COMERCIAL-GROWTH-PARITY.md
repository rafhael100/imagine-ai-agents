# AIA-P49 — Paridade funcional com a calculadora Visitas → Leads → Clientes do Imagine Comercial

16/11/2026. Continuação da P48 (#424) e comparação do código original do site Imagine Comercial F331–F340 (candidato [PR #429](https://github.com/rafhael100/imagine-comercial/pull/429), SHA c4264b4f9a7e55008cf156474549b191cc32caa2).

A P48 implementou o funil SEMANAL do n8n, que calcula 120 visitas semanais, 12 leads e 2 clientes com 10% e 20%. A calculadora pública proposta pelo Imagine Comercial é DIFERENTE: ela usa mês ilustrativo de quatro semanas, cenários hipotéticos (conservador 2%→10%, referência 4%→20%, otimista 6%→30%) e arredonda separadamente os leads e os clientes previstos com base em valores esperados não arredondados. Misturar os dois modelos produziria resultados incorretos.

A P49 adiciona, SEM alterar P48, um novo projetor PHP de cenários mensais com a mesma matemática do módulo original growth-potential-calculator.mjs do Comercial e uma função de planejamento reverso: dado um objetivo hipotético de clientes, encontra por busca binária o menor número de visitas semanais dentro do teto de 100 mil. Os valores são projeções didáticas, NÃO médias de mercado ou promessas de vendas.

A CI faz checkout do SHA do Comercial em pasta separada e importa o **módulo JS original**, sem replicar a lógica em um teste JS fabricado. Compara mais de 120 casos PHP↔JS: 31 volumes de visitas × 3 cenários e 11 metas reversas × 3 cenários. Executa também os testes P48, a regressão de segurança Agents P13–P46 e o n8n/PG17 oficial RC07 em job separado. Não existe transferência de visitas, leads ou contatos entre serviços.

Exemplo para 100 visitas/semana (400/mês fictício): conservador 8 leads e 1 cliente, referência 16 leads e 3 clientes, otimista 24 leads e 7 clientes. Para 120 visitas/semana (480/mês), os cenários geram 10/1, 19/4 e 29/9. O setor escolhido no Comercial muda apenas a orientação textual, não as taxas.

**Gates:** paridade de cálculo não comprova autorização, métricas reais, CRM integrado nem resultado comercial. Cadastro, consentimento, rastreamento, importação do GA4/GSC, n8n e comunicação operacional precisam de serviços reais e aceite. Nada é implantado na VPS, publicado em DNS ou debitado de créditos.

DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.
