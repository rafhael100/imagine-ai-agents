# AUTO-IA-14 — Primeira execução do workflow no próprio n8n (CI sem rede)

Esta etapa testa a importação, exportação e execução do workflow de exemplo **dentro da aplicação n8n**, sem instalar ou publicar serviços na VPS Leaseweb.

## Ambiente da prova
- Imagem oficial de release estável `ghcr.io/n8n-io/n8n:2.47.7`, identificada pelo release GitHub de 13/11/2026. Na primeira execução CI a imagem é baixada **somente pelo runner** para registrar o digest real; a aprovação de qualquer instalação futura exigirá pin por digest.
- Container efêmero `--network none`, sem portas publicadas, com limites de recursos, privilégios reduzidos e montagem do JSON do workflow sintético como somente leitura. Nenhuma ligação ao CRM, Portal, n8n da VPS ou serviço de IA.
- Comandos documentados oficialmente pelo n8n: `import:workflow`, `export:workflow` e `execute --id`. Fonte: <https://docs.n8n.io/deploy/host-n8n/configure-n8n/use-the-command-line.md>.
- Verificação do gate AUTO-IA-13 de integridade antes de importar; workflow precisa continuar inativo, somente Manual Trigger e Code, sem credenciais.
- Após o ensaio, o contêiner e diretório temporário são excluídos.

## Limites
Os testes só podem ser executados em GitHub Actions do repositório `rafhael100/imagine-ai-agents`. O runtime n8n roda isolado com SQLite temporário para a importação/execução manual; **não testa PostgreSQL dedicado ao n8n nem interface administrativa/MFA real**. A interface n8n não é publicada, e nenhum fluxo fica ativo depois da execução.

Esse teste não demonstra IAM multiempresa, webhook S2S, conectividade com serviços reais, backups e rollback da Leaseweb ou autorização de uso de IA. O Gate `production_decision=NO_GO` continua válido.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-AI-SPEND.**
