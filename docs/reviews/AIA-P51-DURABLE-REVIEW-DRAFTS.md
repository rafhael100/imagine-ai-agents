# AIA-P51 — Persistência segura de rascunhos determinísticos para revisão humana

16/11/2026. Implementação de laboratório sobre a P50 (#428), sem sistema operacional ativado.

## Conteúdo funcional

A classe tools/labs/PgDeterministicDraftRegistryLab.php oferece um registro transacional de rascunhos P49, em PostgreSQL16 descartável com verificação explícita da identidade da base de CI. Ela recebe comandos P48 e rulesets P49 com referências fictícias, calcula as propostas internamente (não aceita listas de propostas já "aprovadas" fornecidas pelo chamador) e registra:

- Um lote por empresa e solicitação, com ruleset, versão, SHA-256 do comando, da configuração e do resultado, com limite de até oito propostas.
- Zero a oito propostas por lote, com identificadores determinísticos do P49 e estado único AWAITING_HUMAN_REVIEW_LAB.
- Transação única: lote e todos os rascunhos confirmam juntos ou são revertidos; não existe aprovação, escrita real no CRM, envio de mensagem, débito de crédito nem evento n8n.

## Idempotência e isolamento

Repetir exatamente a mesma empresa, solicitação, regras e entradas produz LAB_DRAFTS_ALREADY_STAGED, sem alterar registros. Reutilizar a mesma solicitação com métricas diferentes, nova revisão, configuração divergente ou corrupção histórica gera DENY. Empresas diferentes possuem namespaces independentes e as leituras são filtradas por tenant. A consulta de rascunhos é somente leitura, com limite de 128 itens: se ultrapassado, a chamada falha fechada e NÃO trunca silenciosamente a fila.

## Testes

A CI verifica um lote com duas propostas, replay exato, alteração de comando e de ruleset, tentativa de outro tenant, armazenamento independente da empresa B, exclusão de uma proposta seguida de detecção sem reparo automático, e concorrência com bloqueio PostgreSQL FOR UPDATE. As regressões P13–P50, incluindo n8n oficial isolado em PG17, continuam associadas à mesma branch.

## Limites e próximos gates

O registro é apenas uma fixture de CI, não uma migration operacional, fila de execução nem base de clientes. Falta integrar a verdadeira Central de Aprovações do CRM/Portal com identidade, autorização, revisores humanos autenticados, dupla confirmação onde necessária, política de privacidade, retenção, auditoria, API segura e homologação. Nenhum "aprovado" neste laboratório pode levar à execução externa.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
