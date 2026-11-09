# FT01/SEC-07 — Negação de timeout não enviado e ordem de eventos

**Situação:** correção em laboratório PHP sintético, condicionada à CI. Sem conexão, dados reais, credenciais, CRM ou deploy.

## Problema identificado

Antes desta alteração, `SupervisedCoordinator::afterTimeout` validava a correspondência da tentativa com a intenção e registrava `TIMEOUT` **antes** de chamar `reconcile`. Se nenhuma operação tivesse sido enviada, `reconcile` negava corretamente com `INTENT_NOT_SUBMITTED`, mas o evento de timeout indevido já havia sido acrescentado à trilha. O revisor também não verificava se `STATUS_REQUESTED` ou `TIMEOUT` precediam `SUBMITTED`. Isso podia criar histórico fictício inconsistente, mesmo sem chamadas externas.

## Correção fail-closed

- `afterTimeout` exige que a intenção esteja em `submitted` antes de gerar `TIMEOUT`. Um timeout não enviado resulta em `INTENT_NOT_SUBMITTED`, sem poluir eventos ou consultar status.
- `ReadOnlyTraceReview::inspect` exige `SUBMITTED` prévio aos eventos `STATUS_REQUESTED` e `TIMEOUT`. Caso contrário, devolve `INDETERMINADO / STATE_CONFLICT`, sem aceitar prova posterior.
- O caminho legítimo de resposta perdida continua sem reenviar: primeiro `SUBMITTED`, depois `INDETERMINADO`, `TIMEOUT`, consulta de status e eventual confirmação sintética.

## Evidências, compatibilidade e restrições

Sete novos casos U03-28..34 verificam rejeição antecipada sem evento, tentativa inválida, ordem forjada de consulta e timeout, reconciliação de resposta perdida apenas por status, consulta legítima e envio posterior após tentativa rejeitada. A suíte passa de 266 para **273 testes PHP**, mantendo **75 arquivos lint**. A CI valida no SHA exato, sem instalar dependências ou contactar serviços.

É apenas um controle de integridade temporal de registros **não autenticados e voláteis**. Não garante entrega, persistência, deduplicação distribuída ou autorização de CRM. R01-G01..G11, CRM CORE/SEG/AUT e licenças continuam pendentes; o [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece DRAFT. **NO-GO operacional**. `main`, VPS, produção e custos fora de escopo.
