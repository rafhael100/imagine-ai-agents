# Imagine AI Agents — estado atual

**Consolidação das candidatas verificadas por SHA/run. Produção: NO-GO.**

## Estado executivo

**Fase vigente:** integração CRM → Agents executada em homologação fictícia isolada, com jornada humana de sessão/MFA, planejamento comercial nativo e criação/consulta de rascunhos pelo `owner` ativo. A autorização específica desse fluxo já foi dada; não é necessário decidir novamente quem pode criar ou consultar. A continuidade autorizada de desenvolvimento permanece, com limites de escopo e evidência por SHA.

As quatro candidatas abaixo estão **OPEN/DRAFT, sem merge**. Esta atualização registra autorização e resultados existentes; não concede autoridade de runtime nem autoriza instalação, deploy, produção ou gastos.

## Candidatas verificadas

| Recorte | HEAD verificada | Evidência aprovada |
| --- | --- | --- |
| [Agents P67 #476](https://github.com/rafhael100/imagine-ai-agents/pull/476): receptor de rascunhos com autoria e idempotência | `0c722eee6bd53a7ee46c4ba68ab70200fc191b78` | [CI 44230030310](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44230030310) |
| [Agents P68 #477](https://github.com/rafhael100/imagine-ai-agents/pull/477): recuperação descartável dos seis conjuntos P67 | `85f4c37a6d905b91923d3e31ba9068b2c35d7657` | [CI 44233999376](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44233999376) |
| [CRM #658](https://github.com/rafhael100/imagine-crm/pull/658): jornada owner e recuperação inerte do addon | `e390c56af2a544d8191cce86752637efe6a35e43` | [CI 44237380307](https://github.com/rafhael100/imagine-crm/actions/runs/44237380307) |
| [CRM #659](https://github.com/rafhael100/imagine-crm/pull/659): comparação correta da ACL padrão de sequências no backup legado | `b86f6f7005b3c8aa79e64fa8dfe9abecab99ad01` | [CI 44234701904](https://github.com/rafhael100/imagine-crm/actions/runs/44234701904) |

A CRM #658 executou o receptor **original P67 fixado na SHA acima**, com 407 verificações/52 requisições HTTP e 607 verificações Chromium, quatro PNGs e três idiomas. Sessão/MFA, fonte nativa, empresa resolvida no backend, member recusado e consultas sob autoridade atual foram exercitados na aplicação real com dados fictícios. A prova humana é **externa, no CRM**: os ensaios internos P67/P68 continuam declarando corretamente `human_crm_session_verified=false`; o P67 também conserva `crm_rule_source_verified=false`. [Contrato e separação das evidências](docs/reviews/AIA-P67-CRM-OWNER-DRAFTS.md).

Na mesma HEAD CRM #658, passaram as regressões [OP-01](https://github.com/rafhael100/imagine-crm/actions/runs/44237380328), [jornada privada](https://github.com/rafhael100/imagine-crm/actions/runs/44237380315), [backup base/S2S](https://github.com/rafhael100/imagine-crm/actions/runs/44237380311) e [reconciliação P22](https://github.com/rafhael100/imagine-crm/actions/runs/44237380304). A recuperação própria do addon passou 836 verificações, 27 tabelas, 13 negativas SQL, 18 mutações negativas e 15 equivalências fechadas de representação PostgreSQL; os catálogos brutos são explicitamente diferentes. A #659 preserva a recusa do addon no backup legado. [Limites da recuperação Agents e da evidência CRM](docs/reviews/AIA-P68-OWNER-DRAFT-RECOVERY.md).

**Próximo marco concreto:** disponibilizar o mesmo fluxo owner criar/consultar em homologação persistente, com o contrato, a autoridade atual e a autoria histórica já definidos. Preparar a mudança revisável e a validação da jornada nesse ambiente, sem reabrir a decisão de papel do owner. A configuração e a disponibilidade do ambiente alvo devem ser verificadas; estas CIs não demonstram falta de infraestrutura, R2, MFA ou credenciais. Não há alegação de recuperação distribuída, RPO zero ou GO operacional.

## Registro histórico da linha offline — posição de 14/11/2026

As seções históricas preservam decisões, métricas e pendências das respectivas datas. Expressões como “offline”, “CRM não executado” ou “nenhuma rota autorizada” nesses registros não substituem o estado e a autorização específica das candidatas acima.

**Fase registrada naquela data:** offline.

**Branch:** `dev/ft01-aia-core-mocks-2026-11-06`; `main` intacta. **879 testes PHP / lint 110 + Python/DOM**; EVID-43,EVID-44,EVID-45,EVID-46,EVID-47,EVID-48,EVID-49,EVID-50,EVID-51,EVID-52; #293/#294 (43605185307,43605619299), #296/#297 (43606939495,43607272003), #299/#300/#301 (43609511492,43610007078,43610242488); 43038243942,43088648397. **NO-GO**.

## Entregas históricas verificadas da linha offline

**SEC-60/F187:** #261 CI #43019868566 (cross-Run); #262 CI #43020983307 (JSON), **810 testes PHP / lint 100**. [EVID-42](docs/AIA-R01-EVID-42-SEC60-F187-CI-POSTMERGE.md). **NO-GO**.

**F184–SEC-59:** #255–259, CI #43013609438, **800 testes PHP**; [EVID-41](docs/AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md). CRM real **NO-GO**.

**SEC-57–F183:** #250 CI #42980115062; #251 CI #42980489349; #252 CI #42981149687; #253 CI #42981975813 (**776 testes PHP**). [EVID-40](docs/AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md). **NO-GO**.

**OP-01:** #239 CI #42949459592; #240 CI #42949959755; #241 CI #42950862393; #242 CI #42951498940 (**699 testes PHP**). [EVID-39](docs/AIA-R01-EVID-39-OP01-QUEUE-PILOT-POSTMERGE.md). **NO-GO**.

**SEC-53 integrada à dev (PR #237):** baseline **648 testes PHP**, lint de **83 arquivos PHP**, [CI #42945854088](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42945854088); bloqueia alteração de Run, U75-01..08, [SEC-53](docs/AIA-FT01-SEC-53-RUN-LIFECYCLE-READONLY-CALLBACK.md), [EVID-38](docs/AIA-R01-EVID-38-SEC53-CI-POSTMERGE.md). CRM `NAO_EXECUTADO` / **NO-GO**.

**SEC-52 integrada à dev (PR #235):** baseline **640 testes PHP**; [CI #42943113650](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42943113650), guarda compartilhada; sete U74-01..07, [SEC-52](docs/AIA-FT01-SEC-52-GUARDA-COORDENADOR-CALLBACK.md), [EVID-37](docs/AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md). CRM `NAO_EXECUTADO` / **NO-GO**.

**SEC-51 integrada à dev (PR #233):** **633 testes PHP**, [CI #42940604581](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42940604581); reentrada negada, U73-01..04, [SEC-51](docs/AIA-FT01-SEC-51-REENTRADA-FERRAMENTAS-CALLBACK.md), [EVID-36](docs/AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md). **NO-GO**.

**SEC-50 integrada à dev (PR #230):** baseline **629 testes PHP**, U72-01..08, [CI #42938703131](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42938703131), [SEC-50](docs/AIA-FT01-SEC-50-CAPABILITY-RECHECK-POS-FERRAMENTA.md), [EVID-35](docs/AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md). Revogação pós-tool; CRM **NO-GO**.

**SEC-49 integrada à dev (PR #228):** epoch do trust compartilhado; oito U71-01..08, [SEC-49](docs/AIA-FT01-SEC-49-REVISAO-TRUST-COMPARTILHADO.md), [EVID-34](docs/AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md).

**SEC-48 integrada à dev (PR #226):** nega READ aninhada antes de auditoria positiva; U70-01..08. [SEC-48](docs/AIA-FT01-SEC-48-REENTRADA-LEITURA-OP01.md), [EVID-33](docs/AIA-R01-EVID-33-SEC48-CI-POSTMERGE.md), [CI #42924834951](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42924834951). CRM **NO-GO**.

**SEC-47 integrada à dev (PR #224):** recheck monotônico por recibo; U69-01..08. [SEC-47](docs/AIA-FT01-SEC-47-RECHECK-TEMPO-MONOTONICO.md), [EVID-32](docs/AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md), [CI #42923158871](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42923158871). CRM **NO-GO**.

**SEC-46 integrada à dev (PR #222):** revisão monotônica e recibo contra ABA; U68-01..12. [SEC-46](docs/AIA-FT01-SEC-46-RECIBO-REVISAO-OWNER-ABA.md), [EVID-31](docs/AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md), [CI #42918687251](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42918687251). CRM **NO-GO**.

**SEC-45 integrada à dev (PR #220):** revalida autorização sintética após a sugestão e antes de concluir o Run. Revogação no provedor termina como `FALHOU`, sem resumo, nova leitura ou segundo nonce; dez regressões U67-01..10. [SEC-45](docs/AIA-FT01-SEC-45-REVALIDACAO-POS-SUGESTAO.md); [EVID-30](docs/AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md), [CI #42913437800](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42913437800). Homologação CRM: `NAO_EXECUTADO` / **NO-GO**.

**SEC-44 integrada à dev (PR #218; [CI #42906719897](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42906719897)):** a ferramenta OP-01 também usa a mesma invariante de projeção das outras três camadas; 10 regressões U66-01..10 validam ferramenta → edge → fachada → leitor, revogações durante a leitura, replay e auditoria. [SEC-44](docs/AIA-FT01-SEC-44-FERRAMENTA-PROJECAO-COMUM.md), [EVID-29](docs/AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md). CRM real: `NAO_EXECUTADO` / **NO-GO**.

**SEC-43 integrada à dev (PR #216):** revalida autorização sintética do proprietário, credenciais e revogação compartilhada **após** a leitura e antes da auditoria de sucesso, sem consumir segundo nonce. Dez regressões U65-01..10. [SEC-43](docs/AIA-FT01-SEC-43-REVALIDACAO-POS-LEITURA.md), [EVID-28](docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md). Homologação real: `NAO_EXECUTADO` / **NO-GO**.

**SEC-42 integrada à dev (PR #214):** matriz adversarial determinística de mais de 200 entradas para o parser OP-01, comparada à execução PHP em lotes limitados; contrato estrito e sem rede. [SEC-42](docs/AIA-FT01-SEC-42-PARIDADE-MUTACOES-OP01.md). Os 13 cenários reais permanecem `NAO_EXECUTADO` / **NO-GO**.

**SEC-41 integrada à dev (PR #213):** a projeção OP-01 usa o mesmo contrato de validação na borda, resposta pública e telemetria. Leitores fictícios inconsistentes (inclusive valores de 1 MiB) não podem gerar auditoria de sucesso; nove novos testes U64-01..09. [SEC-41](docs/AIA-FT01-SEC-41-PROJECAO-OP01-UNIFICADA.md). Homologação CRM ainda `NAO_EXECUTADO` / **NO-GO**.

**F163 / EVID-25 integrado à dev (PR #211):** 13 cenários OP-01 ensaiados offline (U63-OP01-N01..N11/P01..P02), [EVID-25](docs/AIA-R01-EVID-25-ENSAIOS-OP01-13-CENARIOS.md), [EVID-26](docs/AIA-R01-EVID-26-F163-CI-POSTMERGE.md). Homologação real `NAO_EXECUTADO` / **NO-GO**.

[EVID-24](docs/AIA-R01-EVID-24-CONSOLIDACAO-SEC39-40.md): SEC-39/40; [CI #42889307984](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42889307984) aprovada, 533 testes PHP. Não autoriza CRM.

**SEC-40 integrada à dev (PR #209):** limites de manifestos/referências; U62-01..07, [SEC-40](docs/AIA-FT01-SEC-40-MANIFESTO-LIMITADO.md). Sem registro real de agentes.

**SEC-39 integrada à dev:** limita projeções sintéticas antes de chamar provedor, U61-01..08. [SEC-39](docs/AIA-FT01-SEC-39-LIMITES-SUGESTOES.md). Sem IA real.

**SEC-38 integrada à dev:** concessões de ferramenta sintética validam identificadores `id` e `toolId` com no máximo 90 bytes e formato fechado já na criação. Seis regressões U60-01..06 para 1 MiB, fronteira 90/91 bytes, padrões inválidos e revogação. [SEC-38](docs/AIA-FT01-SEC-38-CONCESSOES-LIMITADAS.md). Não implementa IAM real.

**SEC-37 integrada à dev:** parser OP-01 valida primeiro o total de 8 chaves e recusa referências com mais de 90 bytes antes do regex; não consome nonce ou auditoria de leitura para entradas inválidas. Sete regressões U59-01..07 com cargas grandes. [SEC-37](docs/AIA-FT01-SEC-37-PARSER-OP01-LIMITADO.md). Só dados sintéticos, sem API real.

**SEC-36 integrada à dev:** IDs longos de ferramenta são negados antes de consulta ao catálogo ou recursos e sua auditoria usa hash de marcador constante. Cinco regressões U58-01..05 (incluindo 1 MiB de entrada, borda 128/129 bytes, preservação de nonce e ferramenta válida posterior). [SEC-36](docs/AIA-FT01-SEC-36-IDS-FERRAMENTA-LIMITADOS.md). Sem gateway de rede.

**SEC-35 integrada à dev:** o coordenador também impede regressão `PENDENTE → INDETERMINADO` em respostas de adaptadores inconsistentes, sem substituir objetos de evidência já conhecidos. Oito regressões U57-01..08; normaliza respostas sem prova e mantém status-only após timeout. [SEC-35](docs/AIA-FT01-SEC-35-COORDENADOR-MONOTONICO.md). Sem CRM real.

**SEC-34 integrada à dev:** a versão sintética selecionada deixa de ser apresentada como ativa quando a revisão associada é revogada; consultas com relógio explícito também verificam validade temporal. Sete regressões U56-01..07. [SEC-34](docs/AIA-FT01-SEC-34-REVALIDACAO-REVISAO-ATIVA.md). Não aprova versões reais.

**SEC-33 integrada à dev:** impede regressão de evidências sintéticas de `PENDENTE` para `INDETERMINADO`, preserva replays não terminais sem alocar novo efeito e mantém resultados terminais imutáveis. Seis regressões U55-01..06. [SEC-33](docs/AIA-FT01-SEC-33-EVIDENCIAS-MONOTONICAS.md). Sem CRM ou IAM real.

**SEC-32:** limita a 256 efeitos sintéticos; [documentação](docs/AIA-FT01-SEC-32-EFEITOS-LIMITADOS.md). Sem CRM real.

**SEC-31:** limites locais de 256 fontes e 1.024 eventos; [documentação](docs/AIA-FT01-SEC-31-LIMITES-CONHECIMENTO.md). Sem RAG real.

**SEC-30 — catálogo limitado:** limites de modelos/snapshots fictícios sem apagar histórico. [Detalhes](docs/AIA-FT01-SEC-30-CATALOGO-LIMITADO.md). Sem agentes publicados.

**SEC-29 integrada à dev (PR #198):** limite de decisões e eventos do controle de versões simulado, com reserva de espaço para suspensão e seis regressões. [Evidência técnica SEC-29](docs/AIA-FT01-SEC-29-RESERVA-AUDITORIA-VERSOES.md). A execução segue offline e o CRM real continua **NO-GO**.

**SEC-28:** o quadro de revisão local limita registros de votação a 128 pacotes sem descartar decisões anteriores. O segundo parecer de pacotes já iniciados continua possível. [Detalhes e regressões](docs/AIA-FT01-SEC-28-REVISOES-BOUNDED.md). **Isto não é homologação real.**

**SEC-26 / PR #194** vinculou a identidade do consumo ao estado do transporte ([CI pós-merge #42863662530](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863662530) success). **SEC-27 / PR #195** limitou IDs e unidades no domínio de uso ([CI pós-merge #42864322239](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864322239) success). [EVID-23](docs/AIA-R01-EVID-23-IDENTIDADE-USO-446.md) registra evidência e limites. Os marcos anteriores abaixo são históricos.

A [SEC-25 / PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192) limitou o medidor de uso em memória e garantiu falha segura sem perda de idempotência; [CI pós-merge #42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) success. Ver [EVID-22](docs/AIA-R01-EVID-22-MEDIDOR-USO-433.md). Entregas anteriores abaixo são históricas.

[SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) aplicou limites a Intent/Approval ([CI pós-merge #42847828963](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42847828963) success). [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) estendeu os limites aos objetos básicos Run/Step ([CI pós-merge #42848621884](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42848621884) success). Ver [EVID-21](docs/AIA-R01-EVID-21-LIMITES-DOMINIO-427.md). Marcos anteriores abaixo são históricos.

As SEC-21 e SEC-22 foram integradas somente à `dev`: [PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) exige Run/Step canônicos na interface de ferramentas ([CI pós-merge #42836105212](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836105212) success); [PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) limita o tamanho dos IDs ([CI pós-merge #42836672922](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836672922) success). [EVID-20](docs/AIA-R01-EVID-20-RUN-STEP-CANONICO-414.md) apresenta o estado técnico vigente. Os marcos anteriores abaixo são históricos.

A [SEC-20 / PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) corrigiu a auditoria de projeções inconsistentes antes de registrar sucesso; [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) aprovada. Consulte [EVID-18](docs/AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md). O [README](README.md) atual foi consolidado e a versão completa anterior permanece arquivada.

**Marcos históricos:** F1-30 / SEC-16 a SEC-19 (PRs #173, #174, #175, #177, #178) e EVID-16 (PR #179) estão documentados no [arquivo integral](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md); CIs e decisões históricas permanecem preservadas.

**Reproduzir em checkout revisado:**

```bash
php examples/f127-read-only-pilot-demo.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=117 EXPECTED_CASES=879 bash tools/run_r01_offline_quality_gate.sh
```

O comando acima exige PHP e Python já instalados, não emite credenciais e não acessa CRM real. O sucesso não abre gates operacionais.

## Registro histórico — pacote formal de revisão entregue, sem aceite (10/11/2026)

O [PR #184](https://github.com/rafhael100/imagine-ai-agents/pull/184) integrou um [pacote JSON de aceite bilateral](docs/evidencias/op01-pacote-aceite-bilateral.json) que correlaciona **7 decisões OP01-R01..R07**, **14 gates** e **13 cenários não executados**, com propostas de responsáveis e evidências pendentes. A [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) passou. Consulte [EVID-19](docs/AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md).

O registro foi comunicado no CRM PR #57 **sem aprovação ou merge**. Os dados deste pacote não provam a execução de testes reais e permanecem **NO-GO**.

## Registro histórico — bloqueadores do pacote CRM ↔ Agents de 10/11/2026

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT, não integrado e não homologado** em 10/11/2026. A versão documental revisada foi reconciliada com o Agents em [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md), usando HEAD CRM `97bc1e8d6ce4cf4b52378472c31cb49de6a66d51` e blob `cefb390ec41a76d486b137279d92627f097d1d47`; isso **não** aprova nenhum gate. A proposta é documental; nenhuma rota, API real ou permissão foi autorizada. As decisões precisam de aceite independente por área:

- **R01-G01/G03 e CRM AUT/SEG:** identidade serviço-serviço, issuer/audience, vínculo principal/tenant, expiração, revogação e anti-replay distribuído;
- **R01-G02 e CRM CORE/SEG:** autorização final pelo backend CRM por tenant, membership, recurso, finalidade e campo, incluindo revogação e isolamento multiempresa;
- **R01-G05/G08:** classificação dos campos reais, base legal, minimização, retenção, esquema/versionamento de mensagens, erros não enumeráveis e responsabilidade de cada componente;
- **R01-G07/G10:** ambiente isolado, segredos sob controle, revisão independente de segurança, testes de integração positivos/negativos, recuperação e rollback;
- **R01-G11:** responsáveis, limites financeiros e autorização específica do piloto. Produção exige aceite adicional.

**Todos os gates R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO.** Nenhum teste sintético, CI verde ou comentário em PR substitui a aprovação e a evidência de runtime requeridas.

## Referências da linha histórica e arquivo preservado

- [Dossiê técnico EVID-16](docs/AIA-R01-EVID-16-SEGURANCA-TOOL-397.md) — SEC-18/19, evidências e limites.
- [Handoff EVID-15](docs/AIA-R01-EVID-15-HANDOFF-READ-TOOL-389.md) e [EVID-14](docs/AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md) — entregas anteriores.
- [Roadmap atual](docs/ROADMAP.md) — sequência de decisões e critérios de saída.
- [Status histórico integral, preservado sem perda](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) — decisões, PRs e registros datados; **não** interpretar métricas antigas como status atual.
- [Roadmap histórico integral](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) — evolução e ideias anteriores.

**Restrições mantidas:** nenhuma modificação em `main`, VPS ou produção, sem dados de cliente, segredos, chamadas pagas ou novos contratos. A continuidade permitida é revisão e desenvolvimento controlado em `dev`, não autorização de ativação comercial.


## Registro histórico da preparação Agents P62–P64 — 18/11/2026 UTC

A linha Agents mantém uma composição temporária e verificada com a Central
P30 `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8`, sem merge entre as linhas.
A [P30 #464](https://github.com/rafhael100/imagine-ai-agents/pull/464) passou
na [CI 44203383716](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203383716).

- **P62 #465:** a fila consulta propostas, autoria, pareceres atuais e histórico
  no mesmo snapshot de leitura, com estados reais persistidos no laboratório.
  HEAD `bb1a0e5a348ec68fde6a249f4e081750bc73aeca`,
  [RC73 aprovada](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203592593).
- **P63 #466:** os lotes novos preservam os insumos canônicos; a página reproduz
  a condição pelo motor original e confere o lote inteiro. Legado sem insumos
  é declarado indisponível. HEAD `6b65a109799df5f34e6431474ba8f3e58cb0fccc`,
  [RC74 aprovada](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44205251055).
- **P64 / RC75 candidata:** formulário local com prévia numérica, confirmação
  vinculada à sessão e gravação atômica de lote, rascunhos e autoria sintética.
  Writer por empresa limitado às três tabelas; fila/detalhe em processo de
  leitura separado. Replay não duplica nem repara autoria ausente. A matriz
  RC75 deve confirmar a HEAD concreta antes de registrar aprovação.

A jornada é entrada → prévia → guardar rascunhos → fila → detalhe verificável.
Pareceres continuam observações: `can_approve=false`, `can_execute=false`.
Primeiro avanço real previsto: OP-01 de leitura em homologação, condicionado
à autoridade e ao contrato de identidade/empresa/permissão do produto dono.
Identidade humana, alçadas, efeitos CRM, licença/política MFA obrigatória,
backup externo e instalação não são concedidos pelas provas sintéticas.

Contrato da nova jornada:
[LOCAL-AUTHORED-DRAFT-JOURNEY](docs/reviews/AIA-P64-LOCAL-AUTHORED-DRAFT-JOURNEY.md).
**DRAFT / NO-GO operacional; main e ambientes operacionais sem alteração.**

## Registro histórico da continuidade em homologação isolada — 17/11/2026

As candidatas P62/P63/P64 concluíram a composição de fila assinada, detalhe verificável e criação atômica com autoria. A [P65 #471](https://github.com/rafhael100/imagine-ai-agents/pull/471), SHA `740abafc813dbde70f5a2ad6e138402ed7945276`, acrescentou o consumidor mínimo e passou 6 casos positivos/85 negativos na [CI 44213754313](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44213754313). A [RC76 #473](https://github.com/rafhael100/imagine-ai-agents/pull/473), SHA `bd50ab49113e775490cb55d76b088731ec108cc9`, passou na [CI 44217619259](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44217619259), executando toda a matriz e o consumidor original na árvore combinada `cd4b6fba353a20f02b00facec4614be4f052a18f` com a Central P30.

O recorte funcional expressamente aprovado no CRM passou em [#649](https://github.com/rafhael100/imagine-crm/pull/649) e [#651](https://github.com/rafhael100/imagine-crm/pull/651): consulta humana com sessão/MFA/empresa/participação verificadas, referência e etapa como únicos dados de negócio, auditoria transacional e resumo navegável nos três idiomas. A revisão final do resumo `d3f06599cddfc244f6089d3dac01f29e5a45b8c0` passou 68 leituras HTTP, 247 verificações Chromium e a regressão privada completa. Isso não encerra os treze cenários históricos de delegação S2S ou libera produção.

A candidata P66/RC77 atende à pendência visual aprovada na [issue #289](https://github.com/rafhael100/imagine-ai-agents/issues/289): logo original, tokens Imagine, navegação, teclado e responsividade na criação P64 e fila/detalhe P56/P63. Reutiliza o domínio e as roles existentes. A prova prevê Chromium isolado, comparação de dados e capturas antes/depois da mesma base; resultados efetivos e limitações ficam na PR da SHA executada. [Escopo e validação](docs/reviews/AIA-P66-IMAGINE-OPERATOR-UI.md).

O primeiro ensaio Chromium também revelou a incompatibilidade entre `Referrer-Policy: no-referrer` e a origem obrigatória dos POSTs nativos da criação. A correção usa `same-origin` no escritor, preserva a verificação de Origin/Host/CSRF e acrescenta a contraprova de origem `null`; a nova execução deve comprovar o fluxo sem injeção de cabeçalhos no navegador.

As entregas dessa etapa permaneceram candidatas DRAFT, sem merge, deploy, acesso a clientes ou APIs pagas. Os requisitos operacionais de identidade/permissões, MFA administrativo, backup e pareceres continuam sujeitos à validação do respectivo ambiente; este registro não é um inventário de disponibilidade desses recursos. O estado vigente das candidatas owner está no início deste arquivo.

## P67 — continuidade do fluxo owner aprovado

A aprovação específica do responsável autorizou implementar criação e consulta de rascunhos pelo owner ativo do CRM em homologação fictícia. A candidata P67 acrescenta recebimento mTLS fechado, operações e chaves distintas, persistência/idempotência e autoria histórica atestada pelo CRM. Não altera o motor comercial nem os contratos e autores sintéticos anteriores. O CRM correspondente mantém intenção commitada antes do transporte e reconciliação por consulta sob autoridade atual. A execução bilateral já ocorreu na CRM #658, contra P67 exato, conforme os resultados consolidados no início deste arquivo. [Contrato e evidência](docs/reviews/AIA-P67-CRM-OWNER-DRAFTS.md). Candidaturas DRAFT, sem merge/deploy/IA externa ou GO operacional.
