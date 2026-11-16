# P10 — gate de despacho explícito antes de qualquer cobrança de IA

**16/11/2026 — correção de consistência do laboratório de créditos. Sem API real, CRM, n8n ou cobrança.**

## Vulnerabilidade de fluxo corrigida

Na P8, `RESERVED` podia ir diretamente para `UNCERTAIN` ou `FINALIZED`; sem prova de passagem pela fronteira de despacho, um chamador podia informar medição e debitar uma reserva **sem ao menos ter registrado encaminhamento ao provedor**. Além disso, `reserve()` respondia `REPLAY` a pedidos com o mesmo idempotency ID já `CANCELED` ou `FINALIZED`, o que poderia ser interpretado erroneamente como permissão para uma nova execução.

## Estados permitidos no ensaio P10

- `RESERVED`: somente saldo reservado. Pode ser **cancelado antes do despacho** ou receber `markDispatched` explicitamente.
- `DISPATCHED`: a tentativa fictícia de encaminhamento já foi marcada; cancelamento pré-despacho e replay são recusados. Não equivale a comprovar que um provedor recebeu a requisição.
- `UNCERTAIN`: após `DISPATCHED`, timeout/resultado desconhecido mantém toda a reserva bloqueada.
- `FINALIZED`: apenas após `DISPATCHED` ou `UNCERTAIN` e medição observada fictícia compatível; prova duplicada com o mesmo valor não gera débito adicional.
- `CANCELED`: terminal. Mesmo ID não pode ser reutilizado como nova autorização.
- `DISPUTED`: terminal e congelamento de tenant por conflito ou medida acima da reserva.

O mesmo request/mesma entrada pode devolver `REPLAY` somente enquanto ainda estiver `RESERVED`; após qualquer encaminhamento, cancelamento ou finalização, o novo pedido falha fechado (`null`). Em sistemas reais, replays da resposta antiga precisam de contrato de status não autorizador e autenticado — esta prova não implementa isso.

A CI executa dez processos PostgreSQL16 competindo por saldo fictício, ensaia a fronteira `markDispatched`, cancela antes do despacho, mantém bloqueio em timeout, finaliza apenas com observação sintética e impede cobrança sem despacho ou replay após estado terminal. Inclui regressões P5 (automação antes de IA), P9 (ACK n8n com fencing por tentativa), P4 (cotas com 12 workers).

**Limitação fundamental:** `markDispatched` é um marcador **local**, não prova de entrega ao provedor. `reconcile(..., observed)` recebe valor fictício e ainda não valida uma assinatura de medição de fornecedor, identidade do requisitante ou ACL. Nenhum código foi ligado a endpoint; falta IAM, S2S, fonte de observação confiável, conciliação financeira, LGPD e homologação do CRM OP-01 #57. Sem merge, deploy ou custos.
