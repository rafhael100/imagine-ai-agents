# FT01/F1-23 — Registro da indisponibilidade de consulta de status

**Escopo:** laboratório PHP sintético, não uma implementação de CRM, rede, monitoramento de produção ou retenção durável. **NO-GO** operacional.

## Lacuna identificada

No fluxo de reconciliação, o coordenador registrava o início de uma consulta (`STATUS_REQUESTED`) antes de chamar o gateway. Se este lançasse erro, a exceção era propagada sem um evento que encerrasse explicitamente a tentativa. Um leitor da trilha não conseguia distinguir uma consulta sem resposta por falha do processo de uma consulta ainda pendente. O erro de status também não deveria alterar a última confirmação já obtida nem disparar novo envio.

## Implementação

`SupervisedCoordinator::reconcile` envolve **somente a chamada ao gateway** com `try/catch`. Em caso de exceção, adiciona um evento fechado `SYNTHETIC_STATUS_UNAVAILABLE` vinculado à intenção e relança a exceção sem converter o efeito em confirmação, recusa ou ausência. Não insere dados do erro, conteúdo de requisição ou texto de resposta no histórico.

`ReadOnlyTraceReview::inspect` reconhece o novo código e exige sua sequência: sempre depois de `STATUS_REQUESTED`, nunca antes do envio e nunca duas vezes para uma consulta. Consultas de status devem terminar com um resultado fictício de efeito ou com `STATUS_UNAVAILABLE` antes de qualquer novo evento. Uma consulta sem fechamento retorna `INDETERMINADO / STATE_CONFLICT`. Uma falha de consulta **não** substitui ou rebaixa o efeito anterior já observado.

A guarda de capacidade já introduzida em SEC-08 reserva duas posições para `STATUS_REQUESTED` + resultado/indisponibilidade, e três no caso de `TIMEOUT` seguido de consulta. Não há retry automático nem segundo `submit`.

## Regressão

Quatorze testes PHP em `tests/Unit/status_outage_trace.php` (U23-01..14) cobrem: falha após confirmação, falha após resultado indeterminado, timeout com consulta indisponível, evento fabricado sem consulta, indisponibilidade antes de envio, resposta duplicada, consulta sem fechamento, interferência de evento não relacionado, minimização de detalhes de erro e reexecução do caminho `execute` sem novo envio. A suíte espera **318 testes PHP e 79 lint**, condicionados à CI no SHA exato.

## Limitações e pendências

Eventos e provas permanecem transitórios, de processo único e sem autenticidade criptográfica. O evento `STATUS_UNAVAILABLE` registra somente a falha **da consulta**, não o estado do efeito na origem. Não há armazenamento, alertas operacionais, transações, integração real ou validação das permissões do CRM. [PR #57 do CRM](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT continuam ABERTOS. Não modificar `main`, VPS, clientes, tokens, serviços pagos ou produção.

**Regressão adicional:** uma consulta de status que encontra o mesmo efeito terminal conhecido deve fechar a sequência com novo evento `CONFIRMADO` ou `NEGADO` sem duplicar a chamada `submit`. O caso U23-13 verifica essa condição, e U21-07 ajusta a contagem de eventos da projeção A. A identidade do `Effect` original é preservada.

**Cobertura de estado terminal negado (U23-14):** um resultado `NEGADO` já conhecido precisa igualmente encerrar a consulta com um novo evento `NEGADO`, sem alterar o mesmo `Effect` ou reenviar a operação simulada. Esse estado não concede autorização de mutação.
