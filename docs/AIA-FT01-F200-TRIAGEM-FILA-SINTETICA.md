# FT01/F200 — Triagem agregada da fila sintética (núcleo PHP)

**11/11/2026. Laboratório offline; não é IAM, CRM real, worker, autorização nem homologação. Produção: NO-GO.**

## Funcionalidade

A classe `MockQueueTriage` agora produz um relatório mínimo, determinístico e sem identificadores a partir do `MockUncertaintyReviewBoard::overview()`. O relatório reúne contagens da fila, distribuição das revisões e estado de admissão com os códigos de observação:

| Precedência | Situação | Estado | Indicação local |
|---|---|---|---|
| 1 | Admissão pausada | `PAUSED` | `INSPECT_PAUSE` |
| 2 | Alguma execução indeterminada | `REVIEW_REQUIRED` | `INSPECT_UNCERTAINTY` |
| 3 | Reserva em andamento | `LEASE_IN_PROGRESS` | `OBSERVE_LEASE` |
| 4 | Pedido aguardando | `PENDING` | `OBSERVE_PENDING` |
| 5 | Sem trabalho ativo ou pendente | `QUIET` | `NO_LOCAL_WORK` |

Todos são **rótulos informativos de um simulador**. Mesmo `QUIET` retorna `operational_decision: NO_GO`, `real_authorization: false`, `external_calls: 0` e `write_calls: 0`.

A triagem valida as oito chaves do contrato de entrada, seis contagens de fila, quatro de revisão, limite agregado de 64 casos, inteiros não negativos e igualdade entre revisões e resultados indeterminados. O acesso respeita tanto o tenant da triagem quanto a ligação interna entre quadro de revisão e fila. Não há mutação, relógio, novas admissões, coleta de identidade nem auditoria adicional.

## Testes e execução

O PHP oficial executa 16 novos cenários `U100-01..16`: vazio, pendente, reserva, expiração, pausa, retorno, dois pareceres, conflito, conclusão, cancelamento, contexto cruzado, erro de conexão entre board/fila, ausência de IDs e leitura repetida sem alteração de estado.

A contagem desta entrega passa de **835 para 851 testes PHP** e de **103 para 105 arquivos com lint**. O gate único, workflow GitHub, status técnico, contratos de teste e contadores do laboratório F108 foram atualizados juntos. A nova base depende de CI verde no SHA revisado, não implica aprovação de produção.

```bash
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=105 EXPECTED_CASES=851 bash tools/run_r01_offline_quality_gate.sh
```

CRM PR #57 permanece OPEN/DRAFT, 13 cenários OP-01 reais NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. A `main`, a VPS, clientes, deploy, segredos, dados reais e custos não sofrem alteração.
