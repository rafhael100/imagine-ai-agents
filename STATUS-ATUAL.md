# STATUS-ATUAL — Imagine AI Agents

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
