# FT01/F190 — Ensaio executável de pausa, revisão e retomada

**11/11/2026. CLI PHP determinístico em memória, sem rede. Operação real: NO-GO.**

## Resultado

O `examples/f190-queue-admission-demo.php` exercita os controles SEC-61 e o novo estado de admissão no contrato de supervisão F189 como um fluxo completo, não apenas casos unitários isolados:

1. Dois pedidos de leitura sintéticos são enfileirados; um recebe lease curto.
2. O operador fictício pausa admissões; o prazo do primeiro lease expira durante a pausa. A fila **não reserva** o segundo job, mas registra a expiração do primeiro como **INDETERMINADO**.
3. Duas observações sintéticas concordantes são registradas sobre o resultado desconhecido, que continua **REVIEWED_UNRESOLVED** e **INDETERMINADO**.
4. O operador fictício retoma a fila com outra decisão. Somente o pedido que ainda estava pendente é reservado e encerrado como `CONCLUIDA`; o job expirado **jamais é reenfileirado**.

O CLI retorna **um JSON seguro**, contrato `SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1`, com duas fotografias do contrato `SYNTHETIC_QUEUE_SUPERVISION_V1`: `paused` e `resumed`. Contagens de trabalho e revisão, `admission.state=PAUSED|OPEN`, `NO_GO`, `real_authorization=false`, `external_calls=0` e `write_calls=0`. Não inclui referência de Run, empresa, lead, revisor, claim, decisão, motivo, token ou campo bruto de entrada.

## Invariantes reproduzíveis

| Campo | Na pausa | Após retomada |
| --- | ---: | ---: |
| Admissão | PAUSED | OPEN |
| Pendentes | 1 | 0 |
| Concluídos | 0 | 1 |
| Indeterminados | **1** | **1** |
| Revisados sem conclusão | **1** | **1** |
| Autorização real | false | false |

A demonstração verifica também que há exatamente um evento de pausa e um de retomada, sem apagar a trilha da incerteza. Não aceita stdin, variáveis de ambiente, usuários ou credenciais, e qualquer falha retorna somente `SYNTHETIC_F190_DEMO_FAILED` no stderr (sem diagnósticos privados).

Os **sete testes Python** em `tests/Contracts/test_f190_queue_pause_demo.py` validam saída fechada, contagens, incerteza não resolvida, ausência de IDs, independência de stdin, inexistência de chamadas externas e determinismo. O comando entra no gate offline.

```bash
php examples/f190-queue-admission-demo.php
python3 -m unittest discover -s tests/Contracts -p 'test_f190_queue_pause_demo.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

**Gate proposto:** 835 testes PHP e lint 103 arquivos PHP, contratos Python e smoke Node; a contagem de casos PHP não muda, pois o incremento é um CLI de exemplo, sete testes Python e documentação.

## Condições de operação real que permanecem pendentes

CRM PR #57 OPEN/DRAFT, 13 cenários reais OP-01 `NAO_EXECUTADO`, gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. `PAUSED` e `OPEN` são estados de um único objeto PHP fictício, não comandos administrativos de clientes. Faltam IAM, fila persistente/distribuída, logs duráveis, teste bilateral com CRM, revisão independente de AppSec/Infra, rotinas LGPD e liberação formal. Sem `main`, VPS, deploy, clientes, credenciais ou custos.
