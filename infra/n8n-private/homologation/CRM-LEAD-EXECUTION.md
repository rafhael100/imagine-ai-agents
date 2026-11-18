# Evento do CRM até uma execução nativa do n8n

## Escopo da candidata

Esta extensão liga a captura autenticada de `CRM_LEAD_EVENT_V1` à fila P6
existente e ao ledger nativo P26. O recorte possui uma operação explícita:
`imagine-crm / lead.ingest`. O resultado é um recibo de preparo de integração
vinculado ao lead recebido. O fluxo não consulta dados comerciais nem altera
campos do CRM.

O código de domínio e a Central são revisados em branches separadas, cada um
com seu SHA fixado. A CI compõe uma árvore Git temporária para testar os dois
em conjunto. Essa composição não faz merge em `main` ou promove uma instalação.

## Origem, tarefa e autorização

Há dois hashes diferentes e ambos são necessários:

| Campo | Conteúdo protegido |
| --- | --- |
| `source_payload_hash` | Bytes exatos do evento autenticado e capturado do CRM |
| `payload_hash` | Tarefa canônica colocada na fila, incluindo o hash da origem |

O contrato fechado `SYNTHETIC_CRM_LEAD_INGEST_V1` contém `contract`,
`event_ref`, `tenant_ref`, `company_id`, `lead_id`, `source_payload_hash`,
`kind` e `occurred_at`. O tipo é fixo em `crm.lead.ingest`. O instante da
tarefa representa seu preparo para a fila; o horário de admissão original
continua preservado na captura.

A ponte valida a captura persistida, sua inbox e os vínculos de empresa e
tenant. Em uma transação, consome uma decisão de proprietário assinada para
`lead.ingest`, registra a proteção de repetição P16, enfileira na P6 e grava
a associação entre origem e tarefa. Não fabrica prova do transporte P7, que
tem outra finalidade. Uma permissão de `run.status.project` não autoriza lead.

O envelope de ingresso pode ter expirado depois de uma recepção legítima.
Ele permanece como história da admissão. A tarefa e a autorização de execução
têm sua própria validade corrente; o código não prolonga o envelope antigo.

## Execução e recuperação

O fluxo usa três nós nativos do n8n: disparo manual, atribuição de referências
e construção do recibo. A entrada, o template e o grafo materializado ficam
vinculados ao contexto de execução. A observação do resultado é validada em
Python e em PHP contra o contrato fechado.

O recibo `AIA_NATIVE_LEAD_INGEST_V1` conserva referências da empresa, tenant,
evento e lead, os dois hashes e a identificação da tentativa. Seu resultado
é `lead_status=PREPARED`, `crm_mutated=false` e `ai_api_calls=0`.

A mesma máquina de estados P26 protege o processamento:

- `STARTED`: intenção persistida antes da chamada ao n8n.
- `RESULT`: resultado observado e recibo persistidos antes do ACK da fila.
- `ACKED`: confirmação da fila e evidência assinada conferidas.
- `UNKNOWN`: resultado incerto; repetir automaticamente a execução é negado.

Uma interrupção depois de gravar `RESULT` permite reconciliar o ACK usando o
resultado já persistido. A restauração deve preservar captura, associação,
fila, resultado e evidência, com autoridades desativadas no destino. Restaurar
um evento ou um workflow não concede nova execução.

## Contratos de recebimento continuam separados

O ACK de ingresso continua sendo um recibo de recebimento. O campo histórico
`CAPTURED_NOT_DISPATCHED` registra o fato ocorrido naquela admissão e nunca é
reescrito para representar o estado atual da fila. A consulta P27 também não
é uma API de acompanhamento dessa execução. O estado posterior é verificado
na fila e no ledger, através do ensaio específico desta candidata.

## Evidência exigida

O runner `tools/run_crm_lead_execution_virtual_tree.sh` executa o harness
`check_crm_lead_execution.py` com PostgreSQL, mTLS e n8n reais, descartáveis,
mantendo as regressões de captura e do fluxo anterior de run status. As
asserções incluem origem ausente ou divergente, autorização incompatível,
troca de template, duplicidade, revogação, reconciliação e recuperação.

A aprovação dinâmica depende da CI no SHA exato da candidata e no SHA de
domínio fixado no workflow. A documentação do desenho não substitui esse
resultado.

**Limite operacional: NO-GO.** A identidade do proprietário, certificados,
chaves e dados são sintéticos. Esta extensão não comprova uma instalação na
VPS, backup externo no R2, custódia real de chaves, atualização de registros
comerciais ou uso por clientes. Não há chamada de IA paga.
