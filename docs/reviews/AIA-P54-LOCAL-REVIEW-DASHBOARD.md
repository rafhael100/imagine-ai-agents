# AIA-P54 — Demonstração navegável da fila de propostas, somente localhost

16/11/2026. Camada de apresentação sobre o contrato de leitura P53 (#433).

## Visualização

examples/p54-review-queue-local.php fornece uma demonstração responsiva de leitura, com identidade visual Imagine e quatro filtros: Todas, Revisão, Tarefas CRM e Avisos. Os dados são exclusivamente sintéticos e fixos, formatados pelo ReviewQueueReadModel real da P53. O painel apresenta três indicadores, lista de propostas, valores previstos e limiares das regras, sem botões de aprovação, edição, execução, despacho, cobrança ou conexão a serviços.

Para testar em uma máquina PHP habilitada, **somente** com bind loopback:

\`\`\`bash
php -S 127.0.0.1:18754 -t examples
\`\`\`

Abrir no próprio computador: http://127.0.0.1:18754/p54-review-queue-local.php. Não há URL pública, DNS ou implantação no servidor Leaseweb. A página recusa requisições fora do servidor embutido PHP e da origem local de loopback, aceita apenas GET e filtros em lista fechada, aplica CSP bloqueando carregamento de recursos externos e scripts, e desabilita cache e iframe. Sem bibliotecas externas, imagens ou JavaScript.

## Regressão

O teste em CI inicia o servidor PHP em porta aleatória ligada a 127.0.0.1, valida HTTP 200, três rascunhos fictícios, filtros individuais, rejeição de POST/ações e filtros inválidos, ausência de botões/scripts/URLs externas, CSP e resposta de negação ao invocar o script como CLI. A CI herdada mantém P13–P53 e o n8n oficial/PG17 como segundo job independente.

A demonstração é **apenas visual** e não autentica revisores, processa dados de clientes, grava banco, abre um n8n real ou constitui homologação do Portal/CRM. Para publicar no ambiente Imagine serão necessários IAM verdadeiro, UX vinculada ao Portal, endpoint protegido, políticas de revisão e CSRF, isolamento por empresa, revisão de segurança e aceite humano.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
