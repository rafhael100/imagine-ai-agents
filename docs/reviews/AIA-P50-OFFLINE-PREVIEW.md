# AIA-P50 — Prévia funcional de regras sem IA por linha de comando

16/11/2026. Complemento da P49 (#426), sem banco, autenticação ou interface HTTP.

## Execução local

No checkout da PR (PHP 8.1+ instalado):

\`\`\`bash
php tools/preview_noai_automation.php --fixture
\`\`\`

A demonstração imprime um único JSON com visitas 120, leads projetados 12 e clientes projetados 2 e duas propostas de ação para revisão, mas nenhuma operação executada. O resultado contém explicitamente decision=NO_GO, source=SYNTHETIC_OFFLINE_ONLY e operational_authorization=false.

Há também um modo com input JSON canônico via STDIN, limitado a 8192 bytes e apenas dois objetos de entrada: command (AIA_DETERMINISTIC_COMMAND_V1) e ruleset (AIA_RULESET_V1). O programa revalida as mesmas regras fechadas de P48/P49, incluindo operação e escopo tenant sintéticos. Dados não canônicos, chaves duplicadas, JSON malformado, campos extras e operações não autorizadas são recusados com o código de retorno 2 e um erro sanitizado, sem imprimir o payload. O modo fixture e o modo stdin não realizam requests de rede, escritas de arquivos, debitos, efeitos no CRM, chamadas a IA nem mudanças no n8n.

## CI e limites

O teste por subprocesso valida modo fixture, entradas JSON canônicas e determinismo, redirecionamento de credenciais ausente, tentativa de chave JSON duplicada, extração de campos, cross-tenant, operação suspeita, corpo maior que 8192 bytes, saída sem propostas e relatório sanitizado. A CI mantém as regressões de P13–P49 e o segundo job com n8n oficial PostgreSQL17 da RC07, mas **não cria endpoint ou roteamento operacional**.

Uma linha de comando sem IAM não deve ser disponibilizada a clientes como serviço. Um futuro adaptador de interface precisa de autenticação CRM/Portal verdadeira, decisão do proprietário, idempotência e revisão de permissões antes de qualquer função externa.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
