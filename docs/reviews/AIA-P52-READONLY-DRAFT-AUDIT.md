# AIA-P52 — Auditoria de integridade dos rascunhos de aprovação

16/11/2026. Candidata de laboratório empilhada sobre P51 (#431), sem implantação operacional.

## Mudanças

A gravação sintética das propostas P51 inclui agora um fingerprint SHA-256 por linha, calculado a partir de empresa, request, ruleset, revisão, ID da proposta, regra, natureza do rascunho, métrica, valor observado, limiar e estado fixo AWAITING_HUMAN_REVIEW_LAB. A idempotência P51 passa a comparar também o fingerprint de cada proposta antes de considerar o replay válido.

O novo PgReadOnlyDraftAuditLab consulta os lotes e rascunhos num único snapshot PostgreSQL REPEATABLE READ, READ ONLY. Recalcula cada fingerprint, verifica que o número de rascunhos corresponde à contagem declarada e nega silenciosas perdas ou alterações. O auditor não gera propostas, não recupera registros perdidos, não altera aprovações, não chama IA e não executa operações CRM.

O check inclui múltiplos tenants independentes, valor adulterado, correção manual em laboratório, rascunho apagado, contagem do lote adulterada, lote legítimo de zero propostas e perda da tabela. Ausência da fonte provoca INDETERMINATE, jamais um resultado limpo.

## Limites

**SHA-256 sem assinatura não é trilha inviolável:** um administrador malicioso com acesso de escrita pode alterar linha e hash conjuntamente. A proposta serve para detectar corrupção e incoerência de laboratório, não para comprovar auditoria jurídica ou emissão de autorização por CRM. A auditoria não consulta serviços externos, logs imutáveis ou consentimento real. Para produção são necessários identidades reais, controles de acesso, banco com privilégios mínimos, política de retenção, storage de logs protegidos, revisão AppSec/DBA/LGPD, publicação e backup supervisionados.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
