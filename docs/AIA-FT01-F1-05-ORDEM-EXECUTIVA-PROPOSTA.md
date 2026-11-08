# AIA-FT01/F1-05 — Autorização executiva restrita ratificada | v0.1

> **ESTADO: AUTORIZAÇÃO EXECUTIVA EXPRESSA F1-05 CONCEDIDA PELO RESPONSÁVEL EM 2026-11-08.** O responsável respondeu **“sim”** à pergunta objetiva de autorização do AIA-FT01-F1-05 para PHP puro mockado e testes unitários sintéticos, **sem merge de código, custos, integrações reais ou alterações na infraestrutura**. A aprovação é restrita ao escopo desta ordem e condicionada ao preflight. Não equivale a código/testes já executados.
>
> Data: 2026-11-08. Repo: `rafhael100/imagine-ai-agents`. Branch **somente documental**: `docs/ft01-f1-05-ordem-executiva-revisao-2026-11-08`. Destino deste PR documental: `dev/ft01-aia-core-mocks-2026-11-06` (**somente a documentação**); a `main` continua preservada.
>
> **Precedência:** o responsável ratificou as alternativas A F105-D01 a D10 **documentalmente** no PR #85, integrado somente na dev (commit `93ca068dbbb6e4962a532a5f98994b55fd097621`). **Posteriormente concedeu autorização executiva separada em 2026-11-08** para código e testes F1-05 exclusivamente mockados, após preflight. Esta autorização **NÃO** libera merge de código, produção, integração CRM/LLM real, despesas, F1-06 ou fechamento dos gates.
>
> **Bloqueios:** `main`, CRM, Portal, Performance AI, Universidade, Sites e demais projetos preservados; R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**; F1-06 também bloqueado. Sem SQL, ALLOW, dados reais, APIs de fornecedores, LLM, autenticação/ACL real, credenciais, custos, Docker, Composer, GitHub Actions, VPS/Cloudflare/n8n ou deploy.

## 1. Objetivo restrito da execução autorizada

Sob a **autorização executiva expressa concedida em 2026-11-08**, implementar em **PHP puro e memória volátil** um laboratório de:
1. Política de unidades técnicas e cota fictícia por contexto completo (produto, tenant, principal, finalidade, recurso, operação e versão) e tempo lógico explícito.
2. Registro técnico local de `Usage` e eventos de tentativa, deduplicação estrita por identidade material, sem recontar em retry.
3. Diferenciação entre `ESTIMADO`, `OBSERVADO`, `AJUSTADO`, `CONCILIADO` como estados de **evidências sintéticas**, nunca créditos comerciais.
4. Alertas mínimos de limite, com códigos fechados e referências fictícias, **sem envio de notificações**.
5. Comportamento conservador diante de timeout, eventos tardios, falta de prova e inconsistência — sem declarar custo real nem afirmar quota transacional.

**Fonte de autoridade:** o CRM continua proprietário de elegibilidade, créditos, planos, cobrança e saldos. O AIA não escreve no CRM e não recebe autorização de acesso a partir de quota; os grants/aprovações de F1-04 e a confirmação de efeito do F1-02 continuam separados.

Nenhum número de quota da fixture corresponde a plano ou preço Imagine. Nenhuma moeda ou tarifa real será embutida.

## 2. Branch de implementação autorizada — criação apenas após preflight

| Item | Regra |
| --- | --- |
| Repositório | `rafhael100/imagine-ai-agents` |
| Branch de referência para código | `dev/ft01-aia-core-mocks-2026-11-06`, **PR #85 já integrado documentalmente no commit `93ca068dbbb6e4962a532a5f98994b55fd097621`** |
| Branch candidata não criada | `dev/ft01-f1-05-usage-quotas-mocks-2026-11-08` |
| Ponto inicial | SHA atual da branch base aferido via GitHub no momento da futura autorização |
| Destino eventual do PR de código | **Draft filha → dev; sem merge** |
| Main, demais projetos e infra | Intocados |
| Autorização disponível hoje | Diretrizes documentais somente; execução bloqueada |

