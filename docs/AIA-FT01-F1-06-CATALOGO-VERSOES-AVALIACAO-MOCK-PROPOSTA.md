# AIA-FT01/F1-06 — Catálogo de versões, avaliações e promoção simulada de agentes | Proposta documental v0.1

> **ESTADO: F106-D01 a D10, alternativas A, RATIFICADAS EXCLUSIVAMENTE DOCUMENTALMENTE em 2026-11-08.**
>
> **Data:** 2026-11-08. Repositório: `rafhael100/imagine-ai-agents`. Branch exclusivamente documental `docs/ft01-f1-06-catalogo-versoes-avaliacao-mock-2026-11-08`, baseada na `dev/ft01-aia-core-mocks-2026-11-06`.
>
> **Autorização efetiva:** o responsável primeiro respondeu **“sim”** ao pedido de **iniciar o planejamento documental F1-06** e, posteriormente, respondeu **“sim”** à pergunta específica de aprovar **as dez alternativas A de F106-D01 a D10 exclusivamente como diretrizes documentais**. Esta ratificação **NÃO autoriza** criar branch de programação, executar Codex, escrever PHP, executar testes F1-06, realizar merge documental/de código, serviços externos, integrações reais, gastos, servidores ou deploy.
>
> **Gates:** R01-G01 a G11, CORE/SEG/AUT CRM e demais homologações **TODOS ABERTOS**. `main`, outros repositórios, CRM, Portal, Performance AI, Sites, Universidade e VPS/Cloudflare/n8n inalterados. Nada de SQL/ALLOW, dados privados, autenticação real, JWT/tokens, LLM/API, Composer/Actions, credenciais, custos ou publicação real. F1-07 não autorizada.

## 1. Precedência e razão do recorte

As etapas **F1-01 a F1-05 foram integradas exclusivamente à branch dev**, incluindo o PR #88 da F1-05, squash `8b128c2882c7baf7e59264b16361c9bc9f8108d7`. As evidências anteriores de laboratório reportam **58/58 testes unitários sintéticos PASS** e **30/30 arquivos PHP com lint válido** em PHP 8.4.24, com hashes conferidos. **Não** demonstram segurança operacional, API CRM, persistência, IAM real ou preparo para produção.

F1-01 define domínio, F1-02 coordenação supervisionada, F1-03 resiliência e leitura conservadora, F1-04 ferramentas/capabilities mock, F1-05 medição e quotas técnicas mock. O repositório ainda não contém `src/MockCatalog/`. As diretrizes AIA-01 (catálogo central de agentes), AIA-02 (instruções e regras) e AIA-09 (laboratório de avaliações) já têm aprovação funcional, mas não autorizam aplicação real.

**Lacuna candidata:** faltam modelos locais para ensaiar como versões de agente/instruções são congeladas, avaliadas, promovidas ou suspensas **sem** permitir alterações silenciosas no comportamento, na lista de ferramentas ou nas permissões. Este documento define **um experimento in-memory**, não um serviço administrativo, UI, catálogo operacional ou sistema de publicação.

## 2. Recorte recomendado F1-06 — exclusivamente SINTÉTICO e ainda não aprovado para código

| Componente candidato | Responsabilidade no laboratório | Proibição explícita |
| --- | --- | --- |
| `MockAgentTemplateCatalog` | Catálogo fechado de templates `SYNTHETIC_*` por produto elegível, finalidade e owner fictícios | Cadastro real de agentes, descoberta remota e painel de administração |
| `MockAgentVersionSnapshot` | Manifesto imutável de instruções sintéticas referenciadas, capacidades elegíveis, política, parâmetros e versão, com impressão digital material determinística | Registrar prompt de cliente, mudar conteúdo após aprovação ou permitir código dinâmico |
| `MockVersionReview` | Evidência de avaliação unitária fictícia: cenário/versão, resultados, risco, decisor fictício e validade | Tratar revisão fake como homologação CRM/AIA ou assinatura verdadeira |
| `MockPromotionGate` | Decisão local fail-closed para promoção, suspensão e retorno a versão previamente permitida; nunca criar privilégios | Publicação, troca de versão operativa, deploy, rollout de produção |
| `MockEvaluationTrace` | Rastro mínimo de decisões e motivos fechados, sem texto integral de instrução/segredo | Logs pessoais ou exportação externa |
| `tests/Unit/catalog_release.php` | Propor 12 casos U06-01..U06-12, reutilizando 58 regressões existentes | CI/Actions, rede, CRM real ou pagamento de modelo |

