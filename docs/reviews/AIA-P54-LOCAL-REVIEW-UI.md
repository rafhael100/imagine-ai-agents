# AIA-P54 — Demonstração navegável da fila de propostas (localhost)

16/11/2026. Continuação de P53 (#433). Páginas e dados estritamente sintéticos, sem serviço externo.

## Como visualizar localmente

No checkout da branch (PHP 8.4+):

\`\`\`bash
php -S 127.0.0.1:18754 -t examples
\`\`\`

Depois abrir o endereço local \`http://127.0.0.1:18754/p54-review-queue-local.php\`.

A tela responsiva utiliza a identidade visual Imagine (referência laranja #F9941D), apresenta três propostas fictícias, três indicadores e filtros navegáveis (Todas, Revisão, Tarefas CRM, Avisos). Os dados são fixtures PHP fechadas, passaram pelo contrato de leitura P53 e nunca acessam banco real ou serviços.

## Isolamento e segurança

O script aceita somente o SAPI do servidor PHP embutido, origem e servidor no loopback, requisições GET e a query allowlisted \`kind\`. POST/PUT/DELETE e parâmetros desconhecidos respondem com negação genérica. Não existem forms, buttons, scripts, chamadas externas, APIs, aprovações ou gravações. Headers de CSP restritivo, X-Frame-Options DENY, X-Content-Type-Options nosniff, Cache-Control no-store e meta noindex reforçam o caráter de demonstração.

O teste Python de CI sobe o servidor PHP em uma porta efêmera 127.0.0.1, valida tela, filtros, headers, ausência de controles de execução, bloqueio de métodos e queries, e o encerra. Não há URL pública, serviço persistente, deploy na VPS nem instalação em Docker.

## Próxima etapa

Acoplar uma rota de leitura de teste à fila persistida P51 sem expor dados reais e sem criar qualquer ação de aprovação. Para uma Central utilizável por clientes serão necessários autenticação e autorização do Portal/CRM, auditoria de acesso, CSRF, LGPD, aprovações revisadas e isolamento multiempresa operacional. P54 não é um produto implantado.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
