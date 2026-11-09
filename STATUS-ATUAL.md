# STATUS-ATUAL — Imagine AI Agents

## FT01/SEC-09 — identidade de execuções e etapas no coordenador (09/11/2026)

- O coordenador passa a rejeitar IDs duplicados de `Run` e `Step` registrados, e impede que outros objetos com o mesmo ID participem da trilha ou da execução de um `Run` já iniciado nele.
- Novos 15 testes (U22-01..15) cobrem colisão, aprovação não reutilizável por etapa destacada, preservação da trilha, isolamento por empresa e compatibilidade com fixtures legadas. Meta: **303 testes PHP e 78 lint**.
- É consistência de identidade em **memória sintética**, não autenticação de usuário ou registro distribuído. CRM PR #57 continua em rascunho e gates reais permanecem **NO-GO**.

---

## FT01/F1-21 — projeção somente leitura das trilhas por execução (09/11/2026)

- O coordenador agora disponibiliza `eventsForRun(Run)`, que separa eventos fictícios de execuções intercaladas e permite revisar cada uma isoladamente, mantendo o histórico global original.
- Oito testes U21-01..08 cobrem duas execuções, rejeição de outro tenant, ausência de vazamento, consultas e cancelamento. Meta da suíte: **288 testes PHP e 77 lint**.
- As sequências da projeção são locais à visualização, **não** prova de auditoria autenticada. Sem acesso ao CRM real; gates R01/CORE/SEG/AUT permanecem **NO-GO**.

---

## FT01/SEC-08 — limite de capacidade das trilhas sintéticas (09/11/2026)

- O coordenador agora respeita o mesmo teto de **1.024 eventos** aceito pelo revisor de trilhas e reserva capacidade antes de gerar eventos ou chamar gateways/provedores simulados.
- Sete testes de fronteira U20-01..07 verificam o limite exato, ausência de envios/consultas duplicados, transições bloqueadas e eventos de rejeição. A meta da suíte passa a **280 testes PHP e 76 lint**.
- Limite preventivo de laboratório, sem retenção ou armazenamento de produção. Gates R01/CRM seguem ABERTOS e `NO-GO` para serviços reais.

---

## FT01/SEC-07 — timeout e consulta exigem envio prévio (09/11/2026)

- `SupervisedCoordinator::afterTimeout` agora rejeita a tentativa de timeout de uma intenção não enviada **antes** de registrar qualquer evento. O revisor de trilhas também recusa `STATUS_REQUESTED` e `TIMEOUT` que apareçam antes de `SUBMITTED`.
- Sete testes novos (U03-28..34) cobrem negativas sem poluição de auditoria e recuperações válidas apenas por consulta de status, sem segundo envio. Meta: **273 testes PHP / 75 lint**.
- Controles totalmente sintéticos; não autorizam integração CRM. PR #57 permanece DRAFT e gates R01/CORE/SEG/AUT **NO-GO**.

---

## FT01/SEC-06 — hash da intenção vinculado à trilha sintética (09/11/2026)

- O coordenador registra apenas o hash da assinatura completa de `Intent` nos eventos de envio, aprovação negada, consulta, timeout e efeito. O revisor exige correspondência com a intenção alvo, impedindo que uma trilha antiga sustente uma prova fictícia de outro conteúdo ou chave.
- Nove novos testes PHP (U03-19..27) cobrem alteração de conteúdo, idempotência, adulteração e ausência do hash, preservando trilhas válidas com relógios monotônicos. Expectativa: **266 testes PHP / 75 lint**.
- Esse hash **não autentica a origem dos eventos** nem autoriza CRM real. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua DRAFT, gates R01/CORE/SEG/AUT abertos, estado **NO-GO**.

---

## FT01/SEC-05 — aprovação vinculada a execução, etapa e chave (09/11/2026)

- A assinatura de `Intent` agora inclui a chave de idempotência e os identificadores de `Run` e `Step`. Uma aprovação fictícia de uma execução não pode ser reutilizada em outra com a mesma operação e conteúdo.
- Onze casos PHP novos (U19-01 a U19-11) verificam proteção contra reuso de aprovações, conflitos de idempotência e operações legítimas com chaves independentes. A suíte passa a exigir **257 testes PHP e 75 arquivos lint**.
- A mudança não cria autenticação ou aprovação humana real. O CRM PR #57 continua em rascunho e os gates operacionais permanecem **NO-GO**.

---

## FT01/SEC-04 — vínculo de intenção nas trilhas sintéticas (09/11/2026)

- O revisor `ReadOnlyTraceReview::inspect` agora exige referências explícitas de intenção e etapa em eventos de envio, status, timeout e efeito; uma etapa correspondente deve aparecer antes do envio.
- Seis testes negativos novos (U03-13 a U03-18) cobrem eventos incompletos, etapa errada/ausente e sequência inválida. A suíte oficial passa a verificar **246 testes PHP e 74 arquivos de sintaxe**.
- A checagem é de **consistência offline**, não autenticação de logs ou autorização de CRM. O PR CRM #57 permanece DRAFT; R01 e CORE/SEG/AUT continuam **NO-GO**.

---

## R01/CI-OPS-01 — execução offline unificada (09/11/2026)

- Criado `tools/run_r01_offline_quality_gate.sh`, que reúne prontidão, contrato CRM sintético, matriz JSON/PHP, painel, segurança do PHP e regressão oficial em um único comando, sem rede ou instalações.
- A CI da `dev` agora chama essa mesma sequência, preservando o SHA revisado, limite de 6 minutos, checkout fixado e permissões somente de leitura. Sete testes estruturais verificam a cobertura do gate.
- O script retorna aprovação **somente da qualidade offline**, nunca `GO` para integração CRM; gates reais ainda abertos.

---

## R01/CI-SEC-01 — checkout oficial Node.js 24 fixado por SHA (09/11/2026)

- O workflow consolidado da `dev` passou de `checkout@v4` para o commit verificado da release oficial **v7.0.1** (Node.js 24), impedindo deriva silenciosa da tag.
- Oito testes Python conferem credenciais não persistidas, permissões de leitura, repositório público, timeout e SHA da regressão. Sem gastos contratados ou integrações reais.
- A alteração vale apenas para o workflow principal da dev e será confirmada por CI antes do merge. CRM segue **NO-GO**.

---

## R01/EVID-08 — testes diferenciais JSON/PHP do contrato OP-01 (09/11/2026)

- Nova matriz determinística em `tests/Contracts/test_op01_contract_matrix.py`, comparando o schema JSON sintético fixado e o parser PHP F1-12 em mais de 120 fixtures com dados falsos.
- Os controles cobrem limites, tipo/versão/operação, campos, projeções, identificadores e propriedades extras. Cada resultado permanece **NO-GO**, sem alterar ou consultar a API real do CRM.
- CI unificada passa a exigir a matriz sem bibliotecas externas. A interpretação JSON é limitada ao **subconjunto fechado deste schema**, não equivale a validador JSON Schema genérico ou homologação.

---

## FT01/SEC-03 — barreira contra execução indireta em PHP (09/11/2026)

- O scanner offline agora identifica chamadas por variáveis, construtores dinâmicos, crases e funções importadas por alias, além das chamadas diretas já cobertas.
- Sete testes adicionais verificam essas formas e preservam conteúdo inerte. **Não equivale a sandbox, autenticação ou permissão CRM**; gates operacionais seguem abertos.
- Detalhes: `docs/AIA-FT01-SEC-01-GUARDA-ISOLAMENTO-PHP.md`.

---

## Estado vigente — SEC-01/SEC-02 com CI integrada (09/11/2026)

