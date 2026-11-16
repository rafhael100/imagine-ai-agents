# AIA-P23 — Auditoria somente de leitura: consistência do staging P17

16/11/2026. A atomicidade P17 e os testes de queda P18 reduzem registros parciais, mas não demonstram que nenhuma divergência posterior possa ocorrer por operação manual, migração defeituosa, falha de armazenamento ou escrita externa.

Novo `PgAtomicStagingAuditLab` lê, sem alterar, as tabelas de antirrepetição P16 e outbox P6, correlacionadas por empresa/evento. Identifica registro faltante em qualquer lado, hash divergente, conteúdo de envelope fora do contrato canônico, identidade de empresa/evento divergente ou digest incompatível. Falta do banco/tabela gera `INDETERMINATE`, nunca "consistente". O resultado não contém o corpo do evento, dados pessoais ou segredos.

A CI injeta anomalias **somente em PostgreSQL descartável** e comprova cada alerta. Restaura dados apenas no script de teste, não no auditor. Não corrige automaticamente filas, saldos, créditos ou eventos: inconsistência real exige triagem humana e plano de remediação aprovado.

Ainda não há IAM operacional, n8n conectado, dados reais ou aceitação de deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
