# AIA-P34 — Revogação deve bloquear lease e ACK de eventos já registrados

16/11/2026. Continuação da P33 (#396), alinhada à finalidade de revogação segura da PR independente #384 **sem fundir códigos ou permitir serviços reais**.

**Lacuna:** o fluxo P31 verificava uma política sintética ativa quando registrava o evento. Se a permissão fosse revogada após o stage, um evento pendente ainda poderia obter lease e ser confirmado depois.

**Mudanças:**
- Tabela fictícia de autoridade multiempresa no PostgreSQL16 efêmero com `active` e `revision` monotônica. Revogar e reativar incrementa a revisão.
- Novas variantes **opcionais** de P28 e P30 exigem revisão previamente conhecida e fazem `SELECT ... FOR SHARE` na própria transação que reserva ou confirma o evento. Isso ordena revogação concorrente com decisão de lease/ACK, sem janela entre leitura de política e commit.
- `PgRevocationBoundCycleLab` expõe apenas as variantes revision-bound. Uma revisão antiga jamais volta a ser válida após reativação. O bloqueio em stage é preflight sintético e **não promete atomicidade do próprio stage com revogação**, mas nenhuma execução externa é realizada nesse estágio.
- CI testa revogação após o lease, ACK negado, bloqueio de nova reserva, isolamento da empresa B, reativação com revisão diferente, fencing antigo e bloqueio efetivo da transação de revogação por lock SHARE.

**Limites:** esta tabela é apenas uma fixture; não identifica clientes, chaves reais, sistemas ou consentimentos. Não substitui a correção de concorrência da inbox assinada #384, que continua em outra branch. A chamada P28/P30 sem revisão permanece disponível para suites antigas e NÃO deve ser usada operacionalmente. P34 não instala n8n, não dá GO e não toca VPS/main.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