- **Proteção do núcleo sintético:** [PR #132](https://github.com/rafhael100/imagine-ai-agents/pull/132) integrado só à dev em `1c4cdbf87d8a7b003b5564e76784f9333dc5900c`; [CI integrada #42632148664](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632148664) PASS. Adicionado scanner estático limitado de operações de rede, processo e banco em `src/`, com dez testes Python.
- **Referências de contexto:** [PR #133](https://github.com/rafhael100/imagine-ai-agents/pull/133) integrado só à dev em `07088bb58c2e4f4a3d68e11091de50737f875368`; [CI integrada #42632952682](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632952682) PASS no SHA exato. `TenantContext` agora valida referências de 1 a 80 caracteres ASCII conforme OP-01, com 12 testes novos; suíte oficial **240/240 PHP e 74/74 lint**.
- **Fronteira de autorização:** scanner e testes **não** equivalem a isolamento de produção, IAM, ACL real ou aprovação do CRM. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT/sem merge, R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos. **NO-GO** para piloto operacional; `main`, VPS, clientes e provedores não foram tocados.

---

## FT01/SEC-02 — referências sintéticas de contexto alinhadas ao contrato (09/11/2026)

- O `TenantContext` agora rejeita sufixos vazios, letras minúsculas, Unicode, separadores e controles; utiliza o mesmo formato estrito de **1 a 80 caracteres ASCII** do contrato fictício CRM OP-01.
- Nova suíte com **12 testes PHP negativos e positivos** no runner oficial. CI da `dev` passa a exigir **240 casos PHP** e **74 arquivos lint**, mantendo o verificador de isolamento e as matrizes de homologação.
- Este ajuste **não cria autenticação real** nem fecha gates R01/CRM. Ver `docs/AIA-FT01-SEC-02-REFERENCIAS-CONTEXTO-SINTETICO.md`.

---

## FT01/SEC-01 — guarda de isolamento do núcleo sintético (09/11/2026)

- Novo verificador PHP por tokens, aplicado ao diretório `src/` sem executar código-fonte. Identifica introdução direta de chamadas de rede, banco, processo, arquivos, carregamento dinâmico e superglobais.
- Dez testes Python com fixtures fictícias cobrem bloqueios, comentários e strings inertes, parse inválido, links simbólicos e limites de tamanho. CI unificada da dev executa a nova guarda antes da regressão completa.
- Limitação explícita: **não é sandbox nem análise estática exaustiva**; não fecha R01-G01..G11 ou CRM CORE/SEG/AUT. `NO-GO` para qualquer conexão real.

---

## Estado vigente — EVID-07 integrado, integridade do contrato reforçada (09/11/2026)

- [PR #130](https://github.com/rafhael100/imagine-ai-agents/pull/130) integrado **somente à dev**, squash `21300c5e518166e7ac51ad5020d645c17668edb0`. A [CI do PR #42630115145](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630115145) passou no SHA revisado `0277abf91012bfc12f51a2625fce296abab1a689`; a [CI integrada #42630193545](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630193545) passou no HEAD exato `21300c5e518166e7ac51ad5020d645c17668edb0`.
- EVID-07 acrescentou seis testes negativos à paridade OP-01: o snapshot CRM PR #57 agora exige **commit e blob exatos**, e o contrato JSON rejeita palavras-chave raiz adicionais e metadados falsos de produção. A regressão PHP **228/228** e **73/73 lint** permaneceu aprovada na CI.
- [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57): **OPEN/DRAFT, sem merge e sem aceite bilateral**. Os gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS. **NO-GO operacional**: nenhum endpoint CRM, identidade real, autorização, dado de cliente, `main`, VPS, gasto ou deploy foi ativado.
- **Prioridade seguinte:** obter decisões formais de CORE/SEG/AUT/Privacidade e donos do piloto. Não ampliar o escopo do laboratório sintético enquanto a segurança contratual real segue pendente.

---

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


## HISTÓRICO FT01/F1-09 — desenvolvimento isolado e regressão PHP validada (08/11/2026); registro histórico

- **Base imutável na abertura:** `dev/ft01-aia-core-mocks-2026-11-06` em `0fc74060c5fdfe914fd2a1c89f58d22818f775a2` (F1-08 já integrada). `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.
- **Branch:** `dev/ft01-f1-09-evaluation-comparison-2026-11-08`. Três classes `src/MockEvaluation/`, 12 testes `U09-01..12` em `tests/Unit/evaluation_compare.php`, runner PHP, exemplo `examples/f109-evaluation-demo.php`, painel `examples/f109-comparador-versoes-offline.html`, teste `tests/Browser/f109_smoke.cjs` e documento técnico F1-09.
- **Gate real de regressão sintética:** PHP CLI 8.4.24 `php tests/Unit/run.php` → **97 cases, 0 failures, exit 0**; **48/48 PHP lint PASS**, todos os **48/48 blobs PHP têm SHA-1 idêntico à árvore do commit GitHub** (fingerprint ordenado FNV32 `39e991ef`, 48 arquivos); sem dependências instaladas.
- **Demo PHP:** `php examples/f109-evaluation-demo.php` retornou `REVIEW_REQUIRED` (6/6), `BLOCKED_CRITICAL` (4/6, duas falhas críticas) e `INCONCLUSIVE` (5/6, evidência faltante), `promotion_allowed=false` e zero efeitos externos.
- **Frontend QA concluído em 09/11/2026:** HTML standalone de três abas, três candidatas e seis casos. Node.js 22.16.0 real `node tests/Browser/f109_smoke.cjs` → **F109_SMOKE_PASS**. Chromium headless real via Playwright, HTML byte-exato do blob Git `ccca71572725ba6e6900a5d189d24c5fadf1334b`, **5/5 resoluções** (1440, 1024, 768, 390, 320 px), três resultados corretos, foco teclado, `aria-pressed`, histórico e reset. Encontrado e corrigido overflow horizontal mobile; tabela com rolagem interna orientada e acessível. Zero erros JS, zero requisições externas e zero overflow global após correção. Teste reprodutível em `tests/Browser/f109_responsive_qa.py` (blob `6d2c59545d1166cafa988a68c20731083a819885`). **Firefox não testado**; Chromium validado via `page.set_content` em ambiente isolado.
- **Governança:** nenhum caso simulado se refere a clientes reais, nenhum backend CRM/Portal/Performance/Universidade, IA real, SQL, segredo, n8n, Cloudflare, VPS, custo ou implantação. QA visual/Node concluído; integração na `dev` segue condicionada ao preflight final de SHA, diff e mergeabilidade. `main` e produção preservadas. R01-G01..G11 e CORE/SEG/AUT CRM permanecem abertos.

**Precedência:** este é o estado da branch filha F1-09, não implementação ou homologação em produção; a dev principal permanece F1-08 até eventual merge controlado.


## ESTADO VIGENTE — FT01/F1-08 integrado à dev (08/11/2026)

- **Código e protótipo:** PR #98 **MERGED exclusivamente em `dev/ft01-aia-core-mocks-2026-11-06`**, squash `d37cd9a603981c3d963823307119c402b67edf89`. `main` inalterada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Nenhum deploy, VPS, integração, gasto ou fornecedor acionado.
- **Entrega:** `examples/f108-laboratorio-navegavel.html` (quatro telas e seis decisões offline), `tests/Browser/f108_smoke.cjs`, especificação F1-08 e melhorias mobile/acessibilidade. HTML no commit da dev: blob SHA-1 `a6ff332f520a55089efa6178aeda98cb4a9008eb`; smoke Node: `38360656fa421275075be054d71680a84078e0d4`, iguais aos testados.
- **QA realmente executado:** Node.js 22.16.0 em processo real `F108_SMOKE_PASS`; Chromium headless real com Playwright `page.set_content` em cinco viewports (`1440×900`, `1024×768`, `768×1024`, `390×844`, `320×680`), **5/5 PASS**, seis casos e 14 eventos por viewport, sem erros de JS/console, requisições externas ou overflow horizontal. Capturas desktop/mobile inspecionadas; `aria-pressed` e foco por teclado conferidos. **Firefox não testado**; navegação `file://` bloqueada pelo sandbox, por isso renderização foi por `set_content`.
- **Regressão anterior preservada:** `php tests/Unit/run.php` na base sintética reconstruída → `85 cases, 0 failures`; os **43 arquivos PHP da dev pós-merge #98 têm blobs idênticos aos da base validada**. Estes números não significam homologação de segurança, ACL, LGPD, RAG, APIs ou produção real.
- **Autorização vigente:** responsável concedeu autorização ampla para desenvolver/provar mocks sintéticos e fazer merges **somente na dev** após validações, com solicitações apenas para decisões críticas. `AGENTS.md` foi atualizado documentalmente para refletir esse processo.
- **Bloqueios preservados:** R01-G01..G11 e CORE/SEG/AUT CRM abertos; `main`, produção, infraestrutura, serviços reais, dados de clientes, credenciais, migrações, SQL/ALLOW e despesas exigem novas decisões específicas.

**Precedência:** trechos históricos abaixo que descrevem F1-08 como PR DRAFT, ou o QA visual como pendente, são registros do desenvolvimento anterior à integração #98 e não definem o estado atual.

---


## FT01/F1-08 — Laboratório visual sintético em branch isolada (08/11/2026)

- **Base validada:** `dev/ft01-aia-core-mocks-2026-11-06` em `23c076c623a47b9dc6c91122eb72c6c271d27a78`, após PRs #96 e #97 integrados; 85 testes PHP sintéticos previamente aprovados.
- **Branch F1-08:** `dev/ft01-f1-08-visual-lab-2026-11-08`, derivada desse SHA. Adicionou `examples/f108-laboratorio-navegavel.html`, `tests/Browser/f108_smoke.cjs` e a especificação `docs/AIA-FT01-F1-08-LABORATORIO-VISUAL-OFFLINE.md`. Nenhum arquivo PHP existente alterado.
- **Objetivo:** permitir navegar em quatro telas e executar seis cenários **exclusivamente JS local**. O laboratório visual **não utiliza o executor PHP** e não representa sistemas ou clientes conectados.
- **Verificação feita:** arquivos exatos obtidos do GitHub; smoke de DOM executado em isolado V8 com adaptadores de módulos nativos de Node: **PASS** em quatro telas, seis casos, 14 eventos, contadores, reset e atalhos. Content Security Policy com `connect-src 'none'`; sem chamadas de rede ou storage persistente.
- **QA concluído:** teste em Node.js v22.16.0 real `F108_SMOKE_PASS`; Chromium real com Playwright em cinco resoluções 1440, 1024, 768, 390 e 320 px, sem erros JavaScript, chamadas de rede ou overflow horizontal. Seis cenários e 14 eventos auditados por viewport; capturas desktop/mobile revisadas; menu mobile, IDs, fixtures e `aria-pressed` corrigidos. HTML e teste com SHA-1 de blob Git conferidos. Firefox não foi testado; HTML servido via `set_content` devido ao bloqueio de file:// no sandbox. `php tests/Unit/run.php` reexecutado localmente na cópia de F1-07: 85 casos, zero falhas; este resultado permanece histórico e não é uma homologação da interface.
- **Limites:** PR #98 ainda em branch isolada até reconfirmar SHAs antes do merge exclusivamente na dev; `main`, VPS, produção, integração CRM/Portal/Performance/Universidade, provedor/LLM, SQL/ALLOW, dados privados ou custos. Gates R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos.

**Nota de precedência:** este parágrafo registra a proposta técnica F1-08 na branch filha. O estado vigente da dev continua sendo F1-07 integrado e validado até eventual merge específico da nova proposta.


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


**Data de referência:** 2026-11-06  
**Fase:** AIA-01 aprovado funcionalmente (D01–D08); detalhamento técnico e implementação ainda bloqueados. AIA-02 aprovado funcionalmente (D01–D10); AIA-03 aprovado funcionalmente (D01–D10); AIA-04 aprovado funcionalmente (D01–D10); AIA-05 aprovado funcionalmente (D01–D10); AIA-06 aprovado funcionalmente (D01–D10). AIA-07 aprovado funcionalmente (D01–D10); AIA-08 aprovado funcionalmente (D01–D10); AIA-09 aprovado funcionalmente (D01–D10); AIA-10 aprovado funcionalmente (D01–D10); AIA-11 aprovado funcionalmente (D01–D10); AIA-12 aprovado funcionalmente (D01–D10); os doze módulos possuem diretrizes funcionais aprovadas. A revisão transversal AIA-R01 está aprovada documentalmente (R01-D01–D08); os 11 gates técnicos continuam abertos. R02/S01 aprovado documentalmente (D01–D10); R02/S02 aprovado documentalmente (D01–D10); R02/S03 aprovado documentalmente (D01–D10); R02/S04 aprovado documentalmente (D01–D10); R02/S05 aprovado documentalmente (D01–D10); os cinco recortes S01–S05 foram ratificados documentalmente. AIA-R02/R01 aprovado documentalmente (D01–D08); R02/R02 aprovado documentalmente (D01–D10); R02/R03 aprovado documentalmente (D01–D10); R02/R04 aprovado documentalmente (D01–D10); R02/R05 aprovado documentalmente (D01–D10); todos os critérios de testes permanecem não executados. R02/R06 aprovado documentalmente (D01–D08), concluindo a síntese documental de segurança do R02; R03/S01 aprovado documentalmente (D01–D10); R03/S02 aprovado documentalmente (D01–D10); R03/S03 aprovado documentalmente (D01–D10); R03/S04 aprovado documentalmente (D01–D10); R03/S05 aprovado documentalmente (D01–D10). R03/R01 aprovado documentalmente (D01–D08); R04/S01 aprovado documentalmente (D01–D10); R04/S02 aprovado documentalmente (D01–D10); R03/R02 aprovado documentalmente (D01–D10), sem escolha de stack nem autorização de código; AIA-FT01 aprovado documentalmente (FT01-D01–D10); FT01-F1-01 EXPRESSAMENTE AUTORIZADO em 2026-11-06 para PHP mockado e testes unitários locais sintéticos em branches isoladas; execução/resultado pendente de verificação. Fast-track permanece apenas como critérios, sem código ou homologação de contratos. Sem código, testes, deploy, contratações ou fechamento de gates. Sem credenciais, redes, testes ou programação. Não há escolha de banco/fila, infraestrutura, testes ou programação. Todos R01-G01 a G11 permanecem abertos. Implementação permanece bloqueada.  
**GitHub:** `rafhael100/imagine-ai-agents`, branch padrão `main`.

## Estado confirmado

- Repositório privado criado e acesso administrativo/escrita verificado.
- Documentação inicial publicada; nenhuma implementação autorizada.
- AIA-01: diretrizes AIA01-D01 a D08 **aprovadas funcionalmente em 2026-11-06**; especificação em `docs/AIA-01-CATALOGO-AGENTES.md`. A implantação segue bloqueada.
- Nenhum deploy ou provisionamento de infraestrutura executado por este projeto.
- Nenhuma alteração autorizada nos repositórios CRM, Portal, Performance AI ou Universidade.

## Diretrizes existentes a respeitar

- CRM Imagine: SaaS multiempresa e motor nativo de automações configuráveis para clientes, com políticas próprias de permissões e créditos de IA.
- n8n: uso central em fluxos controlados pela Imagine, sujeito à validação de licenciamento comercial.
- Serviços, dados e autenticações de produtos devem permanecer segregados; integração por contratos e APIs.
- Decisões pendentes em outros projetos não são consideradas automaticamente aprovadas aqui.

- AIA-02: diretrizes AIA02-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-02-INSTRUCOES-PERSONALIDADE-REGRAS.md`; sem autorização de implementação.

- AIA-03: diretrizes AIA03-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-03-MODELOS-PROVEDORES-ROTEAMENTO.md`. Fornecedor, modelo, preço, contrato, orçamento, stack e implementação permanecem sem aprovação.

- AIA-04: diretrizes AIA04-D01 a D10 **aprovadas funcionalmente em 2026-11-06**. Documento `docs/AIA-04-FERRAMENTAS-INTEGRACOES.md`; sem implementação, credenciais ou integrações reais.

- AIA-05: diretrizes AIA05-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-05-BASE-CONHECIMENTO-RAG.md`. Sem ingestão real, indexação, código ou despesas.

- AIA-06: diretrizes AIA06-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-06-CONTEXTO-MEMORIA.md`. Sem memória real, código, custos ou integração.

- AIA-07: diretrizes AIA07-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-07-EXECUCAO-ORQUESTRACAO.md`. Sem programação, execução real ou infraestrutura.

- AIA-08: diretrizes AIA08-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-08-AUTONOMIA-PERMISSOES-APROVACOES.md`. Sem concessão real de privilégios, código, custos ou execução.

- AIA-09: diretrizes AIA09-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-09-LABORATORIO-TESTES-AVALIACOES.md`. Sem testes reais, código, contratação ou despesas.

- AIA-10: diretrizes AIA10-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-10-MONITORAMENTO-CUSTOS-OBSERVABILIDADE.md`. Sem coleta de telemetria real, código, despesas ou integração.

- AIA-11: diretrizes AIA11-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-11-GOVERNANCA-SEGURANCA-AUDITORIA.md`. Sem implementação de controles, testes reais ou programação.

- AIA-12: diretrizes AIA12-D01 a D10 **aprovadas funcionalmente em 2026-11-06**; documento `docs/AIA-12-SDK-APIS-CONTRATOS-PRODUTOS.md`. Sem endpoints, SDK, integrações ou programação real.

- AIA-R01: diretrizes R01-D01 a D08 **aprovadas documentalmente em 2026-11-06**, com registro em `docs/AIA-R01-CONSOLIDACAO-TRANSVERSAL-GATES.md`. Gates R01-G01 a G11 continuam **todos abertos**; sem autorização de implementação.

- AIA-R02/S01: diretrizes R02-S01-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-S01-MATRIZ-IDENTIDADE-AUTORIZACAO-SEGREGACAO.md`. Gates R01-G01 a G11 ainda abertos; sem autenticação real ou código.

- AIA-R02/S02: diretrizes R02-S02-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R02-S02-CONTRATOS-AUTENTICACAO-CLAIMS.md`; sem escolha de protocolo, credenciais ou implementação e sem fechar gates.

- AIA-R02/S03: diretrizes R02-S03-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-S03-MATRIZ-PERMISSOES-ESCOPOS-CRM.md`. Nenhum ALLOW, SQL, código, teste ou concessão real; gates CORE/SEG/AUT do CRM e R01-G01 a G11 preservados.

- AIA-R02/S04: diretrizes R02-S04-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-S04-SEGREGACAO-RETENCAO-PRIVACIDADE.md`. Sem prazos específicos, tratamento real de dados, código ou fechamento de gates.

- AIA-R02/S05: diretrizes R02-S05-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-S05-ESTADOS-APROVACOES-IDEMPOTENCIA.md`. Sem programação, autorização real ou fechamento de gates.

- AIA-R02/R01: diretrizes R02-R01-D01 a D08 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-R01-CONSOLIDACAO-SEGURANCA-CONTRATOS.md`. Conflitos C01–C08 exigem contratos técnicos; R01-G01 a G11 abertos. Sem homologação ou programação.

- AIA-R02/R02: diretrizes R02-R02-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-R02-CONTRATOS-CONFIANCA-ESCOPOS-CRM.md`. R01-G01 a G11 e CORE/SEG/AUT do CRM preservados; sem código, SQL, ALLOW ou testes reais.

- AIA-R02/R03: diretrizes R02-R03-D01 a D10 **aprovadas documentalmente em 2026-11-06**; documento `docs/AIA-R02-R03-MAQUINA-ESTADOS-ALCADAS-EFEITOS.md`. Gates R01-G01 a G11 e CORE/SEG/AUT do CRM seguem abertos; sem SQL, ALLOW, APIs reais, testes ou código.

- AIA-R02/R04: diretrizes R02-R04-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R02-R04-DADOS-AUDITORIA-RETENCAO-PRIVACIDADE.md`. Nenhuma política legal concreta, prazo, acesso a dados reais, SQL, ALLOW ou código aprovado; R01-G01 a G11 e CORE/SEG/AUT do CRM preservados.

- AIA-R02/R05: diretrizes R02-R05-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R02-R05-HOMOLOGACAO-SEGURANCA-EVIDENCIAS.md`. Casos sintéticos **não executados**, sem evidência de testes nem homologação de gates. R01-G01 a G11 e CORE/SEG/AUT do CRM preservados; sem código, SQL ou ALLOW.

- AIA-R02/R06: diretrizes R02-R06-D01 a D08 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R02-R06-SINTESE-SEGURANCA-MATRIZ-EVIDENCIAS.md`. Evidências G01–G11 pendentes, **sem testes executados nem homologação**. CORE/SEG/AUT do CRM preservados, sem SQL, ALLOW, código, credenciais ou deploy.

- AIA-R03/S01: diretrizes R03-S01-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-S01-TOPOLOGIA-FRONTEIRAS-AMBIENTES.md`. Topologia conceitual, nenhum fornecedor, VPS, rede, stack ou região escolhido/provisionado; R01-G01 a G11 e CORE/SEG/AUT do CRM seguem abertos.

- AIA-R03/S02: diretrizes R03-S02-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-S02-PERSISTENCIA-FILAS-IDEMPOTENCIA-RECUPERACAO.md`. Persistência, filas, idempotência e recuperação permanecem conceituais, sem tecnologia, prazo, fornecedor, SQL, ALLOW, código, infraestrutura ou testes autorizados. Gates R01-G01 a G11 e CORE/SEG/AUT do CRM preservados.

- AIA-R03/S03: diretrizes R03-S03-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-S03-IDENTIDADE-SEGREDOS-REDES-CONECTIVIDADE.md`. Contratos de identidade, custódia de segredos, conectividade e isolamento conceituais; sem protocolo, provedor, chave, firewall, infraestrutura, SQL, ALLOW ou teste autorizado. Gates R01-G01 a G11 e CORE/SEG/AUT do CRM preservados.

- AIA-R03/S04: diretrizes R03-S04-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-S04-MODELOS-PROVEDORES-CUSTOS-LICENCAS.md`. Catálogo de modelos, privacidade, custos/créditos, licenciamento n8n e contingência seguem conceituais; nenhum fornecedor, modelo, licença, preço ou infraestrutura homologado. R01-G01 a G11 e CORE/SEG/AUT CRM abertos.

- AIA-R03/S05: diretrizes R03-S05-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-S05-IMPLANTACAO-HOMOLOGACAO-VERSIONAMENTO-ROLLBACK.md`. Versionamento, rollout, go/no-go e rollback são apenas critérios de planejamento; nenhum teste, release, deploy, compra ou código executado. R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos.

- AIA-R03/R01: diretrizes R03-R01-D01 a D08 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-R01-REVISAO-CONSOLIDADA-ARQUITETURA.md`. Revisão de R03/S01–S05 e riscos ARQ-C01–C12 ainda dependentes de evidências; nenhum stack, fornecedor, serviço, prazo ou preço homologado; R01-G01 a G11 e CORE/SEG/AUT CRM seguem abertos.

- AIA-R04/S01: diretrizes R04-S01-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R04-S01-CONTRATOS-TECNICOS-MINIMOS-MVP-CRM.md`. Operações, campos, erros, estados e confirmações ainda conceituais, sem API real, schema, SQL, ALLOW, teste, integração ou alteração do CRM. R01-G01 a G11 e CORE/SEG/AUT seguem abertos.

- AIA-R04/S02: diretrizes R04-S02-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R04-S02-SCHEMAS-VERSIONAMENTO-OPERACOES-CRM-AIA.md`. Modelos tipados, versões, erros, idempotência e critérios de fast-track continuam **somente documentais**, sem contrato CRM homologado, API real, schema executável, código, SQL, ALLOW, teste ou integração. Gates R01-G01 a G11 e CORE/SEG/AUT do CRM continuam abertos.

- AIA-R03/R02: diretrizes R03-R02-D01 a D10 **aprovadas documentalmente em 2026-11-06**, documento `docs/AIA-R03-R02-COMPARATIVO-STACK-CUSTOS-FAST-TRACK.md`. Shortlist de stack, custos e critérios de fast-track mockado seguem somente documentais. Nenhuma stack/versão, licença, servidor, orçamento ou autorização de código homologada; R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.

- AIA-FT01: diretrizes FT01-D01 a D10 **APROVADAS DOCUMENTALMENTE em 2026-11-06**, documento `docs/AIA-FT01-PROPOSTA-AUTORIZACAO-CODIGO-MOCKADO.md`. Define planejamento do primeiro lote mockado e futura branch de código, com preflight Git obrigatório; **FT01-F1-01 NÃO AUTORIZADO**. Nenhuma programação ou teste autorizado neste PR; R01-G01 a G11 e CORE/SEG/AUT CRM seguem abertos.

- AIA-FT01/F1-01: **APROVADO PR #70 E AUTORIZADO EXPRESSAMENTE EM 2026-11-06** pelo responsável, documento `docs/AIA-FT01-F1-01-ORDEM-EXECUTIVA-CONDICIONADA.md`. Permissão restrita a modelos, contratos e mocks PHP puros e testes unitários locais sintéticos nas branches isoladas. Sem merge de código em main, SQL/ALLOW, API real, dados privados, instalação, gastos ou deploy. Gates R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos. Resultados e executor serão registrados após preflight/execução.

## Propostas complementares em avaliação

- Serviço compartilhado de agentes, com administração interna central e configuração limitada nos produtos.
- Catálogo de agentes, modelos, ferramentas, políticas, conhecimentos, memórias, execuções, testes e custos.
- Autonomia controlada, aprovação humana de operações sensíveis e melhoria supervisionada.
- Escopo modular AIA-01 a AIA-12, ainda **não aprovado para desenvolvimento**.

## Gates abertos

- [x] Criar e confirmar repositório privado no GitHub.
- [x] Aprovar funcionalmente AIA01-D01 a D08 (2026-11-06).
- [ ] Definir/validar os critérios técnicos detalhados de AIA-01 e integrações antes da implementação.
- [x] Aprovar funcionalmente AIA02-D01 a D10 (2026-11-06).
- [ ] Detalhar e validar aspectos técnicos de AIA-02, incluindo segurança, interfaces e testes.
- [x] Aprovar funcionalmente AIA03-D01 a D10 (2026-11-06).
- [ ] Detalhar AIA-03: provedores e modelos específicos, contratos, privacidade, custos e critérios técnicos.
- [x] Aprovar funcionalmente AIA04-D01 a D10 (2026-11-06).
- [ ] Detalhar aspectos técnicos de AIA-04 e homologação de contratos/ferramentas.
- [x] Aprovar funcionalmente AIA05-D01 a D10 (2026-11-06).
- [ ] Detalhar aspectos técnicos de AIA-05, retenção, ACL, indexação e homologação de dados.
- [x] Aprovar funcionalmente AIA06-D01 a D10 (2026-11-06).
- [ ] Detalhar aspectos técnicos AIA-06, retenção, consentimento/base legal, fluxos de revogação e recuperação.
- [x] Aprovar funcionalmente AIA07-D01 a D10 (2026-11-06).
- [ ] Detalhar critérios técnicos, recuperação, filas, quotas e idempotência de AIA-07.
- [x] Aprovar funcionalmente AIA08-D01 a D10 (2026-11-06).
- [ ] Detalhar aspectos técnicos de AIA-08, matriz de alçadas, eventos de aprovação, autorização e segurança.
- [x] Aprovar funcionalmente AIA09-D01 a D10 (2026-11-06).
- [ ] Detalhar critérios técnicos de avaliação, datasets sintéticos, métricas, gates e evidências de AIA-09.
- [x] Aprovar funcionalmente AIA10-D01 a D10 (2026-11-06).
- [ ] Detalhar AIA-10: eventos, correlação, custos, limites, alertas, privacidade e conciliação.
- [x] Aprovar funcionalmente AIA11-D01 a D10 (2026-11-06).
- [ ] Detalhar AIA-11: responsabilidade jurídica, matriz de risco, retenção, contratos de segurança, auditoria e incidentes.
- [x] Aprovar funcionalmente AIA12-D01 a D10 (2026-11-06).
- [ ] Detalhar tecnicamente AIA-12, schemas, identidade, integrações e compatibilidade.
- [x] Elaborar proposta AIA-R01 de revisão cruzada dos 12 módulos (2026-11-06).
- [x] Aprovar documentalmente AIA-R01-D01 a D08 (2026-11-06).
- [ ] Manter R01-G01 a G11 abertos até homologação com evidências e decisão explícita.
- [x] Aprovar documentalmente R02-S01-D01 a D10 (2026-11-06).
- [ ] Detalhar contrato técnico de R02/S01, sem presumir fechamento de R01-G01/G02.
- [x] Aprovar documentalmente R02-S02-D01 a D10 (2026-11-06).
- [ ] Detalhar contratos técnicos de autenticação sem presumir fechamento de R01-G01/G02.
- [x] Aprovar documentalmente R02-S03-D01 a D10 (2026-11-06).
- [ ] Validar matriz de escopos e papéis com a documentação do CRM, sem fechamento cruzado de gates.
- [x] Aprovar documentalmente R02-S04-D01 a D10 (2026-11-06).
- [ ] Detalhar categorias, bases legais, contratos de terceiros, retenção e expurgo com validação de responsáveis; R01-G05 permanece aberto.
- [x] Aprovar documentalmente R02-S05-D01 a D10 (2026-11-06).
- [ ] Conciliar tecnicamente estados, alçadas, idempotência e confirmação com CRM e AIA-04/07/08/10/12; R01-G03/G04/G06/G08 seguem abertos.
- [x] Aprovar documentalmente R02-R01-D01 a D08 (2026-11-06).
- [ ] Detalhar e homologar conflitos C01–C08, com evidências e contratos cruzados entre produtos, sem fechar gates por inferência.
- [x] Aprovar documentalmente R02-R02-D01 a D10 (2026-11-06).
- [ ] Conciliar contratos com papéis/ACL oficiais do CRM e homologar gates em decisões separadas.
- [x] Aprovar documentalmente R02-R03-D01 a D10 (2026-11-06).
- [ ] Homologar regras técnicas de estados, alçadas, efeitos e idempotência por produto, sem fechar automaticamente R01-G03/G04/G06/G07/G08.
- [x] Aprovar documentalmente R02-R04-D01 a D10 (2026-11-06).
- [ ] Detalhar responsáveis por fluxos, bases legais, prazos e evidências materiais para homologação futura; R01-G05 permanece aberto.
- [x] Aprovar documentalmente R02-R05-D01 a D10 (2026-11-06), **sem executar testes**.
- [ ] Validar tecnicamente critérios de homologação, responsáveis e evidências antes de qualquer execução autorizada; R01-G07 continua aberto.
- [x] Aprovar documentalmente R02-R06-D01 a D08 (2026-11-06), sem homologar gates nem executar testes.
- [ ] Reunir evidências futuras para R01-G01 a G11 individualmente, sob autorização explícita e com responsáveis.
- [x] Aprovar documentalmente R03-S01-D01 a D10 (2026-11-06), sem escolher topologia física/fornecedor.
- [ ] Confrontar alternativas de topologia com requisitos de isolamento, custo, segurança e recuperação; R01-G10 segue aberto.
- [x] Aprovar documentalmente R03-S02-D01 a D10 (2026-11-06), sem selecionar tecnologia.
- [ ] Homologar no futuro a persistência/filas, idempotência, recuperação e limites em contratos e evidências específicas, preservando R01-G03/G05/G06/G07/G08/G10/G11.
- [x] Aprovar documentalmente R03-S03-D01 a D10 (2026-11-06), sem emitir credenciais ou alterar redes.
- [ ] Comparar protocolos/controles de segurança e validar escopos/revogação em homologação futura, sem fechar R01-G01/G02/G05/G08/G09/G10/G11.
- [x] Aprovar documentalmente R03-S04-D01 a D10 (2026-11-06), sem homologar fornecedores, licenças ou valores.
- [ ] Validar termos efetivos de provedores e n8n, custos e contratos comercial/jurídicos em decisão própria; R01-G05/G06/G09/G10/G11 seguem abertos.
- [x] Aprovar documentalmente R03-S05-D01 a D10 (2026-11-06), sem liberar testes, código ou deploy.
- [x] Aprovar documentalmente R03-R01-D01 a D08 (2026-11-06), sem homologar arquitetura, fechar gates ou autorizar código.
- [x] Aprovar documentalmente R04-S01-D01 a D10 (2026-11-06), sem implementar API ou fechar gates.
- [x] Aprovar documentalmente R04-S02-D01 a D10 (2026-11-06), sem homologação técnica nem autorização de código.
- [x] Aprovar documentalmente R03-R02-D01 a D10 (2026-11-06), sem selecionar stack ou autorizar código.
- [x] Aprovar documentalmente FT01-D01 a D10 (2026-11-06), **sem autorização para executar FT01-F1-01**.
- [x] Aprovar PR #70 e autorizar expressamente FT01-F1-01 em 2026-11-06, sob condições documentadas.
- [ ] Executar FT01-F1-01 apenas após preflight Git autenticado, sem integração real ou alteração da main; registrar diff e testes locais sintéticos reais.
- [ ] Avaliar futuros contratos técnicos bilaterais CRM/AIA e comparação de stack sem código, SQL/ALLOW, testes ou infraestrutura.
- [ ] Manter R01-G01 a G11 abertos e sujeitos à homologação específica.
- [ ] Aprovar escopo e fronteiras de responsabilidade entre produtos.
- [ ] Aprovar contratos de autenticação, autorização e isolamento.
- [ ] Definir provedores, modelos, abstração, contingência e custos.
- [ ] Definir stack técnica do núcleo, filas, armazenamento e observabilidade.
- [ ] Definir políticas de dados, memória, retenção e bases de conhecimento.
- [ ] Validar licenciamento comercial de ferramentas e n8n.
- [ ] Fechar MVP, orçamento, critérios de teste e aceite.
- [ ] Autorizar implementação em branch separada.

**Bloqueio:** sem programação, deploy, migração, mudanças em outros repositórios ou consumo pago de APIs sem autorização.

## Execução restrita FT01-F1-01 — 2026-11-06

Registro atual deste lote prevalece sobre descrições históricas de ausência de
código/testes autorizados acima. Executor: Codex, ambiente gerenciado `/workspace`.
Autorização expressa do responsável nesta conversa e ordem F1-01 ratificada.

- Repositório: `rafhael100/imagine-ai-agents`.
- Base existente: `dev/ft01-aia-core-mocks-2026-11-06`.
- Filha existente: `dev/ft01-f1-01-domain-mocks-2026-11-06`.
- SHA inicial comum de main/base/filha: `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.
- Preflight: `gh auth status` autenticado como `rafhael100`, permissão ADMIN,
  `git ls-remote` confirmou as três refs; `git push --dry-run` retornou
  `Everything up-to-date`. Checkout local rastreia a filha existente; nenhuma
  branch remota foi recriada, sobrescrita ou forçada.
- Leitura: AGENTS, README, STATUS, PR #70, AIA-FT01 (proposta e ordem),
  R03/R02, R04/S01–S02 e R02/R03. Árvore inicialmente limpa, apenas documental.
- Entrega: oito modelos puros e erro local em `src/Domain`; três interfaces em
  `src/Contracts`; três mocks em `src/Mocks`; runner com dez casos em
  `tests/Unit/run.php`; README e este registro. AGENTS e demais documentos intactos.
- PHP: `php -v` retornou `php: command not found` (127). **Zero testes unitários
  executados e zero lint PHP executado.** Dez casos escritos, todos não executados.
  Nenhuma versão de runtime verificada e nenhuma instalação realizada, conforme
  seção 1 da ordem executiva. Compatibilidade sintática/alvo PHP 8.1+ pendente.
- Verificação disponível: revisão estática do diff e `git diff --check`;
  não equivale a validação de execução PHP ou segurança do produto.
- Limites: fixtures fixas, igualdade material por bytes, relógio explícito,
  idempotência volátil sem concorrência, prova de efeito somente sintética;
  contexto não autenticado e políticas locais não substituem ACL CRM.
- Pendências: revisão do draft filha → base e execução futura de lint/testes
  em PHP previamente disponível e autorizado. Commits e URL do draft serão
  informados no relatório final/GitHub, sem merge.
- R01-G01 a G11 TODOS ABERTOS; CORE/SEG/AUT CRM preservados. Sem APIs reais,
  dados pessoais, credenciais de serviços de produto, Composer, SQL, ALLOW,
  instalação, serviços pagos, VPS/Cloudflare/n8n, deploy ou início F1-02.
  GitHub usado exclusivamente no versionamento autorizado. Main, base e demais
  repositórios não recebem alterações deste lote.

## Revalidação solicitada do PR #71 — 2026-11-06

- Executor: Codex, ambiente gerenciado `/workspace`; exclusivamente na filha
  `dev/ft01-f1-01-domain-mocks-2026-11-06`, árvore inicialmente limpa.
- Código sob validação: `b55a42b612ad4937e496327401502a2ae3011ac9`.
  PR #71 confirmado OPEN/DRAFT, filha e base corretas.
- `command -v php php8.1 php8.2 php8.3 php8.4 php8.5`: nenhum executável
  encontrado (saída vazia).
- Verificação dos padrões `/usr/bin/php*`, `/usr/local/bin/php*`,
  `/opt/php*/bin/php`, `/opt/php/*/bin/php`: nenhum runtime encontrado.
- Busca adicional por arquivos `php` ou `php8.[1-9]` em `/usr/bin`,
  `/usr/local/bin`, `/opt` e `/workspace`: nenhum resultado.
- `php -v`: **FAIL na disponibilidade do runtime**, código de saída **127**,
  `/bin/bash: line 1: php: command not found`. Versão PHP não verificável.
- Lint PHP: **NÃO EXECUTADO**; dez casos de `tests/Unit/run.php`:
  **TODOS NÃO EXECUTADOS**. Nenhum resultado PASS/FAIL de código é atribuído.
  Não foi possível determinar necessidade de correções pela execução.
- Nenhuma instalação, dependência, container ou ambiente alternativo utilizado.
  Nenhuma alteração de código. Apenas este registro documental na filha.
- `git diff --check`: PASS para a atualização documental.
- Pendência: executar sintaxe de todos os arquivos PHP em `src/` e `tests/`
  e `php tests/Unit/run.php` quando houver PHP 8.1+ previamente instalado
  no ambiente autorizado. PR permanece rascunho, validação de runtime bloqueada.
- Sem merge, F1-02, deploy, alteração de main/base ou outros repositórios,
  acesso CRM/VPS/Cloudflare/n8n/APIs reais de produto. GitHub restrito ao
  versionamento e registro no PR autorizado.
- R01-G01 a G11 TODOS ABERTOS; CORE/SEG/AUT CRM permanecem abertos.

## Validação independente dos mocks PHP — 2026-11-07

Esta atualização **prevalece sobre o impedimento histórico de PHP no Codex**:
o runtime segue ausente no ambiente Codex, mas a validação foi executada
independentemente em ambiente isolado de análise, sem GitHub Actions.

- Executor da validação: ChatGPT, **PHP CLI 8.4.24**, em ambiente isolado local,
  sem acesso autenticado à conta GitHub e sem instalação de dependências.
- Integridade: os 16 arquivos PHP (`src/` e `tests/Unit/run.php`) foram
  reproduzidos a partir do conteúdo autenticado do GitHub e **cada arquivo
  teve SHA-1 Git blob idêntico ao informado pelo conector**. Portanto a
  validação corresponde aos bytes existentes no PR #71 no momento da leitura.
- Lint: `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l`
  → **16/16 sem erros de sintaxe**, código de saída 0.
- Testes: `php tests/Unit/run.php` → **10 PASS, 0 FAIL**,
  saída `10 cases, 0 failures`, código de saída 0.
- Limitações: apenas testes locais sintéticos sobre os mocks. Não comprovam
  autenticação/ACL real, segurança de produção, concorrência, durabilidade
  transacional, CRM real, LGPD em produção ou desempenho.
- Custo: **zero execuções de GitHub Actions para esta validação**.
  O workflow proposto inicialmente foi removido da branch filha sem execução,
  evitando introduzir gatilho de consumo de minutos. Nenhuma configuração
  de cobrança da conta foi alterada.
- Preservação: `main`, branch base e repositórios de outros produtos
  inalterados; PR #71 permanece **DRAFT/SEM MERGE** até decisão específica.
  R01-G01 a G11 e CORE/SEG/AUT CRM continuam **ABERTOS**;
  F1-02, integrações, infraestrutura e deploy não autorizados.

## Encerramento controlado FT01-F1-01 — 2026-11-07

- Aprovação expressa do responsável para merge **somente no ramo de desenvolvimento** registrada nesta conversa.
- PR [#71](https://github.com/rafhael100/imagine-ai-agents/pull/71) **MERGED**, método squash, commit `d419d481af9fbaea0e1baa60094ddcc799fb72b4`, destino `dev/ft01-aia-core-mocks-2026-11-06`.
- Código aceito **somente como primeiro lote mockado**, não como produto homologado: 16 arquivos PHP validados em ambiente local isolado com PHP 8.4.24, lint 16/16, 10 testes unitários sintéticos PASS e zero falhas, conforme registro e comentário do PR. Não representa autenticação, integração CRM nem segurança de produção.
- Nenhuma execução GitHub Actions; workflow temporário removido antes do merge.
- `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Não há merge para `main`, deploy, código F1-02, SQL, ALLOW, integração real, dados pessoais, credenciais, n8n, Cloudflare ou VPS.
- **Próxima etapa: somente proposta e análise documental de FT01-F1-02**. Desenvolvimento F1-02 requer nova autorização expressa, branch filha exclusiva e review separado.
- Gates R01-G01 a G11 **TODOS ABERTOS**; CORE/SEG/AUT CRM e demais bloqueios técnicos **ABERTOS**.

## Proposta documental FT01-F1-02 — 2026-11-07

- O responsável **aprovou a preparação do próximo plano documental**, não a programação F1-02.
- Documento **RATIFICADO DOCUMENTALMENTE em 2026-11-08, sem autorização de execução**: `docs/AIA-FT01-F1-02-COORDENACAO-SUPERVISIONADA-MOCK-PROPOSTA.md` na branch documental `docs/ft01-f1-02-proposta-orquestracao-mock-2026-11-07`, baseada em `dev/ft01-aia-core-mocks-2026-11-06`.
- Decisões **FT01-F102-D01 a D10 APROVADAS DOCUMENTALMENTE (alternativas A, 2026-11-08)**, propondo uma camada de coordenação supervisionada apenas simulada, status e reconciliação fake, menor privilégio lógico, 12 novos casos sintéticos e preservação dos 10 casos de regressão F1-01.
- **F1-02 NÃO AUTORIZADO para código**, branch de execução não criada; nenhum teste novo, PHP instalado, CI/Actions, despesa, instalação, servidor, API ou merge autorizado.
- **F1-01 encerrado apenas na branch dev**; `main`, CRM, Portal e demais produtos intactos.
- R01-G01 a G11 **TODOS ABERTOS** e CORE/SEG/AUT CRM **TODOS ABERTOS**.
- Próximo passo: preparar autorização executiva **separada** para avaliação do responsável, antes de Codex ou testes F1-02.

## F1-02 — Ratificação documental e proposta executiva — 2026-11-08

- PR [#73](https://github.com/rafhael100/imagine-ai-agents/pull/73) **MERGED apenas em** `dev/ft01-aia-core-mocks-2026-11-06`, commit `77fb9abeec46f35e82f2c4151b95620cc200c2ea`, com aprovação **documental** das alternativas A FT01-F102-D01 a D10. Nenhum código F1-02 foi criado.
- **ORDEM EXECUTIVA AUTORIZADA EM 2026-11-08, somente no escopo PHP mockado F1-02**: `docs/AIA-FT01-F1-02-ORDEM-EXECUTIVA-PROPOSTA.md` na branch documental `docs/ft01-f1-02-autorizacao-executiva-proposta-2026-11-08`. O texto estabelece os limites de futuro código PHP puro, branch exclusiva, 12 testes novos + regressão dos 10 testes F1-01, sem instalar dependências.
- O responsável **AUTORIZOU EXPRESSAMENTE a execução F1-02** em 2026-11-08, respondendo “ok aprovado” à solicitação explícita de código PHP mockado e testes unitários sintéticos sem merge, custos ou infraestrutura. A execução ainda depende de preflight, commits e evidências; o aceite não autoriza merge de código, F1-03 ou deploy.
- `main`, CRM, Portal, demais repositórios e VPS/Cloudflare/n8n não foram alterados nesta fase documental. Não há autorização de SQL/ALLOW, APIs reais, dados privados, credenciais, GitHub Actions, despesas, merge de código ou deploy.
- **R01-G01 a G11 TODOS ABERTOS**, **CORE/SEG/AUT CRM ABERTOS**. F1-03 não autorizado.

## Autorização executiva F1-02 — 2026-11-08

- O responsável autorizou **AIA-FT01-F1-02** para programação PHP puro mockado, coordenação supervisionada local e testes unitários sintéticos, exclusivamente na futura branch filha `dev/ft01-f1-02-supervised-mocks-2026-11-07`, base `dev/ft01-aia-core-mocks-2026-11-06`, somente após preflight Git e preservação da base.
- **PR #75:** autorização documental e ordem técnica; não confundir com PR de código ou evidências de execução. Devem ser entregues 12 novos testes mais regressão dos 10 originais, quando runtime PHP autorizado estiver disponível, sem instalar dependências.
- **Nenhuma autorização:** alteração de `main`, merge de código, F1-03, SQL/ALLOW, `src/Contracts/`, APIs CRM/IA reais, VPS/Cloudflare/n8n, GitHub Actions, credenciais, dados pessoais, gastos ou deploy.
- R01-G01 a G11 **TODOS ABERTOS** e CORE/SEG/AUT CRM **ABERTOS**.

## Execução FT01-F1-02 — 2026-11-08

- Executor: Codex, ambiente gerenciado `/workspace`. Autorização expressa do
  responsável nesta conversa e ordem executiva ratificada no PR #75.
- `gh auth status`: autenticado como rafhael100. Remoto confirmado:
  `https://github.com/rafhael100/imagine-ai-agents.git`.
- Base e filha remotas existentes no início: ambas
  `4c705744711a7153174dda012b91af2cae77102b`. Main observada:
  `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.
- Filha exclusiva: `dev/ft01-f1-02-supervised-mocks-2026-11-07`, checkout local
  rastreando a ref existente; sem recriar/sobrescrever branch remota. Árvore
  inicialmente limpa, F1-01 presente e nenhum workflow `.github` na árvore.
- Lidos AGENTS, README, STATUS, AIA-FT01, R03/R02, R04/S01–S02, proposta de
  coordenação F1-02 e ordem executiva. As restrições históricas documentais cedem
  apenas ao escopo expressamente autorizado; gates mantidos.
- Plano/arquivos: novo `src/MockOrchestration/SupervisedCoordinator.php`, novo
  `src/Mocks/MockSupervisedGateway.php`, novos 12 casos em
  `tests/Unit/supervised.php`, inclusão no runner existente, README e este STATUS.
  Nenhum arquivo de Domain ou Contracts alterado; dez casos F1-01 preservados.
- Implementado: composição determinística, aprovação exata/revogação/expiração
  pelo modelo existente, reserva de envio, conflito de chave, consulta após
  timeout sem novo submit, quatro resultados fake, transições/cancelamento e
  rastros referenciais sem conteúdo material. Riscos/limitações no README.
- Runtime: `command -v php php8.1 php8.2 php8.3 php8.4` não encontrou executável;
  `php -v` retornou **127**, `php: command not found`. Versão não verificável.
- **Lint PHP NÃO EXECUTADO. Dez regressões F1-01 e 12 casos novos F1-02 TODOS
  NÃO EXECUTADOS (22 pendentes)**. Nenhum resultado PASS/FAIL de runtime atribuído.
  Não houve instalação, Composer, container, CI ou GitHub Actions.
- Verificações estáticas: `git diff --check` e comparação dos dez casos originais
  com o SHA de origem; não equivalem a execução de PHP ou homologação de segurança.
- Pendências: revisão do draft filha → base e validação isolada posterior com
  PHP 8.1+ previamente disponível. Aceite de código depende dessa validação;
  commits e PR serão identificados no relatório final e na descrição do PR.
- Main/base e outros repositórios preservados. Nenhum acesso CRM/IA reais,
  credenciais de produto, dados pessoais, SQL/ALLOW, infraestrutura, serviço pago,
  deploy ou F1-03. GitHub utilizado somente no versionamento autorizado.
- R01-G01 a G11 TODOS ABERTOS; CORE/SEG/AUT CRM ABERTOS.

## Validação independente FT01-F1-02 — 2026-11-08

Este registro **supera a limitação histórica de ausência do PHP no executor Codex** para o código do PR #76, sem alterar o fato de que o PHP continua ausente naquele ambiente.

- **Escopo testado:** código PHP do head `13a7b001e34dfa7bf5f3dbd2ecfdbe2f572ff170` do PR #76, filial `dev/ft01-f1-02-supervised-mocks-2026-11-07`, sem alteração funcional.
- **Execução independente:** ChatGPT em ambiente local isolado, PHP CLI **8.4.24**, sem autenticação com sistemas Imagine, sem PHP/Composer instalado para este trabalho, sem rede ou dependências pagas.
- **Proveniência:** 19 arquivos `.php` reproduzidos do conteúdo retornado pelo conector GitHub e todos os **19/19 hashes SHA-1 de Git blob conferidos** contra os identificadores do GitHub. Não houve edição do código usado para os testes.
- **Lint:** `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **19/19** sem erros de sintaxe, retorno **0**.
- **Testes:** `php tests/Unit/run.php` → **22 PASS, 0 FAIL**, última linha `22 cases, 0 failures`, retorno **0**; incluem 10 cenários F1-01 e 12 cenários F1-02.
- **GitHub Actions:** zero execuções verificadas no ramo; nenhum workflow criado para esta validação e nenhum minuto consumido. Sem instalações, APIs reais ou despesas adicionais.
- **Limites do aceite:** apenas comportamento sintético de mocks PHP 8.4.24; **não é homologação** de autenticação/ACL real, isolamento SaaS em produção, LGPD, durabilidade, concorrência, transações, serviços de IA/CRM ou compatibilidade de runtime em todas as versões PHP 8.1+.
- **Git:** PR #76 permanece **OPEN/DRAFT**, **SEM MERGE**. Branch base, `main`, outros repositórios e VPS/Cloudflare/n8n inalterados. F1-03 e deploy não autorizados.
- **Governança:** R01-G01 a G11 **TODOS ABERTOS** e CORE/SEG/AUT CRM **ABERTOS**. Eventual merge do PR #76 somente na branch de desenvolvimento exige autorização expressa separada do responsável.

## Encerramento controlado FT01-F1-02 — 2026-11-08

- O responsável **APROVOU EXPRESSAMENTE** o merge do [PR #76](https://github.com/rafhael100/imagine-ai-agents/pull/76) **somente** na branch `dev/ft01-aia-core-mocks-2026-11-06`.
- PR #76 **MERGED via squash**, commit `c9cfa8a41706b9fb3daf444cbe4c0fb483aa5f0a`, branch filha `dev/ft01-f1-02-supervised-mocks-2026-11-07` → dev. O estado histórico registrado acima, anterior ao merge, permanece como evidência temporal, não representa o estado atual do PR.
- **Validação previamente executada:** PHP 8.4.24 isolado, 19/19 arquivos no lint, 22/22 testes sintéticos PASS (10 do F1-01 e 12 do F1-02), 0 falhas e hashes Git blob correspondentes. Aceite técnico **somente para comportamento mockado**, sem equivaler a homologação de segurança/integrações reais.
- `main` permaneceu em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; nenhuma alteração no CRM, Portal, demais repositórios ou VPS/Cloudflare/n8n. Sem GitHub Actions, novas dependências, custos, deploy, dados pessoais, SQL/ALLOW ou APIs reais.
- **F1-03 NÃO AUTORIZADO.** Próximo passo sugerido: apresentar proposta documental F1-03 para revisão e aprovação em processo separado antes de qualquer código.
- R01-G01 a G11 **TODOS ABERTOS**; CORE/SEG/AUT CRM e demais gates **ABERTOS**.

## FT01-F1-03 — planejamento documental proposto em 2026-11-08

- O responsável disse **“sim”** para **preparar o planejamento documental de F1-03**, sem autorizar executar código ou testes.
- **PROPOSTA DOCUMENTAL, AINDA NÃO RATIFICADA:** docs/AIA-FT01-F1-03-RESILIENCIA-REPLAY-AUDITORIA-MOCK-PROPOSTA.md, na branch documental docs/ft01-f1-03-proposta-resiliencia-auditoria-mock-2026-11-08, derivada de dev/ft01-aia-core-mocks-2026-11-06.
- **F103-D01 a D10 TODAS ABERTAS**: falhas compostas sintéticas, classificação fail-closed de estados, replay **somente de leitura**, detecção de conflito em rastros mínimos, isolamento de tenant e relógio explícito; 12 cenários F1-03 **propostos** e 22 regressões F1-01/F1-02 **existentes** (34 testes planejados, NÃO executados nesta etapa).
- **F1-03 NÃO AUTORIZADO para execução**; nenhuma branch filha de código, PHP, teste, Actions, infraestrutura, API real, dependência, dado pessoal ou despesa autorizados. F1-04 também não autorizado.
- **Histórico preservado:** F1-01 e F1-02 foram integrados somente na branch dev após revisão e testes unitários sintéticos; isso não concede homologação de CRM/IA reais.
- main, CRM, Portal, Performance, Universidade e VPS/Cloudflare/n8n intocados; sem merge de código/deploy. Gates R01-G01 a G11 **TODOS ABERTOS** e CORE/SEG/AUT CRM **ABERTOS**.
- **Próximo ato:** submeter F103-D01 a D10 para ratificação **documental**; depois, se aprovado, propor autorização executiva separada. Não usar Codex antes dela.

## Ratificação documental FT01-F1-03 — 2026-11-08

- O responsável respondeu **“aprovado”** à solicitação explícita de ratificação **DOCUMENTAL das alternativas A de F103-D01 a D10**, referentes ao [PR #78](https://github.com/rafhael100/imagine-ai-agents/pull/78).
- **F103-D01 a D10 RATIFICADAS APENAS COMO DIRETRIZES DOCUMENTAIS**: simulação de falhas compostas, replay estritamente de leitura, journaling mínimo sintético, fail-closed, isolamento entre tenants e testes condicionais futuros.
- A proposta técnica contempla **12 testes F1-03 ainda NÃO EXECUTADOS**, além de 22 regressões F1-01/F1-02 já validadas anteriormente em cenário sintético; 34 cenários são **meta futura**, nunca um resultado de execução F1-03.
- **Nenhuma autorização executiva F1-03:** sem Codex para código, branch filha de implementação, testes F1-03, GitHub Actions, dependências, SQL/ALLOW, APIs reais, dados privados, VPS/Cloudflare/n8n, despesas, merge ou deploy.
- PR #78 permanece **DRAFT/OPEN**, exclusivamente documental; não fazer merge por aprovação genérica. `main`, demais repositórios e infraestrutura não são objeto de mudanças nesta ratificação.
- **R01-G01 a G11 e CORE/SEG/AUT CRM permanecem TODOS ABERTOS**, assim como homologações reais. F1-04 não autorizado.
- Próximo passo: **preparar e submeter ordem executiva F1-03 separada para avaliação**, com escopo, arquivos, cenários, condições de interrupção e aprovação explícita exigida antes de execução.

## F1-03 — Ratificação documental e proposta executiva separada — 2026-11-08

- O [PR #78](https://github.com/rafhael100/imagine-ai-agents/pull/78) foi **MERGED somente na branch** `dev/ft01-aia-core-mocks-2026-11-06`, SHA `6ed2e77b9d3366252a5258ffd4f2dcdad4b43de4`, com ratificação documental de F103-D01 a D10 (alternativas A), sem autorizar código F1-03.
- A documentação histórica acima que descreve #78 como draft/open corresponde à situação **anterior** ao merge; o registro atual prevalece.
- **ORDEM EXECUTIVA EXPRESSAMENTE AUTORIZADA em 2026-11-08, restrita ao escopo F1-03 mockado:** `docs/AIA-FT01-F1-03-ORDEM-EXECUTIVA-PROPOSTA.md`, na branch **documental** `docs/ft01-f1-03-autorizacao-executiva-proposta-2026-11-08`.
- Autoriza após preflight Git a branch de código `dev/ft01-f1-03-resilience-mocks-2026-11-08` (**ainda não criada neste registro inicial, mas agora autorizada a criação após preflight**), PHP puro, laboratório de falhas/replay somente leitura e até 12 novos testes sintéticos com regressão dos 22 existentes, sem instalar PHP no Codex.
- **F1-03 EXPRESSAMENTE AUTORIZADO PARA CÓDIGO MOCKADO E TESTES SINTÉTICOS EM 2026-11-08** pelo responsável, que respondeu “autorizado” à pergunta explícita sobre executar F1-03. Permite Codex e branch filha após preflight; **NÃO** permite merge de código, acesso real, infraestrutura, GitHub Actions, instalações, despesas ou deploy.
- `main` observada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; CRM, Portal e demais produtos/VPS/Cloudflare/n8n preservados. **R01-G01 a G11 TODOS ABERTOS; CORE/SEG/AUT CRM ABERTOS.** F1-04 também não autorizado.
- Próximo ato: preflight Git, criar/confirmar branch filha autorizada, executar código pelo Codex (executor externo) em PR draft e apresentar evidências e 34 testes, sem merge. Exigir aprovação específica antes de eventual merge.

## Autorização executiva específica FT01-F1-03 — 2026-11-08

- O responsável **AUTORIZOU EXPRESSAMENTE** o F1-03 limitado a PHP puro de resiliência/replay apenas leitura e auditoria **simulados**, 12 casos sintéticos novos + 22 regressões, exclusivamente em `rafhael100/imagine-ai-agents`, branch `dev/ft01-f1-03-resilience-mocks-2026-11-08` derivada da `dev/ft01-aia-core-mocks-2026-11-06` após preflight.
- O registro de aprovação permite iniciar trabalho mockado no Codex **sem qualquer efeito real**; não comprova que Codex tenha rodado ou que os 34 testes tenham passado. Se PHP ausente, não instalar; registrar testes NÃO EXECUTADOS.
- PR de código deve permanecer DRAFT filha → dev, **SEM MERGE**, até nova autorização expressa. `main`, src/Contracts/, SupervisedCoordinator, outros produtos, AGENTS.md e infraestrutura permanecem inalterados.
- SQL, ALLOW, APIs reais, dados privados, credenciais, GitHub Actions, Composer, instalações, gastos, VPS/Cloudflare/n8n, deploy e F1-04 não autorizados. R01-G01 a G11 **TODOS ABERTOS**; CORE/SEG/AUT CRM **ABERTOS**.

## FT01-F1-03 — execução mockada autorizada; revisão pendente (2026-11-08)

- **Autorização executiva expressa**: depois de ratificar documentalmente as decisões F103-D01 a D10 no [PR #78](https://github.com/rafhael100/imagine-ai-agents/pull/78), o responsável respondeu **“sim”** ao pedido separado de autorização para programação e testes sintéticos F1-03, **sem merge nem deploy**.
- Branch filha exclusiva: `dev/ft01-f1-03-resilience-mocks-2026-11-08` → base `dev/ft01-aia-core-mocks-2026-11-06`. `main` observada preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.
- Arquivos novos: `src/MockResilience/MockFaultGateway.php` (falhas de transporte determinísticas), `src/MockResilience/ReadOnlyTraceReview.php` (análise fail-closed estritamente de leitura), `tests/Unit/resilience.php` (12 cenários U03-01–U03-12).
- `tests/Unit/run.php` alterado SOMENTE para carregar nova suíte após as 22 verificações existentes. `README.md` e `docs/AIA-FT01-F1-03-ORDEM-EXECUTIVA-2026-11-08.md` documentam escopo e limitações.
- **Validação auxiliar já efetuada:** PHP CLI 8.4.24 disponível em ambiente local isolado; lint PHP dos três arquivos novos sem erros; **12/12 casos novos PASS, 0 FAIL** com reprodução local das classes anteriores em memória, sem APIs reais, dependências, rede, credenciais, custos ou GitHub Actions. SHA-1 Git blob das três cópias locais dos arquivos novos conferiu com o GitHub.
- **Pendência de aceite:** os **34 casos completos ainda NÃO foram executados contra o checkout integral exato da branch do PR**. Os 22 testes anteriores foram validados separadamente no PR #76; tal validação anterior não substitui reexecução dos 34 juntos. Não declarar 34/34 PASS até execução real e comprovação.
- `src/Contracts/`, `src/Domain/`, `src/MockOrchestration/`, `AGENTS.md`, CRM, Portal e infraestrutura não alterados neste lote; não existe persistência, fila, CRM real, IA real, ACL real ou recuperação durável.
- **PR de código obrigatório em DRAFT, sem merge; F1-04 não autorizado.** R01-G01–G11 e CORE/SEG/AUT CRM permanecem ABERTOS. Nenhum deploy, gasto ou CI.
- Próximo ato: executar `php -l` em todos os arquivos e `php tests/Unit/run.php` no checkout completo em ambiente já equipado com PHP 8.1+, documentar resultados e submeter para revisão humana antes de qualquer solicitação de merge.

## Revisão/continuação Codex F1-03 — 2026-11-08

- Executor: Codex, ambiente gerenciado `/workspace`; autorização explícita do
  responsável nesta conversa. GitHub autenticado como rafhael100, árvore limpa.
- Main observada: `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`;
  base: `61bcb9e665b5b6a0a88dabc9299b2083d0342458`;
  filha existente: `a9a1d0d88e170bddc36072caec506575020dea7e`.
- A filha já continha sete commits F1-03 e o PR #80 OPEN/DRAFT, com base correta.
  Foi vinculada ao checkout sem recriar ou sobrescrever a ref remota. Não foi
  duplicado o PR nem descartada a implementação existente.
- Lidos AGENTS, README, STATUS, contratos anteriores e ordens/proposta F1-03.
  As alterações documentais prévias em `docs/` foram preservadas e não são
  alterações realizadas pelo Codex nesta continuação.
- Revisão estática encontrou aceitação de prova antiga apesar de regressão
  posterior do efeito. Corrigido `ReadOnlyTraceReview`: exige STARTED inicial,
  rejeita novo submit e regressão de terminal, compara último efeito com a prova,
  verifica campos opcionais presentes mesmo quando nulos. Ampliados cenários
  dentro de U03-04/U03-05/U03-12, sem novos IDs além dos 12 autorizados.
- `command -v php php8.1 php8.2 php8.3 php8.4 php8.5`: nenhum executável;
  `php -v`: saída **127**, `php: command not found`. Sem versão verificável.
- **Lint e os 34 testes NÃO EXECUTADOS neste ambiente**. Evidência auxiliar
  histórica de PHP 8.4.24 não comprova o SHA corrigido ou a suíte integral.
  Nenhuma instalação, dependência, Actions, container ou serviço pago utilizado.
- Verificação estática: `git diff --check`, inventário de 12 novos casos e
  comparação das 22 regressões com a base; sem alegar validação PHP.
- Arquivos desta continuação: `src/MockResilience/ReadOnlyTraceReview.php`,
  `tests/Unit/resilience.php`, README e STATUS. Contratos, coordenador,
  AGENTS, Domain e documentação ratificada intactos nesta revisão.
- PR #80 permanece draft, sem merge. Pendência: lint e runner completos em PHP
  8.1+ previamente disponível e revisão humana antes de autorização de merge.
- Main/base, outros repositórios e infraestrutura preservados. R01-G01 a G11
  e CORE/SEG/AUT ABERTOS. Sem dados privados, credenciais de produto, SQL/ALLOW,
  APIs reais, deploy ou F1-04. GitHub somente para versionamento autorizado.

## Validação independente integral do PR #80 — 2026-11-08

- **Commit de código testado:** `f5d675ec08ffa8d8db40777fa836c28d89f89de2`, após a revisão corretiva do Codex sobre `ReadOnlyTraceReview.php` e os cenários U03-04/U03-05/U03-12. O histórico anterior indicando que o Codex não tinha PHP permanece válido para aquele executor, mas a pendência de execução da suíte completa está resolvida **em ambiente independente** para este commit.
- **Reprodução exata verificada:** os **22/22 arquivos PHP** de `src/` e `tests/Unit/` foram reconstruídos a partir do conteúdo dos arquivos e seus hashes Git blob SHA-1 conferidos contra os objetos do GitHub (incluindo os dois arquivos revisados). Sem arquivos PHP adicionais na árvore testada ou alterações funcionais para obter PASS.
- **Ambiente:** PHP CLI **8.4.24**, sandbox local isolado do ChatGPT; sem serviços externos, Composer, GitHub Actions, instalação de dependências, dados privados, credenciais, despesas ou VPS.
- **Comandos efetivamente executados:** `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **22/22 sem erros de sintaxe, exit 0**; `php tests/Unit/run.php` → **34/34 PASS, 0 FAIL, exit 0**; 10 regressões F1-01, 12 F1-02 e 12 U03-01–U03-12 com os casos reforçados.
- **Aceite limitado ao laboratório sintético:** não comprova autenticação ou ACL reais, integrações CRM/IA, estado durável, concorrência, recuperação, privacidade operacional, segurança de produção ou compatibilidade em outras versões PHP.
- **PR #80 permanece DRAFT e SEM MERGE**. Main, base dev, outros repositórios, VPS/Cloudflare/n8n preservados; F1-04, SQL/ALLOW, execução real e deploy não autorizados. **R01-G01 a G11 e CORE/SEG/AUT CRM ABERTOS.**
- **Próxima decisão:** submeter o PR #80 à revisão do responsável e solicitar autorização específica para eventual merge **exclusivamente na branch `dev/ft01-aia-core-mocks-2026-11-06`**, rechecando SHAs e ausência de conflitos imediatamente antes de qualquer merge.

## F1-04 — proposta documental de catálogo de ferramentas simuladas (2026-11-08)

- O responsável **AUTORIZOU APENAS PREPARAR DOCUMENTAÇÃO F1-04** ao responder "sim" ao planejamento sugerido. Não autorizou programação, testes F1-04, branch de código ou merge.
- **PROPOSTA DOCUMENTAL RATIFICADA em 2026-11-08, alternativas A F104-D01 a D10:** `docs/AIA-FT01-F1-04-CATALOGO-CAPACIDADES-DESPACHO-MOCK-PROPOSTA.md`, branch documental `docs/ft01-f1-04-catalogo-capacidades-ferramentas-mock-2026-11-08`, base `dev/ft01-aia-core-mocks-2026-11-06`; relacionada à issue #81.
- **F104-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-08:** catálogo local de tools e capabilities fictícias, versionamento/esquema, separação de sugestão e autorização, decisões de leitura e mutação fake, segregação por tenant, fail-closed, idempotência e trace mínimo.
- **12 testes U04-01..U04-12 PROPOSTOS, NÃO EXECUTADOS**, mais regressão obrigatória dos 34 F1-01/02/03 já verificados anteriormente no laboratório. Meta futura: **46 cenários**, sem alegar que tenham sido executados nesta etapa.
- O merge anterior do PR #80 foi confirmado somente na branch de desenvolvimento em `b5931b6f94331b6bcc9ab26020ded9a4c5707eeb`; `main` observada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`, sem novos commits neste planejamento.
- **F1-04 NÃO AUTORIZADO PARA CÓDIGO, TESTES OU MERGE:** não criar `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`, não chamar Codex, instalar PHP/Composer, configurar GitHub Actions, acessar APIs/CRM real, SQL/ALLOW, dados privados, credenciais ou VPS/Cloudflare/n8n, incorrer despesas ou realizar deploy. F1-05 também NÃO autorizado.
- R01-G01 a G11 **TODOS ABERTOS**; CORE/SEG/AUT CRM **ABERTOS**. `main` e todos os demais repositórios/serviços preservados.
- **Próximo ato:** preparar proposta de autorização executiva F1-04 **em ato independente**, para avaliação expressa antes de qualquer Codex ou criação de branch de código.

## Ratificação documental F104-D01 a D10 — 2026-11-08

- O responsável respondeu **“sim”** ao pedido específico de aprovar documentalmente as alternativas A de F104-D01 a D10, sem autorização de programação.
- Escopo ratificado: catálogo/capabilities fictícios, validação fail-closed de parâmetros e versões, escopo/tenants, separação leitura-sugestão-mutações fake, supervisão de aprovações, idempotência e rastro mínimo sintético.
- Os **12 cenários U04-01 a U04-12 continuam propostos, não implementados nem executados**; os 34 cenários históricos F1-01/02/03 são regressão futura. **46/46 é meta, não resultado.**
- **F1-04 NÃO ESTÁ AUTORIZADO PARA CÓDIGO/TESTES.** PR #82 é apenas documental; sem branch de código, merge de código, CI/Actions, instalação, SQL/ALLOW, API real, gastos, VPS/Cloudflare/n8n, dados privados ou deploy.
- `main` e outros projetos preservados, R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM **ABERTOS**. F1-05 bloqueado.
- Próximo passo: apresentar **minuta executiva F1-04 separada** para eventual autorização específica do responsável.

## F1-04 — Ratificação documental e minuta executiva separada — 2026-11-08

- O [PR #82](https://github.com/rafhael100/imagine-ai-agents/pull/82) foi **MERGED somente na branch** `dev/ft01-aia-core-mocks-2026-11-06` (SHA `84f882bb4b9c5ed22514114491e5138cc599bb07`), com F104-D01 a D10 (alternativas A) **ratificadas exclusivamente como diretrizes documentais**. Registros históricos anteriores que descrevem o PR #82 como draft são anteriores ao merge.
- **Nova minuta de autorização executiva AINDA NÃO APROVADA:** `docs/AIA-FT01-F1-04-ORDEM-EXECUTIVA-PROPOSTA.md` na branch **documental** `docs/ft01-f1-04-autorizacao-executiva-proposta-2026-11-08`.
- Propõe para autorização futura e independente a branch **DE CÓDIGO NÃO CRIADA** `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`, catálogo/capabilities e despacho de ferramentas puramente fake, 12 novos testes U04-01..12 e 34 regressões, total candidato de 46 casos **NÃO EXECUTADOS neste lote**.
- A aprovação documental F104 **não autoriza** Codex, branch de código, novos testes, merge de código, nenhuma integração CRM real, credenciais, dados privados, SQL/ALLOW, instalação PHP/Composer, GitHub Actions, despesas, VPS/Cloudflare/n8n ou deploy.
- `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` na verificação. R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM **ABERTOS**, F1-05 não autorizado.
- **Próximo ato:** submeter esta minuta para revisão e, somente depois de decisão executiva expressa do responsável, iniciar código F1-04 em branch filha isolada.

## F1-04 — Autorização executiva expressa, restrita, concedida em 2026-11-08

- O responsável **AUTORIZOU EXPRESSAMENTE** AIA-FT01-F1-04 em resposta **“sim”** à pergunta específica sobre **desenvolvimento PHP mockado + testes unitários sintéticos**, **sem merge, custos, integrações reais ou alterações na infraestrutura**. Trata-se de decisão **separada** da aprovação documental de F104-D01 a D10 (PR #82).
- A ordem vinculante está em `docs/AIA-FT01-F1-04-ORDEM-EXECUTIVA-PROPOSTA.md` (atualizada como **autorização expressa**). A aprovação não significa que Codex ou os 46 testes já tenham sido executados.
- **Após preflight Git**, autoriza apenas criar/verificar `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`, filha de `dev/ft01-aia-core-mocks-2026-11-06`, código PHP puro de catálogo/capabilities e despacho **somente fake**, 12 novos casos U04-01–U04-12 e regressão dos 34 já existentes, sem alterar contratos estabilizados.
- Arquivos permitidos: `src/MockTooling/`, `src/Mocks/`, `src/Domain/` somente novos tipos indispensáveis, `tests/Unit/`, `README.md`, `STATUS-ATUAL.md`. **Proibido** alterar `AGENTS.md`, `src/Contracts/`, `SupervisedCoordinator`, `ReadOnlyTraceReview`, `.github/workflows/`, outros projetos, CRM/Portal ou `main`.
- Se PHP 8.1+ já existir no executor, executar lint e a suíte integral, registrar versão, SHA, saída e falhas; se não existir, registrar **NÃO EXECUTADOS**, sem instalar PHP/Composer ou habilitar GitHub Actions. **46 testes são meta futura, NÃO resultados desta autorização.**
- Criar PR de código obrigatoriamente **DRAFT filha → dev, SEM MERGE**. Qualquer merge de código exige aprovação expressa posterior e somente para a branch dev.
- **Nenhuma autorização** para SQL/ALLOW, dados privados, segredos, APIs/IA/CRM reais, despesas, VPS/Cloudflare/n8n, deploy, F1-05, fechamento dos R01-G01 a G11 ou CORE/SEG/AUT CRM: **TODOS ABERTOS**.
- Este novo registro atualiza o estado da autorização; as referências históricas anteriores a “minuta não aprovada” descrevem o estado anterior à decisão do responsável.

## FT01-F1-04 — execução sintética autorizada e validada (2026-11-08)

- O responsável **AUTORIZOU EXPRESSAMENTE** código PHP e testes F1-04 ao responder **“sim”** à ordem executiva separada apresentada após PR #83. Não autorizou merge, produção, infraestrutura ou F1-05.
- Branch de implementação: `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`, derivada de `dev/ft01-aia-core-mocks-2026-11-06`. Encontrada previamente vazia, foi reaproveitada sem recriar/forçar refs.
- Entregues `src/MockTooling/MockToolCatalog.php`, `MockCapabilityGrant.php`, `MockToolDispatcher.php`, `tests/Unit/tool_dispatch.php` (12 testes U04-01–U04-12) e alteração mínima no `tests/Unit/run.php`. `README.md` e este status documentados. Classes `src/Contracts/`, `src/Domain/`, `src/MockOrchestration/`, `src/MockResilience/` e `AGENTS.md` preservadas.
- **Validação efetiva:** PHP CLI **8.4.24** em sandbox local isolado, `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l`: **26/26 PASS**, exit 0; `php tests/Unit/run.php`: **46/46 PASS, 0 FAIL, exit 0** (10 F1-01, 12 F1-02, 12 F1-03, 12 F1-04).
- **Proveniência:** código dos 26 arquivos PHP reproduzido fielmente da branch GitHub, hashes Git blob SHA-1 **26/26 coincidentes** com a versão testada. Nenhum arquivo PHP pré-existente alterado salvo a linha de inclusão da nova suíte no runner.
- Durante o primeiro teste local, U04-08 teve fixture de grant vencido simultaneamente à aprovação; corrigiu-se a **fixture** com concessão vigente para comprovar bloqueio por aprovação expirada. Reexecutados todos os casos com **46/46 PASS** sem relaxar controles.
- Sem Composer, GitHub Actions, deploy, redes/APIs/LLM reais, SQL/ALLOW, banco, filas, dados pessoais, tokens, custos, VPS/Cloudflare/n8n ou mudanças em outros repositórios. `main` permanece fora do escopo.
- **Próximo passo condicionado:** abrir PR de código **draft**, base exclusivamente na branch dev. Revisão humana e nova autorização expressa são exigidas antes de qualquer merge. R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**; F1-05 não autorizado.

## FT01-F1-05 — planejamento documental de uso, cotas e observabilidade (2026-11-08)

- O responsável respondeu **“sim”** ao pedido para iniciar **exclusivamente a documentação F1-05**, após a integração da F1-04 pelo PR #84. Isso NÃO autoriza branch de código, programação, testes F1-05, merge, provisionamento ou deploy.
- **PROPOSTA AINDA NÃO RATIFICADA:** `docs/AIA-FT01-F1-05-USO-COTAS-OBSERVABILIDADE-MOCK-PROPOSTA.md` na branch `docs/ft01-f1-05-consumo-cotas-observabilidade-mock-2026-11-08`, com base `dev/ft01-aia-core-mocks-2026-11-06`.
- **F105-D01 a D10 TODAS ABERTAS:** medição de unidades técnicas, quota fake por contexto, deduplicação e reconciliação conservadora de eventos, alertas e traces sintéticos mínimos, sem plano/crédito/cobrança comercial.
- **12 cenários U05-01..12 PROPOSTOS, NÃO IMPLEMENTADOS NEM EXECUTADOS**; preservar 46 regressões já verificadas nos lotes F1-01..F1-04. A meta de **58 casos** é futura, não resultado.
- **F1-05 SEM AUTORIZAÇÃO EXECUTIVA:** nenhuma branch de código, alteração PHP, testes, SQL/ALLOW, API/CRM real, credenciais, dados privados, Composer/Actions, custos, VPS/Cloudflare/n8n ou deploy. F1-06 não autorizado.
- `main`, demais projetos e infra inalterados; R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**.
- **Próximo passo:** apresentar F105-D01 a D10, alternativas A, para avaliação e possível ratificação **somente documental**; depois, se aprovado, preparar ordem executiva em ato separado.

## Ratificação documental F105-D01 a D10 — 2026-11-08

- Após apresentar as dez alternativas A no [PR #85](https://github.com/rafhael100/imagine-ai-agents/pull/85), o responsável respondeu **“sim”** à pergunta específica de **ratificação documental das decisões F105-D01 a D10**. A aprovação é DOCUMENTAL, separada de futura autorização de implementação.
- **F105-D01–D10: alternativas A APROVADAS SOMENTE DOCUMENTALMENTE**; as B não foram adotadas. Recorte: medição técnica e políticas de cotas sintéticas, deduplicação de eventos de uso, reconciliação conservadora, alertas referenciais mínimos, segregação por produto/tenant/finalidade e distinção absoluta entre uso técnico AIA e créditos/cobrança CRM.
- **12 testes U05-01..U05-12 continuam apenas propostos, não implementados e NÃO EXECUTADOS.** Os 46 anteriores F1-01..F1-04 permanecem como regressão futura; **58 é META, não resultado**.
- O [PR #85](https://github.com/rafhael100/imagine-ai-agents/pull/85) permanece **DOCUMENTAL, DRAFT e SEM MERGE**, até autorização específica. A minuta executiva a preparar é **proposta separada não aprovada**.
- **NÃO AUTORIZADOS:** programação F1-05, Codex, branch de código, novos testes, alterações `main`/CRM/Portal/outros projetos, APIs reais, SQL/ALLOW, dados privados, credenciais, GitHub Actions, despesas, VPS/Cloudflare/n8n, deploy, F1-06 ou fechamento de gates.
- **R01-G01 a G11 e CORE/SEG/AUT CRM TODOS ABERTOS.** Próxima decisão independente: revisar e autorizar, ou não, a **ordem executiva F1-05**; merge de código exigirá autorização ainda posterior.

## F1-05 — PR #85 integrado, minuta executiva limpa em revisão (2026-11-08)

- O responsável aprovou a incorporação **documental** do PR #85 e a preparação da minuta executiva F1-05. O PR #85 já foi **MERGED exclusivamente na branch dev**, squash `93ca068dbbb6e4962a532a5f98994b55fd097621`, com F105-D01 a D10 alternativas A ratificadas apenas documentalmente. Anotações anteriores de #85 como draft/sem merge são históricas.
- Minuta de autorização executiva **NÃO APROVADA**: `docs/AIA-FT01-F1-05-ORDEM-EXECUTIVA-PROPOSTA.md`, no ramo documental limpo `docs/ft01-f1-05-ordem-executiva-revisao-2026-11-08` criado diretamente da branch dev após #85. Esta minuta não pode ser interpretada como ordem ao Codex.
- A minuta anterior #86 provinha de branch documental pré-squash e exibia o mesmo arquivo F1-05 de #85 como alteração adicional por ancestralidade. A presente revisão preserva o conteúdo da ordem sem duplicar o arquivo já integrado. **Não fazer merge sem decisão específica.**
- F1-05: **12 testes novos U05-01..U05-12 NÃO EXECUTADOS e 46 anteriores como regressão**, meta futura 58, sem código ou branch de implementação autorizados.
- **Preservar main**, R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM **ABERTOS**, demais projetos e VPS/Cloudflare/n8n intactos. Sem SQL/ALLOW, APIs/CRM reais, credenciais, dados privados, Github Actions, instalação PHP/Composer, custos, deploy ou F1-06.
- Próximo ato: revisão da ordem executiva e **autorização expressa independente para F1-05 código/testes**, separada de qualquer autorização para merge de código futura.

## F1-05 — Autorização executiva expressa e restrita (2026-11-08)

- O responsável respondeu **“sim”** à pergunta explícita sobre autorizar o AIA-FT01-F1-05 **somente para desenvolvimento PHP mockado e testes unitários sintéticos, sem merge, despesas, integrações reais ou mudanças na infraestrutura**. Trata-se de decisão EXECUTIVA distinta da aprovação documental F105-D01 a D10 do PR #85.
- A ordem formal de execução permitida está em `docs/AIA-FT01-F1-05-ORDEM-EXECUTIVA-PROPOSTA.md` (título atualizado para autorização ratificada). O PR da autorização permanece **estritamente documental**; sua integração NÃO é merge de código F1-05.
- Autoriza após preflight autenticado criar/verificar `dev/ft01-f1-05-usage-quotas-mocks-2026-11-08`, filha da `dev/ft01-aia-core-mocks-2026-11-06`. **Não substituir refs ou criar branch alternativa sem conciliação.**
- Escopo de implementação exclusivamente sintético: política de cotas técnicas por contexto, eventos Usage deduplicados, evidências estimadas/observadas/conciliadas sem custos comerciais reais, alertas sem envio, fail-closed, rastro mínimo; arquivos `src/MockUsage/`, `tests/Unit/`, `README.md`, `STATUS-ATUAL.md`.
- 12 casos U05-01–U05-12 autorizados e regressão de 46 casos existentes; **58 é meta futura, NÃO resultado executado**. Testar só se PHP 8.1+ já existir; na ausência, registrar **NÃO EXECUTADOS** sem instalar PHP/Composer ou usar GitHub Actions.
- Entrega de código F1-05 deve ocorrer em **PR DRAFT da branch filha → branch dev, SEM MERGE**. Merge de código exige revisão e **autorização expressa independente**, não abrangida pelo “sim” atual.
- `main` continua preservada, R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM **ABERTOS**. **Não** SQL/ALLOW, banco real, CRM/Portal, LLM/APIs reais, dados privados, credenciais, compras/gastos, infraestrutura VPS/Cloudflare/n8n, deploy ou F1-06. Registros anteriores de “minuta não aprovada” são anteriores a esta autorização.

## FT01-F1-05 — execução sintética AUTORIZADA, IMPLEMENTADA e VALIDADA (2026-11-08)

- **Autorização:** após aprovação documental F105-D01 a D10 (alternativas A) e merge exclusivamente documental PR #85 (`93ca068dbbb6e4962a532a5f98994b55fd097621`), o responsável respondeu **“sim”** à pergunta expressa sobre **programação e testes sintéticos F1-05 em branch isolada, sem merge ou deploy**. A autorização atual supera apenas os bloqueios históricos referentes ao **código local F1-05**, não os demais gates.
- **Preflight:** PR #85 já integrado na dev; PR documental #87 permanece fora do escopo do código e não foi alterado. `main` observada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; base `dev/ft01-aia-core-mocks-2026-11-06` inicialmente em `93ca068dbbb6e4962a532a5f98994b55fd097621`. A branch de código **não existia** e foi criada a partir desse SHA, sem force-push.
- **Implementação:** `src/MockUsage/MockUsagePolicy.php`, `MockUsageLedger.php`, `MockUsageProof.php`, `tests/Unit/usage_quota.php` (12 testes U05-01–U05-12) e inclusão mínima em `tests/Unit/run.php`. `README.md` e este status atualizados. `src/Contracts/`, `src/Domain/`, `src/MockOrchestration/`, `src/MockResilience/`, `src/MockTooling/`, `src/Mocks/`, `AGENTS.md`, documentos ratificados e outros repositórios **NÃO alterados**.
- **Sem integração real:** concessão fake e medição técnica não são identidade, reserva, custo real, crédito comercial ou autorização no CRM. Política vinculada ao contexto completo e à operação original; prova contraditória bloqueia reconciliação posterior; amostras duplicadas com material divergente são recusadas; timeout mantém incerteza e não zera consumo observado; logs e alertas guardam somente referências hash e códigos fechados.
- **Prova executada:** PHP CLI **8.4.24** em sandbox local sem dependências externas; `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **30/30 sem erro, exit 0**; `php tests/Unit/run.php` → **58/58 PASS, zero falhas, exit 0**: 10 F1-01, 12 F1-02, 12 F1-03, 12 F1-04 e 12 F1-05.
- **Integridade:** hashes **Git blob SHA-1 dos 30/30 arquivos PHP publicados coincidiram exatamente com os 30 arquivos executados localmente**. Não houve alteração de código depois desses testes; documentação posterior não afeta a versão testada.
- **LIMITAÇÕES:** processo único, estado volátil, sem medição real de fornecedor, sem atomicidade entre workers, sem persistência, sem moeda/fatura/saldo, sem garantias de operação/segurança/privacidade em produção. Resultados sintéticos não fecham gates.
- **Proibições mantidas:** PR de código será **DRAFT filha → dev SEM MERGE**; novos merges dependem de aprovação expressa específica. `main`, CRM, Portal, Sites, Performance AI, Universidade, VPS/Cloudflare/n8n e demais infra inalterados; sem SQL/ALLOW, credenciais, dados privados, API/LLM real, instalação PHP/Composer, GitHub Actions, gastos ou deploy. R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. F1-06 **NÃO AUTORIZADO**.

## F1-06 — proposta DOCUMENTAL de catálogo de versões e avaliações sintéticas (2026-11-08)

- O responsável respondeu **“sim”** ao pedido de **iniciar somente a documentação F1-06**, após integração F1-05 pelo PR #88 (squash `8b128c2882c7baf7e59264b16361c9bc9f8108d7`). **Não autorizou** código, testes F1-06, criação de branch de programação, merge, integração real, infraestrutura ou deploy.
- **PROPOSTA DOCUMENTAL AINDA NÃO RATIFICADA:** `docs/AIA-FT01-F1-06-CATALOGO-VERSOES-AVALIACAO-MOCK-PROPOSTA.md` na branch `docs/ft01-f1-06-catalogo-versoes-avaliacao-mock-2026-11-08`, a partir de `dev/ft01-aia-core-mocks-2026-11-06`.
- **F106-D01 a D10 TODAS ABERTAS:** snapshots imutáveis de templates fictícios, versionamento de instruções/capabilities, avaliação vinculada a manifesto, gates locais fail-closed, suspensão e rollback mockado, sem grants reais ou publicação operacional.
- **12 testes U06-01..U06-12 apenas PROPOSTOS/NÃO IMPLEMENTADOS/NÃO EXECUTADOS**; preservar os 58 testes históricos F1-01..F1-05. A meta futura **70/70 não é resultado obtido**.
- **Branch candidata de código NÃO CRIADA:** `dev/ft01-f1-06-agent-release-mocks-2026-11-08`. PR futuro de código, caso expressamente autorizado, será draft filha → dev; merge dependerá de autorização específica posterior.
- `main`, CRM, Portal, demais produtos, VPS/Cloudflare/n8n, bancos, SQL/ALLOW, dados reais, credenciais, GitHub Actions, PHP/Composer instalados, custos e deploy preservados. **R01-G01–G11 e CORE/SEG/AUT CRM TODOS ABERTOS**. F1-07 não autorizado.
- **Próximo passo:** submeter F106-D01 a D10, alternativas A, para possível **ratificação SOMENTE documental**, sem confundir aprovação do plano com ordem executiva.

## Ratificação DOCUMENTAL F106-D01 a D10 — 2026-11-08

- O responsável respondeu **“sim”** à pergunta específica de **aprovar documentalmente as dez alternativas A F106-D01 a D10**, após apresentação no PR #89. **Alternativas A aprovadas exclusivamente documentalmente**; alternativas B não adotadas.
- **Escopo documental ratificado:** catálogo e versões de agentes exclusivamente sintéticos, snapshots imutáveis, revisões/evidências vinculadas à versão exata, controles fail-closed, isolamento por empresa/produto/finalidade, suspensão e rollback simulados, rastros mínimos e revisões explícitas. A versão de agente não outorga grant, autorização de ferramenta nem cota.
- **12 testes U06-01..U06-12 NÃO IMPLEMENTADOS/NÃO EXECUTADOS**; os 58 testes F1-01 a F1-05 são regressão candidata futura. **70/70 é meta futura, NÃO resultado obtido.**
- **PR #89 permanece exclusivamente DOCUMENTAL, DRAFT e SEM MERGE.** Nenhuma autorização executiva para criar branch de código, acionar Codex, programar F1-06, executar testes F1-06, fazer merge ou deploy.
- `main`, CRM, Portal, outros repositórios, VPS/Cloudflare/n8n, custos, dados privados, SQL/ALLOW, API/LLM reais, CI/Actions e infraestrutura preservados. **R01-G01–G11 e CORE/SEG/AUT CRM TODOS ABERTOS**; F1-07 não autorizado.
- **Próximo ato:** preparar **minuta executiva F1-06 separada** para nova avaliação. A ratificação documental não antecipa autorização de código/testes ou merge.

## FT01-F1-06 — execução sintética expressamente autorizada e validada (2026-11-08)

- **Autorização do responsável:** respondeu **"sim"** à pergunta explícita de executar código PHP e testes F1-06 **somente em branch filha isolada**, após o merge exclusivamente documental F1-06 via PR #89 (commit `2453b3cd3a0283136c35b64dd03f559a0d4ca053`). **Não autorizou merge deste código**, `main`, integração real, implantação, novas despesas ou F1-07.
- **Preflight GitHub:** `main` intacta em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; base dev autenticada em `2453b3cd3a0283136c35b64dd03f559a0d4ca053`; branch de código não existia, criada sem force-push `dev/ft01-f1-06-agent-release-mocks-2026-11-08`. PR documental #90 permaneceu OPEN/DRAFT e intocado.
- **Código:** novas classes `src/MockCatalog/MockAgentVersionSnapshot.php`, `MockAgentTemplateCatalog.php`, `MockVersionReview.php`, `MockPromotionGate.php` e suíte `tests/Unit/catalog_release.php` com U06-01 a U06-12. Única mudança de PHP pré-existente: inclusão mínima da suíte em `tests/Unit/run.php`. `README.md` e `STATUS-ATUAL.md` atualizados; nenhum contrato, classe anterior, teste histórico ou `AGENTS.md` alterados.
- **Validação REAL:** PHP CLI **8.4.24** disponível previamente no sandbox local, sem instalação, Composer, Actions ou redes; lint PHP **35/35 PASS, exit 0**, `php tests/Unit/run.php` **70/70 PASS, 0 falhas, exit 0**: 10 F1-01, 12 F1-02, 12 F1-03, 12 F1-04, 12 F1-05, 12 F1-06.
- **Proveniência e integridade:** hashes Git blob SHA-1 dos **35/35 arquivos PHP** da branch foram confrontados com os arquivos PHP locais executados, sem divergência. O caso U06-04 inclui entradas malformadas (arrays aninhados) para provar negação sem warning; corrigiu-se a ordem da checagem de unicidade de ferramentas somente na nova classe F1-06.
- **Sem autoridade real:** `SYNTHETIC_*` significa fixture, não sanitização, grant, ACL, assinatura, publicação ou homologação; promoção/rollback são apenas estados em memória e não alteram catálogo/CRM/provedor reais. Traces limitados a hash e códigos, não expõem instruções, tokens, leads ou prompts.
- **Limitações:** estado efêmero, processo único, sem concorrência/persistência, identidade ou credenciais reais, JSON/API homologados, provedores/modelos, teste semântico de qualidade, custos, compliance de produção, reserva de quotas, publicação ou rollback operacional.
- **Bloqueios:** abrir PR de código obrigatoriamente **DRAFT filha → dev, SEM MERGE**; revisão e aprovação expressa adicional exigida para qualquer merge de código. `main`, CRM/Portal/Sites/Performance/Universidade, VPS/Cloudflare/n8n, GitHub Actions, dados reais, SQL/ALLOW, licenças, custos e deploy preservados. **R01-G01 a G11 e CORE/SEG/AUT CRM TODOS ABERTOS**. F1-07 NÃO autorizado.

## FT01-F1-07 — proposta DOCUMENTAL de conhecimento e contexto mockados (2026-11-08)

- **Autorização expressa recebida:** o responsável respondeu **“autorizado”** à proposta de **iniciar apenas a documentação F1-07**, após integração do PR #91 (squash `02ed376c7be5258d091b26667ca00f63a0db46ab`) na branch dev.
- **Proposta NÃO RATIFICADA** em `docs/AIA-FT01-F1-07-CONHECIMENTO-CONTEXTO-MOCK-PROPOSTA.md`, branch documental isolada `docs/ft01-f1-07-retrieval-context-mocks-2026-11-08`, base `dev/ft01-aia-core-mocks-2026-11-06`. **F107-D01 a D10 ABERTAS.**
- **Recorte futuro recomendado:** catálogo fechado de fontes fictícias, recuperação determinística read-only, escopo por empresa/produto/principal/finalidade/recurso, validade e revogação, composição mínima de contexto e rastreabilidade. Texto recuperado não confere autoridade. **Sem memória persistente, LLM/RAG real, embeddings, OCR, API, CRM ou dados pessoais**.
- **U07-01 a U07-12 SOMENTE PROPOSTOS, NÃO IMPLEMENTADOS/NÃO EXECUTADOS**. Os 70 testes sintéticos anteriores F1-01..F1-06 são regressão planejada; **82 é meta futura, não resultado comprovado**.
- **Branch candidata de código NÃO CRIADA/NÃO AUTORIZADA:** `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08`. Qualquer programação/teste dependerá de ordem executiva específica em ato separado, e qualquer merge exigirá autorização adicional; PR de código futuro deve ser DRAFT filha → dev, SEM MERGE.
- `main` observada intacta em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; PR documental histórico #90, todos os outros repositórios, infraestrutura e VPS preservados. **R01-G01 a G11 e CORE/SEG/AUT CRM TODOS ABERTOS**. Sem SQL/ALLOW, credenciais, GitHub Actions, pagamentos, modelo/fornecedor externo ou deploy. F1-08 não autorizado.
- **Próximo passo:** apresentar as alternativas A de F107-D01 a D10 para avaliação e eventual ratificação **somente DOCUMENTAL**, sem presumir ordem executiva.

## Ratificação exclusivamente DOCUMENTAL F107-D01 a D10 — 2026-11-08

- Após a apresentação das dez alternativas A no [PR #93](https://github.com/rafhael100/imagine-ai-agents/pull/93), o responsável respondeu **“sim aprovado”** à pergunta específica de aprovar **DOCUMENTALMENTE F107-D01 a D10**. Todas as alternativas A foram ratificadas **somente no nível documental**; alternativas B não adotadas.
- **Escopo ratificado:** recuperação somente sintética e read-only; escopo por empresa/produto/principal/finalidade/recurso; origem, versão e validade da fonte; revogação/rechecagem antes da entrega; conteúdo tratado como dado não confiável, isolamento, dados transacionais pertencentes ao CRM, contexto mínimo, sem memória persistente; PHP puro e aprovação em atos separados.
- Os **12 casos U07-01..U07-12 permanecem NÃO IMPLEMENTADOS/NÃO EXECUTADOS**; os 70 testes anteriores F1-01..F1-06 são regressão futura. **82/82 é somente META, não resultado validado.**
- O **PR #93 permanece DOCUMENTAL, DRAFT e SEM MERGE**. Minuta executiva F1-07 a preparar em branch/PR documental separados, **sem autorização para código, testes, branch de implementação, merge ou deploy**. A aprovação documental não equivale a ordem executiva.
- PR #92 de correção F1-06 e PR #90 documental F1-06 permanecem separados; nenhuma interferência nesses PRs, `main`, demais projetos ou infraestrutura.
- R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. Sem dados reais, OAuth/ACL real, SQL/ALLOW, API/LLM real, GitHub Actions, fornecedores pagos, VPS/Cloudflare/n8n ou publicação. **F1-08 não autorizado**.
- **Próximo ato:** avaliar a minuta executiva e/ou autorizar, em decisão separada, o **merge documental** do PR #93. Nenhum desses atos deve ser presumido.

## FT01/F1-07 — implementação sintética controlada sob autorização ampla posterior (2026-11-08)

- **Autorização vigente mais recente:** o responsável autorizou amplamente o avanço imediato do Imagine AI Agents, solicitando perguntas apenas em decisões estritamente críticas. Essa ordem autoriza desenvolvimento e testes sintéticos F1-07 em branch isolada e supera o bloqueio executivo histórico da minuta #94; não equivale a aprovação de produção, despesas, chaves reais, integração com CRM ou fechamento de gates.
- **Preflight:** `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; dev autenticada em `40a71cb2797d8c954f392e94ef4b02b4cd04e55d` (PR #93 documental já MERGED). Branch filha `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08` criada dessa dev; PR #92 de correções F1-06 permanece OPEN/DRAFT e separado, sem merge ou alteração.
- **Escopo implementado:** seis classes PHP puras em `src/MockKnowledge/`, 12 casos U07-01..12 em `tests/Unit/knowledge_context.php`, inclusão mínima da suíte em `tests/Unit/run.php`, atualizações documentais `README.md` e `STATUS-ATUAL.md`. Não alterar arquivos antigos de F1-01..F1-06, contratos, `AGENTS.md`, outros produtos ou infraestrutura.
- **Evidência efetiva:** PHP CLI 8.4.24 pré-existente; lint de **6/6 classes novas PASS**; **6/6 Git blob SHA-1 coincidentes** com as classes executadas localmente. Runner auxiliar sintético das 12 categorias U07 + um caso extra de IDs repetidos entre tenants, **13/13 PASS, zero falhas**. Os 12 casos oficiais constam na branch, mas **NÃO foram executados como `php tests/Unit/run.php`**; 82 casos é **META**, não resultado. Quando integradas as 3 correções U06-C01..C03 do PR #92, nova meta conjunta passa a 85.
- **Bloqueios de integração:** executar lint de todos os PHP e regressão oficial no checkout exato, revisar diffs/SHAs e registrar eventuais correções. PR F1-07 deverá permanecer DRAFT sem merge automático até aceite técnico; nenhuma ativação real.
- **Gates preservados:** R01-G01–G11 e CORE/SEG/AUT CRM todos ABERTOS; sem deploy, SQL/ALLOW, APIs/LLM reais, persistência, dados privados, credenciais, fornecedores pagos ou modificações em `main`. F1-08 exige escopo próprio e não foi iniciado nesta rodada.

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
