# AIA-FT01/F1-02 — Autorização Executiva Restrita e Ordem ao Codex | v0.1 ratificada

> **ESTADO: F1-02 EXPRESSAMENTE AUTORIZADO PELO RESPONSÁVEL EM 2026-11-08**, em resposta afirmativa à pergunta específica de autorização do segundo lote. Autoriza somente PHP puro mockado, testes unitários sintéticos e branch filha isolada, após preflight Git; não autoriza merge, infraestrutura, despesas, integração real ou F1-03. A autorização NÃO comprova que o código tenha sido executado.
>
> **Referência:** 2026-11-08. **Repositório:** `rafhael100/imagine-ai-agents`. **Branch documental:** `docs/ft01-f1-02-autorizacao-executiva-proposta-2026-11-08`, base `dev/ft01-aia-core-mocks-2026-11-06`.
>
> **Precedência:** F1-01 integrado apenas no ramo de desenvolvimento via PR #71; 16 lints e 10 testes unitários sintéticos aprovados em ambiente PHP isolado; FT01-F102-D01 a D10 aprovadas **somente documentalmente** no PR #73. `main` segue isolada.
>
> **Gates e preservações obrigatórias:** R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM **ABERTOS**, sem SQL/ALLOW, credenciais, banco/filas, APIs reais, dados pessoais, integrações, instalações de PHP/Composer/Laravel, GitHub Actions, despesas, VPS/Cloudflare/n8n, alteração de `main`, merge de código ou deploy.

## 1. Objetivo e recorte técnico da autorização concedida

Implementar, **sob a autorização executiva expressa de 2026-11-08**, o segundo lote de **coordenação supervisionada local** sobre as estruturas PHP puras do F1-01, com contratos mockados, sem conexões reais e sem poderes de escrita externa.

O resultado esperado é uma camada sintética que:
- coordena `Run → Step → Intent → Approval → Effect` com estados e falhas explícitas;
- distingue sugestão de decisão/efeito e resultado indeterminado;
- consulta `status` do mock após falha/timeout, **sem repetir `submit` automaticamente**;
- registra somente rastros mínimos, sintéticos, sem payload de leads/prompts;
- mantém o controle de efeitos no gateway CRM **fake**, sem criar ACL real;
- conserva os 10 testes F1-01 e propõe 12 novos testes F1-02 sem modificar comportamento existente por conveniência.

F1-02 é **recorte de laboratório** e não produto pronto. Não adicionar motor próprio de automações CRM, cobrança, agentes IA remotos, persistência, filas ou infraestrutura.

## 2. Branch exclusiva de código autorizada — criar após preflight

| Elemento | Valor proposto |
| --- | --- |
| Repositório | `rafhael100/imagine-ai-agents` |
| Base de código | `dev/ft01-aia-core-mocks-2026-11-06` |
| Branch filha futura | `dev/ft01-f1-02-supervised-mocks-2026-11-07` |
| Origem | SHA vigente da branch base, validada por Git autenticado antes da criação |
| Destino do PR de código | **Branch filha → branch base**, em modo **draft** |
| `main` | Não criar commits nem fazer merge |
| Autorização atual | **F1-02 expressamente autorizado em 2026-11-08; sem merge, deploy ou F1-03** |

A criação da branch filha de F1-02 foi autorizada expressamente em 2026-11-08, condicionada ao preflight. Caso exista, apontar a SHA e parar para conciliar; não sobrescrever, forçar ou recuperar por atalhos.

## 3. Preflight obrigatório ao executor futuro

