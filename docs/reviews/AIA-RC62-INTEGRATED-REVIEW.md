# AIA-RC62 — Candidata integrada para revisão de P14 a P61

16/11/2026. **PACOTE ÚNICO DE REVISÃO — NÃO É UMA PUBLICAÇÃO.**

## Por que este pacote existe

Entre as fases P14 e P61 existem **48 incrementos** técnicos empilhados sobre a candidata P13, com cerca de **229 commits e 192 arquivos alterados** na comparação realizada antes da criação deste manifesto. Essas entregas, anteriormente distribuídas em PRs pequenos, podem ser revisadas também como um pacote integrado — sem precisar iniciar novo projeto ou reexecutar manualmente a aprovação de cada commit. A rastreabilidade dos PRs individuais continua preservada.

Esta branch parte da P61 completa, sem reimplementar as fases. O PR agregado aponta para a base P13 SHA \`658a26b798c57ab4446eb146df064dbdc3111293\`; a entrega integrada contém o SHA aprovado da P61 \`ab040500f62b041ab9e4a591bf16412bd5d0cdd4\`. Nenhum código é mesclado em \`main\`, \`dev\` ou VPS por esta ação.

## Escopo consolidado

- P14–P47: barreiras de identidade, mTLS, empresa/tenant, não-repetição, owner decision, revogação, integridade de evidências e ensaios do n8n oficial PostgreSQL17 **em jobs independentes**.
- P48–P50: núcleo determinístico NO_AI, regras configuráveis que geram rascunhos e CLI de prévia sem efeitos.
- P51–P56: persistência de rascunhos em PostgreSQL16 descartável, integridade e interface local de leitura com RLS de laboratório.
- P57–P59: dois papéis de pareceres assinados, revogação, serialização de concorrência, bloqueio de revisão pelo autor.
- P60–P61: visão de estados de pareceres e auditoria histórica de autoria/assinaturas.

**A CI da candidata integrada** herda os testes reais dos contratos P13–P61, a tela local de PostgreSQL16 e o job independente do n8n oficial PostgreSQL17. O novo \`tools/check_rc62_integrated_review.py\` verifica que a árvore inclui os subsistemas essenciais, os dois commits-âncora fazem parte da história e os bloqueios operacionais permanecem explícitos. O manifesto JSON \`AIA-RC62-REVIEW-TRAIN.json\` é um artefato de controle, não uma aprovação de segurança.

## Gate de saída: permanece NO-GO

O pacote pode ser usado para avaliação técnica, mas **NÃO** pode ser mesclado ou executado como produto em produção até a comprovação, em ambiente controlado, de:

1. Identidade de serviço CRM e Portal real, autorização por empresa/recurso/operação e revogação.
2. n8n privado recebendo eventos autorizados, ACKs autênticos e bloqueio de efeitos não autorizados.
3. Consentimento e retenção LGPD, gestão de chaves em cofre, monitoramento, backups, rollback e restauração.
4. Revisão independente AppSec/DBA, trilha de auditoria, aceite humano de release e aprovação separada para qualquer custo.

O manifesto marca todos esses pontos como \`NOT_VERIFIED_NO_GO\`. Não confundir aprovação da CI com autorização operacional.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
