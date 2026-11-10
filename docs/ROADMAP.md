## Marco vigente — fechar contrato real OP-01, não ampliar mocks (09/11/2026)

- [SEC-14 / Agents PR #170](https://github.com/rafhael100/imagine-ai-agents/pull/170) impede consumo desnecessário de nonce quando owner policy nega. [SEC-15 / Agents PR #171](https://github.com/rafhael100/imagine-ai-agents/pull/171) retira leitor direto do despachante: somente borda OP-01 poderá produzir projeção fictícia. CIs [#42746265495](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42746265495) e [pós-merge #42746791090](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42746791090) success. **Gate: 370 PHP/82 lint + Python**.
- Detalhes e critérios de saída em [EVID-14](AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md). O próximo marco depende de revisão bilateral do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57), identity/IAM, ACL do CRM por tenant/lead/campo, LGPD, testes integrados e autorização de piloto.
- R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS/NO-GO**; proibição de `main`, CRM real, VPS, dados pessoais reais, despesas ou produção sem validações independentes.

---

## Gate técnico SEC-13 — política CRM-owner obrigatória; integração real continua pendente (09/11/2026)

- [PR Agents #168](https://github.com/rafhael100/imagine-ai-agents/pull/168) (merge dev `422bb277ecccf80f45b243a94903b63a305cd069`) removeu a possibilidade de `MockCrmReadBoundary` operar sem política explícita do produto dono. [CI de revisão #42743896039](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42743896039) e [CI pós-merge #42743951923](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42743951923) `success`.
- **Baseline revisado: 362 testes PHP / 82 lint** e regressões Python. Nunca interpretar estes números como percentual de produto real pronto: cobrem apenas simulações em memória e contrato fictício.
- O próximo avanço de integração depende de **CRM PR #57, G01/G02/G05/G08/G10/G11, CORE/SEG/AUT e demais gates aplicáveis**, com contrato e autorização de dados aprovados bilateralmente. Nenhum endpoint CRM real foi criado pelo Agents.
- Preservar `NO-GO` para `main`, VPS, produção, clientes, segredos ou novas despesas até homologação e autorização específica.

---

## Etapa offline consolidada — próximo marco requer CRM homologado (09/11/2026)

- [F1-28 / PR #165](https://github.com/rafhael100/imagine-ai-agents/pull/165): CI [#42741450740](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42741450740) `success`; evita consumir nonce e leitura quando a Run não pode começar. Merge dev `cba280c1b5106d1861d75d78bc8ad1185a9787b1`.
- [F1-29 / PR #166](https://github.com/rafhael100/imagine-ai-agents/pull/166): CI [#42741942944](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42741942944) `success`; finaliza Run com falha local quando sugestão falha ou viola contrato, com resposta uniforme e sem escrita. Merge dev `6683e398614a49626cb446955938a398f98d7955`.
- Gate offline vigente **359 PHP / 82 lint**, mais testes Python. Os resultados não representam autenticação, ACL, disponibilidade, persistência, transação distribuída nem autorização de deploy.
- **Próximo marco técnico (dependência externa):** homologar identidade de serviço, ACL no backend CRM por empresa/recurso/campo, contrato OP-01, política LGPD, ambiente de desenvolvimento, revisão independente de segurança, rollback e autorização formal do piloto. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) `OPEN/DRAFT`; R01-G01..G11 e CORE/SEG/AUT `ABERTOS / NO-GO`.
- Não avançar para produção, API real, `main`, VPS ou dados de clientes antes da autorização específica. Documentar provas, não transformar mais mocks isolados em porcentagem fictícia de produto completo.

---

## Status atual — demonstração offline validada; próximo marco é contrato CRM real (09/11/2026)

- [F1-27 / PR #162](https://github.com/rafhael100/imagine-ai-agents/pull/162), [CI #42739875000](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42739875000) `success`: demo CLI sem parâmetros, sem rede, com cinco cenários e relatórios minimizados. Merge apenas na dev `e669f11dbc7a2adc052f4461c4e820a74aae52bc`.
- [CI-SEC-02 / PR #163](https://github.com/rafhael100/imagine-ai-agents/pull/163), [CI #42740221620](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42740221620) `success`: triggers de push e PR agora incluem `examples/**`. Merge dev `8d8b981f93b25e0a726131034247467bdce84f0c`. **351 PHP / 82 lint** + suites de contrato Python é o gate vigente.
- **Próximo marco não substituível por mais mocks:** resolver o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) e os gates de IAM, ACL multiempresa/campo, LGPD, contrato OP-01, Infra e revisão independente. A demonstração offline não aprova integração real; R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS, NO-GO**.
- CI pode ser ignorada caso o repo se torne privado; sem `success` no SHA correto, não fazer merge de código. A fase offline oferece uma prova de fluxo, **não um sistema pronto para produção**.

---

## Próximo marco: homologar piloto CRM OP-01 real (09/11/2026)

- **EVID-10:** [PR Agents #159](https://github.com/rafhael100/imagine-ai-agents/pull/159), [CI de revisão #42737036742](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42737036742) `success`: contrato fechado de resposta OP-01 e verificação cruzada PHP↔Python sem rede.
- **F1-26:** [PR Agents #160](https://github.com/rafhael100/imagine-ai-agents/pull/160), [CI de revisão #42738080284](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42738080284) `success`: orquestração sintética somente leitura até a sugestão sem nenhuma operação mutável. Baseline offline atual **351 testes PHP / 81 lint** e regressões de contrato Python.
- **Caminho crítico:** concluir aceites G01/G02/G05/G08/G10/G11 e CRM CORE/SEG/AUT, revisar [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (OPEN/DRAFT), homologar o contrato real e só então autorizar endpoint OP-01 com identidade, ACL por tenant/campo, revogação, auditoria e testes em ambiente isolado. Consulte [EVID-11](AIA-R01-EVID-11-ENTREGA-FT01-HANDOFF-CRM-OP01.md).
- Sem custos, tokens, acesso a clientes, VPS, `main` ou produção; todos os gates seguem **ABERTOS / NO-GO**. CIs do HEAD de PR não são prova de aceite em runtime.

---

## Prioridade vigente — integração OP-01 depende de aprovação independente (09/11/2026)

- **Baseline de revisão mais recente:** FT01/SEC-12, [PR #157](https://github.com/rafhael100/imagine-ai-agents/pull/157) (merge dev `e7435a5f51e71f3fa3ea9b27e5370f588f37d3cc`), [CI #42731788772](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42731788772) `success` no HEAD do PR `738f7c545e9067486510ac4f69ac650f171234cc`, **339 testes PHP / 79 lint**. O PR #156/SEC-11 foi integrado antes (merge `785271386bed0734175dfe3a713926235f668685`; CI de revisão #42731454833 success).
- **Revisão técnica:** nonces e revogações sintéticos têm limites; uma revogação que não puder ser gravada bloqueia o estado de confiança, sem liberar replay ou restaurar o serviço pelo simples toggle.
- **Não inventar outro mock para declarar homologação:** a próxima fase requer contrato bilateral CRM OP-01, IAM de serviço real, ACL/RLS, finalidade e minimização, revogação distribuída, observabilidade/rollback e autorização de piloto. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece proposta documental não homologada. R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS / NO-GO**.
- Não alterar `main`, dados reais, VPS, produção, licenças ou despesas para aparentar progresso. Os baselines inferiores citados adiante são **históricos**.

---

## R01/EVID-09 — reconciliação F1-23 e gate bilateral CRM (09/11/2026)

- [PR Agents #151](https://github.com/rafhael100/imagine-ai-agents/pull/151) integrado **somente à dev** em `f50e15f906169d0c12a11c69ec79aab3cdc3e901`, após [CI #42678725023](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42678725023) `success` no HEAD do PR `b69f76bcc24f21d8255af5c06582c187c9ebb048`. Gate de revisão: **320 testes PHP / 79 lint**. Os eventos `STATUS_UNAVAILABLE` e `STATUS_REJECTED` fecham consultas sintéticas sem substituir provas anteriores.
- **Próximo trabalho com valor operacional:** obter aceite bilateral OP-01 de identidade, autorização, finalidade, contrato e operação; reconciliar [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (OPEN/DRAFT, base da dev avançada). Consulte [EVID-09](AIA-R01-EVID-09-RECONCILIACAO-F123-CRM.md) para provas, pendências e sequência.
- R01-G01..G11 e CRM CORE/SEG/AUT continuam abertos. **NO-GO** de acesso real, API, VPS, produção, gastos e `main`.

---

# Roadmap proposto — Imagine AI Agents

## FT01/SEC-09 — registro canônico de execuções e etapas sintéticas (09/11/2026)

- **[PR #149](https://github.com/rafhael100/imagine-ai-agents/pull/149):** `start` rejeita IDs duplicados de Run; passos registrados não podem ter IDs duplicados; `step`, `suggest`, `transition`, `execute`, `reconcile` e `afterTimeout` impedem reutilizar outra instância com o mesmo identificador de uma execução registrada. `eventsForRun` também recusa instância não canônica e preserva visão vazia de ID desconhecido.
- A primeira execução da CI destacou a alteração de precedência no teste legado U03-10: o objeto de Run externo agora é recusado antes da verificação de idempotência. A correção manteve cobertura específica de idempotência na mesma etapa canônica (U22-16). Resultado após correção: **16 novos testes U22-01..16** e [CI integrada #42670724846](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42670724846) `success` em SHA `f40ce6137b81808d293279e7bad1d2da6e60c36d`, **304 PHP / 78 lint**, sem falhas.
- **Limitação conhecida:** protótipos legados com Run/Step externos ainda são permitidos quando o coordenador não iniciou nenhum Run, para compatibilidade de laboratório. Identidade por referência em PHP não equivale a IAM, prova de origem, transação ou estado distribuído. A revisão bilateral do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT. Gates R01-G01..G11 e CORE/SEG/AUT abertos, **NO-GO**, sem ativação de API, `main`, VPS, custos ou produção.

---

## FT01 SEC-08 / F1-21 — capacidade e segregação das trilhas (09/11/2026)

- **SEC-08 / [PR #146](https://github.com/rafhael100/imagine-ai-agents/pull/146):** o coordenador falha fechado quando faltam espaços para registrar envio+resultado, consulta, timeout ou transição; alinha o teto a 1.024 eventos aceitos pelo revisor. Sete testes U20-01..07. [CI pós-merge #42665958643](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42665958643) `success`, **280/280 testes PHP e 76/76 lint**.
- **F1-21 / [PR #147](https://github.com/rafhael100/imagine-ai-agents/pull/147):** `eventsForRun(Run)` devolve visão isolada por execução, sem alterar eventos globais. Sequências locais servem à análise sintética, **não** atestam ordenação global ou autenticidade. Oito testes U21-01..08; [CI pós-merge #42666931411](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42666931411) `success` em `b95a4252eeb031c52a0d64de52c04fac4b054724`, **288/288 PHP e 77/77 lint**.
- **Pendências reais:** [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT, sem merge ou aprovação bilateral. Não ativar IAM, API, acesso a clientes, `main`, VPS, produção, licenças ou fornecedores antes dos gates R01/CORE/SEG/AUT. Estado operacional: **NO-GO**.

---

## SEC-06/SEC-07 — integridade da trilha e recuperação sintética (09/11/2026)

- **PR #143 / SEC-06:** `SupervisedCoordinator::trace` adiciona somente hash da assinatura completa de `Intent` em eventos vinculados; o revisor exige essa correspondência, impedindo troca silenciosa de material/chave. Nove testes de regressão U03-19..27; [CI integrada #42662578637](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42662578637) PASS (**266 PHP / 75 lint**).
- **PR #144 / SEC-07:** timeout de intenção não enviada falha antes de alterar a trilha. O revisor rejeita eventos de timeout e status anteriores ao envio. Sete testes U03-28..34; [CI integrada #42663498033](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42663498033) PASS no commit `ab3d8a1ecd0e74ceaa65038c036134126b2a3c6e` (**273 PHP / 75 lint**).
- **Próximo marco independente:** revisão e aceite bilateral dos contratos reais CORE/SEG/AUT e do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57), ainda OPEN/DRAFT. As trilhas permanecem **voláteis e não autenticadas**; SHA-256 da assinatura é apenas uma checagem referencial. Gates R01-G01..G11 e CRM CORE/SEG/AUT abertos. **Não subir código para `main` nem ativar API, acesso a cliente, VPS ou despesas.**

---

## Verificação integrada da segurança de trilhas e aprovações (09/11/2026)

- **SEC-04 / PR #140:** correção de sequência e vínculo de eventos sintéticos (`STEP_CREATED`, `SUBMITTED`, status/timeout/efeitos). Seis testes de resiliência U03-13..18. [CI de integração #42658449082](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42658449082) PASS, suíte 246/74 no commit `f7ffe7881668cb1a4423ad7d2b77c6ca7104a4a2`.
- **SEC-05 / PR #141:** assinatura sintética de intenção reforçada com run/step/idempotencyKey, impedindo que uma aprovação de outra execução seja considerada equivalente. Onze casos U19-01..11. [CI integrada #42659871911](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42659871911) PASS, **257/257 testes PHP e 75/75 lint** no HEAD `0512bbdc8bde815cc3d58dde6e217be6872f1953`.
- Ambos são aprimoramentos em memória, **não** IAM ou auditoria autenticada. A assinatura sintética mudou intencionalmente, sem migração de dados persistidos; não inferir compatibilidade de digests entre versões. Próximo gate real continua a revisão bilateral do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) ainda DRAFT, com R01-G01..G11 e CORE/SEG/AUT abertos. `main`, VPS e produção permanecem bloqueados.

---

## Entregas verificadas da dev — fronteira sintética e CI (09/11/2026)

- **SEC-03 / PR #135:** scanner PHP reforçado contra despacho por variável, construtor dinâmico, crases e alias de função; sanitização de trilhas refatorada para métodos fixos. [CI integrada #42644765532](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42644765532) PASS.
- **EVID-08 / PR #136:** testes diferenciais de 166 fixtures entre contrato JSON sintético OP-01 e parser PHP; [CI integrada #42645750148](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42645750148) PASS. Não testa o CRM real.
- **CI-SEC-01 / PR #137:** checkout v7.0.1 Node24 pinado por SHA, com permissão de leitura e verificações estruturais; [CI integrada #42646958562](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42646958562) PASS.
- **CI-OPS-01 / PR #138:** todos os validadores no comando único `tools/run_r01_offline_quality_gate.sh`, compartilhado por CI e checkout local; [CI integrada #42647428097](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42647428097) PASS em `96bb4d04473842108eba6304d713dbf5bdff5ee0`, **240/240 PHP e 74/74 lint**.
- **Próximo gate legítimo:** aceite bilateral da proposta CRM PR #57 (DRAFT/sem merge), escopo/finalidade, IAM, ACL/RLS por tenant e campo, revogação, LGPD, licenças, topologia, responsáveis e aprovação expressa do piloto. Até lá, **NO-GO** e nenhuma modificação em `main`, VPS, clientes ou produção.

---

## Estado vigente — SEC-01/SEC-02 com CI integrada (09/11/2026)

- **Proteção do núcleo sintético:** [PR #132](https://github.com/rafhael100/imagine-ai-agents/pull/132) integrado só à dev em `1c4cdbf87d8a7b003b5564e76784f9333dc5900c`; [CI integrada #42632148664](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632148664) PASS. Adicionado scanner estático limitado de operações de rede, processo e banco em `src/`, com dez testes Python.
- **Referências de contexto:** [PR #133](https://github.com/rafhael100/imagine-ai-agents/pull/133) integrado só à dev em `07088bb58c2e4f4a3d68e11091de50737f875368`; [CI integrada #42632952682](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632952682) PASS no SHA exato. `TenantContext` agora valida referências de 1 a 80 caracteres ASCII conforme OP-01, com 12 testes novos; suíte oficial **240/240 PHP e 74/74 lint**.
- **Fronteira de autorização:** scanner e testes **não** equivalem a isolamento de produção, IAM, ACL real ou aprovação do CRM. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT/sem merge, R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos. **NO-GO** para piloto operacional; `main`, VPS, clientes e provedores não foram tocados.

---

## Estado vigente — EVID-07 integrado, integridade do contrato reforçada (09/11/2026)

- [PR #130](https://github.com/rafhael100/imagine-ai-agents/pull/130) integrado **somente à dev**, squash `21300c5e518166e7ac51ad5020d645c17668edb0`. A [CI do PR #42630115145](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630115145) passou no SHA revisado `0277abf91012bfc12f51a2625fce296abab1a689`; a [CI integrada #42630193545](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630193545) passou no HEAD exato `21300c5e518166e7ac51ad5020d645c17668edb0`.
- EVID-07 acrescentou seis testes negativos à paridade OP-01: o snapshot CRM PR #57 agora exige **commit e blob exatos**, e o contrato JSON rejeita palavras-chave raiz adicionais e metadados falsos de produção. A regressão PHP **228/228** e **73/73 lint** permaneceu aprovada na CI.
- [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57): **OPEN/DRAFT, sem merge e sem aceite bilateral**. Os gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. **NO-GO operacional**: nenhum endpoint CRM, identidade real, autorização, dado de cliente, `main`, VPS, gasto ou deploy foi ativado.
- **Prioridade seguinte:** obter decisões formais de CORE/SEG/AUT/Privacidade e donos do piloto. Não ampliar o escopo do laboratório sintético enquanto a segurança contratual real segue pendente.

---

## Estado vigente — EVID-05/EVID-06 integrados e CI da dev comprovada (09/11/2026)

- **Painel gerado EVID-05:** [PR #127](https://github.com/rafhael100/imagine-ai-agents/pull/127), squash `44bab6e8a1c01bc6b30761018fe6271b94374bd6`, integrado apenas à dev. A [CI #42628509823](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42628509823) validou no SHA `5e3f0485d03a47d084332837d39f7fafe3169f75` o gerador determinístico, testes negativos, os validadores anteriores e a regressão PHP **228/228 testes e 73/73 lint** (mais o probe isolado).
- **CI consolidada EVID-06:** [PR #128](https://github.com/rafhael100/imagine-ai-agents/pull/128), squash `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c`, integrado somente à dev. A CI de revisão [#42629105845](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629105845) passou; sobretudo, a [CI #42629209058](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629209058) foi disparada **automaticamente pelo próprio merge na dev** no HEAD exato `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c` e concluiu `success` em todos os passos, inclusive a suíte PHP.
- **Governança e limites:** CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT/sem merge**. R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS; o painel e a CI retornam evidência de **laboratório, não autorização**. Estado operacional **NO-GO**, sem mudanças em `main`, CRM real, VPS, clientes, credenciais, contratos comerciais ou produção.

**Próximo passo que depende de terceiros:** pareceres bilaterais formais de CORE/SEG/AUT, Privacidade, Infra e direção sobre OP-01, com identidade, ACL, finalidade, revogação, topologia e custos. Até essas evidências, não iniciar piloto real.

---

## Estado vigente — paridade contratual e painel offline integrados à dev (09/11/2026)

- **EVID-03:** [PR #124](https://github.com/rafhael100/imagine-ai-agents/pull/124) integrado somente à dev no squash `1a0c41875b477101ff8617da4682f8c7b44889fd`; [CI #42624040384](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624040384) no SHA `1bca6d5fadf7e46429b81e8cb79699f1f6631066` concluiu `success` nos testes de snapshot do CRM PR #57, paridade do schema JSON/parser PHP e regressão **228/228 testes PHP, 73/73 lint oficiais + 1 lint do probe**. Isso **não verifica API CRM real**.
- **EVID-04:** [PR #125](https://github.com/rafhael100/imagine-ai-agents/pull/125) integrado somente à dev no squash `53fc200e48b1c05789317e85b9b143efc47b76f0`; [CI #42624860955](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624860955) no SHA `7ca16416914291f1a6fd7d9f47126228ff2f8526` concluiu `success` no painel offline `examples/r01-op01-readiness-offline.html`, incluindo teste de correspondência dos **14 gates e 13 casos**, testes negativos EVID-02/03/04 e regressão PHP completa.
- **Governança inalterada:** o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **DRAFT, OPEN, sem merge**; R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. Resultado operacional **NO-GO**. A `main`, a VPS, o CRM funcional, credenciais, clientes e produção não foram alterados.

**Próxima atividade que depende de aceite externo:** revisão bilateral formal de identidade, ACL, campos, privacidade, revogação, topologia, licenciamentos e escopo do piloto; não substituir assinaturas de responsáveis por simulações ou status de CI.

---

## Estado vigente — R01/EVID-02 e análise G09 integrados à dev (09/11/2026)

- **PR [#121](https://github.com/rafhael100/imagine-ai-agents/pull/121) integrado exclusivamente à dev** no squash `9bf82e93466903e42dbbdaf981736b50a6ec8638`. [GitHub Actions #42621128657](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42621128657) executou, no head exato `bf5fabe8d151bf6d9988017b4df0cf74a4ec0e1f`, **15/15 testes do inventário OP-01**, **228/228 PHP** e **73/73 lint PHP**, todos com conclusão `success`. O teste do inventário devolve sempre `NO_GO` para integração real, mesmo que o registro tenha integridade válida.
- **PR [#122](https://github.com/rafhael100/imagine-ai-agents/pull/122) documental integrado exclusivamente à dev** no squash `ab31c28afeac4cf75fa3e09a33cec9db37916865`. Estudo de cinco cenários de licenciamento n8n com fontes oficiais; possível exigência Enterprise para backend SaaS, sem concluir licença ou autorizar aquisição. **G09 ABERTO**.
- **Gates ainda ABERTOS:** R01-G01..G11 e CRM CORE/SEG/AUT; [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT. Sem `main`, servidores, clientes, APIs reais, credenciais, compras ou deploy.

---

## R01-G09 — investigação do licenciamento n8n (09/11/2026)

Fontes oficiais do n8n (FAQ atualizada em 06/10/2026, termos SUL e oferta OEM) foram confrontadas com o desenho multiempresa Imagine. O uso Community **não foi homologado** para backend SaaS; automações configuráveis do cliente permanecem responsabilidade do motor próprio do CRM. Ver `docs/AIA-R01-G09-ANALISE-LICENCIAMENTO-N8N-2026-11-09.md` e matriz `docs/evidencias/aia-g09-matriz-uso-n8n.csv`. **G09 ABERTO**, sem compras ou integração.

---

## Preparação de homologação bilateral CRM/AIA — EVID-01 (09/11/2026)

Foi produzido o dossiê `docs/AIA-R01-EVID-01-DOSSIE-ACEITE-PILOTO-CRM-OP01.md` e a matriz rastreável `docs/evidencias/aia-r01-gates-op01.csv`, confrontando o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (OPEN/DRAFT) com os gates do Agents. **Todos os R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS**, sem integração, credencial, custo, `main` ou deploy. Resultado: **NO-GO operacional; material pronto para revisão**.

---

## Atualização — F1-18 integrada apenas à dev (09/11/2026)

A [CI #42610138453](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42610138453) verificou **228/228 casos PHP e 73/73 arquivos com lint**, no SHA `210ff31ec365267f019aafbf2d4c1e48991b02a7`; o [PR #118](https://github.com/rafhael100/imagine-ai-agents/pull/118) foi integrado somente à dev (`2603c8da0a7ac7392cf5a27289249d776a095e81`). A contagem de falhas de schema antes da auditoria é apenas sintética. Os gates R01 e CRM continuam abertos. **Próxima prioridade:** revisão bilateral de confiança/contrato CRM e critérios formais para um piloto seguro — não criar mocks ilimitadamente. Sem `main` ou produção.

---

## Estado vigente em 09/11/2026 — protótipos FT01, sem produção

A fundação documental e os laboratórios **sintéticos F1-01 a F1-12** evoluíram e foram integrados **exclusivamente à dev** (último commit `562896763f900a8a264ea46aa35e2aeacd5e0903`). O runner PHP passou **144/144 testes**, e **60/60 arquivos PHP** tiveram sintaxe e blobs Git conferidos. Nenhum agente real, provedor, autenticação/ACL efetiva, CRM, banco, deploy, custo ou produção foi integrado. Os gates R01-G01..G11 e CORE/SEG/AUT CRM permanecem abertos.

**Prioridade real de conclusão, sem criar mock após mock indefinidamente:**
1. **Contratos de segurança e confiança serviço-serviço (offline)**: representação mockada de contexto, recurso, escopo, emissor/audience e replay **já implementada no F1-11**. O F1-12 entregou a primeira proposta de contrato versionado OP-01 somente leitura, com fixtures, parser e 17 testes adicionais. Próxima atividade: **revisão bilateral** do [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57), com aprovação da autoridade de recursos e de segurança. Não emitir tokens nem conectar servidores. Consultar [G01/G02 — proposta de confiança](AIA-R01-G01-G02-CONTRATO-CONFIANCA-PROPOSTA.md).
2. **Homologação transversal R01-G01..G11**: especificar responsáveis, evidências e aceites necessários antes de qualquer acesso real. Consultar [plano de transição FT01 → piloto](AIA-R01-TRANSICAO-FT01-PILOTO-CONTROLADO.md).
3. **Piloto CRM somente leitura em desenvolvimento**, condicionando credenciais, infraestrutura e acesso de teste à aprovação crítica de G01/G02/G05/G08/G10/G11 e dos gates do CRM. Nada de mutações/produção no primeiro piloto.
4. **Operação controlada**, após autenticação real, proteção de dados, testes de carga, observabilidade, idempotência, aprovação humana real, licenças, orçamento e rollback.

Os passos abaixo são o **roadmap documental de origem**; referências a fase 0 como “atual” e fase 1 como “apenas após aprovação” são históricos. A autorização ampla posterior permite **mocks offline com merge somente na dev**, mas não altera gates reais ou custos.

---


## Fase 0 — Fundação documental (atual)

- Repositório privado e arquivos `README.md`, `AGENTS.md`, `STATUS-ATUAL.md`.
- Delimitação inicial entre serviço central e consumidores.
- Levantamento de riscos, contratos, custos, licenciamento e backlog.

**Gate:** aprovação formal de arquitetura, responsabilidades e MVP.

## Fase 1 — Núcleo mínimo (apenas após aprovação)

- Cadastro e versionamento de agentes, provedores e instruções.
- Executor com autenticação, autorização e rastreamento.
- Ferramentas seguras e segregação multiempresa.
- Limites e cotas, registros de execução e custos.

**Gate:** testes automatizados, segurança, isolamento e critérios de aceite.

## Fase 2 — Caso-piloto integrado ao CRM

- Agente de qualificação de leads: buscar lead autorizado, recomendar classificação, produzir resumo e sugerir tarefa.
- Fluxos de aprovação conforme permissões e risco.
- Avaliações de qualidade, segurança, custos, auditabilidade e rollback.

**Gate:** aceite do CRM e evidências de testes.

## Fase 3 — Conhecimento, memória e expansão

- Fontes de conhecimento aprovadas, indexação e recuperação.
- Memória controlada e políticas de retenção.
- Integração progressiva a Performance AI, Portal e Universidade, respeitando os gates de cada produto.
- Painéis centrais de qualidade, consumo e incidentes.

## Fase 4 — Multiagentes e melhoria supervisionada

- Coordenação de especialistas.
- Análise de feedback, avaliação contínua e propostas de melhoria.
- Aprovação e publicação controlada de versões, sem autoalteração de regras críticas.

## Fora do escopo inicial

- Treinar modelo fundacional próprio.
- Acesso direto a bancos de outros produtos.
- Duplicar ou substituir o motor nativo de automações do CRM.
- Autonomia irrestrita ou autoalteração de privilégios.
- Implantar produção sem autorização expressa.
