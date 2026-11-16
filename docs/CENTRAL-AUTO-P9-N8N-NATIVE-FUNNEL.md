# CENTRAL-AUTO-P9 — Execução nativa n8n/PG com zero nós Code e zero IA

Base [CENTRAL-AUTO-RC06 #407](https://github.com/rafhael100/imagine-ai-agents/pull/407), que já integrou o Compose privado endurecido da [P4 #391](https://github.com/rafhael100/imagine-ai-agents/pull/391) e os ensaios de transporte mTLS da P8.

## Objetivo
Executar o cálculo educativo **Visitas → Leads → Clientes** no **n8n oficial já integrado ao PostgreSQL de runtime não-superusuário**, sem ativar `Code`, `Execute Command`, `SSH`, `HTTP Request`, serviços de IA, credenciais externas ou qualquer conexão com CRM/Portal real.

## Workflow versionado
`infra/n8n-private/workflows/native-funnel-noai.workflow.json`: três nós somente. `Manual Trigger` → `Edit Fields (Set)` com dados fictícios → `Edit Fields (Set)` com expressões matemáticas nativas revisadas. Sem webhook ou triggers automáticos, tags, credenciais e pinData. O workflow é `active: false` por construção.

Os valores são **120 visitas**, **10% de conversão em leads** e **20% de leads em clientes**, produzindo **12 leads e 2 clientes** (arredondamento ilustrativo). Os campos `ai_api_calls=0`, `ai_tokens=0`, `commercial_credits_debited=0` e `operational_authorization=false` são atributos da fixture; ausência de cobrança real no ensaio decorre também de não haver credenciais de provedores nem saída da rede interna.

## Provas em CI
1. Validador fechado dos tipos de nós, nomes, ligações, campos/valores e expressões matemáticas. Testes adversariais tentam introduzir nó Code, webhook, chamada HTTP/IA, credencial, débito de créditos, mudança dos cálculos, dados escondidos e execução ativa.
2. Sobe apenas dois contêineres descartáveis no GitHub Actions a partir de imagens oficiais fixadas: n8n e PostgreSQL16. Runtime SQL sem superuser ou bypass RLS, secrets montados via arquivo, redes Docker `internal: true` e **nenhuma porta do host publicada**.
3. Copia o workflow para o contêiner, importa via CLI do n8n, exporta novamente e compara grafo/código com a versão revisada, usando o mesmo PostgreSQL de runtime.
4. Executa o workflow via CLI e valida diretamente o JSON do resultado retornado pela aplicação oficial: 120→12→2, zero créditos declarados, nenhuma execução extra, nenhum nó externo. O teste falha com resultados divergentes ou alterações inesperadas.

## Limitações e gates
Não é simulação de IA, e sim uso de **regras matemáticas determinísticas**. Não é medidor real de custos de provedor, nem demonstra identidade real, disponibilidade, trilha de auditoria de clientes, qualidade das taxas de conversão ou autorização comercial.

**Os dois contêineres existem somente durante a execução CI**; não há n8n instalado na VPS Leaseweb. O workflow não cria tarefa no CRM, não publica Sites, não gera leads reais e não envolve dados pessoais. A configuração privada do n8n ainda exige ingresso administrativo HTTPS/MFA, licenciamento, egress, IAM serviço-serviço, backup/restore de volumes e chaves e revisão independente. Nenhum merge, deploy, API paga ou despesa é autorizado por testes verdes.

**DRAFT / NO-GO / NO-DEPLOY / NO-CREDITS / NO-CUSTOMER-DATA.**
