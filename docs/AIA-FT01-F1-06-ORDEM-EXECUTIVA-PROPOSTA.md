# AIA-FT01/F1-06 — Minuta de ordem executiva restrita | Proposta v0.1

> **SITUAÇÃO: MINUTA EM AVALIAÇÃO. EXECUÇÃO F1-06 NÃO AUTORIZADA.**
>
> **Data:** 2026-11-08. Repo `rafhael100/imagine-ai-agents`. Branch somente documental `docs/ft01-f1-06-ordem-executiva-proposta-2026-11-08`, criada separadamente da branch de planejamento; base `dev/ft01-aia-core-mocks-2026-11-06`.
>
> **Precedência:** o responsável ratificou as alternativas A de F106-D01 a D10 **exclusivamente no nível documental** no PR #89. **O PR #89 foi integrado exclusivamente à branch dev**, mediante merge documental `2453b3cd3a0283136c35b64dd03f559a0d4ca053` (verificado em 2026-11-08). Esta minuta não modifica o PR #89 e não presume programação, aprovação executiva F1-06 nem merge de código.
>
> **Bloqueios intactos:** `main`, outros repositórios, CRM, Portal, Sites, Performance AI, Universidade, VPS/Cloudflare/n8n e infraestrutura. R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. Sem SQL/ALLOW, API/LLM real, banco, filas, autenticação de produção, dados pessoais, credenciais, GitHub Actions, instalação PHP/Composer, compras, custos, deploy ou F1-07.

## 1. Objetivo proposto para futura execução sintética

**Somente após autorização executiva separada e expressa**, elaborar laboratório em PHP puro e memória volátil para:
- catálogo fechado de templates e snapshots imutáveis `SYNTHETIC_*`, versionados por manifesto material e vinculação a produto/contexto;
- avaliação determinística e evidências locais que vinculam `snapshot_ref`, manifesto, versão de dataset e regra, decisor e validade, sem tratar um PASS sintético como homologação;
- promoção ou suspensão **exclusivamente fictícias**, com revisão explícita, guardas fail-closed e ausência de efeitos externos;
- rollback simulado apenas para snapshot previamente elegível e ainda permitido, **sem** recriar grants, aprovações, cotas ou acesso revogado;
- trace mínimo de códigos fechados e hashes de marcadores fictícios, sem armazenamento de prompt bruto, instruções de cliente ou tokens.

O laboratório **não** publica agentes reais, não gera UI, não estabelece permissões CRM, não assina snapshots, não faz deploy e não integra provedor de IA.

## 2. Branch de código futura — NÃO criada e NÃO autorizada

| Critério | Proposta para decisão posterior |
| --- | --- |
| Repositório | `rafhael100/imagine-ai-agents` |
| Branch base futura | `dev/ft01-aia-core-mocks-2026-11-06`, **já com o merge documental do PR #89**; validar novamente SHA atual antes de qualquer execução futura |
| Nova branch de código CANDIDATA | `dev/ft01-f1-06-agent-release-mocks-2026-11-08` — **NÃO CRIADA** |
| Origem | SHA atual autenticado da branch dev após verificação de histórico, `AGENTS.md` e árvore limpa |
| PR de código futuro | **DRAFT**, filha → branch dev, **SEM MERGE** |
| Merge de código | Dependerá de autorização distinta, após revisão de commits e testes |
| `main` e demais projetos | Não tocar |

Se branch homônima já existir, interromper para inspeção; não usar force-push, sobrescrita nem atualização de referências sem análise e decisão. A autorização desta minuta, se eventualmente ocorrer como aceitação **documental**, não substitui uma ordem executiva explícita.

## 3. Preflight obrigatório a eventual executor