Se houver branch homônima ou commits não previstos, inspecionar sem sobrescrever, force-push ou reutilização automática; interromper e reportar divergência.

## 3. Verificações obrigatórias antes de executar

1. Confirmar autenticação GitHub, remote, branch ativa, árvore limpa e SHAs de dev e main; verificar que o PR #85 já está **MERGED apenas na dev**, sem tentar mesclá-lo de novo.
2. Ler `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, `Domain/Usage.php`, `Domain/Attempt.php`, `Domain/TenantContext.php`, contratos e testes F1-01..F1-04, AIA-10, R03/S04, R04/S01–S02 e a proposta documental F1-05.
3. Validar que o catálogo de ferramentas F1-04 e o coordenador F1-02 não serão alterados nem terão sua autoridade substituída; não confundir limites técnicos de uso com créditos CRM.
4. Verificar PHP CLI 8.1+ **já disponível**. Se ausente, registrar lint/testes **NÃO EXECUTADOS**, sem instalar PHP, Composer, ferramentas ou acionar CI.
5. Registrar SHA da base, lista de arquivos pretendidos, testes, zero fornecedores reais, zero dados pessoais e critérios de parada.

## 4. Superfícies autorizadas exclusivamente após preflight

| Local | Uso permitido somente se autorizado | Proibição |
| --- | --- | --- |
| `src/MockUsage/` | Classes puras de política/cotas, livro-razão volátil de unidades e eventos, conciliação simulada, alertas locais mínimos | Banco, filas, locks distribuídos, HTTP, cobrança, ledger comercial ou debitar créditos |
| `tests/Unit/usage_quota.php` | **12 novos** casos U05-01 a U05-12 | Rede, API real, PHPUnit/Composer novo, Actions, testes em CRM |
| `tests/Unit/run.php` | Uma inclusão mínima da nova suíte **depois dos 46 testes existentes** | Reescrever ou desativar testes anteriores |
| `README.md`, `STATUS-ATUAL.md` | Documentar resultados, limites, SHA, ambiente, falhas e gates abertos | Presumir homologação e execução bem-sucedida sem evidência |
| Novos value objects em `src/Domain/` | **Somente após autorização adicional, se indispensáveis**; preferir composição | Mudar estados ou assinaturas públicas já integrados |

**Preservar sem alterações:** `AGENTS.md`, `src/Contracts/`, `src/MockOrchestration/SupervisedCoordinator.php`, `src/MockResilience/ReadOnlyTraceReview.php`, `src/MockTooling/`, classes existentes de domínio, documentos ratificados, `.github/workflows/`, todos os outros repositórios, `main`, infraestrutura e configurações de servidores.

Não criar `composer.json`, lockfiles, banco, migrations, `.env`, cliente HTTP/LLM, chaves, tabelas comerciais, Dockerfile ou jobs agendados.

## 5. Invariantes obrigatórias e verificações de falha segura

- Mesma identidade material de evento e contexto idêntico: contar no máximo uma vez em memória; evento duplicado com material/versão diferente: negar como conflito sem substituir prova existente.
- Política de quota sem contexto completo, revogada, expirada, desconhecida ou versão divergente: negar. Nunca inferir concessão de identidade a partir de prefixos ou mensagens de IA.
- Unidades negativas, absurdamente grandes, com tipo incorreto ou overflow: negar tipadamente; proteger somas e arredondamentos.
- Medição estimada não equivale a observada; observada não vira conciliada sem uma **prova falsa explícita** do laboratório. Eventos tardios não devem apagar consumo previamente contabilizado.
- Timeout ou falha de efeito CRM não significa ausência de custo técnico; status indeterminado permanece indeterminado até evidência fictícia válida.
- Sinal de limite/alerta não autoriza execução de ferramentas, não aumenta quota e não interage com a autorização do CRM.
- Rastros com códigos/referências fechados e mínimos; não registrar prompt bruto, entrada adversarial, números de cartões, tokens ou dados de leads.
- O modelo em memória é **single-process**, não comprova reserva atômica sob concorrência, durabilidade, conciliação financeira, faturamento, SLA, privacidade LGPD operacional ou autorização em produção.

Caso uma invariante exija mudar F1-01..F1-04, **parar e solicitar ampliação explícita**, sem adaptar testes para esconder a falha.

## 6. Matriz de testes sintéticos autorizados — 12 novos + 46 regressões = 58

| ID | Critério de aceite proposto |
| --- | --- |
| U05-01 | Contexto/versão/política válidos admitem somente cota técnica fictícia |
| U05-02 | Limite esgotado ou insuficiente nega tentativa nova sem alterar saldo |
| U05-03 | Mesmo evento material idêntico não contabiliza duas vezes |
| U05-04 | Mesmo ID com unidades/material divergentes resulta em conflito |
| U05-05 | Outro tenant/principal/produto/finalidade não consulta nem compartilha uso |
| U05-06 | Política ausente, expirada, revogada ou com versão incompatível bloqueia |
| U05-07 | Estimado, observado e conciliação explícita permanecem distinguíveis |
| U05-08 | Uso técnico observado após timeout não é zerado por efeito indeterminado |
| U05-09 | Evento ausente/contraditório não se torna custo conciliado |
| U05-10 | Negativos, entradas enormes, overflow e campos extras geram negação segura |
| U05-11 | Alertas locais de aproximação/exaustão não são duplicados nem enviados |
| U05-12 | Instrução adversarial e marcador de segredo fictício não vazam no trace |

**Nenhum dos 12 testes F1-05 foi executado.** A meta 58/58 é futuro critério, não resultado. Os **46 testes F1-01..F1-04** devem permanecer intactos e ser executados com os novos no mesmo checkout quando houver **autorização executiva específica**.

Com PHP 8.1+ previamente disponível, comandos candidatos:
```sh
php -v
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```
Registrar SHA de checkout, versão, hashes de blobs e códigos de saída reais; sem instalar runtime, sem GitHub Actions e sem anunciar PASS sem executar.

## 7. Política de PR, gates e decisões futuras

1. Esta minuta é **DOCUMENTAÇÃO APENAS** e sua leitura ou aprovação documental não significa ordem ao Codex. Não criar branch de código nesta etapa.
2. Uma autorização futura deverá mencionar F1-05 explicitamente, os diretórios permitidos, execução dos testes sintéticos e **proibição de merge e deploy**.
3. Caso autorizado, publicar somente PR de código **DRAFT** com destino dev, sem merge; comunicar resultados reais e desvios.
4. Eventual merge de código exigirá autorização separada do responsável, após revisão técnica.
5. `main`, os gates R01-G01–G11 e CORE/SEG/AUT CRM, e F1-06 permanecerão bloqueados mesmo se laboratório futuro for aprovado.

## 8. Texto da autorização executiva expressamente concedida

> **Autorizo expressamente a programação e os testes sintéticos da FT01-F1-05 no repositório `rafhael100/imagine-ai-agents`**, exclusivamente em uma branch filha isolada `dev/ft01-f1-05-usage-quotas-mocks-2026-11-08` a ser criada após verificação de SHA e base `dev/ft01-aia-core-mocks-2026-11-06` e da homologação documental/merge específico do PR #85. Autorizo somente PHP puro em memória em `src/MockUsage/`, `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`, com política de uso e cota técnica fictícia, deduplicação de eventos, reconciliação conservadora e alertas locais mínimos, mais 12 casos U05-01..U05-12 e regressão dos 46 anteriores. Rodar lint e até 58 casos somente se PHP 8.1+ já estiver disponível, sem instalações; registrar NÃO EXECUTADOS se ausente. Criar PR de código DRAFT filha → branch dev, SEM MERGE. Não autorizo alterar contratos anteriores, `main`, CRM/Portal/outros projetos, infra, APIs reais, dados pessoais, SQL/ALLOW, Composer, GitHub Actions, credenciais, custos, deploy, F1-06 ou gates R01-G01 a G11/CORE/SEG/AUT CRM. Qualquer divergência interrompe a execução.

O responsável **CONCEDEU autorização executiva expressa em 2026-11-08**, respondendo **“sim”** à pergunta: **“Você autoriza expressamente o desenvolvimento do AIA-FT01-F1-05, limitado ao código PHP mockado e aos testes unitários sintéticos, sem merge, despesas, integrações reais ou alterações na infraestrutura?”**. O texto acima passa a ser a **ordem operacional restrita**. Continua **proibido** merge de código, F1-06, implantação, gastos ou alteração de gates.

**Próximo ato:** o responsável deve revisar a minuta e decidir em ato independente sobre executar ou não o lote. Antes disso, F1-05 permanece **documental ratificada, execução bloqueada**.

## 9. Atualização de estado e destino documental — 2026-11-08

- PR #85 **MERGED**, com ratificação exclusivamente documental de F105-D01 a D10 (alternativas A), na branch `dev/ft01-aia-core-mocks-2026-11-06`; merge `93ca068dbbb6e4962a532a5f98994b55fd097621`.
- A **minuta executiva atual** está em branch documental limpa derivada diretamente da branch dev após o merge #85, evitando duplicar o histórico documental anterior. O PR da minuta deve continuar **DRAFT/SEM MERGE** até revisão específica. A execução de código permanece bloqueada.
- **F1-05 AINDA NÃO AUTORIZADO PARA CÓDIGO/TESTES:** nenhuma branch de código, Codex, runtime, CI, integrações, despesas ou implantação autorizados por esta minuta.
- R01-G01–G11 e CORE/SEG/AUT CRM **ABERTOS**. `main`, demais repositórios, VPS/Cloudflare/n8n inalterados; F1-06 bloqueado.

## 10. Registro vinculante da autorização — 2026-11-08

- O responsável **AUTORIZOU EXECUTAR** F1-05 estritamente como laboratório PHP puro em memória, com 12 novos testes unitários sintéticos U05-01–U05-12 e regressão dos 46 anteriores, meta **58 testes**, sem presumir nenhum PASS F1-05 nesta etapa.
- Repositório e destino: `rafhael100/imagine-ai-agents`, branch base `dev/ft01-aia-core-mocks-2026-11-06`, branch filha de código `dev/ft01-f1-05-usage-quotas-mocks-2026-11-08`, a **criar ou verificar somente após preflight autenticado**; base no SHA remoto vigente, sem sobrescrever refs ou executar força.
- Superfícies: `src/MockUsage/`, `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`; **não modificar** `src/Contracts/`, `AGENTS.md`, `SupervisedCoordinator`, `MockToolDispatcher`, `ReadOnlyTraceReview`, outros módulos/contratos existentes e decisões documentais ratificadas. `src/Domain/` fora do escopo sem nova justificativa e autorização.
- PHP 8.1+ **já disponível**: executar lint e runner completo, documentando versão, SHA, hashes, saídas e códigos. PHP indisponível: testes **NÃO EXECUTADOS**, não instalar PHP/Composer nem criar workflow GitHub Actions.
- Entrega futura deve abrir **PR de código DRAFT filha → dev, SEM MERGE**. A aprovação desta execução **não** autoriza merge desse PR; solicitar autorização separada após revisão humana e validação.
- **NÃO AUTORIZADOS**: `main`, CRM/Portal/outros repositórios, dados reais, credenciais, SQL/ALLOW, APIs/LLM, banco, infraestrutura, VPS/Cloudflare/n8n, dependências, compras, CI/Actions, custos, deploy, F1-06, fechamento dos gates R01-G01 a G11 e CORE/SEG/AUT CRM. **TODOS ABERTOS**.
- Se algum passo exigir alterar contratos existentes, permissões reais, dependências, instalar runtime, ou houver divergência Git, **parar** e pedir revisão. Nenhum deploy ou homologação de produção decorre dos testes.

**Estado mais recente (prevalece sobre notas históricas): F1-05 AUTORIZADO PARA CÓDIGO MOCKADO E TESTES SINTÉTICOS; MERGE DE CÓDIGO E PRODUÇÃO NÃO AUTORIZADOS.**