**Nomes e classes são apenas candidatos**. Preferir composição e estruturas imutáveis novas sem alterar `Run`, `Approval`, `Usage`, `SupervisedCoordinator`, `ReadOnlyTraceReview`, `MockToolDispatcher`, `MockUsageLedger` ou qualquer contrato estável. Havendo incompatibilidade, interromper e exigir autorização explícita de alteração do escopo.

## 3. Modelo de fronteiras — catálogo não outorga autoridade

1. O **template** e a versão só declaram uma configuração elegível, **não** autorização de executar ferramenta, consumir quota, acessar lead ou criar tarefa.
2. **Permissões finais são do sistema dono:** grants, aprovação, isolamento multiempresa e validações do CRM não são inferidos a partir de versões publicadas.
3. **Versionamento:** snapshot material imutável com referência de versão exata; mudança em instrução, ferramentas, objetivo, risco, configurações permitidas, política ou produto gera novo snapshot e exige nova evidência, nunca mutação silenciosa.
4. **Instruções não são código de segurança:** texto que solicita ignorar regras, mudar quota ou executar mutações permanece dado não confiável; promoção não altera `L0` ou políticas.
5. **Avaliação:** versão, dataset sintético versionado, regras fechadas, casos positivos/negativos, revisão e decisão de promoção devem estar coerentemente vinculados ao mesmo manifesto; evidência antiga não valida versão nova.
6. **Estados fictícios:** `RASCUNHO`, `EM_REVISAO`, `ELEGIVEL`, `ATIVO_SIMULADO`, `SUSPENSO`, `DESCONTINUADO` são ilustrações locais, não estados físicos homologados; transições falham fechadas.
7. **Suspensão/rollback:** apenas selecionar snapshot **anteriormente avaliado e elegível**, sob escopo de produto/tenant/risco, sem reaproveitar aprovação material de outro release, nem restaurar ferramenta revogada.
8. **Temporalidade e concorrência:** usar relógio lógico explícito, não automatismos; versionamento in-memory **não demonstra** consistência distribuída, corrida de publicação nem rollback de infraestrutura.
9. **Sem decisão automática de IA:** avaliador sintético não possui poder de homologar; asserções determinísticas não medem qualidade de linguagem nem desempenho real do modelo.
10. **Dados:** somente referências e marcadores fictícios; evitar prompts privados, conhecimento de cliente, dados de lead, tokens, valores reais ou conteúdo bruto no trace.

## 4. Entradas documentais candidatas — NÃO constituem API ou schema homologado

| Grupo | Referências fictícias possíveis | Validação proposta |
| --- | --- | --- |
| Template | `template_ref`, `owner_ref`, `product_ref`, `purpose_ref` | Identidade sintética explícita e tenant/produto compatível |
| Snapshot | `snapshot_ref`, `snapshot_version`, `manifest_digest`, `instruction_version` | Digest estável de material tipado, estado imutável e versão exata |
| Política | `tool_capability_refs`, `risk_class`, `policy_version`, `schema_version` | Não ampliar ferramentas/grants por conveniência ou por texto |
| Avaliação | `evaluation_ref`, `dataset_version`, `rule_set_version`, `reviewer_ref`, `review_valid_until` | Evidência para snapshot/manifesto exatos, sem validade universal |
| Decisão | `promotion_ref`, `decision_code`, `previous_snapshot_ref`, `clock` | Códigos fechados, transição permitida e rollback conservador |
| Rastreamento | `sequence`, `snapshot_ref_hash`, `template_ref_hash`, `event_code` | Sem payload, segredo ou dados pessoais nos eventos |

Não escolher algoritmo de assinatura, hashing para dados privados, storage ou API nessa fase. Uma impressão digital local **não equivale a assinatura, autenticação ou anonimização**.

## 5. Falhas obrigatórias para negar ou bloquear

