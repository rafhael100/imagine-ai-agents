# AIA-FT01/F1-01 — Autorização Executiva Restrita | Ratificada v0.1

> **ESTADO: FT01-F1-01 EXPRESSAMENTE AUTORIZADO PELO RESPONSÁVEL em 2026-11-06**, com aprovação do PR #70. Limites: **somente PHP mockado, testes unitários locais sintéticos e branches isoladas**, sem CRM real, dados privados, APIs, credenciais, SQL/ALLOW, dependências instaladas, despesas, infraestrutura, merge de código ou deploy. Gates R01-G01 a G11 e CORE/SEG/AUT CRM seguem abertos.
>
> **Data:** 2026-11-06. **Repositório:** `rafhael100/imagine-ai-agents`. **Fundamento documental:** AIA-FT01 (FT01-D01–D10 aprovadas documentalmente), AIA-R03/R02, AIA-R04/S01–S02.
>
> **Bloqueios intocados:** R01-G01 a G11 TODOS ABERTOS, CORE/SEG/AUT e demais gates CRM ABERTOS; nada de API/SQL/ALLOW/CRM real, contas, credenciais, dados privados, n8n, provedores, VPS/Cloudflare, gastos ou deploy.

## 1. Escopo estritamente autorizado do F1-01

**Somente o lote `FT01-F1-01`**, com objetivo de construir um primeiro domínio de execução **simulado e não operacional**, sujeito às restrições do AIA-FT01 ratificado. Não é autorização de integração CRM↔AIA, de implementação da arquitetura inteira, de filas/bancos reais, de serviços IA ou de produção.

**Tecnologia para este lote exclusivamente:** PHP sem frameworks nem bibliotecas de terceiros, organizado de forma compatível com possível Laravel futuro. **Não fixa linguagem da plataforma em produção**. Criar código sem instalar Composer, Laravel, PHP ou serviços. Caso PHP não esteja disponível no ambiente autorizado, escrever somente o que for possível sem baixar dependências e reportar testes não executados.

**Permissão de testes expressamente concedida em 2026-11-06:** testes unitários **locais e sintéticos**, usando apenas o PHP já disponível, **sem rede, banco, API real ou dependências baixadas**. Proibidos testes de integração, testes em VPS e homologação de segurança real. Se o ambiente não possuir PHP ou houver restrição de execução, não buscar acesso alternativo nem instalar PHP: registrar o impedimento.

## 2. Branches de código autorizadas — criar após preflight

1. **Branch base de desenvolvimento:** `dev/ft01-aia-core-mocks-2026-11-06`, criada a partir da SHA atual de `main` somente após preflight e autorização formal.
2. **Branch filha do lote:** `dev/ft01-f1-01-domain-mocks-2026-11-06`, derivada da branch base.
3. **PR de código:** filha → base de desenvolvimento, **draft** inicialmente, **sem merge**. `main` permanece inalterada.
4. **Sem squash/merge/release/deploy** sem autorização adicional. Branch/PR de código não existem na publicação deste documento.

Se branch já existir ou base divergir, parar e solicitar decisão, sem sobrescrever refs ou alterar o histórico.

## 3. Preflight obrigatório do executor — FT01-F1-01 autorizado

