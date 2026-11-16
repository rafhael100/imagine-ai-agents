# AIA-P32 — Zero consumo de créditos de IA em automações determinísticas

16/11/2026. Continuação da P31 (#394).

Esta entrega valida um requisito de produto: **o motor de automações não deve reservar nem descontar qualquer crédito de IA para tarefas determinísticas**. O teste conecta, exclusivamente em PostgreSQL16 CI descartável, as classes reais de laboratório da pilha P31 e o ledger de créditos P8/P11, evitando testes isolados que poderiam não capturar efeitos indiretos.

São registrados, reservados, confirmados e auditados **24 eventos NO_AI**, 12 de uma empresa com 100 unidades fictícias e 12 de uma empresa com **saldo de IA igual a zero**. Em todas as etapas o snapshot de créditos (allowance, held, spent, available, frozen) deve ser idêntico ao inicial e a tabela `aia_lab_credit_requests_p8` deve permanecer vazia. Uma operação semântica distinta é classificada somente como `AI_REVIEW_REQUIRED`, sem reservar unidades nem invocar modelo. O roteador original não aceita que um atributo de payload converta uma atividade determinística em gasto de IA.

**Limites:** créditos e saldos são técnicos, nunca saldo comercial verdadeiro; eventos são mock, ACK é local, API e n8n não são conectados. O resultado da CI prova comportamento do laboratório, não uma garantia para a futura implantação operacional. Integrações reais precisarão de revisão de IAM, consentimento, aprovação de despesas, auditoria do ledger e monitoramento de provedores antes de habilitar cobranças.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
