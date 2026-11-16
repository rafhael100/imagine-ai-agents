# AIA-P21 — Candidata de revisão integrada e evidências NO-GO

16/11/2026. Candidata empilhada sobre AIA-P20 (#376), incluindo P13–P19 e P8/P11, sem tentar mesclar linhas paralelas.

A pipeline `ci-p21-integrated-review.yml` executa em PostgreSQL16 descartável, com checkout do adaptador AUTO-IA fixado no SHA completo. Regride P13 e P14, política de empresa e operação P15, reservas/recibos sintéticos de créditos P8/P11, outbox P6, antirrepetição durável P16, commit conjunto P17, falhas e corrida de dez workers P18, isolamento RLS P19 e restrições NO_AI de workflow P20.

O relatório JSON produzido é **evidência da presença dos checks e dos bloqueios documentados**, não atestado de segurança operacional. A CI demonstra propriedades no laboratório, não ambiente real.

## Gates que devem permanecer fechados

- Revisão independente dos diffs empilhados, atualizações de dependências, qualidade, privacidade e conflitos;
- fonte real de autoridade de tenant e serviço, permissões de cada produto e revogação operacional;
- assinatura S2S e transporte seguros, rotação de chave e revogação com testes autenticados;
- n8n privado realmente instalado, licença avaliada, backup/restauração e monitoramento;
- aceites do CRM e Portal, migração reversível, supervisão, alertas e autorização explícita antes de produção.

**Não habilitar merge automático de nenhuma das PRs da pilha.** Sem VPS, usuários, dinheiro, tokens, API paga, endpoints reais ou autorização de produção. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
