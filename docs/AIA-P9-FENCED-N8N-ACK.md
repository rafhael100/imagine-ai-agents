# Agents P9 — prevenção de ACK atrasado com worker reutilizado

**16/11/2026 — correção de consistência identificada na revisão da PR P6, somente PostgreSQL16 efêmero.**

## Problema confirmado pelo raciocínio de concorrência

O outbox da P6 validava `tenant_ref`, `event_ref`, `lease_owner` e prazo ao aceitar um ACK. Porém, quando o lease expirava e o **mesmo identificador de worker** executava novamente o evento, um ACK atrasado da primeira tentativa poderia confirmar indevidamente o novo lease. A titularidade por nome de worker não é suficiente para distinguir **duas gerações** do mesmo processamento.

## Correção

O recibo emitido por `claim()` já informava um contador `attempt` incrementado transacionalmente. O método `acknowledge()` agora exige esse contador, em intervalo permitido 1–3. O comando PostgreSQL valida **também `attempts=:attempt`** na atualização de `LEASED` para `ACKED`. Uma confirmação da tentativa 1 não pode encerrar a tentativa 3, mesmo que o worker seja novamente `SYNTHETIC_WORKER_01`.

O teste reproduz precisamente essa situação, usa dois sockets PG16 distintos, verifica que a linha permanece `LEASED` após o ACK atrasado e demonstra que ela finalmente chega a `DEAD` após esgotar três tentativas. Também preserva todas as verificações antigas de empresa, worker, prazo, ACK único, idempotência, `SKIP LOCKED` e falta de banco.

A CI P9 executa também as regressões P5 (automação antes de IA), P8 (reserva conservadora com 10 processos) e P4 (cotas de laboratório com 12 processos). Cada prova ocorre em banco de teste descartável, sem contato com n8n/CRM.

**Limite importante:** `attempt` é um token de *fencing* de concorrência e NÃO é credencial, assinatura ou IAM. O receptor n8n real ainda precisará autenticar mensagens e callbacks S2S (mTLS/assinatura), autorizar tenant, ter chaves rotacionáveis, política de retenção, revogação e proteção contra respostas forjadas. Também não substitui idempotência da operação do lado consumidor; em sistemas distribuídos, *at-least-once* é comum. **NO-GO / NO-MERGE / NO-DEPLOY / sem clientes e sem gastos.**
