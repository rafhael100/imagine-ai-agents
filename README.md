# Imagine AI Agents

## EVID-07 — procedência fixa e schema fechado do contrato OP-01 (09/11/2026)

Reforço do verificador offline: commit e blob exatos do CRM PR #57 ficam fixados e palavras-chave inesperadas na raiz do schema são rejeitadas. Novos testes negativos para mudança de origem, rehash de snapshot e falsas regras/alegações de produção. **Nenhuma homologação ou permissão real foi concedida.**

---

## Estado vigente — EVID-05/EVID-06 integrados e CI da dev comprovada (09/11/2026)

- **Painel gerado EVID-05:** [PR #127](https://github.com/rafhael100/imagine-ai-agents/pull/127), squash `44bab6e8a1c01bc6b30761018fe6271b94374bd6`, integrado apenas à dev. A [CI #42628509823](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42628509823) validou no SHA `5e3f0485d03a47d084332837d39f7fafe3169f75` o gerador determinístico, testes negativos, os validadores anteriores e a regressão PHP **228/228 testes e 73/73 lint** (mais o probe isolado).
- **CI consolidada EVID-06:** [PR #128](https://github.com/rafhael100/imagine-ai-agents/pull/128), squash `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c`, integrado somente à dev. A CI de revisão [#42629105845](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629105845) passou; sobretudo, a [CI #42629209058](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629209058) foi disparada **automaticamente pelo próprio merge na dev** no HEAD exato `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c` e concluiu `success` em todos os passos, inclusive a suíte PHP.
- **Governança e limites:** CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT/sem merge**. R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS; o painel e a CI retornam evidência de **laboratório, não autorização**. Estado operacional **NO-GO**, sem mudanças em `main`, CRM real, VPS, clientes, credenciais, contratos comerciais ou produção.

**Próximo passo que depende de terceiros:** pareceres bilaterais formais de CORE/SEG/AUT, Privacidade, Infra e direção sobre OP-01, com identidade, ACL, finalidade, revogação, topologia e custos. Até essas evidências, não iniciar piloto real.

---

## R01/EVID-06 — CI unificada na branch dev (09/11/2026)

- Workflow `.github/workflows/r01-dev-integrity.yml` concentra os validadores de gates, paridade contratual, gerador do painel e regressão PHP em alterações relevantes de `dev` ou PRs direcionados à dev.
- Trabalho somente offline, em repositório público, com permissão de leitura, timeout de seis minutos, sem instalação ou API externa; se o projeto ficar privado, o job não executa para evitar surpresas de cobrança.
- A CI indica integridade sintética, **não GO de integração**. Ver `docs/AIA-R01-EVID-06-CI-INTEGRIDADE-DEV.md`. Gates R01/CRM continuam abertos.

---

## R01/EVID-05 — geração determinística do painel de homologação (09/11/2026)

- Novo template HTML e gerador offline `tools/generate_op01_dashboard.py`. As **27 linhas** do painel agora provêm dos CSVs validados, sem edição manual duplicada.
- A verificação `--check` reprova divergências entre página e registros; `--write` regenera somente o HTML local, escapando conteúdo não confiável.
- O estado permanece **NO-GO**, com CRM PR #57 DRAFT e todos os gates ABERTOS. CI concluída em SHA exato no [run #42628509823](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42628509823). Ver `docs/AIA-R01-EVID-05-GERADOR-PAINEL-OP01.md`.

---

## Estado vigente — paridade contratual e painel offline integrados à dev (09/11/2026)

- **EVID-03:** [PR #124](https://github.com/rafhael100/imagine-ai-agents/pull/124) integrado somente à dev no squash `1a0c41875b477101ff8617da4682f8c7b44889fd`; [CI #42624040384](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624040384) no SHA `1bca6d5fadf7e46429b81e8cb79699f1f6631066` concluiu `success` nos testes de snapshot do CRM PR #57, paridade do schema JSON/parser PHP e regressão **228/228 testes PHP, 73/73 lint oficiais + 1 lint do probe**. Isso **não verifica API CRM real**.
- **EVID-04:** [PR #125](https://github.com/rafhael100/imagine-ai-agents/pull/125) integrado somente à dev no squash `53fc200e48b1c05789317e85b9b143efc47b76f0`; [CI #42624860955](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624860955) no SHA `7ca16416914291f1a6fd7d9f47126228ff2f8526` concluiu `success` no painel offline `examples/r01-op01-readiness-offline.html`, incluindo teste de correspondência dos **14 gates e 13 casos**, testes negativos EVID-02/03/04 e regressão PHP completa.
- **Governança inalterada:** o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **DRAFT, OPEN, sem merge**; R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. Resultado operacional **NO-GO**. A `main`, a VPS, o CRM funcional, credenciais, clientes e produção não foram alterados.

**Próxima atividade que depende de aceite externo:** revisão bilateral formal de identidade, ACL, campos, privacidade, revogação, topologia, licenciamentos e escopo do piloto; não substituir assinaturas de responsáveis por simulações ou status de CI.

---

## R01/EVID-04 — painel de prontidão OP-01 offline (09/11/2026)

- Novo painel navegável `examples/r01-op01-readiness-offline.html` para consulta local dos **14 gates** e **13 casos** de homologação sem dados reais.
- Filtros e pesquisa funcionam sem servidor. A visualização exibe **NO-GO** e não permite aprovar ou alterar os registros.
- Verificador e testes negativos em `tools/check_op01_readiness_dashboard.py` e `tests/Contracts/test_op01_readiness_dashboard.py`, sujeitos à CI SHA-fixada. Nenhuma integração CRM ou alteração em `main`.

---
## R01/EVID-03 — paridade documental CRM/Agents (09/11/2026)

- PR CRM #57 preservado em snapshot identificado por SHA e conferido com o schema JSON e o parser PHP sintéticos dos Agentes.
- Testes negativos detectam alterações de versão, operação, campos e alegações falsas de aprovação. O resultado permanece `NO_GO` independentemente da integridade dos arquivos.
- Regressão esperada pela CI: 228 testes PHP e 73 arquivos na validação oficial, mais 1 probe PHP com lint separado. Ver `docs/AIA-R01-EVID-03-CONFERENCIA-PARIDADE-CRM-OP01.md`.

---
## Estado vigente — R01/EVID-02 e análise G09 integrados à dev (09/11/2026)

- **PR [#121](https://github.com/rafhael100/imagine-ai-agents/pull/121) integrado exclusivamente à dev** no squash `9bf82e93466903e42dbbdaf981736b50a6ec8638`. [GitHub Actions #42621128657](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42621128657) executou, no head exato `bf5fabe8d151bf6d9988017b4df0cf74a4ec0e1f`, **15/15 testes do inventário OP-01**, **228/228 PHP** e **73/73 lint PHP**, todos com conclusão `success`. O teste do inventário devolve sempre `NO_GO` para integração real, mesmo que o registro tenha integridade válida.
- **PR [#122](https://github.com/rafhael100/imagine-ai-agents/pull/122) documental integrado exclusivamente à dev** no squash `ab31c28afeac4cf75fa3e09a33cec9db37916865`. Estudo de cinco cenários de licenciamento n8n com fontes oficiais; possível exigência Enterprise para backend SaaS, sem concluir licença ou autorizar aquisição. **G09 ABERTO**.
- **Gates ainda ABERTOS:** R01-G01..G11 e CRM CORE/SEG/AUT; [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT. Sem `main`, servidores, clientes, APIs reais, credenciais, compras ou deploy.

---

## R01-G09 — investigação do licenciamento n8n (09/11/2026)

Fontes oficiais do n8n (FAQ atualizada em 06/10/2026, termos SUL e oferta OEM) foram confrontadas com o desenho multiempresa Imagine. O uso Community **não foi homologado** para backend SaaS; automações configuráveis do cliente permanecem responsabilidade do motor próprio do CRM. Ver `docs/AIA-R01-G09-ANALISE-LICENCIAMENTO-N8N-2026-11-09.md` e matriz `docs/evidencias/aia-g09-matriz-uso-n8n.csv`. **G09 ABERTO**, sem compras ou integração.

---

## R01/EVID-02 — verificador automatizado de inventário de gates (09/11/2026)

- Novo utilitário offline `tools/check_r01_readiness.py`, matriz de 13 cenários e suíte negativa `tests/Contracts/test_r01_readiness.py`.
- Avalia somente **integridade documental**, nunca autorização. Mesmo com testes aprovados, devolve `pilot_decision=NO_GO`; revisores e evidências técnicas continuam pendentes.
- Detalhes: `docs/AIA-R01-EVID-02-VALIDADOR-ESTATICO-OP01.md`. `main`, CRM real, infraestrutura e pagamentos preservados.

---

## Preparação de homologação bilateral CRM/AIA — EVID-01 (09/11/2026)

Foi produzido o dossiê `docs/AIA-R01-EVID-01-DOSSIE-ACEITE-PILOTO-CRM-OP01.md` e a matriz rastreável `docs/evidencias/aia-r01-gates-op01.csv`, confrontando o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) (OPEN/DRAFT) com os gates do Agents. **Todos os R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS**, sem integração, credencial, custo, `main` ou deploy. Resultado: **NO-GO operacional; material pronto para revisão**.

---

## Estado vigente — F1-18 validada e integrada somente à dev (09/11/2026)

- **PR [#118](https://github.com/rafhael100/imagine-ai-agents/pull/118) MERGED por squash exclusivamente em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `2603c8da0a7ac7392cf5a27289249d776a095e81`. Não houve merge em `main`, deploy, API CRM, VPS, dado real, chave ou contratação.
- **Evidência executada:** [GitHub Actions #42610138453](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42610138453), SHA testado `210ff31ec365267f019aafbf2d4c1e48991b02a7`, validação do checkout exato, **228 casos PHP/0 falhas** e **73/73 lint PHP**, job `verify` concluído com `success`.
- **Escopo:** contagem agregada de falhas na borda antes do parser e negação uniforme sem payloads, IDs, timestamps ou credenciais. Tudo em memória e com dados fictícios.
- **Transição operacional bloqueada:** R01-G01..G11, CRM CORE/SEG/AUT e o aceite bilateral CRM continuam abertos; o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é somente documental. A próxima prioridade **não é acumular mocks**: é obter revisão contratual e evidências de segurança para um piloto restrito, sem executar integrações reais antes dos gates.

**Precedência:** os registros F1-18 em revisão, abaixo, são históricos anteriores à CI e ao merge #118.

---

## FT01/F1-18 — contadores mínimos da borda, somente offline (09/11/2026)

- **Branch em revisão:** `dev/ft01-f1-18-safe-edge-telemetry-2026-11-09`, baseada no commit dev `0c38e1ecb048d8498b85ab1cd6fbebdd48bc773d`; esta entrada não afirma CI executada nem merge.
- **Escopo:** contador agregado sem payload ou identificadores e decorador que registra falhas de parsing antes do auditor F1-13, com limite de 256 tentativas e negação uniforme em saturação. São **14 testes novos**, com metas de regressão **228 casos PHP e 73 lint**.
- **Restrições:** valores fictícios, zero HTTP/CRM real, zero produção, zero dados privados ou custos; R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS. Ver `docs/AIA-FT01-F1-18-CONTADORES-BORDA-SINTETICA.md`.

---

## Estado vigente — F1-17 integrado exclusivamente à dev (09/11/2026)

- **PR [#116](https://github.com/rafhael100/imagine-ai-agents/pull/116) MERGED por squash somente em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `69992f878c7b3d6e16b60ff4f446f47c3e23edba`. A `main` foi conferida no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deploy, VPS, CRM real, credenciais ou gastos de fornecedor.
- **Prova efetivamente executada:** [GitHub Actions #42583032672](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42583032672), HEAD testado `9e60e0574bc5dfe8f277e4822a3adf6b93777936`, PHP 8.3.6, **214 cases / 0 failures**, **70/70 PHP lint**, `VALIDATION_PASS` de SHA fixado.
- **Escopo entregue:** `MockCrmReadPublicFacade` exige mocks da política proprietária F1-16 e auditoria F1-13 para produzir conteúdo público uniforme `DENIED_OR_NOT_FOUND` em falhas simuladas, com 16 testes U17. Não há HTTP, dados privados ou garantia de indistinguibilidade de tempo/status de rede.
- **Gates ABERTOS:** R01-G01..G11, CRM CORE/SEG/AUT e aceite bilateral do CRM. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue apenas documental DRAFT. O laboratório não autoriza `ALLOW`, API real, IAM, SQL, operações com clientes nem produção.
- **Riscos seguintes:** validar futuramente observabilidade da borda para erros de parsing sem registrar payload, política de falhas HTTP/latência, identidade verificável, revogação persistente, autorização final por recurso/campo pelo CRM e fluxos de incidentes. Isso requer gates de integração específicos; testes de mock não os encerram.

**Precedência:** menções abaixo a F1-17 pendente/sem CI são históricas, anteriores ao merge #116.

---


## FT01/F1-17 — envelope público uniforme fictício (09/11/2026)

- **Branch filha em revisão:** `dev/ft01-f1-17-safe-public-read-2026-11-09`, derivada da dev após F1-16 (PR #114, squash `447672eda494ff365f74e2e39fbaae76a57d115f`; PR documental #115 integrado).
- **Recorte:** `MockCrmReadPublicFacade` obriga a presença dos mocks de autorização do dono e trilha, e produz uma resposta `DENIED_OR_NOT_FOUND` idêntica para todas as falhas sintéticas. Em sucesso devolve exclusivamente `ref/stage` de fixture e flags `real_authorization=false`, `external_calls=0`.
- **16 cenários U17** de negação uniforme, falha de owner, audit cheio, replay, escopo, campos privados e corpo adulterado; expectativa de regressão **214 testes PHP e 70 arquivos lint**. **Resultado ainda não comprovado até CI exata.**
- **Limites explícitos:** sem HTTP, timing, status codes, autenticação, dados reais, CRM, SQL, VPS ou `main`; todos os gates R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS**.
- **Documento:** `docs/AIA-FT01-F1-17-ENVELOPE-PUBLICO-SINTETICO.md`.

---


## Estado vigente — F1-16 integrado exclusivamente à dev (09/11/2026)

- **PR [#114](https://github.com/rafhael100/imagine-ai-agents/pull/114) MERGED por squash apenas em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `447672eda494ff365f74e2e39fbaae76a57d115f`. A `main` foi conferida em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem VPS, CRM real ou deploy.
- **Prova efetivamente executada:** [GitHub Actions #42582545247](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42582545247), head testado `b7866bb8dac2973025569c4a76a84082c28377a2`, PHP CLI 8.3.6, **198 casos, 0 falhas**, **68/68 lint PHP**, `VALIDATION_PASS` de SHA fixado.
- **Entregue:** mock `MockCrmOwnerPolicy` de decisão atual do dono dos recursos, injetado opcionalmente no F1-12; 18 cenários U16 de revogação, suspensão, campo, cross-tenant, falta de política e auditoria. **Não** existe ACL, membership, serviço CRM, autorização ou integração real. O parâmetro opcional mantém retrocompatibilidade apenas para laboratório, não produção.
- **Gates:** R01-G01..G11, CRM CORE/SEG/AUT, revisão LGPD e contrato bilateral CRM **ABERTOS**; o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT, somente documental. Nenhuma operação `ALLOW`, credencial, dado privado, fornecedor pago ou `main` ativada.
- **Próxima frente segura:** simular envelope de resposta pública que não revele motivos internos de negação; persistência e autorização reais ainda dependem de aprovação específica.

**Precedência:** abaixo podem permanecer trechos históricos de F1-16 em revisão; este estado pós-merge é o vigente.

---


## FT01/F1-16 — revalidação fictícia da autorização pelo CRM (09/11/2026)

- **Branch filha em revisão:** `dev/ft01-f1-16-owner-policy-recheck-2026-11-09`, partindo da dev após F1-15 (PR #112, squash `4a2a3b31bb422446a62e563616bd98b45ca3536c`; PR documental #113 também integrado).
- **Código:** `MockCrmOwnerPolicy` negando por padrão e rechecando ficticiamente empresa, vínculo, recurso e campos imediatamente antes de `MockCrmReader`; falha da política nega. Integração opcional mantém F1-12 histórico apenas para testes.
- **Novos testes:** 18 cenários U16; metas de regressão **198 casos PHP** e **68 arquivos lint**. **Ainda não afirmar PASS até validar o SHA exato em CI.**
- **Escopo estritamente sintético/offline:** sem CRM real, RLS, banco, credencial, fornecedor, VPS, `main`, deploy ou dados pessoais. R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS**. CRM PR #57 permanece documental DRAFT.
- **Documento:** `docs/AIA-FT01-F1-16-REVALIDACAO-POLITICA-CRM-SINTETICA.md`.

---


## Estado vigente — F1-15 integrado exclusivamente à dev (09/11/2026)

- **PR [#112](https://github.com/rafhael100/imagine-ai-agents/pull/112) MERGED por squash somente em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `4a2a3b31bb422446a62e563616bd98b45ca3536c`. A `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deploy, VPS, CRM real, segredos ou fornecedor pago.
- **Prova executada:** [GitHub Actions run #42581627932](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42581627932), head SHA-fixado `efa6a3b9584c15705716f54f1fb47dbe31d0dab2`, PHP 8.3.6, **180 cases, 0 failures**, **66/66 lint PHP PASS**, `VALIDATION_PASS`.
- **Conformidade somente fictícia:** 12 cenários OP-01, dos quais 10 negam alterações inválidas de versão/operação/escopo/campo/contexto e 2 permitem apenas projeção sintética `ref/stage`; não existe autorização real do CRM.
- **Gates e responsabilidades:** R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS**. O CRM PR #57 permanece proposta documental DRAFT, sem API ou permissão real. Antes do piloto é indispensável definir identidade verificável, ACL no CRM por recurso/campo, contrato bilateral, retenção/privacidade, implantação isolada e responsável/aceite.
- **Próximo trabalho seguro sem aprovação crítica:** pacote documental de critérios de aceite e fixtures de falhas; não substituir homologação de segurança por testes mockados.

**Precedência:** quaisquer passagens inferiores que indiquem F1-15 em revisão e sem regressão completa são registros históricos anteriores ao CI e squash #112.

---


## FT01/F1-15 — kit de conformidade OP-01 fictício em revisão (09/11/2026)

- **Base integrada atual:** F1-14 pelo [PR #110](https://github.com/rafhael100/imagine-ai-agents/pull/110), squash `9995fb99d3e453ecd1930e4917b63b8846029c24`; [CI oficial #42580749791](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42580749791) aprovou **168/168** e **65/65 PHP lint**.
- **Próximo recorte somente offline:** `dev/ft01-f1-15-contract-conformance-2026-11-09`; 12 fixtures versionadas de contrato CRM OP-01 e consumidor de teste no runner PHP. As novas metas são **180/180 casos e 66/66 PHP**; **não declarar teste aprovado antes do CI**.
- **Autoridade CRM intocada:** a fixture não é API de produção, não valida JWT, ACL ou política de um cliente. CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue documental DRAFT; G01..G11 e CORE/SEG/AUT abertos.
- **Documento:** `docs/AIA-FT01-F1-15-CONFORMIDADE-OP01-SINTETICA.md`. `main`, VPS e produção sem mudanças.

---


## Estado vigente — F1-14 integrado somente à dev (09/11/2026)

- **PR [#110](https://github.com/rafhael100/imagine-ai-agents/pull/110) MERGED por squash exclusivamente em `dev/ft01-aia-core-mocks-2026-11-06`**; commit `9995fb99d3e453ecd1930e4917b63b8846029c24`. A `main` foi verificada antes do merge no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deploy, VPS, CRM real ou custos de fornecedores.
- **Prova executada:** [GitHub Actions #42580749791](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42580749791), PHP CLI 8.3.6 sobre head exato `0b820c68fe4dc185ac92ffb0ae483a069bf183ce`; **168 cases / 0 failures**, **65/65 PHP lint PASS**, `VALIDATION_PASS` SHA-fixado.
- **Escopo:** laboratório `MockSharedTrustState`, injeção opcional em `MockTrustGateway`, auditoria F1-13 para indisponibilidade e 12 casos U14. Apenas dois gateways simulados que compartilham um objeto no mesmo processo, sem replay distribuído real.
- **Restrições vigentes:** sem persistência/atomicidade real, autenticação, ACL CRM, revogação entre processos, dados reais, ambiente de produção, ou gastos. R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS**. CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) ainda documental DRAFT. Revisão de infraestrutura e identidade do piloto necessária antes de qualquer integração.

**Precedência:** os relatos abaixo que descrevem F1-14 em revisão e metas não testadas são históricos, anteriores ao CI e squash #110.

---


## F1-14 — estado compartilhado de confiança sintético (09/11/2026)

- **Branch isolada:** `dev/ft01-f1-14-shared-trust-state-2026-11-09`, derivada da dev após F1-13. Ainda não integrada, `main` e produção inalteradas.
- **Código em revisão:** objeto `MockSharedTrustState` volátil partilhado por dois gateways apenas no mesmo processo; simula nonce consumido, revogação por digest e indisponibilidade com negação por padrão. `MockTrustGateway` só usa esse objeto se explicitamente fornecido; testes anteriores mantêm seu contrato legado.
- **Testes adicionados:** 12 casos U14, novas metas `168/168` casos e `65/65` arquivos PHP. **Não declarar que passaram até existir CI do commit exato**.
- **Sem segurança distribuída real:** ausência de autenticação, banco, múltiplos processos, ACL CRM, credenciais, logs de produção ou contratos habilitados. R01-G01..G11 e CORE/SEG/AUT seguem ABERTOS.
- **Documento:** `docs/AIA-FT01-F1-14-ESTADO-COMPARTILHADO-SINTETICO.md`.

---


## Estado vigente — F1-13 integrado exclusivamente à dev (09/11/2026)

- **PR [#108](https://github.com/rafhael100/imagine-ai-agents/pull/108) MERGED por squash apenas em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `92a2f0a5f01a0014051594f24d96988e111f85c7`. A `main` foi preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; nenhum deploy, VPS, fornecedor, dados privados, CRM real ou gasto foi executado.
- **Validação efetiva:** [GitHub Actions run #42579753806](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42579753806), commit exato `d09b3c57ac7d9c601afcd9a5cbd6d4b61b895620`, PHP CLI 8.3.6, **156 cases / 0 failures**, **63/63 PHP lint PASS**, validador SHA-fixado `VALIDATION_PASS`.
- **Escopo integrado:** duas classes `src/MockAudit/`, 12 casos U13, documentação e verificador offline; trilha volátil de categorias de decisão e fingerprints de IDs fictícios, sem conteúdo de lead ou credenciais. O workflow de teste usa executor GitHub padrão apenas quando o repositório é público.
- **Não se confundir com segurança real:** fingerprints de laboratório não são credenciais ou pseudonimização de produção; não há autenticação, ACL CRM, storage durável, anti-replay distribuído, gateway real, LGPD operacional, IA ou serviços. O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue apenas documental e draft.
- **Gates:** R01-G01 a G11 e CRM CORE/SEG/AUT ainda **ABERTOS**. A próxima fase autorizada pode fazer somente simulações offline de falhas entre instâncias, sem uso de clientes, produção, `main` ou serviços externos.

**Precedência:** os registros inferiores de F1-13 “ainda em revisão/sem merge” são históricos, anteriores à aprovação da suíte e ao squash #108.

---


## FT01/F1-13 — trilha de auditoria sintética sob revisão (09/11/2026)

- **Branch isolada:** `dev/ft01-f1-13-audit-read-synthetic-2026-11-09`, derivada da dev após F1-12. **Ainda não integrada à dev ou main; sem deploy**.
- **Entregue para revisão:** `MockCrmReadAuditTrail` e `MockCrmReadAuditedBoundary` sobre a fronteira fictícia F1-12; registro em memória somente de categorias permitida/negada, fingerprints de IDs sintéticos, nenhuma projeção ou identificador de cliente em claro. Falta de capacidade da trilha nega a operação antes da leitura fictícia.
- **Testes adicionados:** 12 casos U13; runner e validador atualizados para **metas de 156 testes e 63 PHP**. PHP 8.4.24: lint dos três arquivos novos PASS; harness isolado com dublês locais da F1-12: **12/12 PASS**; comparação Git blob SHA-1 dos três arquivos contra o conteúdo testado PASS. **Suite oficial 156/156, lint completo 63/63 e SHA-fixado ainda não executados**; não declarar homologação.
- **Governança:** PR em rascunho para revisão; não fazer merge antes das evidências integrais, revisão de diff e preflight da branch. **CRM PR #57 permanece apenas documento draft, sem integração real.** Gates R01-G01–G11 e CRM CORE/SEG/AUT abertos; `main`, produção, VPS, contratos e despesas sem alteração.
- **Documento técnico:** `docs/AIA-FT01-F1-13-AUDITORIA-SINTETICA-OFFLINE.md`. A trilha volátil e SHA-256 sem segredo não equivalem à auditoria/privacidade de produção.

---


## Estado vigente — FT01/F1-12 integrado na dev, CRM em revisão (09/11/2026)

- **Agents PR [#106](https://github.com/rafhael100/imagine-ai-agents/pull/106) MERGED somente em `dev/ft01-aia-core-mocks-2026-11-06`**, squash `562896763f900a8a264ea46aa35e2aeacd5e0903`. A `main` está preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.
- **Recorte entregue:** duas classes `src/MockBridge/`, JSON Schema de fixture versionado `contracts/crm-aia-readonly.v1.synthetic.schema.json`, 17 testes U12 no runner, teste opcional de esquema e validador de SHA atualizado. Somente operação fictícia OP-01 `crm.lead.read_minimal`, campos fictícios `ref/stage`; sem autenticação, ACL ou chamada CRM real.
- **Prova realmente executada:** `php tests/Unit/run.php` → **144 cases, 0 failures**; **60/60 PHP lint PASS** e **60/60 blobs Git SHA-1 idênticos** ao conteúdo executado, fingerprint FNV32 `87862107`. JSON Schema equivalente semanticamente entre GitHub e ambiente local (FNV32 canônico `87640c2a`), **2 fixtures válidas e 18 inválidas rejeitadas** com Python `jsonschema` pré-instalado.
- **Integridade pós-merge:** conteúdo dos arquivos de código/testes do squash idêntico ao head aprovado. Nenhuma conexão, deployment, compra, chamada externa, token, dado pessoal ou alteração em VPS ou `main`.
- **Proposta bilateral no CRM:** [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) — **DRAFT, SOMENTE DOC, SEM MERGE AUTOMÁTICO**. O CRM exige revisão expressa de donos SEG/CORE/Produto/Privacidade, e `DenyByDefaultAuthorizer` segue negando efeitos. Todos os gates R01-G01 a G11 e CORE/SEG/AUT CRM permanecem **abertos**.
- **Próxima etapa crítica:** homologar formato operacional, autoridade de identidade serviço-serviço, autorização efetiva por empresa/recurso/campo, revogação persistente e ambiente isolado. Os testes sintéticos **não** substituem essas verificações.

**Precedência:** os registros mais antigos abaixo que chamam o F1-12 de branch ainda em revisão são históricos, anteriores ao merge do PR #106. Nenhuma autorização de produção decorre da integração de mocks na dev.

---


## F1-12 — contrato CRM/AIA de leitura mínima, inteiramente offline (09/11/2026)

- **Branch filha:** `dev/ft01-f1-12-crm-read-contract-synthetic-2026-11-09`, derivada de `dev/ft01-aia-core-mocks-2026-11-06` em `836ca9274f65df2adf0e3c90a5fe8552fc6e93e0`.
- **Contrato sintético versionado:** `contracts/crm-aia-readonly.v1.synthetic.schema.json`, parser `MockCrmReadRequest` e `MockCrmReadBoundary`, reutilizando a fronteira F1-11 e o `MockCrmReader` **fictício**. Somente `crm.lead.read_minimal`, com projeção de `ref`/`stage`; sem mutação, clientes ou APIs.
- **Evidência executada localmente:** PHP 8.4.24 `php tests/Unit/run.php` → **144 cases, 0 failures**; **60/60 PHP lint PASS**. Python `jsonschema` → **2 mensagens sintéticas válidas e 18 inválidas rejeitadas**. A implementação e testes PHP foram confrontados com blobs Git, sem serviços ou gastos.
- **Gates de segurança reais:** todos R01-G01..G11 e CRM CORE/SEG/AUT continuam **abertos**; nenhum serviço emissor foi autenticado, não houve ACL real, dados privados, token, `ALLOW`, servidor, `main` ou deploy.
- **Especificação:** `docs/AIA-FT01-F1-12-CRM-READ-ONLY-CONTRACT-OFFLINE.md`. A apresentação bilateral no CRM será **somente documental**, sem API/rota/grant novos. Não confundir o mock com integração funcional.

**Precedência:** até integração na dev por PR validado, F1-12 permanece proposta em branch isolada; o estado operacional dos demais produtos não muda.

---


## ESTADO VIGENTE — F1-11 integrado apenas na dev (09/11/2026)

- **PR #104 MERGED em `dev/ft01-aia-core-mocks-2026-11-06`**, squash `68161e82a2ca36a34634f67b6e34f332c78c1ee8`. `main` intocada no SHA `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deployment, VPS, APIs reais, credenciais ou despesas.
- **Escopo:** contratos de confiança exclusivamente **sintéticos** em `src/MockTrust/`: `MockTrustEnvelope`, `MockOwnerScopeEvidence`, `MockTrustGateway`. Negação por padrão de emissor/audience/empresa/principal/recurso/escopo divergentes, validade lógica, revogação e replay de nonce em memória. Nenhuma autenticação ou ACL real.
- **Validação executada:** PHP CLI 8.4.24 `php tests/Unit/run.php` → **127 cases, 0 failures**; **57/57 PHP lint PASS**, **57/57 hashes Git blob idênticos** (fingerprint FNV32 `de8c8fdc`); exemplo `php examples/f111-trust-demo.php` retorna `SYNTHETIC_READ_ONLY` na primeira operação e `TRUST_REPLAY_DENIED` na repetição, `real_authorization=false`, `external_calls=0`.
- **Pós-merge:** todos os **63 arquivos de código, scripts e testes** do commit da dev têm SHA-1 de blob Git idêntico ao head validado no PR #104. O validador `tools/validate_ft01_offline.sh` agora exige `EXPECTED_SHA`, 57 PHP e 127 casos.
- **Riscos que não foram resolvidos:** issuer/audience são strings fictícias sem autenticação criptográfica, evidência do produto dono não valida ACL, nonces e revogação não são persistentes entre processos. Sem credenciais, token real, infraestrutura, CRM/Portal/Performance/Universidade, LLM, banco, LGPD operacional ou produção.
- **Gates:** R01-G01..G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. A proposta documental `docs/AIA-R01-G01-G02-CONTRATO-CONFIANCA-PROPOSTA.md` detalha as futuras interfaces sem aprová-las. A `main` e produção seguem bloqueadas. Próximas tarefas sem aprovação incluem apenas documentação técnica e testes de contrato fictícios.

**Precedência:** qualquer trecho antigo que diga F1-11 ainda isolado, ou F1-10 como último recorte, é histórico e foi escrito antes do merge #104. Os resultados dos mocks não homologam identidade/autorização real.

---


## FT01/F1-11 — fronteira de confiança sintética em revisão

O F1-11 avança a preparação de contratos de confiança serviço-serviço **somente com fixtures**. Inclui emissor, destinatário, contexto empresa/produto/usuário/recurso/finalidade, escopos de leitura, validade, revogação, evidência fictícia do produto dono e bloqueio de replay em memória. **Nenhum token, login, ACL, assinatura ou endpoint real é executado.** SHA-256 é apenas vínculo de conteúdo, não autenticação.

**Validação efetiva:** PHP CLI 8.4.24, **127/127 testes PASS**, **57/57 lint PASS**, **57/57 Git blobs SHA-1 coincidentes** com a árvore do GitHub (fingerprint FNV32 `de8c8fdc`). A demonstração `examples/f111-trust-demo.php` retornou uma consulta `SYNTHETIC_READ_ONLY` seguida de `TRUST_REPLAY_DENIED`, `real_authorization=false`, `external_calls=0`.

```sh
php tests/Unit/run.php
php examples/f111-trust-demo.php
# Em checkout Git exato da branch:
EXPECTED_SHA=<SHA_EXATO> bash tools/validate_ft01_offline.sh
```

Detalhes em `docs/AIA-FT01-F1-11-CONTRATOS-CONFIANCA-SINTETICOS.md`. A branch isolada é `dev/ft01-f1-11-synthetic-service-trust-2026-11-09`, com integração planejada apenas na dev após preflight. A `main`, produção, CRM/Portal/Performance reais, dados privados, VPS, gastos e gates R01-G01..G11/CORE/SEG/AUT permanecem preservados.



## ESTADO VIGENTE — F1-10 integrado exclusivamente à dev (09/11/2026)

- **PR #102 MERGED na `dev/ft01-aia-core-mocks-2026-11-06`**, squash `70d81bf58d3e3e0b1e3ac87250538451a9c166e9`. `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Sem deploy, infraestrutura alterada ou custos adicionais.
- **Entregue:** pacote de revisão independente fictícia `MockReviewPacket`/`MockReviewBoard`, duas funções de revisão com segregação entre autor, Produto e Segurança, revalidação de digest/expiração/revogação e reconhecimento idempotente, sem promover agentes. Novo `tests/Unit/review_handoff.php` com 14 casos e `examples/f110-review-demo.php`. O validador `tools/validate_ft01_offline.sh` agora exige **111 testes e 52 PHP** em checkout SHA-fixado.
- **Prova executada:** `php tests/Unit/run.php` → **111 cases, 0 failures, exit 0**; `php -l` **52/52 PASS**; **52/52 hashes SHA-1 Git blob idênticos** ao conteúdo testado (fingerprint FNV32 `1e3ddd48`). Exemplo local concluiu `READY_FOR_HUMAN_HANDOFF` com **`promotion_allowed=false`** e zero chamadas externas.
- **Conferência pós-squash:** os **58 arquivos de código, testes e scripts** do commit dev possuem hashes iguais aos do head PR #102 validado; os **52 arquivos PHP** permanecem idênticos. Os PRs anteriores F1-01 a F1-09 seguem históricos. A minuta obsoleta PR #90 foi encerrada **sem merge** para não reinstalar bloqueios históricos.
- **Limite explícito:** os revisores são strings sintéticas, sem autenticação ou assinatura digital. SHA-256 é digest de conteúdo, **não prova de identidade**. Não há persistência, API CRM, LLM real, SQL/ALLOW, dados privados, VPS, Cloudflare, release, produção, gasto ou modificação de `main`.
- **Gates ainda abertos:** R01-G01 a G11, CORE/SEG/AUT CRM e autorizações de integração real/produção. O roadmap foi atualizado para distinguir o laboratório integrado da fase operacional ainda pendente.
- **Próxima frente:** `docs/AIA-R01-TRANSICAO-FT01-PILOTO-CONTROLADO.md` organiza G01–G11 e a ordem para um piloto CRM somente leitura. Pode-se seguir com **contratos de confiança e testes sintéticos offline** sem novas decisões; conectar sistemas reais depende do gate executivo correspondente.

**Precedência:** menções históricas abaixo a F1-10 apenas em PR ou F1-09 como etapa atual estão superadas por este registro. Nenhuma evidência de mock deve ser descrita como homologação integrada ou autorização de produção.

---


## FT01/F1-10 — revisão independente sintética (branch em validação)

O recorte F1-10 acrescenta **encaminhamento humano simulado** após a comparação A/B do F1-09. A evidência de avaliação é recomputada e vinculada por SHA-256 a dataset, rubrica, contexto, baseline e candidata. Dois papéis fictícios, Produto e Segurança, precisam reconhecer o pacote com revisores diferentes do autor; mudança de evidência, expiração, revogação, falha crítica ou caso não executado bloqueia o encaminhamento.

**Nenhum reconhecimento autoriza promoção:** `promotion_allowed=false` e `real_authorization=false` em todos os estados. Não existe autenticação, assinatura ou armazenamento persistente. O hash é apenas vínculo de conteúdo, não certificado de identidade.

Comandos de validação sem fornecedores:
```sh
php tests/Unit/run.php
php examples/f110-review-demo.php
# Em checkout Git íntegro do HEAD aprovado:
EXPECTED_SHA=<SHA_EXATO> bash tools/validate_ft01_offline.sh
```

**Evidência executada:** PHP 8.4.24, **111/111 testes PASS**, **52/52 lint PASS**, **52/52 hashes Git blob coincidentes** com a árvore publicada (fingerprint de pares caminho/hash FNV32 `1e3ddd48`). Exemplo PHP em memória retorna `READY_FOR_HUMAN_HANDOFF` após dois reconhecimentos, com `promotion_allowed=false` e `external_calls=0`. Esta evidência não valida atores ou ferramentas reais.

Especificação em `docs/AIA-FT01-F1-10-REVISAO-INDEPENDENTE-SINTETICA.md`. Código em branch isolada `dev/ft01-f1-10-independent-review-mocks-2026-11-09`, com integração planejada **somente na dev** após preflight de SHAs, testes, diff e mergeabilidade. A `main`, produção, VPS, conectores reais, fornecedores e gates R01/CORE/SEG/AUT estão preservados.



## ESTADO VIGENTE — F1-09 integrado somente à dev (09/11/2026)

- **PR de código [#100](https://github.com/rafhael100/imagine-ai-agents/pull/100): MERGED na `dev/ft01-aia-core-mocks-2026-11-06`**, squash `da1710b4cc2cabe7eddf87da0605a1ba69a08724`. A `main` foi conferida em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` e permaneceu inalterada.
- **Entrega:** `src/MockEvaluation/` (suíte sintética, snapshot de versão, comparador com gates críticos e revisão humana), 12 testes U09, exemplo PHP `examples/f109-evaluation-demo.php`, laboratório visual `examples/f109-comparador-versoes-offline.html`, smoke Node `tests/Browser/f109_smoke.cjs` e QA responsiva opcional `tests/Browser/f109_responsive_qa.py`.
- **Provas executadas:** `php tests/Unit/run.php` → **97 cases, 0 failures, exit 0**; **48/48 PHP lint PASS** e **48/48 hashes de blobs PHP idênticos** ao PR. `node tests/Browser/f109_smoke.cjs` em Node.js 22.16 → `F109_SMOKE_PASS`. `python tests/Browser/f109_responsive_qa.py` em Chromium real com Playwright já instalado → **5/5 viewports PASS** (1440×900, 1024×768, 768×1024, 390×844, 320×680), três decisões corretas, navegação por teclado, tabela com rolagem interna acessível, zero erros JS ou requisições externas. Correção mobile feita antes do merge.
- **Integridade pós-merge:** todos os **53 arquivos de código, testes e demos** do commit integrado possuem blobs Git idênticos ao head do PR #100 verificado. HTML F1-09 SHA-1 Git `ccca71572725ba6e6900a5d189d24c5fadf1334b`, smoke Node `f8f3ca01e8256adb99faff79cef5a6b6a1f126e0`, QA Chromium `6d2c59545d1166cafa988a68c20731083a819885`.
- **Limites:** execução **sintética e offline**, sem agentes/LLM, autenticação, CRM real, memória persistente, dados pessoais, custo de fornecedor, VPS, SQL, API, Cloudflare, publicação ou deploy. Browser QA executado via `page.set_content` sobre bytes idênticos; **Firefox e abertura `file://` ainda não foram testados**. A aprovação de testes não homologa uso real.
- **Gates ainda abertos:** R01-G01 a G11, CORE/SEG/AUT CRM, integração e segurança operacional. Autorização ampla vale apenas para desenvolvimento, testes locais e integração controlada na `dev`; não habilita merge na `main` ou produção.

**Precedência:** qualquer texto histórico abaixo que descreva PR #100 como DRAFT, Node/Chromium como pendentes ou F1-09 apenas em branch filha foi escrito antes do merge; consulte o registro vigente acima.

---


## HISTÓRICO FT01/F1-09 — avaliação A/B sintética — desenvolvimento anterior ao merge

O próximo recorte, em branch isolada, acrescenta um **comparador determinístico em PHP** e uma **prévia visual offline**. A suíte de seis casos fictícios fixa dataset, rubrica, escopo e baseline para comparar versões, bloqueando regressões críticas e classificando casos não executados como inconclusivos. **Mesmo um resultado 6/6 exige revisão humana; jamais publica um agente.**

Comandos locais sem serviços ou dependências:
```sh
php tests/Unit/run.php                     # regressão sintética completa — 97 testes
php examples/f109-evaluation-demo.php      # três relatórios A/B fictícios
node tests/Browser/f109_smoke.cjs          # comparador visual offline
python tests/Browser/f109_responsive_qa.py # Chromium + Playwright se já instalados
```

Para navegar: abrir `examples/f109-comparador-versoes-offline.html` em navegador. A especificação está em `docs/AIA-FT01-F1-09-AVALIACAO-COMPARACAO-OFFLINE.md`. Código PHP: **97/97 testes, 48/48 lint, 48/48 hashes de blobs conferidos contra o GitHub**. Smoke executado em **Node.js 22.16 real**, e o comparador HTML foi verificado em **Chromium real, cinco resoluções, sem erros JS, chamadas de rede ou rolagem horizontal externa**, com acesso por teclado e tabela móvel corrigida. O HTML do GitHub foi conferido por hash Git; **Firefox não testado**. Os gates técnicos de laboratório estão cumpridos; nenhum gate de produção, autenticação ou integração foi encerrado. Sem LLM, APIs, dados reais, custos, deployment, main, nem fechamento de R01/CORE/SEG/AUT.


## ESTADO VIGENTE — FT01/F1-08 integrado à dev (08/11/2026)

- **Código e protótipo:** PR #98 **MERGED exclusivamente em `dev/ft01-aia-core-mocks-2026-11-06`**, squash `d37cd9a603981c3d963823307119c402b67edf89`. `main` inalterada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Nenhum deploy, VPS, integração, gasto ou fornecedor acionado.
- **Entrega:** `examples/f108-laboratorio-navegavel.html` (quatro telas e seis decisões offline), `tests/Browser/f108_smoke.cjs`, especificação F1-08 e melhorias mobile/acessibilidade. HTML no commit da dev: blob SHA-1 `a6ff332f520a55089efa6178aeda98cb4a9008eb`; smoke Node: `38360656fa421275075be054d71680a84078e0d4`, iguais aos testados.
- **QA realmente executado:** Node.js 22.16.0 em processo real `F108_SMOKE_PASS`; Chromium headless real com Playwright `page.set_content` em cinco viewports (`1440×900`, `1024×768`, `768×1024`, `390×844`, `320×680`), **5/5 PASS**, seis casos e 14 eventos por viewport, sem erros de JS/console, requisições externas ou overflow horizontal. Capturas desktop/mobile inspecionadas; `aria-pressed` e foco por teclado conferidos. **Firefox não testado**; navegação `file://` bloqueada pelo sandbox, por isso renderização foi por `set_content`.
- **Regressão anterior preservada:** `php tests/Unit/run.php` na base sintética reconstruída → `85 cases, 0 failures`; os **43 arquivos PHP da dev pós-merge #98 têm blobs idênticos aos da base validada**. Estes números não significam homologação de segurança, ACL, LGPD, RAG, APIs ou produção real.
- **Autorização vigente:** responsável concedeu autorização ampla para desenvolver/provar mocks sintéticos e fazer merges **somente na dev** após validações, com solicitações apenas para decisões críticas. `AGENTS.md` foi atualizado documentalmente para refletir esse processo.
- **Bloqueios preservados:** R01-G01..G11 e CORE/SEG/AUT CRM abertos; `main`, produção, infraestrutura, serviços reais, dados de clientes, credenciais, migrações, SQL/ALLOW e despesas exigem novas decisões específicas.

**Precedência:** trechos históricos abaixo que descrevem F1-08 como PR DRAFT, ou o QA visual como pendente, são registros do desenvolvimento anterior à integração #98 e não definem o estado atual.

---


## FT01/F1-08 — Laboratório visual navegável (branch de demonstração)

Uma prévia offline em português já está disponível em [examples/f108-laboratorio-navegavel.html](examples/f108-laboratorio-navegavel.html). Baixe o HTML e abra-o no navegador, sem servidor ou dependências. Ele contém quatro telas, três agentes **fictícios** e seis cenários determinísticos de segurança. Todos os dados da UI são sintéticos; o JavaScript não invoca as classes PHP, não utiliza IA real, não envia informações a servidores e não guarda dados em disco.

Para verificar as interações com Node 18+ (somente módulos nativos):
```sh
node tests/Browser/f108_smoke.cjs
```

A especificação do recorte está em [docs/AIA-FT01-F1-08-LABORATORIO-VISUAL-OFFLINE.md](docs/AIA-FT01-F1-08-LABORATORIO-VISUAL-OFFLINE.md). O smoke em Node.js real passou; a revisão visual em Chromium real foi concluída em cinco resoluções (desktop, tablet e celular), sem falhas de navegação, scroll horizontal, erro JS ou requisições externas. Corrigidos menu mobile, textos e estado acessível dos cenários. Firefox e qualquer integração real não foram homologados. Este protótipo não habilita login, autorizações reais, integrações entre produtos, credenciais, APIs, produção ou gastos. `main` e os gates de segurança permanecem preservados.


## ESTADO VIGENTE — 08/11/2026, pós-merge FT01 (prevalece sobre históricos abaixo)

- **Branch de desenvolvimento:** `dev/ft01-aia-core-mocks-2026-11-06`, SHA `550aa742b10754ffa0cc1502e22bbfb72d966a81` (squash **PR #96** F1-06 corretivo + F1-07 sintético). **PR #96 MERGED somente na dev.**
- **Validado de fato com PHP CLI 8.4.24:** `php tests/Unit/run.php` **85 cases, 0 failures, exit 0**; lint PHP em **43/43 arquivos PASS**; **43/43 SHA-1 de blob Git iguais** aos arquivos do HEAD `91919913ddbe346f0cdda64e41df7c73a38d7dee` testado. Todos os 43 arquivos PHP da dev pós-squash foram comparados com esse HEAD e os hashes coincidem.
- **Modo da verificação:** em razão da falta de DNS para `git clone` no sandbox, os 43 arquivos exatos foram reconstruídos a partir dos blobs GitHub e comparados por hash; a cópia reconstruída não tinha diretório `.git`. A evidência comprova conteúdo executado, não simula um checkout Git nem CI.
- **PRs #92 e #95:** encerrados **sem merge individual**, porque integralmente substituídos pelo PR #96; comentários e commits preservados. **PR #94:** minuta histórica de F1-07, ainda documental e sem merge.
- **O que foi implementado:** laboratório em memória, dados exclusivamente sintéticos, sem agentes reais conectados, CRM/Portal APIs, LLM, banco, segredo, produção, pagamentos ou custos. O exemplo offline permanece em `examples/knowledge_context_demo.php`. `main` permanece `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`, sem alterações.
- **Gates NÃO encerrados:** R01-G01..G11, CORE/SEG/AUT CRM e avaliação de integração/segurança real continuam abertos. A cobertura de testes de um mock não equivale à homologação de produção.
- **Próxima fase:** análise de F1-08 em documentação isolada; nenhuma dependência externa, deploy ou serviço real está automaticamente autorizado.

**Precedência:** qualquer trecho antigo abaixo afirmando que PRs #92/#95 estão abertos, que F1-07 não foi implementado ou que os 85 testes não foram executados refere-se a uma fotografia histórica, **não ao estado vigente**. Mantenha esses registros por rastreabilidade, não para orientar novo merge.

---


**Status:** projeto criado no GitHub; fundação documental inicial (05/11/2026).  
**Repositório:** `rafhael100/imagine-ai-agents` (privado).  
**Execução:** sem código de produto, sem infraestrutura provisionada e sem deploy.

## Propósito

Construir a camada compartilhada de **configuração, execução supervisionada e governança de agentes de IA** do ecossistema Imagine.

**Produtos consumidores previstos:** Imagine CRM, Portal Imagine, Imagine Performance AI e Universidade Imagine.

## Princípios

1. **Núcleo comum, domínios independentes:** cada produto mantém suas APIs, identidades, dados e regras de negócio. Não há acesso direto e irrestrito aos bancos dos produtos.
2. **Isolamento multiempresa:** contexto, memória, documentos, ferramentas e custos segregados por empresa e produto, com autorização obrigatória no backend.
3. **A IA propõe, o sistema valida:** regras críticas (acesso, finanças, exclusão, publicações, cobranças) não dependem somente de prompts.
4. **Autonomia graduada:** informar, sugerir, solicitar aprovação e executar operações explicitamente permitidas conforme risco.
5. **Observabilidade:** versões, execuções, erros, custos, testes, avaliações e trilhas de auditoria.
6. **Melhoria controlada:** feedback e testes antes de promover versões.
7. **Integração por contratos técnicos:** APIs autenticadas, eventos, filas e ferramentas versionadas.
8. **Não duplicação:** o CRM mantém o motor nativo de automações; n8n pode atender fluxos internos controlados pela Imagine, mediante validação do licenciamento comercial.

## Documentos

- [AGENTS.md](AGENTS.md) — diretrizes para Codex e agentes de desenvolvimento.
- [STATUS-ATUAL.md](STATUS-ATUAL.md) — fatos, pendências e bloqueios.
- [docs/ARQUITETURA-INICIAL.md](docs/ARQUITETURA-INICIAL.md) — limites e componentes.
- [docs/ESCOPO-FUNCIONAL.md](docs/ESCOPO-FUNCIONAL.md) — 12 módulos candidatos.
- [docs/ROADMAP.md](docs/ROADMAP.md) — etapas e critérios para avançar.

## Próximas decisões

Revisar/aprovar limites do serviço, contratos entre produtos, proteção de dados, estratégias de modelos/provedores, custos, licenciamento, MVP e critérios de aceite.

**Regra:** a publicação documental neste repositório não autoriza programação, alterações nos demais repositórios, despesas, infraestrutura ou deploy.

## FT01-F1-01 — domínio mockado (2026-11-06)

Primeiro lote autorizado: oito modelos em `src/Domain`, contratos locais em
`src/Contracts`, três mocks determinísticos em `src/Mocks` e dez casos sintéticos
em `tests/Unit/run.php`. Nenhum contrato é homologado com CRM. Todos os dados de
exemplo são marcados `SYNTHETIC`; o prefixo é convenção de teste, não sanitização
nem prova de identidade. Não fornecer dados reais.

Requisito de sintaxe: PHP 8.1+ já disponível (não há Composer nem bibliotecas).
No diretório do repositório, executar localmente:

```sh
php tests/Unit/run.php
```

O runner usa autoload local e verificações explícitas, com código de saída 1 em
falha. Não usa rede, banco ou serviços. **Neste ambiente PHP não está instalado:
nenhum teste PHP ou lint PHP foi executado; compatibilidade ainda não verificada.**
Nenhum runtime ou dependência foi instalado para contornar a restrição.

O contexto contém produto, tenant, principal, finalidade, recurso e `mock-v1`.
Aprovação usa relógio inteiro fornecido pelo chamador e vincula intenção material
imutável. Deduplicação é volátil, por produto/tenant/chave, com igualdade exata de
material e contexto; não oferece atomicidade ou recuperação. O estado global de
run não confirma efeito. `CONFIRMADO` exige prova **sintética** do fake, sem valor
para o CRM real. Sugestões ignoram instruções contidas no texto de entrada e não
dispõem de capacidade de escrita. Uso técnico não implementa crédito/faturamento.

Vocabulário mínimo de run: `RECEBIDA → EXECUTANDO ↔ AGUARDANDO`, com saídas
terminais `CONCLUIDA`, `FALHOU`, `CANCELADA`. É um recorte local dos documentos,
não a máquina definitiva de R02/R03. Não há compensação, fila, persistência,
callback, expiração de run ou reconciliação oficial. Status indeterminado permanece
indeterminado; nenhum retry produz novo efeito nesse fake.

Pendências: executar lint e os dez casos em ambiente autorizado com PHP já
existente; revisar os contratos simulados e homologar contratos reais separadamente.
R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos. F1-02, merge e deploy exigem
nova autorização.

## FT01-F1-02 — coordenação supervisionada local (2026-11-08)

`SupervisedCoordinator` recebe contexto, gateway fake e provedor fake por injeção
manual. Permite iniciar run, criar etapas, sugerir, executar intenção com aprovação
externamente fornecida, consultar efeito e transitar a run explicitamente. Não
emite aprovação: usa o objeto F1-01, inclusive revogação e janela `[issuedAt,
expiresAt)` com relógio passado como parâmetro. Não há relógio global ou agendamento.

`execute` reserva a intenção antes do único `submit`. Repetição da mesma intenção
consulta `status`; divergência material falha com `IDEMPOTENCY_CONFLICT`. Falha de
envio vira `INDETERMINADO`, nunca retry automático. `afterTimeout` exige tentativa
TIMEOUT vinculada à intenção e faz somente consulta. `reconcile` também funciona
para run terminal, preservando a distinção entre estado da run e efeito. Novas
operações exigem run ativa. Confirmado/negado não podem regredir; ausência de
registro após dispatch é tratada como indeterminação. Erro na consulta propaga a
falha sem liberar novo envio ou apagar a última evidência.

`MockSupervisedGateway` compõe o fake F1-01 sem modificar contratos. Expõe contadores
para observar submissões/consultas e permite simular perda da resposta depois do
efeito. `recordSyntheticEvidence` é controle explícito de fixture, não API real;
atualiza resultado pendente/indeterminado para confirmação, negativa ou continuação
da espera. Prova CONFIRMADO continua exclusivamente `SYNTHETIC_MOCK_CRM`.

Rastros são arrays em memória com sequência, referências de produto/tenant/run/
step/intent e códigos fechados. Referências recebem hash determinístico para evitar
copiar conteúdo arbitrário mesmo com prefixo SYNTHETIC. Não armazenam material,
prompt, sugestão, erro bruto ou stack. Hash não é autenticação nem anonimização de
produção. Cada coordenador serve um contexto sintético; não é ACL CRM.

Os dez casos originais são preservados; `tests/Unit/supervised.php` acrescenta os
12 casos U02-01–U02-12 da ordem executiva, carregados pelo mesmo runner. Em ambiente
autorizado com PHP 8.1+ **previamente disponível**, executar:

```sh
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```

Nesta execução Codex, PHP está ausente: **lint e os 22 testes NÃO EXECUTADOS**.
Nenhuma instalação ou GitHub Actions utilizada. A validação independente anterior
F1-01 registrada no STATUS não comprova o código novo F1-02.

Limitações: estado volátil por instância, nenhuma atomicidade, concorrência,
recuperação durável ou prova de serviço real. Run concluída não indica efeito
confirmado; cancelamento não apaga efeitos existentes. Novos objetos/coordenadores
não representam recuperação de processo. Sem merge, F1-03 ou deploy. R01-G01 a G11
e CORE/SEG/AUT CRM continuam abertos.

## FT01-F1-03 — laboratório sintético de resiliência (2026-11-08)

Escopo executado com autorização específica do responsável **somente** na branch filha de desenvolvimento `dev/ft01-f1-03-resilience-mocks-2026-11-08`. Nenhuma execução real, merge ou deploy autorizado.

- `MockFaultGateway` envolve o gateway fake existente e permite perda determinística da **resposta** de submit após entrega ao fake, mais falhas de consulta status. Não possui retry, rede, relógio ou persistência.
- `ReadOnlyTraceReview` interpreta cópias de eventos estritamente sintéticos e nega confirmação sem `Effect` fake compatível; resiste a truncamento, conflito, contexto divergente, eventos desconhecidos e clocks explícitos regressivos. `afterSimulatedRestart` SEMPRE sinaliza ausência de estado durável, sem invocar efeitos.
- `tests/Unit/resilience.php` adiciona **12** cenários U03-01 a U03-12 ao runner, preservando as 22 regressões F1-01/F1-02. **34** é a meta de execução do runner oficial, não um resultado presumido.
- Nova verificação local auxiliar: PHP 8.4.24, sintaxe dos 3 arquivos novos aprovada, 12/12 cenários sintéticos aprovados contra reprodução local dos contratos F1-01/F1-02. As três cópias de arquivos novos exibiram hashes Git blob SHA-1 idênticos aos do GitHub. **Não é evidência de execução dos 34 testes contra o checkout integral do PR**; essa pendência permanece.
- Em ambiente autorizado que tenha PHP 8.1+ **já disponível**, executar `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` e `php tests/Unit/run.php`, registrar os códigos de saída, versão e os 34 resultados efetivos. Não instalar PHP/Composer por iniciativa própria nem habilitar GitHub Actions.

**Limitações:** estado e provas apenas fictícios e voláteis; nenhum journal durável, autenticação/ACL real, integração CRM/IA, garantias de concorrência, idempotência entre processos, conformidade operacional ou recuperação após queda de produção. Não armazenar dados pessoais nesses testes. Os gates R01-G01–G11 e CORE/SEG/AUT CRM permanecem abertos.

Revisão Codex F1-03 em 2026-11-08: o trabalho já existente na filha e no PR #80
foi preservado. A leitura do trace agora exige início explícito, rejeita nova
submissão na mesma sequência e regressão de efeito terminal, compara a prova
com o último efeito observado e rejeita relógio/assinatura opcionais nulos.
Os casos U03-04/U03-05/U03-12 incluem essas entradas adversariais, mantendo os 12
casos F1-03 e as 22 regressões anteriores. Neste ambiente, `php -v` retorna 127
(`php: command not found`): lint e os **34 casos NÃO EXECUTADOS pelo Codex**.
A evidência auxiliar histórica acima não valida estas correções nem a suíte
completa no SHA atual. Aceite permanece pendente de execução posterior.

## FT01-F1-04 — ferramentas e capabilities simuladas (2026-11-08)

**Autorização executiva específica:** o responsável autorizou **somente programação e testes unitários sintéticos F1-04**. Branch filha `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`; PR de código apenas **draft** contra `dev/ft01-aia-core-mocks-2026-11-06`, sem merge ou deploy.

- `src/MockTooling/MockToolCatalog.php`: catálogo fechado e versionado de três ferramentas fictícias (leitura, sugestão e mutação fake), com validação de parâmetros, whitelist e limites.
- `src/MockTooling/MockCapabilityGrant.php`: concessão puramente fictícia injetada fora de textos de IA; exige produto, empresa, principal, finalidade, recurso e versão idênticos; permite revogação e prazo explícito.
- `src/MockTooling/MockToolDispatcher.php`: despacho somente local, leitura pelo reader fake, sugestão pelo coordenador anterior e mutação fake **apenas via SupervisedCoordinator** já autorizado. Nunca concede aprovação, envia HTTP ou retenta gravação cega. Rastro limitado a códigos fechados e referências sintéticas hash.
- `tests/Unit/tool_dispatch.php`: 12 cenários novos U04-01 a U04-12. O runner carrega as 34 regressões anteriores sem reescrevê-las.

**Evidência independente do código publicado:** PHP CLI 8.4.24; **26/26 arquivos PHP lint PASS**; **46/46 testes sintéticos PASS, zero falhas**; **exit code 0**. Os hashes Git blob SHA-1 de todos os 26 arquivos PHP locais testados coincidem com os blobs correspondentes da branch GitHub. Uma fixture de teste U04-08 foi ajustada para distinguir concessão ainda vigente de aprovação expirada; todas as regras de autorização foram mantidas.

**Não é produção:** grants sintéticos **não são identidade/ACL**, resultados fake não são confirmações reais do CRM, ausência de persistência, filas, concorrência, APIs, modelo LLM, dados reais e validação bilateral. Gates R01-G01–G11 e CORE/SEG/AUT CRM continuam **ABERTOS**; `main`, infra e demais projetos intocados. Merge e F1-05 **não autorizados**.

## FT01-F1-05 — laboratório de consumo técnico e cotas fictícias (2026-11-08)

**Autorização executiva distinta e expressa:** o responsável respondeu `sim` à pergunta específica sobre executar programação e testes sintéticos F1-05, após ratificação documental de F105-D01 a D10 e incorporação do PR #85. A autorização **não permite merge de código, deploy, integração real, F1-06 ou alteração de `main`**.

- `src/MockUsage/MockUsagePolicy.php`: política técnica *fictícia*, em memória, vinculada a produto, empresa, principal, finalidade, recurso, operação `mock.*`, versão do contexto e intervalo lógico de validade; revogação e cota máxima.
- `src/MockUsage/MockUsageLedger.php`: registro volátil de tentativas/estados ESTIMADO/OBSERVADO/AJUSTADO, deduplicação e conflito material, consumo conservador por tentativa sem somar estimativa e observação, quota `SYNTHETIC_WITHIN_QUOTA` **não autoriza ferramentas**, conciliação incerta, e alertas locais deduplicados.
- `src/MockUsage/MockUsageProof.php`: prova de uso fictícia, isolada, proveniente apenas de `SYNTHETIC_MOCK_PROVIDER`; **não constitui comprovante de despesa, transação ou efeito do CRM**.
- `tests/Unit/usage_quota.php`: 12 casos novos U05-01 a U05-12. `tests/Unit/run.php` apenas inclui esta suíte após as 46 regressões F1-01 a F1-04, sem alterar os testes anteriores.

**Execução real independente (código exato verificado):** PHP CLI **8.4.24**, sandbox local; `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **30/30 PASS**, exit 0; `php tests/Unit/run.php` → **58/58 PASS, zero falhas**, exit 0. Conteúdo dos **30/30 arquivos PHP** publicados na branch F1-05 conferido por **Git blob SHA-1** contra os arquivos locais testados, sem divergências.

**Limitações:** somente **processo único, memória volátil e dados inventados**. Sem reserva de quota atômica, durabilidade, validação real de identidade ou permissão, custos efetivos, preço, cobrança, saldo de créditos, provedor, faturamento, conciliação financeira, LGPD operacional, alertas externos ou CRM real. Não há qualquer fonte de verdade além das fixtures do laboratório.

**Segurança/governança:** `src/Contracts/`, domínio anterior, coordenador F1-02, resiliência F1-03, despacho F1-04, `AGENTS.md` e documentos ratificados permanecem intactos. Código somente na branch filha, PR futuro **draft e sem merge**, `main` e infraestrutura intocadas, R01-G01–G11 e CORE/SEG/AUT CRM **ABERTOS**, F1-06 não autorizado.

## FT01-F1-06 — catálogo e revisão de versões SOMENTE sintéticos (2026-11-08)

**Autorização executiva delimitada:** após integrar documentalmente a F1-06 (PR #89), o responsável respondeu **"sim"** à pergunta específica de executar programação e testes **somente sintéticos** em branch filha isolada, **sem merge, deploy ou integrações reais**.

- `src/MockCatalog/MockAgentVersionSnapshot.php`: manifesto de referências `SYNTHETIC_*`, tipado, com versão e digest material determinístico; snapshot imutável, `safeView` limitado.
- `src/MockCatalog/MockAgentTemplateCatalog.php`: catálogo fechado por contexto de produto/empresa/principal/finalidade/recurso, sem consultas externas; versões conflitantes e suspensas negadas.
- `src/MockCatalog/MockVersionReview.php`: revisão fictícia vinculada ao SHA de manifesto, dataset, regra, revisor e intervalo lógico; revogação explícita; **não é assinatura nem homologação**.
- `src/MockCatalog/MockPromotionGate.php`: promoção, suspensão e rollback **apenas locais**; estado fail-closed, elegibilidade vigente, idempotência e trilha mínima (hashes/códigos), sem chamadas de ferramentas, concessão de capacidades ou publicação.
- `tests/Unit/catalog_release.php`: 12 casos U06-01 a U06-12; runner passou a carregar os 58 casos anteriores intactos + os 12 novos.

**Validação realizada sobre código idêntico à branch GitHub:** PHP CLI **8.4.24**, lint `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **35/35 PASS, exit 0**; `php tests/Unit/run.php` → **70/70 PASS, zero falhas, exit 0**. Os **35 hashes Git blob SHA-1** dos arquivos PHP da branch foram confrontados com os 35 arquivos locais testados, sem divergências. Inclui verificação adversarial de arrays de ferramentas malformados, sem suprimir avisos.

**Limites obrigatórios:** processo único e estado volátil, sem agentes/identidades/permissões reais, modelo LLM, assinatura criptográfica, publicação operacional, rede, CRM, autenticação, persistência, concorrência, custo ou rollback de infraestrutura. Catálogo fictício não altera cota, approval, ferramentas ou crédito comercial. O PR de código permanece **DRAFT e SEM MERGE**; `main` e VPS intocadas. Gates R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**, F1-07 bloqueado.

## FT01-F1-07 — recuperação de conhecimento e contexto SOMENTE sintéticos (2026-11-08)

**Autorização executiva atualizada:** o responsável concedeu autorização ampla para desenvolver o Imagine AI Agents, sem necessidade de consultas rotineiras, mas mantendo isolamento de branch, ausência de despesas e preservação de `main`/produção. Esta autorização mais recente substitui o bloqueio executivo histórico de código F1-07, **sem homologar nenhuma integração real**. F107-D01..D10 alternativas A já estavam ratificadas documentalmente (PR #93, merge exclusivamente documental na dev). O PR #92 de correções F1-06 permanece DRAFT pendente de regressão integral e NÃO é alterado pela F1-07.

**Código novo nesta branch filha da dev:**
- `src/MockKnowledge/MockKnowledgeSource.php` — metadados sintéticos tipados, versão, fonte, dono, classificação, validade lógica e digest determinístico.
- `MockKnowledgeCatalog.php` — catálogo fechado e segregado por **contexto integral + referência + versão**; fonte desconhecida, expirada, desativada ou de outro contexto recebe negativa uniforme.
- `MockRetrievalGrant.php` — grant inteiramente fictício, explícito por fonte/base/versão/contexto, com janela lógica e revogação.
- `MockKnowledgeRetriever.php` — seleção local, determinística e somente leitura, limitada a três fontes previamente autorizadas; pedidos dinâmicos recebem `DYNAMIC_SOURCE_REQUIRED`.
- `MockContextEnvelope.php` — rechecagem de grant e de todas as fontes ao entregar, proteção contra material adulterado, teto de conteúdo, sinalização `UNTRUSTED_DATA`, conflito factual sem inventar resposta e encerramento explícito.
- `MockKnowledgeTrace.php` — somente códigos fechados, sequência e hash de referências fictícias, sem corpo de passagem ou prompt.
- `tests/Unit/knowledge_context.php` — U07-01..U07-12; `tests/Unit/run.php` inclui a suíte após os **70 testes** históricos. Meta atual desta branch F1-07: **82** casos. Quando o PR #92 de três testes adicionais entrar na dev, a integração conjunta poderá requerer **85** casos, não presumir resultado.

**Evidência executada nesta revisão:** PHP CLI 8.4.24 já disponível, sem instalação nem fornecedor. Lint **6/6 PASS** nas seis classes novas localmente reconstruídas; hashes Git blob SHA-1 **6/6 coincidentes** com os arquivos publicados na branch. Runner auxiliar local exercitando U07-01..U07-12 e isolamento adicional de referência igual entre tenants: **13/13 PASS, 0 falhas**. Essa reprodução **não equivale** à execução do arquivo oficial `tests/Unit/knowledge_context.php` no checkout completo, nem à regressão oficial `php tests/Unit/run.php`; **82/82 NÃO foi executado/comprovado**. O checkout integral segue inacessível por DNS no sandbox; não houve Actions, Composer ou custos.

**Restrições:** laboratório de processo único e memória volátil; nenhum RAG, LLM, OCR, embedding, crawler, banco/SQL, autenticação/ACL real, documentos de cliente, APIs CRM, importação, persistência, retenção legal, cobrança, infraestrutura, publicação ou efeito externo. `main`, contratos/implementações F1-01..F1-06 e outros produtos preservados. PR de código DRAFT sem merge automático; R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**.

### Demonstração offline executável F1-07 (2026-11-08)

O arquivo `examples/knowledge_context_demo.php` é um exemplo sem infraestrutura, Composer, banco, API ou LLM. Execute no checkout da branch com PHP 8.1+ já instalado:

```sh
php examples/knowledge_context_demo.php
```

O exemplo materializa uma única passagem fictícia (`READY` / `UNTRUSTED_DATA`), simula revogação antes da entrega (`DENIED`) e tenta reutilizar o grant de outra empresa (`DENIED`). A saída não contém dados de clientes nem autoriza ferramentas.

**Validação reproduzida:** PHP 8.4.24 já presente; `php -l` do exemplo PASS; execução exit 0. Hash Git blob do exemplo `0d9fe667bb3e09e34228618b6b9234b19d1ef9fc`, idêntico ao arquivo publicado. Um pacote portátil offline de demonstração foi criado no ambiente de revisão (não implantado). Os **82 testes oficiais da branch F1-07 não foram executados**, nem os **85 futuros combinados** com a correção #92. PR de código continua DRAFT sem merge.

### Validador offline de regressão (sem rede e sem Actions)

O script `tools/validate_ft01_offline.sh` exige um **checkout Git completo** com PHP 8.1+ já instalado e verifica o SHA, integridade mínima de diretórios, lint de todos os PHP e execução real do runner oficial. Retorna **exit 2 / BLOCKED** se o checkout está incompleto e **exit 1 / FAIL** se lint/testes falham; só devolve `VALIDATION_PASS` em caso de regressão efetiva sem falhas.

```sh
# Após checkout completo do PR #95 (F1-07 sem #92):
bash tools/validate_ft01_offline.sh

# Após conciliar também o PR #92 (3 casos F1-06 a mais):
EXPECTED_CASES=85 bash tools/validate_ft01_offline.sh
```

A base atual do PR #95 exige **43 arquivos PHP** (incluindo o exemplo) e **82 testes**. Com F1-06 corretivo, permanece 43 PHP e passam a ser exigidos 85 testes. Para fixar uma revisão específica, defina `EXPECTED_SHA` com o SHA completo previamente verificado no GitHub. O validador não instala dependências, não chama rede e não cria CI.

**Evidência desta revisão:** o script exato foi conferido por hash Git blob SHA-1 `2359d514cf2983b2101d87b08393388df04a1970`. Na cópia PARCIAL do sandbox, retornou corretamente `BLOCKED` / exit 2, sem falsificar validação completa. **82/82 e 85/85 não executados**. Os PRs #92 e #95 seguem DRAFT sem merge.