1. Confirmar autenticação GitHub, remoto, `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, estado da branch base, SHA de `main`, que o PR #89 segue integrado apenas à dev, e eventuais divergências. NÃO repetir o merge do PR #89.
2. Ler AIA-01, AIA-02, AIA-08, AIA-09, AIA-R03/R04, planejamento F1-06 e os componentes PHP de F1-01..F1-05.
3. Inspecionar `src/Domain/`, `src/MockTooling/`, `src/MockUsage/`, `src/MockOrchestration/`, `src/MockResilience/`, `tests/Unit/run.php`; preservar contratos, esquema de autorização e suites anteriores.
4. Conferir que PHP CLI 8.1+ **já** esteja disponível. Se não houver, declarar `NÃO EXECUTADO` para lint e testes, sem instalar PHP, Composer, dependências, GitHub Actions ou usar fornecedores pagos.
5. Registrar SHA da base, lista de arquivos, riscos, comandos e proibições antes de qualquer mudança.

## 4. Superfícies permitíveis sob autorização EXECUTIVA FUTURA

| Caminho | Alteração hipoteticamente autorizável | Proibido |
| --- | --- | --- |
| `src/MockCatalog/` | Classes puras para templates/snapshots/evidência de avaliação/gates de versão/trace sintéticos | LLM, acesso a rede, API, storage real, privilégio/autorização CRM, publicação |
| `tests/Unit/catalog_release.php` | 12 casos novos U06-01 a U06-12 com entradas fictícias | Suprimir regressão, testes com dados de usuário, API/CRM real |
| `tests/Unit/run.php` | Inclusão mínima da nova suíte após os 58 testes existentes | Reescrever ou desabilitar os anteriores |
| `README.md`, `STATUS-ATUAL.md` | Descrever código, evidências verificadas, SHA, limites e gates | Declarar homologação, produção ou PASS sem execução |

**Não alterar** `AGENTS.md`, `src/Contracts/`, classes consolidadas `src/Domain/`, `SupervisedCoordinator`, `ReadOnlyTraceReview`, `MockToolDispatcher`, `MockUsageLedger`, documentos ratificados, `.github/workflows/`, `main` ou outros repositórios. Se um requisito depender dessas alterações, **parar**, reportar e pedir decisão específica.

Não criar endpoints HTTP, `.env`, Dockerfile, migrations, banco, filas, mecanismos reais de autenticação, importação de conhecimento de clientes, planos/comercial ou integração de modelo.

## 5. Invariantes obrigatórias do recorte

- **Snapshot é dado e não autoridade:** capacidade listada ou template ativo não concede grant, cota, aprovação de efeito ou identidade.
- **Imutabilidade real em memória:** versão e digest correspondem ao manifesto congelado; mutação/versão conflitante invalida evidências anteriores em vez de editar o histórico.
- **Revisão explícita:** nenhuma promoção por silêncio, resposta de IA, flag textual ou `PASS` anterior de outra versão.
- **Prova vinculada:** manifesto + versão + dataset/regra + finalidade/risco/contexto + revisor sintético + validade devem coincidir. Desconhecido ou expirado → negar.
- **Isolamento:** empresa, produto, finalidade, principal e recurso não podem ser misturados em release, evidência ou rollback fictícios.
- **Idempotência:** mesma referência e material/versão não duplica evento; referência repetida com material distinto gera conflito.
- **Rollback conservador:** elegibilidade histórica não retoma permissão/capability que tenha sido revogada depois; versão incompatível não volta automaticamente.
- **Trace seguro:** apenas códigos e referências sintéticas mínimas, sem conteúdo de instrução, lead, segredo ou exceção crua.
- **Sem falso aceite:** avaliação determinística não avalia qualidade semântica de LLM, segurança real, autorização backend, durabilidade, concorrência, deploy ou conformidade LGPD operacional.

## 6. Matriz de testes futura — 12 novos + 58 regressões = 70

| Caso | Resultado exigido no laboratório sintético |
| --- | --- |
| U06-01 | Template/snapshot conhecidos retornam manifesto imutável sem privilégios |
| U06-02 | Alteração material em instrução/capability invalida evidência |
| U06-03 | Snapshot desconhecido/inativo falha fechado, sem fallback automático |
| U06-04 | Campo extra, schema inválido ou dados excessivos são negados |
| U06-05 | Evidência ausente, expirada, revogada ou reprovação bloqueia promoção |
| U06-06 | Produto/tenant/finalidade/principal incorretos não vazam dados |
| U06-07 | Manifesto e avaliação válidos permitem só estado `ATIVO_SIMULADO` |
| U06-08 | Mudança de ferramenta, política ou risco exige reavaliação |
| U06-09 | Repetição material da mesma promoção é idempotente |
| U06-10 | Colisão de referência com manifesto diferente gera conflito |
| U06-11 | Suspensão e rollback respeitam elegibilidade e revogações atuais |
| U06-12 | Injeção adversarial e token sintético não aparecem no trace nem elevam privilégios |

**Estado atual:** U06-01 a U06-12 NÃO IMPLEMENTADOS E NÃO EXECUTADOS. O histórico de **58 testes anteriores** é evidência do laboratório até F1-05, não substitui regressão de um novo commit. `70/70` é **meta futura**, não resultado.

Com autorização executiva distinta e PHP 8.1+ existente no ambiente:
```sh
php -v
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```
Reportar saída e exit codes efetivos, SHA exato e hashes dos PHP validados. Na ausência de PHP, registrar claramente **NÃO EXECUTADOS**, sem instalação ou uso de Actions.

## 7. Condições de parada e reporte

Parar diante de:
- divergência de branch/PR/base SHA, necessidade de alterar contratos existentes ou classes estabilizadas;
- troca de autoridade: tentativa de tornar publicação ou texto de agente uma concessão;
- integração CRM, acesso a banco, segredos, dados reais, provedor de IA, despesas ou infraestrutura;
- necessidade de CI/Actions, dependência extra, arquitetura distribuída, interface operacional ou deploy;
- mudanças em `main`, fechamento de gates ou ampliação para F1-07.

O executor deverá apresentar arquivos alterados, testes e limitações; nunca afirmar homologação de produção baseada em fixtures.

## 8. Texto sugerido de autorização EXECUTIVA futura — NÃO CONCEDIDA

> **Autorizo expressamente executar apenas o laboratório sintético AIA-FT01/F1-06** no repositório `rafhael100/imagine-ai-agents`, em branch filha `dev/ft01-f1-06-agent-release-mocks-2026-11-08` criada de SHA verificada da branch `dev/ft01-aia-core-mocks-2026-11-06` após verificar que o merge documental já realizado do PR #89 permanece na branch dev e que a base não apresenta divergências. Autorizo PHP puro e memória volátil em `src/MockCatalog/`, `tests/Unit/catalog_release.php`, inclusão mínima no runner, `README.md` e `STATUS-ATUAL.md`, somente para catálogo/versionamento de templates e snapshots fictícios, avaliação local e promoção/suspensão/rollback exclusivamente simulados. Autorizo até 12 novos casos U06-01–U06-12 e regressão dos 58 anteriores, se PHP CLI 8.1+ já estiver disponível; sem instalações ou Actions. Abrir PR de código DRAFT filha → dev, **SEM MERGE**. Não autorizo `main`, outros projetos, contratos/classes estáveis, API/CRM/LLM reais, bancos/SQL/ALLOW, dados privados, credenciais, VPS/Cloudflare/n8n, despesas, deploy, F1-07 ou fechamento dos gates R01/CORE/SEG/AUT. Se houver bloqueio, parar e apresentar a divergência.

Este texto é **proposta para futura decisão independente**, não ordem concedida por ratificação documental.

## 9. Estado e próximas decisões

> **Atualização de preflight documental (2026-11-08):** o PR #89 já consta como MERGED, exclusivamente na dev (`2453b3cd3a0283136c35b64dd03f559a0d4ca053`). O PR #90 continua DRAFT, sem merge. A branch documental do PR #90 foi criada antes da integração do #89; não realizar rebase, force-push ou alteração da base sem revisão adicional. A minuta atualiza apenas fatos documentais: **nenhuma execução ou autorização de código F1-06 foi concedida**.

- [x] F106-D01 a D10, alternativas A, ratificadas **documentalmente** pelo responsável em 2026-11-08, registradas no PR #89.
- [x] **Merge exclusivamente documental do PR #89** realizado na dev em 2026-11-08, commit `2453b3cd3a0283136c35b64dd03f559a0d4ca053`; isso não concede autorização de código F1-06.
- [ ] Revisão e autorização **executiva explícita** da F1-06 em novo ato.
- [ ] Somente depois: branch de código, desenvolvimento, validação sintética, PR draft sem merge.
- [ ] Merge de código e qualquer deploy sempre exigem aprovações **adicionais e específicas**.
- [ ] R01-G01–G11 e CORE/SEG/AUT CRM todos abertos, `main` intocada, F1-07 não autorizado.

**Conclusão:** F1-06 aprovada documentalmente; **nenhuma programação F1-06, novo teste, merge ou implantação autorizada neste ato**.
