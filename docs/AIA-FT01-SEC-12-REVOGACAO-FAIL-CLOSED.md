# FT01/SEC-12 — Bloqueio persistente após falha de registro de revogação

**Data:** 09/11/2026. **Escopo:** laboratório offline, estados e evidências sintéticos. **NO-GO operacional**.

## Risco
SEC-11 limitou o número de revogações registradas. Entretanto, a simples rejeição de uma **nova revogação** por falta de capacidade não bastava: um envelope diferente, ainda não consumido nem registrado como revogado, continuaria podendo ser autorizado por um estado que acabara de perder uma exigência de revogação. Isso caracterizaria falha permissiva.

## Correção
`MockSharedTrustState` agora estabelece um sinal interno monotônico `revocationOverflowed` **antes de lançar** `TRUST_STATE_CAPACITY_DENIED` quando uma nova revogação não cabe. A partir desse instante, `consume` e futuras operações de revogação são bloqueadas por `TRUST_STATE_UNAVAILABLE`. Uma chamada de `setAvailable(true)` não remove o sinal e não substitui recuperação validada de estado.

O bloqueio é restrito à falha de **revogação**. Capacidade de nonce esgotada rejeita nonces adicionais, mas não finge ter perdido uma revogação. Registro duplicado de revogação continua idempotente enquanto não ocorreu overflow.

## Regressões U29-01..04
Cobrem falha de revogação seguida de autorização, recuperação artificial incapaz de apagar o bloqueio, limite cheio sem falha adicional e comportamento independente do orçamento de nonces.

**Meta do gate:** 339 testes PHP e 79 arquivos PHP com lint, com validação CI no SHA do PR.

## Limitações
Esta trava em memória **não é** uma política de reset operacional, cache distribuído ou mecanismo real de IAM. Em produção, estouro de revogação exige intervenção, retenção/compaction criptograficamente segura, consistência entre nós e recuperação auditável, aprovadas por Segurança, Infra e CRM. Não implementar automaticamente credenciais, CRM real, APIs externas, VPS, pagamentos, deploy ou `main`. CRM PR #57 segue DRAFT; R01-G01..G11 e CORE/SEG/AUT **abertos**.
