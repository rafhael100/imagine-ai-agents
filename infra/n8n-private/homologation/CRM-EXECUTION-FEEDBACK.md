# Retorno de execução do n8n para o CRM

## Resultado deste recorte

O CRM consulta uma API privada de acompanhamento, verifica a resposta assinada e
persiste uma projeção do processamento e seu histórico. A fonte continua sendo o
evento original do CRM, capturado no Agents e ligado à tarefa autorizada e ao
ledger nativo do n8n. Não há acesso do n8n ao banco do CRM.

Este é um candidato de desenvolvimento com identidades e dados sintéticos. Não
habilita uma instalação operacional, não altera regras comerciais nem aciona IA
paga. Os resultados de execução efetivos ficam vinculados à SHA e à CI da PR.

Dependências já publicadas: captura [#467](https://github.com/rafhael100/imagine-ai-agents/pull/467),
domínio [#468](https://github.com/rafhael100/imagine-ai-agents/pull/468) e execução
nativa [#469](https://github.com/rafhael100/imagine-ai-agents/pull/469).

## Dois contratos com finalidades distintas

`POST /private/events/receipt` continua a responder somente sobre recebimento do
evento. Seu contrato P27 permanece `RECEIVED / DISABLED / NOT_DISPATCHED`. Esse
recibo não acompanha o processamento posterior.

`POST /private/events/execution` usa o novo contrato
`AUTO_IA_EXECUTION_QUERY_V1` / `AUTO_IA_EXECUTION_STATUS_V1`, com a operação
`event.execution.read`. O acesso exige mTLS, identidade da empresa, tenant,
evento, hash exato do payload original, chave de consulta atual e grant próprio
de leitura. As credenciais de ingresso ou de recibo, sozinhas, não concedem essa
permissão. A chave de assinatura da resposta é distinta da chave do recibo.

A autorização de acompanhamento é independente da autorização de execução.
Revogar o ingresso ou a decisão do executor não apaga um resultado já registrado.
Uma leitura histórica ainda exige o grant de acompanhamento, o vínculo da empresa
e a chave da consulta válidos. Revogar a leitura bloqueia novas consultas.

## Significado dos estados

| Estado de execução | Evidência exigida | Interpretação no CRM |
| --- | --- | --- |
| `NOT_STAGED` | Captura íntegra, sem tarefa autorizada | Evento capturado, ainda sem tarefa |
| `NOT_STARTED` | Captura e tarefa vinculadas, sem intenção de execução | Tarefa aguardando início; a fila informa se está pendente, reservada ou encerrada |
| `STARTED` | Intenção durável no ledger nativo | Execução iniciada; ainda sem resultado comprovado |
| `RESULT` | Resultado íntegro persistido, sem confirmação final completa | Resultado preparado, confirmação pendente |
| `ACKED` | Resultado e confirmação/evidência vinculados à mesma tarefa | Preparo confirmado |
| `UNKNOWN` | Ledger com resultado incerto | Exige revisão; consulta não autoriza repetir a execução |

`result_code=PREPARED` descreve apenas o preparo técnico do evento de lead. Não
significa qualificação comercial, contato com cliente, mudança de funil ou tarefa
comercial concluída. Não existem essas mutações neste fluxo. Antes de um resultado
comprovado, `result_code=NONE`.

O estado da fila é separado: `NONE`, `PENDING`, `LEASED`, `ACKED` ou `DEAD`.
A consulta nunca reserva, confirma, reenvia ou reconcilia a fila. Ela não chama o
n8n e não renova a janela do evento original.

## Contrato e vínculos criptográficos

O pedido tem os mesmos campos fechados do recibo P27, com schema e operação novos:
`schema`, `issuer`, `audience`, `product`, `operation`, `company_id`, `tenant_ref`,
`event_ref`, `payload_hash`, `request_ref`, `nonce_ref`, `issued_at`, `expires_at`,
`key_id`, `signature`. O prefixo HMAC é
`IMAGINE_AGENTS_EXECUTION_QUERY_V1`. `payload_hash` continua a ser o SHA-256 dos
bytes originais enviados pelo CRM, não o hash da tarefa.

A resposta possui, nesta ordem canônica:

```
schema, issuer, audience, company_id, tenant_ref, event_ref, payload_hash,
lead_id, task_payload_hash, execution_state, queue_state, result_code,
observation_hash, attempt, request_ref, nonce_ref, issued_at, expires_at,
key_id, signature
```

O HMAC da resposta usa `IMAGINE_AGENTS_EXECUTION_STATUS_V1`, seguido de uma quebra
de linha e dos primeiros 19 valores separados por quebras de linha. A assinatura
não depende da ordem recebida das chaves JSON. Duplicatas, inclusive nomes
escapados equivalentes, são recusadas antes da conversão para objeto.

`observation_hash` é SHA-256 de `IMAGINE_AGENTS_EXECUTION_OBSERVATION_V1`, uma
quebra de linha e os valores, na ordem: empresa, tenant, evento, hash de origem,
lead, hash da tarefa, estado de execução, estado da fila, código do resultado e
tentativa. É recalculado pelo CRM. Assim, requests, nonces e horários novos não
criam outra transição quando os fatos observados são os mesmos.

`task_payload_hash` fica vazio somente em `NOT_STAGED`; nas demais situações é o
digest da tarefa canônica vinculada à captura. `attempt=0` indica ausência de
intenção de execução. Uma intenção ou resultado exige a tentativa do ledger.

## Persistência e recuperação

O CRM mantém a confirmação de entrega original separada da projeção de execução.
A gravação da projeção e do histórico é atômica. Uma resposta inválida, um grant
expirado ou uma falha SQL não deve deixar uma transição parcial. As consultas
concorrentes do mesmo evento são serializadas e a repetição de uma observação é
idempotente. Uma resposta antiga não pode regredir um resultado mais avançado.

A leitura no Agents usa um snapshot consistente dos vínculos e estados, sem
alterar o ledger. A autorização da leitura permanece protegida até o commit;
as janelas são verificadas novamente após esperas por locks. O CRM aplica a mesma
regra ao seu grant e ao evento de origem durante a confirmação local.

O perfil de backup do CRM reconhece explicitamente os objetos desta extensão.
Um perfil parcial ou desconhecido continua recusado. Na restauração, owners,
privilégios, FORCE RLS e histórico devem ser preservados, com roles inertes e sem
execução automática de trabalho.

## Evidências exigidas pela CI

O workflow fixa o commit do domínio Agents e o commit do CRM, exporta uma árvore
temporária e preserva os blobs revisados. A CI deve usar o emissor e o reconciliador
reais do CRM, PostgreSQL separado, HTTPS com mTLS e n8n nativo real, com fixtures
sintéticas. O runner não recebe credenciais operacionais.

As provas cobrem ciclo de retorno, duplicatas, concorrência, escopo de empresa,
assinatura e request/nonce, revogação, falha de gravação e rollback, leitura sem
mutação, preservação de resultado incerto e recuperação sem repetir a execução.
As regressões anteriores de captura, execução e recuperação permanecem exigidas.

CI verde comprova o candidato e os casos executados. Identidade operacional,
custódia de chaves, instalação na VPS, backup externo e autorização de uso com
clientes continuam fora desta evidência. Critérios operacionais:
[#414](https://github.com/rafhael100/imagine-ai-agents/issues/414).