- Template desconhecido, versão desativada ou manifesto divergente: **negar promoção e não escolher outra versão automaticamente**.
- Evidência de avaliação incompleta, reprovada, expirada, de dataset/regra incompatível ou de outro snapshot: **negar**.
- Instrução adversarial de publicar diretamente, alterar permissão ou contornar revisão: **sem efeito**, mantendo autorização de backend separada.
- Tentativa de aplicar template de outro produto/tenant, risco, finalidade ou principal: **negar** sem revelar metadados alheios.
- Repetição da mesma decisão material: estado estável e sem evento/efeito duplicado; referência repetida com manifesto diferente: conflito.
- Pedido de rollback para snapshot nunca avaliado, revogado, incompatível ou com ferramenta que perdeu permissão: **negar**.
- Mudança de ferramentas, instruções, quota ou risco após a avaliação: **invalidar evidência**, exigir nova versão.
- Traces contendo prompt bruto, segredo sintético ou texto de lead: **reprovar** no laboratório.
- Falha de relógio ou ausência de decisão revisora: manter estado **INDETERMINADO/NEGADO**, não aceitar silêncio como aprovação.

## 6. Cenários unitários FUTUROS U06-01 a U06-12 — NÃO EXECUTADOS

Apenas após autorização de programação e testes F1-06 independente: **12 novos casos + 58 regressões anteriores = 70 cenários planejados**. Nenhum PASS F1-06 é presumido nesta proposta.

| Caso | Cenário sintético | Resultado esperado |
| --- | --- | --- |
| U06-01 | Template conhecido e snapshot íntegro, campos permitidos | Recuperar manifesto fictício imutável, sem privilégios |
| U06-02 | Alterar instrução ou capability após avaliação | Invalidar evidência da versão original |
| U06-03 | Versão desconhecida ou inativa | Negativa sem fallback automático |
| U06-04 | Schema/parâmetro extra ou dados excessivos | Negativa com código seguro |
| U06-05 | Falta/revogação/expiração de avaliação ou revisão | Bloqueio de promoção |
| U06-06 | Versão avaliada para outro produto/tenant/finalidade | Bloqueio e isolamento sem vazamento |
| U06-07 | Versão com evidência válida, decisões explícitas | Transição local para ativo simulado, sem chamada de ferramenta |
| U06-08 | Alteração de ferramenta/política/risco após revisão | Negação e exigência de reavaliação |
| U06-09 | Repetição da mesma decisão de promoção | Idempotência, nenhum evento duplicado |
| U06-10 | Reutilização de `promotion_ref` com manifesto diferente | Conflito tipado, sem nova promoção |
| U06-11 | Suspensão e rollback para snapshot anteriormente elegível vs inelegível | Permitir só o caso legítimo; sem recuperar grants revogados |
| U06-12 | Prompt adversarial e tokens fictícios em entradas | Rastro mínimo sem conteúdo e sem elevação de autoridade |

**Regressões futuras:** preservar os 58 casos F1-01..F1-05, sem editar casos antigos para tornar uma versão nova aparentemente compatível. Meta futura **70/70**, não resultado obtido. Com PHP CLI 8.1+ **preexistente** e autorização executiva separada, comandos candidatos: `php -v`, lint de arquivos PHP e `php tests/Unit/run.php`. Se ausente, documentar **NÃO EXECUTADOS**, sem instalar PHP/Composer ou usar GitHub Actions. Validar hashes do commit exato testado.

## 7. Decisões F106-D01 a D10 — ALTERNATIVAS A RATIFICADAS DOCUMENTALMENTE

