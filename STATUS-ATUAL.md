# Imagine AI Agents — estado atual

**18/11/2026 UTC. Produção: NO-GO.** Desenvolvimento em candidatas isoladas; `main` e ambientes operacionais sem alteração.

## Estado executivo

A candidata atual deste checkout é `feat/aia-faisca-r05-public-bridge-2026-11-18`, filha da P67 `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`. O recorte Faísca R05 liga processos Node e PHP com provedor sintético, sem habilitar modelo externo ou efeitos CRM. O inventário revisto para o gate desta candidata é **131 arquivos PHP e 879 casos legados**, com verificação R05 adicional por processos PHP reais. Resultados de execução pertencem à SHA registrada na PR; desenvolvimento concluído não significa CI já executada.

O índice histórico da linha `dev/ft01-aia-core-mocks-2026-11-06` continua abaixo: **879 testes PHP / lint 110 + Python/DOM** identifica aquele baseline integrado, não o inventário desta candidata. As provas sintéticas e a homologação isolada não concedem GO operacional.

## Faísca R05 — ponte pública sintética entre processos — 18/11/2026

A continuidade Faísca aprovada acrescenta, em branch filha da P67 `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`, um CLI PHP de invocação única para o servidor Node de laboratório do Comercial. O PHP recebe hashes e referências curadas, executa os componentes originais `MockKnowledgeRetriever`/`MockContextEnvelope` e `SupervisedCoordinator`/`MockAiSuggestionProvider`, revalida o contexto na entrega e devolve recibo estritamente correlacionado. O texto final permanece editorial do Comercial; nenhum texto livre, token HTTP, cliente ou credencial passa ao provider PHP.

A política pública é versionada e pinada, o contexto `SYNTHETIC_FAISCA_*` é fixo e um gateway deny-only impede efeitos ou consulta CRM. O CLI permanece desligado sem flag explícita. Contraprovas incluem schema/autoridade/fontes/versão, callback, revogação, atraso, resposta truncada e saída após despacho real. A candidata acrescenta dois PHP ao inventário (131 no recorte do gate), preserva os 879 testes legados e exige testes R05 com processos PHP reais na CI. Resultados efetivamente executados são registrados na PR da SHA testada.

[Contrato, implementação e comandos](docs/FAISCA-R05-PUBLIC-BRIDGE.md). Não altera Central, OP-01, migrações, dados reais ou gates existentes. **DRAFT / NO-GO operacional; sem merge, deploy, VPS, modelo externo ou despesas.**

## Continuidade funcional P62–P67

