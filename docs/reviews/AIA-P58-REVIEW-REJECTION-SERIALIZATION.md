# AIA-P58 — Serialização de pareceres assinados e rejeições concorrentes

16/11/2026. Incremento sobre P57 (#441). A P57 já exigia decisões Ed25519 de revisores independentes, mas um par REJECT/ACK em transações paralelas podia observar a ausência inicial de rejeições e gravar ambos os pareceres antes do commit do concorrente.

## Correção

O método de submissão de pareceres passou a adquirir \`SELECT ... FOR UPDATE\` na **própria proposta P51**, antes de ler os votos ou verificar outras políticas. Assim, toda submissão do mesmo tenant/proposal fica serializada: rejeição e ACK concorrentes não atravessam a leitura do estado anterior simultaneamente. A verificação da chave pública continua com \`FOR SHARE\`, fazendo revogações aguardarem a conclusão da decisão já iniciada.

Não há mudança de estado de execução. Mesmo dois votos favoráveis permanecem com \`can_approve=false\`, \`can_execute=false\` e \`operational_authorization=false\`.

## Teste adversarial real em PostgreSQL16

A CI cria um tenant C com duas propostas sintéticas e duas chaves Ed25519. Em duas conexões distintas:

- Uma transação segura \`FOR UPDATE\` na proposta; outra tenta ACK, esgota \`statement_timeout\` de laboratório e falha fechada, sem inserir.
- Após liberar o lock, PRODUCT grava REJECT. Um ACK de SECURITY posterior é negado, e há uma única observação rejeitada.
- Numa segunda proposta, o ACK concorrente falha sob o mesmo lock, depois conclui normalmente com ambos os papéis distintos sem conceder autorização operacional.
- Uma transação \`FOR SHARE\` na chave do revisor faz a revogação esperar; depois do commit, a revogação conclui e o status previamente consistente passa a DENY.

O ensaio usa somente o banco PostgreSQL descartável da CI, sem canais HTTP, n8n real, dados pessoais ou créditos pagos. A suite conserva todas as regressões P13–P57 e os jobs isolados P56 browser e RC07 n8n PG17.

## Pendências

Ainda é preciso autenticar a pessoa real e sua equipe autorizada no CRM/Portal, registrar autoria, independência, MFA, status revogável e evidência audível em ambiente operacional. Nenhum parecer de laboratório pode aprovar execução, cobrança ou automação externa.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
