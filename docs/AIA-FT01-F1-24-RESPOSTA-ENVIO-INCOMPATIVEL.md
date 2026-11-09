# FT01/F1-24 — Resposta de envio incompatível não constitui prova

Data: 09/11/2026. Escopo: **laboratório sintético PHP, sem integrações reais**.

## Falha corrigida
Após registrar `SUBMITTED`, o coordenador podia receber um objeto `Effect` da operação simulada cujo `Intent` não correspondia à intenção original. O método `remember` recusava a evidência, mas lançava `MockError` antes de registrar um evento de efeito. Assim o histórico ficava com envio registrado e sem resolução e a análise de trilhas podia ser incompleta.

## Implementação
`SupervisedCoordinator::execute` continua reservando idempotência e dois eventos antes do primeiro envio. Ao receber um `Effect` incompatível e ter `MockError` em `remember`, produz somente um `Effect` local de estado `INDETERMINADO`, registra o evento de mesmo nome e devolve a incerteza ao chamador. Não copia campos do objeto incompatível nem confirma ou nega a operação com base nele.

A próxima chamada a `execute` com a mesma intenção consulta **apenas** `status` via `reconcile`, nunca chama `submit` novamente. A consulta de status ainda precisa passar pelos controles F1-23; as evidências continuam exclusivamente sintéticas.

## Regressão de revisão
- U24-01: resposta de envio de outra intenção gera somente estado `INDETERMINADO`, trilha fechada e um único envio.
- U24-02: segunda execução resolve apenas por status e preserva envio único.
- U24-03: IDs e material do objeto incompatível não entram na trilha minimizada.

Meta do gate offline: **323 testes PHP e 79 arquivos PHP com lint**. Essas contagens não são atestadas sem CI `success` no SHA do PR.

## Bloqueios preservados
Nenhuma chamada externa, token, cliente, banco, contratação, VPS ou publicação. A prova do mock não valida autorização real nem confiabilidade distribuída. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua sujeito a aceite CORE/SEG/AUT. Gates R01-G01..G11 **ABERTOS**. Decisão operacional **NO-GO**.
