# AUTO-IA-11 — Pacote de instalação n8n privado, **não executado**

A preparação de infraestrutura da Imagine avança em paralelo aos contratos seguros de eventos #351, #353, #355. Este diretório é um **modelo de homologação** para validação, nunca instrução de executar antes dos gates.

## Composição proposta
- 1 n8n interno e 1 PostgreSQL **separado dos bancos de CRM, Portal e Sites**;
- imagens fixadas obrigatoriamente por digest SHA-256, com revisão das versões antes da instalação;
- UI apenas em porta loopback local \`127.0.0.1:17990\`; sem DNS e sem acesso remoto público;
- dois segmentos Docker \`internal: true\`, sem saída Internet para API paga de IA;
- dados em volumes isolados, backups e restauração ainda por homologar;
- senha do banco e chave de criptografia obrigatórias por injeção externa (não incluídas no repositório);
- modo de homologação explícito e \`restart: no\` para não reiniciar serviços automaticamente.

A própria imagem n8n deve ser revisada por digest e compatibilidade; não existe download, pull de imagem, acesso à Leaseweb ou validação funcional real. A configuração utiliza variável de ambiente para segredos: **em operação a custódia deve migrar para um mecanismo de segredos com avaliação do risco de exposição via processo/inspeção**, não confiar apenas em variáveis de ambiente. O acesso do administrador exige mecanismo HTTPS/MFA homologado. O loopback pode ser acessado somente após SSH tunnel/proxy privado e avaliação de autenticação; não publicar a porta no exterior.

## O que a validação CI comprova
Somente a sintaxe Docker Compose, portas publicadas apenas em loopback, banco não publicado, redes privadas, digest exigido, ausência de provedor de IA na configuração e script de inventário com leitura exclusivamente informativa. **Nenhum contêiner inicia.**

## Gate de entrada para instalação real na VPS
1. Inspecionar os contêineres existentes, preservando CRM e Sites; confirmar porta 17990 livre e recursos reais.
2. Confirmar licença aplicável n8n Community apenas como orquestrador interno da Imagine.
3. Validar imagem/digest e estratégia de atualização, políticas de segurança, chaves, DNS/TLS privado, MFA e retenção de logs.
4. Testar backup/restore dos volumes, rollback do Compose, monitoração, limites CPU/memória e conectividade somente às APIs privadas permitidas.
5. Aprovar etapa de homologação isolada antes de qualquer execução no servidor; **não conectar API de IA ou credenciais pagas**.
6. Só depois habilitar fluxo de ponta a ponta com autenticação servidor-servidor e autorização independente por empresa.

**Status:** artefato de preparação, **NO-GO** de instalação/publicação nesta PR. Nenhum servidor alterado, nenhum custo novo, nenhum dado real.
