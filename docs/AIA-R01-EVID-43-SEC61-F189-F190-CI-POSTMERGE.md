# R01/EVID-43 — Pausa de fila, supervisão e ensaio: SEC-61, F189 e F190

**11/11/2026. Laboratório PHP/HTML offline, sem CRM real. Operação/piloto/produção: NO-GO.**

## Evidências GitHub e entregas integradas

| Entrega | PR integrado | SHA de merge squash na dev | CI de PR | CI pós-merge | Gate |
| --- | --- | --- | --- | --- | --- |
| SEC-61 — pausas de admissão, preservação de leases, controle idempotente | [#264](https://github.com/rafhael100/imagine-ai-agents/pull/264) | `91381e2eb6d242461a472e79bc6568d35a8bdd8b` | [#43035421839](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43035421839) **success** | [#43035537495](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43035537495) **success** | 827 PHP / lint 101 + Python/DOM |
| F189 — `admission: {state: OPEN|PAUSED}` no overview e na tela | [#265](https://github.com/rafhael100/imagine-ai-agents/pull/265) | `4c4bcea504a6375f1497d1ff9d1b0dbad780849f` | [#43037196533](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43037196533) **success** | [#43037555601](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43037555601) **success** | 835 PHP / lint 102 + Python/DOM |
| F190 — CLI PHP pausa, expira, revisa sem resolver, retoma | [#266](https://github.com/rafhael100/imagine-ai-agents/pull/266) | `768ef0b14c267d3b4c927311875fe52498812a55` | [#43038088806](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43038088806) **success** | [#43038243942](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43038243942) **PENDENTE_CONFIRMACAO** | 835 PHP / lint 103 + Python/DOM |

**A CI pós-merge #43038243942 precisa constar como `success` no SHA exato `768ef0b14c267d3b4c927311875fe52498812a55` antes de marcar F190 como pós-merge verificado.** A aprovação pré-merge isolada é insuficiente.

## Avanço técnico

**SEC-61** acrescentou `pauseAdmission()`, `resumeAdmission()` e `admissionState()` à fila local `MockReadOnlyRunQueue`, com contexto vinculado, decisões idempotentes, auditoria redigida e limites fechados. Pause bloqueia novos enqueues e claims sem impedir finish/cancel válidos ou expiração para `INDETERMINADO`. O piloto retorna PAUSED em vez de EMPTY, e o batch não consome outra prova enquanto suspenso. São 17 regressões U92-01..17. **Não existe autenticação operacional** nesse mock.

**F189** sincronizou os contratos sintéticos de supervisão PHP/JS: `MockUncertaintyReviewBoard::overview()` agora inclui o estado de admissão OPEN/PAUSED. O exemplo PHP F182 e o JSON estático da aba F183 são idênticos pelo teste Python; a interface mostra o estado fictício sem alegar disponibilidade real. Oito regressões U93-01..08 cobrem pausa, retomada, lease incerto, redigibilidade, contexto estranho e ausência de leituras.

**F190** adicionou `examples/f190-queue-admission-demo.php`: dois jobs fictícios, primeiro lease expira durante pausa, dois votos concordam apenas sobre a incerteza, segundo job só conclui após RESUME. O CLI publica duas fotografias redigidas `SYNTHETIC_QUEUE_SUPERVISION_V1` dentro do contrato `SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1`; `INDETERMINADO` permanece 1 nas duas. Os sete testes Python novos verificam determinismo, estados, ausência de IDs e invariantes, sem rede ou storage.

## Bloqueios operacionais que continuam inalterados

- Gate atual do laboratório: **835 testes PHP / lint 103** mais Python, smoke DOM Node e verificadores locais. Nunca equivale a homologação de um CRM real.
- Branch `dev/ft01-aia-core-mocks-2026-11-06` é o único destino das integrações. `main` permanecia em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` na verificação desta rodada.
- O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) foi conferido **OPEN/DRAFT, merged:false**; os 13 cenários reais OP-01 seguem `NAO_EXECUTADO` nas evidências, e os gates R01-G01..G11/CORE/SEG/AUT estão **NO-GO**.
- Não foram autorizados autenticação de serviço, IAM real, fila persistente distribuída, dados de clientes, deploy, VPS, produção, credenciais ou novos gastos. Para GO serão necessários revisão independente de segurança/privacidade/infra, auditoria durável, prova bilateral, controle de acesso, planos de rollback e homologação operacional formal.

Evidências anteriores [EVID-40](AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md), [EVID-41](AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md) e [EVID-42](AIA-R01-EVID-42-SEC60-F187-CI-POSTMERGE.md) preservadas. Nenhum dado desta evidência confirma serviço real disponível.
