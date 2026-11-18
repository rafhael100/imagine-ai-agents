# AIA-RC63 — Revisão cruzada de contratos CRM/Portal/Central/Agents

16/11/2026 — **candidata DRAFT / NO-GO.**

## Objetivo

A RC62 (#448) integra P14–P61 em laboratório. A CENTRAL-AUTO-P19 (#450) e a P20 (#451) avançaram na entrada privada CRM→Agents e na preparação do n8n. São linhas Git divergentes; testes independentes verdes não demonstram que possam ser mescladas nem executadas juntas de modo seguro.

Esta RC63 herda a RC62 e acrescenta uma revisão de fontes reais com SHA fixado: Central P20 3cf31b78f745d539425b46b754edbe3c48e9d088, CRM P19 cca5055608acff8c767b888b6322df77fe966558 e Portal P20 12d08d2e2af36b8011bc520d55c5e9cdbcccc108. A RC62 SHA 2ea5df5c7691d8594a129922363454af7072f497 precisa ser ancestral da candidata.

## Distinção obrigatória de operações

O receiver P19 aceita somente o evento CRM_LEAD_EVENT_V1, ação lead.created e operação lead.ingest, com canal mTLS e concessões por empresa/operação; retorna AUTO_IA_ACK_V1 com dispatch DISABLED.

O owner fictício da RC62 trata run.status.project; seu motor determinístico usa funnel.calculate. Nenhuma dessas operações ganha permissão de execução por um evento lead.ingest recebido. Um novo fluxo exige decisão de autorização específica do serviço proprietário, escopo por empresa/recurso e revogação.

A autoridade Portal P20 reforça MFA/operador/grant, mas o transporte permanece desativado por padrão e não há credenciais nem concessões reais de operadores na Leaseweb.

## Validação

1. Checkouts read-only exatos de quatro linhas, recusando alterações do SHA esperado.
2. Leitura dos contratos reais PHP/SQL do CRM e Central P19, grants, ACK e política de RLS; leitura do owner e motor NO_AI na RC62.
3. Falha fechada caso o receiver lead.ingest aceite indevidamente run.status.project ou funnel.calculate.
4. Análise da ancestralidade e tentativa Git merge-tree **sem criar merge commit, atualizar branch, instalar aplicativo ou alterar VPS**. Relatório registra caminhos modificados em ambas as linhas e possíveis conflitos para revisão.
5. Permanência explícita de NO_GO independentemente do resultado técnico do Git.

Não há chamadas S2S, leitura de clientes, utilização de chaves reais, n8n em execução, cobrança nem API de IA. Ausência de conflito textual Git **não é homologação funcional**.

## Pendências de saída

Revisão independente de conflitos e de código, autoridade comercial real e operator MFA, migrações e acessos limitados, custódia TLS, licença n8n, backup externo com restore isolado, monitoramento e autorização de homologação privada. A ativação de produção continua sendo decisão separada.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
