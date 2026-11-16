# CENTRAL-AUTO-RC07 — Banco PG17 para n8n oficial e integração dos testes NO_AI/mTLS

**Origem:** [RC06 #407](https://github.com/rafhael100/imagine-ai-agents/pull/407), [P9 #409](https://github.com/rafhael100/imagine-ai-agents/pull/409). Esta candidata reúne três jobs **no mesmo commit** e corrige a falha de segurança estática identificada ao comparar com a candidata paralela [RC06 #405](https://github.com/rafhael100/imagine-ai-agents/pull/405).

## Motivação
O n8n oficial 2.47.7 avisou nos testes que o PostgreSQL16 recebe apenas suporte de compatibilidade e recomenda PostgreSQL17 ou superior. Por isso **a base exclusiva do n8n** usa a imagem oficial `postgres:17` pinada por digest no runtime GitHub Actions. **O laboratório de segurança dos Agentes mantém PostgreSQL16**, sem atualizar os bancos de CRM, Portal ou AI Agents ou executar migrações reais.

## Pipeline integrado
1. **n8n/PG17 oficial:** Compose em dois serviços isolados. Segredos montados em arquivos efêmeros; runtime PG `NOSUPERUSER`/`NOBYPASSRLS`; `internal: true` em ambas as redes e nenhuma porta publicada. Importa/exporta o workflow inativo de três nós nativos, valida as expressões e executa o cálculo 120 visitas/10% leads/20% clientes → 12 leads/2 clientes, sem Code, webhook, HTTP ou credenciais de IA.
2. **mTLS/PG16:** servidor de estados fictícios e classe original Portal, classes originais CRM, certificado mútuo de laboratório, revogação de grant, nonce persistente, concorrência de oito conexões, falha fechada 503→200→409, `pg_dump` e `pg_restore` para outra base, autorização por empresa de fixture. A prova não representa IAM operacional.
3. **NO-GO:** auditorias de segurança independentes, integridade de artefatos, deny-by-default e revisão do workflow. Inclui `check_rc02_release.py` **literalmente extraído da RC06 #405**, que nega host ports explicitamente. O verificador anterior procurava `127.0.0.1:` no texto Compose e podia aprovar equivocadamente porque o healthcheck interno continha esse texto, embora não existisse porta publicada.

## Diferença entre CLI e aplicação n8n
A primeira tentativa P9 de rodar `n8n execute` **ao lado do processo principal dentro do mesmo contêiner** falhou devido à disputa pela porta interna do Task Broker `5679`. Desabilitar `N8N_RUNNERS_ENABLED` somente no comando não resolveu. O fluxo aprovado utiliza parada **apenas do contêiner n8n descartável de CI**, preserva o PostgreSQL em execução e usa um contêiner `docker compose run --rm --no-deps --entrypoint n8n` isolado, com mesma imagem/rede/volume, para executar o CLI sem competição de porta. A execução da aplicação principal/readiness já havia sido testada antes de pará-la.

## Bloqueios operacionais
- Nenhuma das partes está instalada na VPS Leaseweb; não existe acesso administrativo privado HTTPS/MFA, revisão formal de licença n8n ou inventário completo de contêineres e portas do host.
- A integração CRM→Portal→Agentes ainda utiliza emissor, credenciais e empresas sintéticas, não IAM verdadeiro. A linha paralela [P41 #411](https://github.com/rafhael100/imagine-ai-agents/pull/411) já trata do pré-mapeamento UUID↔tenant em CI e **não deve ser duplicada**.
- O Code node continua bloqueado no n8n de homologação; os cálculos nativos demonstram que automações simples não precisam gastar créditos de IA, mas o campo `ai_api_calls=0` é um resultado do próprio workflow, não recibo financeiro externo.
- A implantação real depende de segredos operacionais, ingress aprovado, backup completo banco + chave/volumes n8n, teste de restauração na infraestrutura, controle de egress e auditoria AppSec/DBA/LGPD, sem transferir autorizações por eventuais chaves de laboratório.

**PR DRAFT / NO-GO PARA MERGE MAIN, VPS, CLIENTES REAIS, COBRANÇAS OU APIs PAGAS.**
