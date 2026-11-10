# FT01/SEC-18 — Sugestão rejeitada deve encerrar a Run sintética

**Data:** 10/11/2026. **Decisão operacional:** NO-GO para integração CRM/produção. Tudo PHP offline sem cliente, LLM ou API real.

## Falha corrigida
A SEC-17 exigiu um contrato fechado na resposta de `MockToolDispatcher::dispatch(SUGGEST)`, mas não encerrava a Run quando o provedor falhava ou retornava uma sugestão adulterada. Assim, a mesma Run permanecia `EXECUTANDO` e podia ser reutilizada em outra chamada, inclusive uma operação sintética de efeito com aprovação separada.

## Nova regra
- `SupervisedCoordinator::assertToolSuggestionReady` verifica, **sem efeitos colaterais**, vínculo da Run com o coordenador, estado ativo e capacidade para dois eventos: `SUGGESTED` (quando houve resposta) e `RUN_CHANGED` (se necessário).
- `MockToolDispatcher` executa essa verificação antes de chamar o provedor. Se não houver capacidade, nega a solicitação sem invocar sugestão e sem consumir uma tentativa de efeito.
- Toda exceção do provedor ou resposta fora do contrato faz o dispatcher **tentar encerrar aquela Run com `FALHOU`**, além do evento minimizado `SYNTHETIC_DENIED` da ferramenta. Não devolve texto do provedor nem stack trace.
- Uma Run `FALHOU` não aceita nova sugestão nem efeito posterior, mesmo com um objeto de aprovação fictício separado. A conclusão de uma sugestão válida **não** encerra automaticamente a Run; aprovação posterior permanece procedimento separado.
- Nenhum efeito é criado pela saída de um modelo. Não alterar `main` ou backend CRM.

## Regressões U39-01..05
Falha do provedor, texto forjado seguido de tentativa de mutação autorizada, falta de espaço para auditoria terminal, sugestão normal preservando estado ativo e `MockError` não confiável. Todos verificam ausência de submissão automática e vazamento no audit.

**Gate de revisão:** 394 testes PHP, 82 arquivos PHP com lint e suítes Python de isolamento e contrato, no SHA exato do PR.

## Limites
Os eventos são voláteis em um único processo; não são prova de auditoria durável, fila, credencial real, IAM ou proteção contra concorrência. O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT, e R01-G01..G11 e CORE/SEG/AUT seguem ABERTOS/NO-GO. Sem acesso a clientes, VPS, provedor de IA, produção ou despesas.