| ID | Alternativa A RATIFICADA exclusivamente documentalmente | Alternativa B NÃO adotada |
| --- | --- | --- |
| **F106-D01 — escopo** | Catálogo, snapshots e promoção/rollback **somente simulados** | Publicar agentes operacionais neste lote |
| **F106-D02 — autoridade** | Versão elegível não é grant, permissão, quota ou aprovação CRM | Template libera ferramenta por simples ativação |
| **F106-D03 — versão** | Snapshots imutáveis com digest/versão material exatos | Alterar prompt/ferramenta em versão ativa silenciosamente |
| **F106-D04 — governança** | Estados e revisão explícitos, transição fail-closed | Autoaprovação por IA ou aprovação por silêncio |
| **F106-D05 — avaliação** | Prova sintética vinculada ao manifesto e dataset/regra versionados | Reaproveitar PASS antigo após mudança de material |
| **F106-D06 — segregação** | Produto, tenant, finalidade, risco e principal conferidos | Compartilhar release/grant entre empresas |
| **F106-D07 — reversão** | Suspensão e rollback só para snapshot previamente validado e ainda elegível | Retomar versão revogada ou não homologada |
| **F106-D08 — auditoria/testes** | Trace mínimo + 12 casos U06 e 58 regressões futuras | Registrar prompts privados ou presumir 70 PASS |
| **F106-D09 — tecnologia/custos** | PHP puro, in-memory, sem dependências/infra | LLM, banco, filas, Actions, API real, despesas |
| **F106-D10 — gates** | Ratificação apenas documental; execução e merge de código em atos separados | Aprovação genérica implica deploy/produção |

**F106-D01 a D10: alternativas A RATIFICADAS EXCLUSIVAMENTE DOCUMENTALMENTE em 2026-11-08.** As alternativas B não foram adotadas. A decisão **não autoriza** iniciar programação, testes F1-06, criar branch de código, merge ou deploy.

## 8. Riscos, lacunas e condições de parada

- Os controles são **mockados**: não há catálogo real, assinatura de manifesto, validação de identidade, armazenamento durável, permissão central, deploy/canary, rollback de serviços nem prova semântica de qualidade do LLM.
- AIA-01/02/09 oferecem diretrizes funcionais, **não especificações técnicas homologadas**; qualquer futura integração bilateral CRM/AIA depende de gates ainda abertos.
- O número de testes é meta de laboratório, não substitui avaliação humana, segurança integrada, custo com provedores, benchmark real ou privacidade operacional.
- Se for preciso modificar `AGENTS.md`, `src/Contracts/`, classes anteriores, `main`, dados privados, API externa ou infra, **parar** e submeter recorte adicional em vez de expandir por conta própria.

## 9. Branch e governança futuras — sem autorização executiva

- **Branch base:** `dev/ft01-aia-core-mocks-2026-11-06` após integração F1-05 via PR #88.
- **Branch deste plano:** `docs/ft01-f1-06-catalogo-versoes-avaliacao-mock-2026-11-08`, contendo **somente** esta especificação e ajuste de `STATUS-ATUAL.md`.
- **Branch candidata de código NÃO CRIADA:** `dev/ft01-f1-06-agent-release-mocks-2026-11-08`.
- Se e somente se houver futura autorização executiva específica, superfícies candidatas: `src/MockCatalog/`, `tests/Unit/`, `README.md`, `STATUS-ATUAL.md`; evitar alterar `Domain` e contratos já estabilizados.
- Um PR de código futuro deverá ser **DRAFT da branch filha para dev, SEM MERGE**; validar SHA, lint e regressões apenas quando autorizado e viável; qualquer merge posterior exige decisão própria.
- **Nenhuma autorização** para alterar `main`, CRM/Portal, demais produtos, serviços, GitHub Actions, VPS/Cloudflare/n8n, credenciais, dados pessoais, SQL/ALLOW, custos, deploy ou F1-07.

## 10. Aceite exclusivamente documental

- [x] Responsável **ratificou somente documentalmente** as alternativas A de F106-D01 a D10 em 2026-11-08.
- [ ] Preparar minuta executiva **separada** com arquivos, SHAs, cenários e bloqueios, **ainda sem programação** e sem autorização implícita.
- [ ] Somente autorização executiva distinta permitirá programação e testes F1-06; autorização de merge de código deverá ocorrer em outro ato.
- [ ] Preservar 58 regressões e declarar 12 casos U06 como **propostos, não executados**.
- [ ] Manter R01-G01–G11, CORE/SEG/AUT CRM e demais gates **ABERTOS**, sem declarar homologação de produção.

**Estado efetivo:** F106-D01 a D10 **RATIFICADAS DOCUMENTALMENTE**, sem execução F1-06 autorizada. Próxima decisão independente: avaliação de minuta executiva específica; programação, testes, merge e implantação continuam não autorizados.
