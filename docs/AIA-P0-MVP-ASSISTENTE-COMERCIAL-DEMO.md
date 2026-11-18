# MVP Imagine Agentes — Assistente Comercial demonstrável

**16/11/2026 — protótipo offline / NO-GO para clientes e dados reais**

Abra `examples/p0-assistente-comercial-demo.html` diretamente no navegador. É um arquivo HTML **autônomo**, com JavaScript local e CSP que proíbe qualquer conexão de rede. Não exige PHP, pacote, CDN, armazenamento do navegador, login, servidor, publicação ou provedores de IA.

O produto **não está implantado**. A página reúne cinco áreas navegáveis: visão geral, agentes, execução simulada, central de aprovações e integrações. O funil da metodologia Imagine **Visitas → Leads → Clientes** aceita visitas semanais, conversão em leads e taxa de fechamento entre 0% e 100%, calcula resultados ilustrativos e nunca promete desempenho comercial. O Assistente Comercial recebe um dos três leads fictícios e apresenta uma recomendação pré-programada por estágio, sem IA generativa, mensagens, escrita ou consulta CRM. As aprovações modificam exclusivamente objetos temporários em memória; atualizar a página apaga tudo.

A aparência utiliza a marca tipográfica Imagine com referência laranja `#F9941D`; recursos visuais não dependem de arquivos externos. A implementação não altera nem substitui o laboratório existente `f108-laboratorio-navegavel.html`, que continua como suíte anterior.

## Testes

`python3 tools/check_p0_sales_demo.py` deve verificar: cinco áreas e controles, CSP com `connect-src 'none'` e `form-action 'none'`, inexistência de fetch/XHR/WebSocket/cookies/localStorage/innerHTML ou scripts de terceiros, sintaxe JavaScript real (Node VM) e cenários de cálculo, limites, sugestões e identificadores não cadastrados, incluindo `__proto__`.

## Próxima entrega

Adicionar ao Portal Imagine um menu e um contrato de abertura em ambiente de homologação, preservando login/MFA e identidade visual; manter integração real com CRM e n8n desligada até os critérios de autorização. Definir controlador e rotas de negócio somente após os pareceres CORE/SEG/IAM/DBA/LGPD, contrato OP-01 e homologação.

**Este protótipo não equivale à conexão operacional nem ativa cobrança, IA, automação, SQL ou entrega aos clientes.**
