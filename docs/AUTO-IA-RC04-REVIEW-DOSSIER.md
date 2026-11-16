# AUTO-IA-RC04 — Revisão consolidada do motor de automações com execução oficial n8n

**Diretriz:** [#339](https://github.com/rafhael100/imagine-ai-agents/issues/339). **Base de convergência:** [AUTO-IA-RC03 #371](https://github.com/rafhael100/imagine-ai-agents/pull/371).

## Evidências verificadas
- [AUTO-IA-13 #375](https://github.com/rafhael100/imagine-ai-agents/pull/375): política fechada de workflows manuais sem IA e hash exato dos scripts.
- [AUTO-IA-14 #377](https://github.com/rafhael100/imagine-ai-agents/pull/377): **n8n oficial 2.47.7 realmente executou** workflow de funil em contêiner CI sem rede e sem portas; imagem fixada em SHA-256; execução [43869429731](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43869429731) aprovada.
- [AUTO-IA-15 #382](https://github.com/rafhael100/imagine-ai-agents/pull/382): validação do JSON **real** da execução, resultados 120 visitas / 12 leads / 2 clientes, flags zero de IA e créditos, controle de import/export e negativos; [GitHub Actions 43870748304](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43870748304) aprovada na HEAD 51207ddf4f71ee1fc2bdfec21270ef206bc9c678.
- Esta RC04 cria um verificador de integridade que fixa **sete hashes Git blob** dos arquivos testados, exige correspondência exata de cada implementação e prova negações contra alterações não revisadas. A RC não modifica o código testado: apenas acrescenta documentação, verificador e workflow de evidências.

## O que está pronto e o que falta
O fluxo é inativo e manual. O uso de IA é zero **no cenário isolado** por não existir conexão de rede com fornecedores e nem chaves de pagamento. As flags de contagem são emitidas pelo próprio código, não são logs oficiais de provedor. CLI, banco interno n8n e import/export foram comprovados apenas dentro da CI.

**NO-GO operacional:** não existe n8n instalado na VPS Leaseweb, IAM confiável CRM/Portal/Agents, política de credenciais e rotação, identidade empresarial vinculada à assinatura em produção, rede privada homologada, consentimento/retenção LGPD, backups/restore reais nem autorização de efeito comercial. Nenhuma publicação ou cobrança decorre deste pacote.

## Preparação para homologação controlada
1. Revisão independente da cadeia AUTO-IA-RC03, P12/P26 em paralelo e dos contratos CRM e Portal, com rejeição de permissões implícitas.
2. Inventário **somente leitura** da VPS: serviços existentes, recursos, conflitos de portas e isolamento de redes Docker.
3. Revisão de licença n8n, imagens por digest, acesso administrativo seguro, segredos, backup/restore e recuperação, sem fornecer editor n8n a clientes.
4. Somente com gates aprovados, instalar n8n privado e ensaiar o fluxo com IAM real e serviços autorizados; proibir qualquer saída paga direta do orquestrador.

**DECISÃO: PR DRAFT / NO-GO PARA MERGE, DEPLOY, API PAGA, CONSUMO DE CRÉDITOS OU DADOS DE CLIENTES. VPS INALTERADA.**
