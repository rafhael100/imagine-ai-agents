# CENTRAL-AUTO-P12 — Restaurar n8n completo em um segundo projeto Docker descartável

Continuação das validações [RC07 #413](https://github.com/rafhael100/imagine-ai-agents/pull/413), [P10 #415](https://github.com/rafhael100/imagine-ai-agents/pull/415) e [HOM-05 issue #414](https://github.com/rafhael100/imagine-ai-agents/issues/414).

## O avanço concreto
O teste P10 recuperou **somente o banco** com a mesma chave e volume do n8n no runner. P12 amplia a prova para a restauração da base PostgreSQL17, volume Docker `.n8n` e chave de criptografia **em outro projeto Compose inteiramente separado**, ainda no mesmo GitHub Actions e com dados inteiramente sintéticos.

Sequência executada por `check_full_ephemeral_restore.sh` após a importação/execução P9 e recuperação de banco P10:
1. Confirma que o n8n principal do projeto de CI foi parado para não disputar o Task Broker 5679, que PostgreSQL permanece saudável e que há exatamente um workflow fictício e **nenhuma credencial de terceiros** na base.
2. Gera `pg_dump -Fc` e um arquivo TAR do volume n8n_data usando imagem oficial PostgreSQL17 fixada, sem rede externa. Copia a chave de criptografia de laboratório apenas para um diretório temporário do runner e gera **novas senhas de banco para o ambiente restaurado**.
3. Sobe **somente o banco** em `imagine-n8n-p12-recovery-ci`, um novo projeto Docker com outras redes e volumes, mantendo o Compose privado sem portas do host. Restaura o banco e o arquivo TAR no volume do segundo projeto, com a mesma chave fictícia preservada.
4. Inicia o n8n oficial no novo projeto, confirma readiness, banco, papel SQL limitado e workflow existente. Para apenas o n8n restaurado; usa CLI isolado para exportar e reexecutar o workflow nativo, verificando 120 visitas → 12 leads → 2 clientes e campos zero-IA/zero-créditos.
5. Remove **exclusivamente** projeto e arquivos temporários de restauração CI; o job principal remove seu próprio projeto descartável ao terminar.

## Condições que a prova NÃO cobre
- A recuperação continua dentro de **um único runner**, não de outra VPS, região ou provedor. Nenhum backup é transferido para storage externo nem criptografado em trânsito/repouso por um sistema operacional de backup; o diretório do runner é temporário.
- A base é **sintética e sem credenciais**. Preservar a mesma chave de laboratório verifica continuidade da configuração, mas **não comprova decriptação de credenciais operacionais** nem teste de perda e rotação de chaves.
- Não há dados de clientes, login do administrador real, IAM CRM/Portal/AI Agents funcional, configuração de licença/HTTPS/MFA, backup real da Leaseweb, nem qualquer serviço pago.
- Os comandos de criação e exclusão de volume são **somente para o projeto Docker CI validado por nome**, nunca devem ser copiados para hosts de clientes ou a VPS operacional.

**DRAFT / NO-GO / SEM MERGE, DEPLOY, API PAGA, CREDENCIAIS REAIS OU VPS MODIFICADA.**