A linha Agents preserva a composição temporária com a Central P30 `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8`, sem merge entre as linhas. [P30 #464](https://github.com/rafhael100/imagine-ai-agents/pull/464), [CI 44203383716](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203383716).

| Marco | Comportamento e evidência registrada |
| --- | --- |
| P62 #465 | Fila, propostas, autoria, pareceres e histórico no mesmo snapshot. SHA `bb1a0e5a348ec68fde6a249f4e081750bc73aeca`; [RC73 / CI 44203592593](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203592593). [Contrato](docs/reviews/AIA-P62-LIVE-SIGNED-REVIEW-SNAPSHOT.md). |
| P63 #466 | Preserva insumos canônicos e reproduz o lote pelo motor original; legado sem insumos fica indisponível. SHA `6b65a109799df5f34e6431474ba8f3e58cb0fccc`; [RC74 / CI 44205251055](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44205251055). [Contrato](docs/reviews/AIA-P63-VERIFIABLE-PROPOSAL-DETAIL.md). |
| P64 / RC75 | Criação local com prévia, confirmação por sessão e gravação atômica de lote, rascunhos e autoria sintética; writer limitado a três tabelas, leitura separada, replay sem duplicar/reparar autoria. [Contrato e limites](docs/reviews/AIA-P64-LOCAL-AUTHORED-DRAFT-JOURNEY.md). |
| P65 #471 / RC76 #473 | Consumidor mínimo: 6 positivos/85 negativos, SHA `740abafc813dbde70f5a2ad6e138402ed7945276`, [CI 44213754313](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44213754313). RC76 SHA `bd50ab49113e775490cb55d76b088731ec108cc9`, [CI 44217619259](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44217619259), árvore combinada `cd4b6fba353a20f02b00facec4614be4f052a18f` com P30. [Evidência](docs/reviews/AIA-RC76-P65-COMBINED-EVIDENCE.md). |
| P66 / RC77 | Identidade Imagine, teclado e responsividade de criação/fila/detalhe, conforme [issue #289](https://github.com/rafhael100/imagine-ai-agents/issues/289). Writer usa `Referrer-Policy: same-origin`, mantendo Origin/Host/CSRF e negativa para origem `null`. [Escopo e prova visual](docs/reviews/AIA-P66-IMAGINE-OPERATOR-UI.md). |
| P67 | Recebimento mTLS fechado, operações/chaves distintas, persistência, idempotência e autoria histórica atestada pelo owner CRM na homologação fictícia aprovada. CRM mantém intenção commitada antes do transporte e reconciliação por consulta sob autoridade atual. [Contrato e evidência por SHA](docs/reviews/AIA-P67-CRM-OWNER-DRAFTS.md). |

A jornada é entrada → prévia → guardar rascunhos → fila → detalhe verificável. Pareceres seguem observações: `can_approve=false`, `can_execute=false`. O motor comercial e os contratos sintéticos anteriores permanecem preservados.

O recorte humano aprovado no CRM está registrado em [#649](https://github.com/rafhael100/imagine-crm/pull/649) e [#651](https://github.com/rafhael100/imagine-crm/pull/651): sessão/MFA/empresa/participação verificadas, referência/etapa como dados de negócio, auditoria transacional e resumo em três idiomas. A revisão `d3f06599cddfc244f6089d3dac01f29e5a45b8c0` registrou 68 leituras HTTP, 247 verificações Chromium e regressão privada completa. Isso não encerra os treze cenários históricos S2S nem libera produção. Identidade/permissões reais entre serviços, MFA administrativo obrigatório, custódia de backup externo e pareceres operacionais continuam dependências explícitas. **DRAFT / NO-GO operacional; sem merge, deploy, clientes ou APIs pagas.**

## Índice histórico da linha integrada

Estas métricas e CIs são marcos datados, preservados para rastreabilidade. O estado integral anterior à R05 está no [arquivo de 18/11/2026](docs/historico/STATUS-ATUAL-ANTES-FAISCA-R05-2026-11-18.md); não substituir a HEAD da candidata por esses baselines.

| Marco integrado | Evidência e baseline histórico |
| --- | --- |
| EVID-43 a EVID-48 | [EVID-43](docs/AIA-R01-EVID-43-SEC61-F189-F190-CI-POSTMERGE.md), [EVID-44](docs/AIA-R01-EVID-44-F191-F195-CI-POSTMERGE.md), [EVID-45](docs/AIA-R01-EVID-45-F196-F198-CI-POSTMERGE.md), [EVID-46](docs/AIA-R01-EVID-46-F199-CI-POSTMERGE.md), [EVID-47](docs/AIA-R01-EVID-47-F200-F203-CI-POSTMERGE.md), [EVID-48](docs/AIA-R01-EVID-48-F204-CI-POSTMERGE.md). Baseline 879 testes PHP / lint 110 + Python/DOM; CIs 43038243942 e 43088648397. |
| EVID-49 | #293 / 43605185307; #294 / 43605619299. [Evidência](docs/AIA-R01-EVID-49-CRM-AGENTS-POSTMERGE.md). |
| EVID-50 | #296 / 43606939495; #297 / 43607272003. [Evidência](docs/AIA-R01-EVID-50-REAL-FACADE-CORE-CRM-POSTMERGE.md). |
| EVID-51 / EVID-52 | #299 / 43609511492; #300 / 43610007078; #301 / 43610242488. [EVID-51](docs/AIA-R01-EVID-51-OP01-CAMINHO-CRITICO-POSTMERGE.md), [EVID-52](docs/AIA-R01-EVID-52-F215-F220-POSTMERGE.md). |
| SEC-60 / F187 | #261 / 43019868566; #262 / 43020983307; 810 testes PHP / lint 100. [EVID-42](docs/AIA-R01-EVID-42-SEC60-F187-CI-POSTMERGE.md). |
| F184–SEC-59 | #255–259 / 43013609438; 800 testes PHP. [EVID-41](docs/AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md). |
| SEC-57–F183 | #250 / 42980115062; #251 / 42980489349; #252 / 42981149687; #253 / 42981975813; 776 testes PHP. [EVID-40](docs/AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md). |
| OP-01 | #239 / 42949459592; #240 / 42949959755; #241 / 42950862393; #242 / 42951498940; 699 testes PHP. [EVID-39](docs/AIA-R01-EVID-39-OP01-QUEUE-PILOT-POSTMERGE.md). |
| SEC-53 integrada à dev (PR #237) | Mutação de Run bloqueada; 648 testes PHP, lint de 83 arquivos PHP; CI 42945854088. [EVID-38](docs/AIA-R01-EVID-38-SEC53-CI-POSTMERGE.md). |
| SEC-52 integrada à dev (PR #235) | Guarda de callback compartilhada; 640 testes PHP; CI 42943113650. [EVID-37](docs/AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md). |
| SEC-51 integrada à dev (PR #233) | Reentrada de callback negada; 633 testes PHP; CI 42940604581. [EVID-36](docs/AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md). |
| SEC-50 integrada à dev (PR #230) | Revogação revalidada após ferramenta; 629 testes PHP; CI 42938703131. [EVID-35](docs/AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md). |
| SEC-49 integrada à dev (PR #228) | Epoch do trust compartilhado. [EVID-34](docs/AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md). |
| SEC-48 integrada à dev (PR #226) | READ aninhada negada antes da auditoria positiva; CI 42924834951. [EVID-33](docs/AIA-R01-EVID-33-SEC48-CI-POSTMERGE.md). |
| SEC-47 integrada à dev (PR #224) | Recheck monotônico por recibo; CI 42923158871. [EVID-32](docs/AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md). |
| SEC-46 integrada à dev (PR #222) | Recibo e revisão contra ABA; CI 42918687251. [EVID-31](docs/AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md). |
| SEC-45 integrada à dev (PR #220) | Autorização revalidada depois da sugestão, antes da conclusão; CI 42913437800. [EVID-30](docs/AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md). |
| SEC-44 integrada à dev (PR #218) | Invariante de projeção comum à ferramenta, edge, fachada e leitor; CI 42906719897. [EVID-29](docs/AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md). |
| SEC-43 integrada à dev (PR #216) | Autorização revalidada depois da leitura. [EVID-28](docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md). |
| SEC-42 integrada à dev (PR #214) | Parser OP-01 com matriz adversarial e paridade PHP offline. [Contrato](docs/AIA-FT01-SEC-42-PARIDADE-MUTACOES-OP01.md). |
| SEC-41 integrada à dev (PR #213) | Projeção OP-01 com validação comum de borda, resposta e telemetria. [EVID-27](docs/AIA-R01-EVID-27-SEC41-42-POSTMERGE.md). |
| F163 / EVID-25 integrado à dev (PR #211) | 13 cenários ensaiados offline; homologação real NAO_EXECUTADO. [EVID-25](docs/AIA-R01-EVID-25-ENSAIOS-OP01-13-CENARIOS.md), [EVID-26](docs/AIA-R01-EVID-26-F163-CI-POSTMERGE.md). |
| SEC-40 integrada à dev (PR #209); SEC-39 integrada à dev | Limites de manifestos e projeções de sugestão; 533 testes PHP; CI 42889307984. [EVID-24](docs/AIA-R01-EVID-24-CONSOLIDACAO-SEC39-40.md). |

SEC-20 a SEC-38 consolidaram projeção, objetos canônicos, limites de domínio/uso/catálogos, evidências monotônicas, parser e concessões limitadas. Todas as descrições, PRs, CIs e referências originais estão no [status integral anterior à R05](docs/historico/STATUS-ATUAL-ANTES-FAISCA-R05-2026-11-18.md). F1-30 / SEC-16..19 (PRs #173, #174, #175, #177, #178) e EVID-16 (PR #179) permanecem no [arquivo integral de 10/11/2026](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md).

## Reproduzir o recorte R05 em checkout revisado

```bash
php -l tools/faisca_public_bridge.php
php -l tools/check_faisca_public_bridge.php
php tools/check_faisca_public_bridge.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=131 EXPECTED_CASES=879 bash tools/run_r01_offline_quality_gate.sh
```

PHP e Python são obrigatórios. A verificação R05 executa processos PHP reais e não admite substituto ou skip. O gate legado permanece inalterado; os dois novos PHP em `src/Integration/Faisca` elevam o inventário revisado de 129 para 131. CI aprovada comprova apenas o recorte da SHA testada e não abre gates operacionais. O [contrato R05](docs/FAISCA-R05-PUBLIC-BRIDGE.md) detalha os comandos e os limites.

## Gates e pacote histórico CRM ↔ Agents

O pacote formal de 10/11/2026 foi integrado em [PR #184](https://github.com/rafhael100/imagine-ai-agents/pull/184), [CI 42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072): [JSON bilateral](docs/evidencias/op01-pacote-aceite-bilateral.json), 7 decisões OP01-R01..R07, 14 gates e 13 cenários reais NAO_EXECUTADO. [EVID-19](docs/AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md). Foi comunicado no CRM PR #57 sem aprovação ou merge.

O registro histórico do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é **OPEN/DRAFT, não integrado e não homologado** em 10/11/2026. [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md) preserva a reconciliação documental (HEAD `97bc1e8d6ce4cf4b52378472c31cb49de6a66d51`, blob `cefb390ec41a76d486b137279d92627f097d1d47`). A aprovação posterior de recortes isolados P62–P67 não equivale ao aceite dos treze cenários S2S nem à liberação de produção.

**R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO no escopo operacional histórico.** As dependências são:

- G01/G03 e AUT/SEG: identidade entre serviços, issuer/audience, principal/tenant, expiração, revogação e anti-replay distribuído.
- G02 e CORE/SEG: autorização final do backend CRM por tenant, membership, recurso, finalidade e campo, com revogação e isolamento multiempresa.
- G05/G08: campos reais, base legal, minimização, retenção, schema/versionamento, erros e responsabilidades.
- G07/G10: ambiente isolado, segredos, revisão independente, testes positivos/negativos, recuperação e rollback.
- G11: responsáveis, limites financeiros e autorização específica de piloto; produção exige aceite adicional.

Nenhum teste sintético, CI verde ou comentário em PR substitui autorização e prova de runtime no escopo correspondente. Sem clientes, segredos, chamadas pagas, VPS, produção ou alteração de `main` nesta entrega.

## Referências e preservação integral

- [Roadmap](docs/ROADMAP.md): sequência de decisões e critérios de saída.
- [Status integral antes da R05](docs/historico/STATUS-ATUAL-ANTES-FAISCA-R05-2026-11-18.md): cópia byte a byte de `STATUS-ATUAL.md` na base P67 `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`; conserva os 21.426 caracteres e todas as evidências anteriores a esta condensação.
- [Status integral anterior à consolidação de 10/11](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) e [roadmap histórico](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md): registros antigos preservados, sem reinterpretar métricas como estado atual.
- [EVID-16](docs/AIA-R01-EVID-16-SEGURANCA-TOOL-397.md), [EVID-15](docs/AIA-R01-EVID-15-HANDOFF-READ-TOOL-389.md) e [EVID-14](docs/AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md): dossiê e handoffs anteriores.
