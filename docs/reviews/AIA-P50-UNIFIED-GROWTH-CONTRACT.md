# AIA-P50 — Contrato unificado com modos de cálculo explicitamente diferentes

16/11/2026. Continuação de P49 (#425).

A P48 implementa o cálculo SEMANAL do workflow nativo n8n RC07 (120 visitas/semana, 10% tornam-se leads e 20% dos leads tornam-se clientes → 120 visitas, 12 leads, 2 clientes projetados). A P49 reproduz os três cenários ilustrativos MENSAIS do site Imagine Comercial F331–F340 com quatro semanas por mês (120 visitas/semana → 480/mês; cenário referência 19 leads, 4 clientes projetados). Esses números não são intercambiáveis.

Novo UnifiedGrowthProjectionService implementa uma única interface de domínio com discriminador obrigatório mode:
- n8n_weekly: campos weekly_visits, lead_rate_bp, client_rate_bp, saída period=week e taxas explícitas, no máximo 100 milhões de visitas.
- comercial_monthly: campos weekly_visits, scenario conservador/referência/otimista, saída period=illustrative_4_week_month, weeks_per_month=4, no máximo 100 mil visitas semanais.

Um schema JSON em contracts/aia-growth-projection-v1.schema.json documenta ambas as alternativas e proíbe campos extras. O serviço PHP também exige referências sintéticas, tipos inteiros exatos, limites e ordem canônica dos campos. Nenhuma entrada exige nome ou e-mail. Todas as respostas indicam NO_AI, LAB_PREVIEW_ONLY, assumption_only=true, actual_performance_data=false, real_results_guaranteed=false, operational_authorization=false e zero créditos, tokens ou chamadas de IA.

A prévia por linha de comando funciona sem rede, autenticação, arquivos persistentes nem integrações:

    php tools/preview_growth_projection.php n8n_weekly 120 1000 2000
    php tools/preview_growth_projection.php comercial_monthly 120 reference

A CI executa testes de alteração maliciosa do contrato (campos de modelos diferentes, tipos errados, limites, identidades, operações) e prova que a CLI recusa modos incorretos sem emitir resposta potencialmente interpretada como sucesso. Preserva a paridade com o módulo JavaScript original do Comercial, os testes de segurança P13–P49 e o workflow n8n PG17 independente.

Limite: ainda é uma biblioteca e CLI de laboratório. Não expõe endpoint autenticado do Portal, não confirma clientes reais, não coleta leads, não é previsão de vendas nem executa modelos. Os dois modos jamais são selecionados automaticamente pela IA. Antes de integrar ao CRM, Portal ou site será preciso a política real de privacidade, consentimento, autorização, API e observabilidade. Nenhum merge, deploy, cobrança ou uso pago ocorreu.

DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.
