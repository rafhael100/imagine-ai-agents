# AIA-P47 — Regressão conjunta de laboratório com n8n oficial e PostgreSQL17

16/11/2026. Entrega empilhada sobre P46 (#422) e compatibilidade somente com a candidata RC07 (#413), sem mesclar nem copiar o código da RC07.

A CI passa a ter dois jobs independentes sob a mesma branch. O primeiro executa a pilha de segurança do AI Agents P13–P46, incluindo autenticação mTLS sintética do CRM/Portal original, registro e revogação do grant de owner, stage atômico, assinatura do executor e auditoria da proveniência em PostgreSQL16 descartável. O segundo faz checkout somente leitura do SHA RC07 cc55e568bc2994574b85b8b48969ac85fe2ec543 e inicia contêineres oficiais de n8n e PostgreSQL17 em rede interna Docker, sem portas publicadas, com segredos efêmeros somente em arquivos e usuário de banco sem superpoderes.

O segundo job reaproveita os scripts originais da RC07 e executa um workflow nativo e manual com nós Set e Trigger, sem Code, Webhook, HTTP Request ou APIs de IA. O resultado verificado é 120 visitas, 12 leads e 2 clientes projetados, com zero tokens e débito de créditos. Todos os contêineres e volumes do job são eliminados após o ensaio.

Limite: os dois jobs não trocam dados nem mensagens. O evento P46 pertence a um tenant de testes distinto do workflow RC07. O teste comprova compatibilidade de diretrizes de segurança e funcionamento independente, NÃO integração S2S operacional, ACK verdadeiro, identidade/autorização real de empresa, consentimento ou homologação na VPS.

Faltam API real de emissão e revogação de decisões CRM, confiança operacional de identidades, separação de permissões, n8n privado autorizado na VPS, rotinas de backup, rollback, gestão de segredos, revisão AppSec/DBA/LGPD e aceite humano. Nenhum merge, deploy, cobrança, dados reais ou chamada paga de IA foi efetuado.

DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.
