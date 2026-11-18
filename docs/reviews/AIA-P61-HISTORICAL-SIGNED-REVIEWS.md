# AIA-P61 — Auditoria histórica de pareceres assinados e autoria

16/11/2026. Continuação empilhada sobre P60 (#445), em banco PostgreSQL16 descartável.

## Auditoria com snapshot somente leitura

\`PgHistoricalSignedReviewAuditLab\` executa uma única transação \`REPEATABLE READ, READ ONLY\` e cruza os rascunhos P51, fingerprints P52, registro do autor P59, observações Ed25519 P57/P58 e chave pública do revisor. A verificação recalcula o hash do rascunho, compara com o selo da autoria, detecta autor igual ao revisor, papéis duplicados e corrupção dos campos. Para cada parecer, reconstitui seu contrato JSON canônico, compara o vínculo tenant/proposta/papel/revisor e verifica a assinatura Ed25519 com a chave registrada.

**Histórico e autorização são coisas distintas:** uma assinatura válida continua sendo historicamente verificável após revogação da chave. Isso **não** autoriza o uso da chave revogada no presente. O scanner não faz consulta de decisão operacional nem cria exceções aos gates P44–P59. As respostas são \`LAB_HISTORICAL_SIGNED_REVIEWS_CONSISTENT_NO_GO\`, \`DENY_INCONSISTENT\` ou \`INDETERMINATE\`. Nenhuma delas concede execução. É permitido ter propostas sem parecer, desde que sua autoria tenha sido vinculada no fluxo opt-in P59.

## Testes adversariais

O teste PostgreSQL16 prepara duas empresas sintéticas: empresa A com dois pareceres assinados, empresa B com proposta de autoria vinculada e sem parecer. Valida estado limpo e, em seguida, adulteração de assinatura, substituição do autor por revisor, alteração do rascunho, exclusão da autoria, perda de tabela e comportamento com chave revogada depois da assinatura. A auditoria nunca repara registros, não vaza corpos assinados, chave pública, nomes de usuários ou credenciais e não produz ações.

O escopo é **somente registros derivados de propostas P51 com autoria P59**. Eventos antigos ou outros modelos de revisão são auditados por seus próprios mecanismos; não se deve concluir que uma auditoria P61 limpa certifica toda a plataforma.

## Pendente

Identidade real, autenticação forte de autores e revisores, registro de consentimento, retenção LGPD, custódia/rotação de chaves, trilha imutável externa e homologação do CRM/Portal. Sem merge, deploy, n8n operacional, IA paga ou despesas.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
