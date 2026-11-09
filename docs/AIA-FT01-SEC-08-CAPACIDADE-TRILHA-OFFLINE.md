# FT01/SEC-08 — limite de capacidade da trilha sintética

**Escopo:** laboratório PHP de processo único, sem integração CRM, persistência, dados reais, despesas ou deploy. **Decisão operacional: NO-GO.**

## Falha de consistência identificada

O revisor `ReadOnlyTraceReview::inspect` limita a entrada a 1.024 eventos para reduzir o risco de consumo descontrolado de recursos e rejeita uma trilha maior. Contudo, `SupervisedCoordinator` gerava eventos indefinidamente, mesmo quando a trilha resultante não poderia mais ser examinada pelo próprio revisor. Além disso, a ordem reserva de idempotência → envio → rastreamento poderia criar uma reserva ou chamar componentes antes de descobrir que não havia espaço para documentar o resultado.

## Correção preventiva

`SupervisedCoordinator` passa a impor `MAX_TRACE_EVENTS = 1024` por processo. Antes de cada operação, verifica espaço para todos os registros previstos:

- `start`, `step`, `suggest` e `transition`: um evento, com a reserva de capacidade antes de criar objetos, chamar a IA fictícia ou alterar o ciclo de vida;
- `execute`: dois eventos, `SUBMITTED` e resultado, antes de reservar a chave de idempotência ou chamar o gateway;
- `reconcile`: dois eventos, consulta de status e resultado, antes de chamar `status`;
- `afterTimeout`: três eventos, timeout, consulta de status e resultado, antes de registrar timeout ou consultar o gateway;
- `trace`: verificação defensiva adicional, para impedir que uma chamada interna futura ultrapasse o teto.

Quando não há espaço, o fluxo falha com `TRACE_CAPACITY_EXCEEDED`, sem fazer chamadas externas de laboratório, sem gravar um evento incompleto e sem alterar a execução. No limite exato, o histórico ainda é inspecionável. Não há descarte silencioso de eventos, reinício da contagem ou confirmação automática de efeitos.

## Testes

O arquivo `tests/Unit/trace_capacity.php` acrescenta sete cenários (U20-01..07), cobrindo o teto de 1.024, reserva atômica de espaço para envio+resultado, reconciliação, recuperação após timeout, estado do `Run`, chamadas de sugestão e rejeição por falta de aprovação. A regressão oficial passa de **273 para 280 testes PHP**, com **76 arquivos para lint**. O gate offline unificado permanece obrigatório no SHA revisado.

## Limites

Esta proteção não cria fila durável, retenção, rotação de logs, deduplicação distribuída, observabilidade de produção ou garantias transacionais. Uma arquitetura real precisará dessas decisões antes da implantação. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua DRAFT; R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos. Não modificar `main`, VPS, serviços pagos ou credenciais.
