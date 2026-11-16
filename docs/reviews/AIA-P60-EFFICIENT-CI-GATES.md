# AIA-P60 — CI consolidada para reduzir execuções redundantes

16/11/2026. Incremento de infraestrutura de desenvolvimento sobre P59 (#443). Não é uma aprovação para publicar serviços.

## Problema observado

As branches empilhadas anteriores rodavam a regressão de segurança do AI Agents, a demonstração PostgreSQL16 e o n8n oficial PostgreSQL17 em vários pushes intermediários, antes de a PR estar pronta. Em uma sequência rápida de commits, testes sobre estados já substituídos permaneciam em fila ou consumindo tempo de runner. Isso atrasa o feedback da versão que realmente será revisada.

## Mudança concreta

\`.github/workflows/ci-p60-efficient-gates.yml\` herda **todos** os testes da P59 e os três jobs existentes, mas agora roda automaticamente **somente na abertura ou atualização da PR contra a branch P59**, com disparo manual \`workflow_dispatch\` opcional.

O grupo \`concurrency\` é calculado a partir da branch de origem e \`cancel-in-progress: true\`: um novo commit na mesma PR cancela a execução obsoleta do workflow P60 antes da validação da versão mais recente. O workflow preserva \`permissions: contents: read\`, checkout fixado no SHA de uma ação verificada e \`persist-credentials: false\`. Não adiciona aprovação de PR nem permissão de merge.

A verificação estática \`tools/check_p60_ci_gates.py\` testa o próprio workflow e dez mutações adversariais, incluindo reintrodução de push, perda de concorrência, permissão de escrita, evento privilegiado \`pull_request_target\`, checkout não fixado, persistência de token e remoção indevida de jobs/testes P59.

## Limitações e escopo

- O controle de cancelamento afeta **apenas o novo workflow P60**. Não altera nem remove as workflows de PRs anteriores, que continuam como evidência histórica.
- Ele não reduz a necessidade de executar a suíte completa numa candidata final: a regressão Agents, o browser PostgreSQL16 e o n8n PostgreSQL17 continuam presentes.
- Não há promessa de redução de custo mensurável, nem de plano de GitHub Actions, pois esses dados dependem de conta, runner e políticas do GitHub.
- A infraestrutura continua em laboratório, sem acesso à VPS, n8n de cliente, APIs pagas ou mudanças em \`main\`.

Uma integração produtiva ainda requer vincular identidades e permissões reais CRM/Portal, revisão AppSec/DBA e autorização operacional para migrações, testes na VPS e execução de ações comerciais.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