1. Conferir `gh auth status`, `git remote -v`, `git status`, `git branch --show-current`, `git ls-remote` e SHA dos refs remotos. Falha de autenticação ou divergência não autoriza `push` por outro mecanismo.
2. Ler `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, AIA-FT01, R03/R02, R04/S01–S02, `docs/AIA-FT01-F1-02-COORDENACAO-SUPERVISIONADA-MOCK-PROPOSTA.md` e esta proposta.
3. Confirmar que a branch base contém os componentes F1-01; não sobrescrever arquivos ou alterar política de gates.
4. Confirmar que nenhuma etapa precisa de segredos, APIs de CRM/IA, licenças pagas, instalação de runtime ou infra.
5. Confirmar se `php` 8.1+ **já está disponível**, mas sua ausência **não autoriza instalação**. Se faltar, documentar impossibilidade de executar testes; código não poderá ser aceito sem validação posterior.
6. Registrar SHA de origem, ambiente e arquivos candidatos antes do primeiro commit.

## 4. Superfícies permitidas pela autorização executiva

| Superfície | Uso permitido | Exclusões |
| --- | --- | --- |
| `src/MockOrchestration/` | Coordenador puro, política/fluxo de decisões, reconciliação local de resultado | Sem workers, scheduler, tasks reais, pipelines, filas, SQL |
| `src/Domain/` | Novos tipos imutáveis pequenos: resultado, decisão, trace referencial se necessários | Sem alterar contrato real CRM, identificação real ou créditos |
| `src/Mocks/` | Política simulada de aprovação, reconciliador fake, coletor de rastros sintéticos | Sem dados privados, rede, persistência, logs de payload |
| `tests/Unit/` | Adicionar os 12 casos de F1-02, manter e reexecutar os 10 de regressão F1-01 | Sem integração, GitHub Actions, servidor, teste pago |
| `README.md` | Explicar limites dos novos mocks e execução local | Sem alegar produto/ACL homologados |
| `STATUS-ATUAL.md` | Registrar executor, branches, commits, validação efetiva e gates | Não fechar gates nem presumir testes |

**Proibido alterar:** `AGENTS.md`, `main`, `docs/` de diretrizes homologadas, repositórios CRM/Portal/Sites/Performance/Universidade, infra e contas reais. Interfaces existentes em `src/Contracts/` devem permanecer estáveis neste lote; se uma alteração for necessária, interromper e submeter nova decisão.

Não adicionar `composer.json`, `.github/workflows/`, `Dockerfile`, `docker-compose.yml`, arquivos `.env`, endpoints HTTP, SDKs, migrations, lockfiles ou dependências de terceiros.

## 5. Implementação simulada: invariantes de segurança lógica

- **Sugestão não grava:** a sugestão fake pode ser lida e registrada como `suggested`, mas nunca executar `submit` diretamente.
- **Identidades sintéticas:** `TenantContext` só descreve relações do mock, não comprova autenticação ou autorização. Respeitar produto/tenant/finalidade/recurso.
- **Aprovação separada:** é vinculada a intenção/material/contexto e janela de tempo; o coordenador solicita ou verifica, não emite aprovação automática.
- **Resultados não transitivos:** `Run::CONCLUIDA` e `Attempt::RESPOSTA` não significam efeito confirmado.
- **Retry seguro:** ao observar `TIMEOUT`/`INDETERMINADO`, consultar `CrmEffectGateway::status`, **nunca disparar novo `submit` automático**.
- **Idempotência e erro:** mesma chave + mesma intenção no fake conserva efeito; mesma chave + material diferente vira conflito explícito, sem override.
- **Cancelamento:** não reiniciar run terminal nem ocultar efeito mockado já confirmado.
- **Rastreio referencial mínimo:** IDs/códigos sintéticos, sem prompts, mensagens brutas, conteúdo de lead, tokens, credenciais ou stack traces.
- **Composição determinística:** sem relógio global, aleatoriedade, bancos ou egress de aplicação; relógio passado como parâmetro e dependências injetadas manualmente.
- **Sem validação de produção:** testes não demonstram ACL real, segregação por tenant no SaaS, concorrência/atomicidade, LGPD de produção nem segurança do CRM.

## 6. Escopo de testes F1-02: 12 casos NOVOS + 10 regressões

Autorização executiva futura, se concedida, **poderá incluir somente testes unitários locais com dados sintéticos**, sem infraestrutura nem instalação de runtime.

| ID | Critério de teste mockado |
| --- | --- |
| U02-01 | Aprovação válida e intenção exata permitem apenas efeito **fake** com prova sintética |
| U02-02 | Sugestão com texto adversarial não causa mutação ou elevação de autoridade |
| U02-03 | Aprovação ausente, pendente ou recusada resulta em negativa |
| U02-04 | Revogação após aprovação e antes da tentativa bloqueia efeito |
| U02-05 | Janela expirada ou intenção/material diferente bloqueia efeito |
| U02-06 | Run concluída + efeito indeterminado não apresenta confirmação |
| U02-07 | Timeout exige consulta de `status` sem reenvio `submit` |
| U02-08 | Idempotency key repetida com material diferente gera conflito |
| U02-09 | Contexto tenant A não consulta/atribui intenção de tenant B |
| U02-10 | Cancelamento impede novos passos, sem apagar efeitos fake anteriores |
| U02-11 | Versão desconhecida/estado terminal inválido falha com código seguro |
| U02-12 | Trace só contém IDs e estados sintéticos, jamais payload completo |

Manter **10 testes originais F1-01** passando sem adulterações oportunistas. Meta de avaliação: **22/22 casos unitários** e lint de todos os PHP, **mas não presumir sucesso**; registrar saídas reais, versão PHP e eventuais erros. Se o Codex não dispuser de PHP, registrar **não executados** e propor validação isolada posterior, sem alegar 22/22.

## 7. Checklist de entrega para PR futuro

- [ ] Autorizar executivamente F1-02 de forma expressa; **não autorizado neste PR**.
- [ ] Fazer preflight Git e confirmar SHA de branch base e arquivos.
- [ ] Criar branch filha **somente após autorização**, sem force-push.
- [ ] Implementar somente componentes mockados em superfícies permitidas.
- [ ] Executar lint e 22 testes somente onde PHP 8.1+ já estiver disponível, sem instalação e sem GitHub Actions.
- [ ] Registrar teste executado ou não executado, cada falha e correções.
- [ ] Abrir **PR draft filha → branch dev**, sem merge.
- [ ] Confirmar ausência de mudanças na `main`, infra, outros repositórios, serviços reais e gastos.
- [ ] Manter gates R01-G01 a G11 e CORE/SEG/AUT CRM **abertos**.
- [ ] Não iniciar F1-03 ou integração real; solicitar autorização de merge separada.

## 8. Autorização executiva expressa concedida

O responsável respondeu **“ok aprovado”** à solicitação inequívoca: **“Você autoriza expressamente o AIA-FT01-F1-02, incluindo código PHP mockado e testes unitários sintéticos, sem merge, custos ou alterações na infraestrutura?”** Portanto, o escopo executivo foi concedido em 2026-11-08 com o seguinte texto operacional:

> **Autorizo expressamente a execução do AIA-FT01-F1-02**, exclusivamente no repositório `rafhael100/imagine-ai-agents`, criando a branch `dev/ft01-f1-02-supervised-mocks-2026-11-07` a partir da `dev/ft01-aia-core-mocks-2026-11-06`, após preflight Git autenticado. Autorizo somente código PHP puro de coordenação supervisionada local, políticas e efeitos mockados em `src/MockOrchestration/`, `src/Domain/`, `src/Mocks/`, até 12 testes unitários novos em `tests/Unit/`, preservando e reexecutando os 10 testes de F1-01 onde PHP 8.1+ já estiver disponível, além de documentação em `README.md` e `STATUS-ATUAL.md`. Se PHP faltar, relatar testes não executados, sem instalar nada. Não autorizo dependências instaladas, mudanças em `src/Contracts/`, SQL, ALLOW, API/CRM/IA reais, dados privados, credenciais, Github Actions, despesas, VPS/Cloudflare/n8n, alteração da `main`, merge ou deploy. Abrir PR draft filha → branch base, apresentar diff, commits e resultados reais e preservar R01-G01 a G11 e CORE/SEG/AUT CRM abertos. Não iniciar F1-03. Se preflight falhar, parar.

**Estado ratificado:** autorização executiva F1-02 concedida em 2026-11-08, restrita ao lote descrito. A codificação efetiva deverá ocorrer no executor autorizado após preflight e ser comprovada por commit, PR e saídas reais; o aceite não autoriza merge do PR de código, deployment nem F1-03. O PR #75 é documental; o código F1-02 permanecerá em PR separado.

## 9. Próximos passos

1. [x] Autorizar expressamente F1-02 em 2026-11-08, somente para o lote mockado e testes locais sintéticos.
2. [ ] Executar preflight Git e conferir o SHA de origem antes de trabalhar na branch filha autorizada.
3. [ ] Codificar e validar apenas no ramo filho; abrir PR de código **draft** para a branch de desenvolvimento, sem merge.
4. [ ] Revisar evidências e pedir nova autorização para eventual merge **somente na branch dev**.
5. [ ] Preservar `main`, gates, infra, zero custos e proibição de F1-03.

**Estado vinculante atual:** F1-01 concluído na branch dev; F1-02 **AUTORIZADO EXPRESSAMENTE em 2026-11-08 para PHP puro mockado e testes sintéticos, não executado até prova em GitHub**. Merge do código, F1-03 e integração real NÃO AUTORIZADOS. Todos os gates permanecem ABERTOS.