- Confirmar Git autenticado de leitura **e escrita** no repositório exato, branch `main` e SHA atual; não assumir que conectividade de navegador equivale a `git push` autenticado.
- Ler obrigatoriamente `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, `docs/AIA-FT01-PROPOSTA-AUTORIZACAO-CODIGO-MOCKADO.md`, os recortes R03/R02 e R04/S01-S02, e esta ordem.
- Verificar se repositório está sem alterações de trabalho conflitantes, se `main` não tem código prévio relevante e se arquivos propostos não serão sobrescritos.
- Confirmar ausência de tokens, segredos reais, VPN/SSH, contas externas, dados CRM e necessidade de chamadas de rede além do GitHub para versionamento autorizado.
- Se Git autenticado falhar, documentar `Access is denied`/erro efetivo e **parar antes de criar qualquer arquivo de código ou branch**; não usar credenciais alternativas, clones substitutos ou bypass de gates.
- Registrar a SHA e plano de alterações no relatório de execução. Não iniciar com pressupostos de que ACL, esquemas ou endpoints do CRM existam.

## 4. Escopo positivo autorizado de F1-01

A primeira atividade deve permanecer pequena, reversível e **puramente local**:

| Superfície futura | Arquivos/artefatos permitidos | Limite |
| --- | --- | --- |
| `src/Domain/` | `Run`, `Step`, `Intent`, `Approval`, `Attempt`, `Effect`, `Usage`, `TenantContext` como objetos puros / tipos de estado | Sem persistência, jobs, autenticação real ou execução remota |
| `src/Contracts/` | Interfaces para leitura CRM mínima, status de efeito CRM, provedor de sugestão de IA e políticas locais | Contratos são apenas **mocks não homologados**; sem código de integração |
| `src/Mocks/` | CRM reader/effect fake e sugestão de IA determinística baseada em dados sintéticos | Sem requests HTTP, bibliotecas externas, dados reais ou IA real |
| `tests/Unit/` | Testes unitários locais e fixtures inteiramente sintéticas se a autorização permitir | Sem rede, SQL, banco, SMTP, arquivos privados, CI externa ou mocks que se declarem prova de segurança |
| `README.md` | Orientação de execução local para mocks e explicação das limitações | Sem prometer produção ou API existente |
| `STATUS-ATUAL.md` | Registro de branch, SHA, diff, resultados de testes realmente executados e gates mantidos | Não registrar gate fechado |
| `composer.json` ou equivalente | **Não permitido neste lote**, salvo autorização específica posterior | Não baixar/instalar dependências |

`AGENTS.md` permanece intacto. Em especial, seus bloqueios só serão excepcionados pelo texto inequívoco de autorização **para o escopo mínimo de código e testes unitários locais**, sem reinterpretar como permissão genérica.

**Fora de escopo mesmo após autorização F1-01:** Laravel instalado, PostgreSQL, Redis, mensageria real, migrations, SQL, ALLOW, Docker, backend HTTP, endpoints externos, agentes LLM reais, clientes SaaS, conexão a CRM/Portal/Sites/Performance/Universidade, n8n, modelos pagos, secrets, aquisição/conta, VPS, Cloudflare, alteração de `main` ou deploy.

## 5. Critérios de aceite de domínio mockado

F1-01 deve manter referência distinta entre `run`, `step`, `intent`, `approval`, `attempt`, `effect` e `usage`, com vínculos de tenant e produto sintéticos. O mock **não é** implementação de autorização de produto.

Casos unitários locais previstos (somente com aprovação expressa de testes):
- tenant sintético A não lê lead sintético B através do fake CRM;
- inferência simulada de resumo não gera tarefa nem grava histórico;
- estado de run concluída com efeito indeterminado **não** declara efeito confirmado;
- repetição de mesma intenção/mesmos parâmetros materiais no mock retorna o mesmo efeito simulado sem duplicação;
- mesma chave com parâmetros materiais diferentes retorna conflito no mock;
- aprovação ausente/revogada/expirada impede mutação simulada;
- cancelamento impede novos passos, sem apagar efeitos sintéticos já confirmados;
- versão lógica não suportada retorna erro, e não coerção permissiva;
- eventos de uso técnico mockados não alteram crédito ou faturamento comercial;
- saída/prompt sintético não eleva privilégios do serviço.

Os testes **não** validam deduplicação transacional real, autenticação/ACL do CRM, LGPD de produção, segurança de rede, filas ou implantação. Relatar exatamente os que foram ou não executados.

## 6. Saída exigida do Codex após execução FUTURA autorizada

Relatório final com: repositório/branch pai/filha e SHA; arquivos criados/modificados; versão PHP **somente se verificável**; testes executados e seus resultados sem inventar; limitações; diff/ausência de mudanças proibidas; URL/ID de PR draft se realmente criado; confirmação de que `main`, demais repositórios, VPS/Cloudflare/n8n e gates permanecem inalterados.

Se não conseguir criar PR pelo GitHub, informar o impedimento real e não alegar entrega concluída; **não executar merge**. Lote F1-02 exige decisão posterior.

## 7. Autorização expressa concedida pelo responsável

O responsável emitiu em 2026-11-06 autorização expressa equivalente ao texto de escopo a seguir:

> **Autorizo expressamente a execução do AIA-FT01-F1-01**, exclusivamente no repositório `rafhael100/imagine-ai-agents`, criando, após preflight Git autenticado, as branches `dev/ft01-aia-core-mocks-2026-11-06` (base a partir de `main`) e `dev/ft01-f1-01-domain-mocks-2026-11-06` (filha). Autorizo somente PHP puro com modelos de domínio, interfaces e mocks locais determinísticos em `src/Domain`, `src/Contracts` e `src/Mocks`; fixtures e **testes unitários locais sintéticos** em `tests/Unit`, mais atualizações de `README.md` e `STATUS-ATUAL.md`. Não autorizo dependências instaladas, SQL, ALLOW, APIs reais, integração CRM, credenciais, dados reais, provedores pagos, redes/VPS/Cloudflare/n8n, testes de integração, alteração da `main`, merge ou deploy. Criar PR draft da branch filha para a branch base e apresentar diff, SHA, testes efetivamente executados e bloqueios. R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos. Se o acesso Git autenticado ou pré-requisitos falharem, interromper e relatar, sem contorno.

**O responsável autorizou expressamente FT01-F1-01 no turno posterior à aprovação de FT01-D01 a D10.** A aprovação executiva limita-se a este lote; o executor deve informar sua identidade/ferramenta real e não alegar que o Codex executou se não houver ferramenta Codex. Sem merge em `main`, integração real ou fechamento de gates.

## 8. Checklist e dependências

- [x] Responsável aprovou PR #70 e autorizou expressamente FT01-F1-01 em 2026-11-06.
- [x] Confirmada linguagem PHP puro somente para mock, sem fixar stack final.
- [x] Confirmada permissão expressa de testes unitários locais sintéticos.
- [x] Confirmadas branch base/filha, superfícies permitidas e política de revisão/stop conditions.
- [ ] Verificar Git, main SHA, licenças de eventuais componentes e disponibilidade local; sem instalar nada previamente.
- [ ] Executar FT01-F1-01 com preflight Git autenticado, registrar executor efetivo, commits e resultados; sem criar branch se preflight falhar.
- [ ] Preservar R01-G01–G11 TODOS ABERTOS e CRM CORE/SEG/AUT ABERTOS, sem homologação cruzada.
- [ ] Não usar dados reais, credenciais, serviços externos, APIs pagas, infraestrutura ou deploy.
- [ ] Exigir nova decisão para a etapa F1-02, qualquer instalação e qualquer merge.

**Estado vinculante:** o responsável **autorizou a execução restrita de FT01-F1-01 em 2026-11-06**. A execução ainda depende de preflight e registro verificável; aprovação não autoriza merge de código, deploy, uso de serviços reais ou F1-02.
