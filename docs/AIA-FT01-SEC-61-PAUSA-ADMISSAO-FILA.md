# FT01/SEC-61 — suspensão e retomada de admissões da fila sintética

**11/11/2026. Apenas laboratório PHP em memória. Não é um botão de produção, nem autenticação. Piloto real/CRM: NO-GO.**

## Capacidade implementada

`MockReadOnlyRunQueue` ganhou controle local de admissão, via `pauseAdmission(context, decisionRef)`, `resumeAdmission(context, decisionRef)` e `admissionState(context)`. O controle usa contexto do mesmo tenant fictício, referências sintéticas estritas, trilha de eventos minimizados e um limite de **32 decisões de controle**, com uma decisão final reservada para suspensão de emergência.

- **Pausa:** novos `enqueue` recebem `QUEUE_ADMISSION_PAUSED`; `claim` não reserva jobs `PENDENTE`. Jobs antigos **não são apagados ou automaticamente reenfileirados**. Retorno de controle: `SYNTHETIC_QUEUE_CONTROL_V1`, `state=PAUSED`, `operational_decision=NO_GO`, `real_authorization=false`, zero chamadas externas/escritas.
- **Leases em andamento:** `finish` do claim válido continua permitido dentro do prazo; `cancel` de job ainda pendente continua possível. Um polling enquanto a fila está pausada ainda transforma reservas **expiradas** em `INDETERMINADO`, sem reservar outra tarefa. Revisão de resultado desconhecido prevalece.
- **Visibilidade correta:** `MockQueuedReadOnlyPilot::runNext` retorna `PAUSED` (e não `EMPTY`) quando não pode reservar e não existem casos indeterminados prioritários. O `MockQueuedBatchRunner` encerra a rodada com `processed=0` e `state=PAUSED`, sem consumir novas provas fictícias.
- **Retomada explícita:** somente uma nova referência de decisão permite voltar a `OPEN`; replays exatos de pausa ou retomada em estado compatível são idempotentes e não criam novos eventos. Referências de decisão antigas não podem reexecutar uma transição depois que o estado mudou, nem trocar PAUSE por RESUME.
- **Auditoria conservadora:** cada transição legítima registra `SYNTHETIC_ADMISSION_PAUSED` ou `SYNTHETIC_ADMISSION_RESUMED` com identificador fixo mascarado, não o ID da decisão. Retomada é negada se falta espaço para auditoria ou se esgotaria a última decisão reservada para pausa. Se uma pausa emergencial ocorrer com trilha já saturada, ela ainda põe a fila em estado seguro de bloqueio, retornando erro auditável `QUEUE_AUDIT_CAPACITY_DENIED`; não há retomada silenciosa sem capacidade de auditoria.
- **Callbacks:** a fila vinculada a um `SupervisedCoordinator` continua exigindo `assertProviderCallbackIdle` antes de mutação. Uma tentativa feita por um provedor capturada em callback bloqueia a sugestão externa. Consultas de estado são read-only e não autorizam nada.

## Regressões U92-01..17

Os 17 testes cobrem visão minimizada inicial, bloqueio de enqueues/claims, cancelamento e finish com pausa, detecção de lease expirado, retomada FIFO sem reenvio, contexto estrangeiro, IDs malformados/excessivos, replay de decisão antiga, conflito de verbos, idempotência sem eventos duplicados, estados incompatíveis, limite e reserva emergencial, piloto com `PAUSED`, batch sem leitura e precedência de revisão, além de negação a pausa dentro do callback de IA.

Comando de desenvolvimento:

```bash
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=101 EXPECTED_CASES=827 bash tools/run_r01_offline_quality_gate.sh
```

**Gate proposto: 827 testes PHP / lint 101 arquivos PHP + Python/Node offline**, sujeito a CI de PR e pós-merge. É um controle técnico em um objeto PHP do mesmo processo, sem API, persistência, IAM, auditoria inviolável, cluster ou disponibilidade real.

## Restrições e liberação

CRM PR #57 permanece OPEN/DRAFT; 13 cenários reais OP-01 `NAO_EXECUTADO`, gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Não houve merge na `main`, VPS, deploy, novos custos, clientes, dados pessoais ou uso de segredos. Pausas operacionais reais precisarão de autenticação forte, RBAC por empresa, trilha durável, motivo e aprovação formal, proteção contra replay distribuído e integração bilateral homologada.
