# AUTO-IA-12 — Primeiro workflow n8n importável, sem IA

O arquivo `tools/auto_ia/demo/n8n-noai-demo.workflow.json` é uma demonstração **inativa** que contém somente Manual Trigger e Code Node. Nenhum webhook, chave, provedor de IA, e-mail, serviço externo ou conexão CRM/Portal.

## O que demonstra
Dados fictícios: 120 visitas semanais, conversão 10% em leads e 20% de leads em clientes. O motor calcula **12 leads e aproximadamente 2 clientes** usando JavaScript convencional, mantendo `ai_api_calls=0`, `ai_tokens=0` e `commercial_credits_debited=0`.

A saída declara `operational_authorization=false`, e o fluxo é `active=false`: não existe automação real, tenant autenticado, aprovação comercial ou efeito externo. A biblioteca n8n não é executada no CI; a verificação avalia formato JSON, tipos de nó, conexões, ausência de credenciais e cálculo JavaScript em processo Node isolado, sem instalar contêiner.

## Próxima prova quando a VPS estiver homologada
Importar o arquivo manualmente numa instância privada e conferir o resultado, após revisão das versões de nós e identidade admin. Somente depois substituir fixtures por chamadas privadas a serviços com identidade comprovada, persistência e deduplicação real.

**NO-GO:** sem n8n instalado, sem credenciais, sem consumo pago, sem publicação.
