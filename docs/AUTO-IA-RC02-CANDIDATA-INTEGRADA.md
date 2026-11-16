# AUTO-IA-RC02 — Candidata integrada de automações e n8n (NO-GO)

**Diretriz:** [issue #339](https://github.com/rafhael100/imagine-ai-agents/issues/339) — **Automação primeiro, IA por exceção**.

Esta revisão reúne AUTO-IA-01..12 sob a mesma HEAD, preservando a base [AUTO-IA-RC01 #346](https://github.com/rafhael100/imagine-ai-agents/pull/346) e as etapas incrementais:
- [AUTO-IA-07 #349](https://github.com/rafhael100/imagine-ai-agents/pull/349): PostgreSQL 16, FORCE RLS, reserva de orçamento atômica com concorrência e uso incerto.
- [AUTO-IA-08 #351](https://github.com/rafhael100/imagine-ai-agents/pull/351): inbox apenas NO_AI, nonce/evento duráveis e isolados.
- [AUTO-IA-09 #353](https://github.com/rafhael100/imagine-ai-agents/pull/353): validador de assinatura HMAC no PHP com identidade e janela de validade fictícias.
- [AUTO-IA-10 #355](https://github.com/rafhael100/imagine-ai-agents/pull/355): ingresso PostgreSQL exigindo assinatura de laboratório e bloqueio do caminho não assinado.
- [AUTO-IA-11 #356](https://github.com/rafhael100/imagine-ai-agents/pull/356): pacote de pré-instalação n8n privado (configuração apenas), PostgreSQL próprio, porta loopback e inventário somente leitura.
- [AUTO-IA-12 #357](https://github.com/rafhael100/imagine-ai-agents/pull/357): workflow n8n Manual Trigger + Code, inativo, Visitas→Leads→Clientes sem IA e sem rede.

## Prova integrada
O workflow \`auto-ia-rc02-integrated.yml\` roda, no mesmo SHA de revisão:
1. Regressões puras PHP/Python da decisão NO_AI e orçamento sintético;
2. PostgreSQL descartável, isolamento por empresa e testes concorrentes (cotas e inbox);
3. Assinatura PHP→PostgreSQL e rejeição de adulteração ou chave revogada;
4. Inspeção estática do Docker Compose, **sem download ou inicialização n8n**;
5. Execução do cálculo do nó n8n no Node, sem executar o aplicativo n8n;
6. Relatório de bloqueios explicitamente NO-GO.

## Limitações e interdependências
A [PR P7 #352](https://github.com/rafhael100/imagine-ai-agents/pull/352) é **uma frente paralela diferente** da mesma aplicação (outbox, cotas e aprovação comercial). Esta RC02 **não incorpora P7**, nem a validação desta RC autoriza merge com P7: antes disso será necessária análise de sobreposição, contrato comum, integridade R01 e revisão de funcionalidades.

- **Não existe IAM verdadeiro entre CRM, Portal, AI Agents e n8n**. Os papéis do PostgreSQL são fictícios, sem prova de titularidade de empresa ou uso de credenciais reais.
- **O n8n não foi instalado na VPS**, a imagem/digest real não foi validada, e a configuração não foi executada ou verificada no host.
- **Não há consumo de IA, token de API, faturamento, débito comercial, execução de workflow no n8n ou aprovação humana efetiva.**
- Toda revisão de segurança AppSec/DBA/LGPD, licenciamento, backup/restore, observabilidade e rollback permanece em aberto. Testes CI de laboratório não substituem homologação em infraestrutura real.

## Próximas ações técnicas (não executadas nesta RC)
1. Revisão independente dos diffs #346 e #352 e harmonização de contratos dos dois ramos.
2. Inventário somente leitura da VPS pelo operador/Codex autorizado, preservando contêineres e portas existentes.
3. Escolha e fixação por digest das imagens n8n/PG, custódia real dos segredos, HTTPS/MFA, revisão de licença e backups.
4. Implementar bridge verdadeira com contrato versionado CRM/Portal → AI Agents → n8n, IAM empresarial e callbacks privados; exigir provas negativas de cross-tenant e replay durável.
5. Instalação privada na VPS e validação funcional somente depois de pré-condições e revisão independente, sem habilitar endpoints de IA paga.

**STATUS: PR DRAFT — NO-GO PARA MERGE NA MAIN, DEPLOY, DADOS REAIS, COBRANÇA OU CONSUMO DE IA.**
