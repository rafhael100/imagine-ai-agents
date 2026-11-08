# AIA-FT01/F1-04 — Catálogo de capacidades e despacho seguro de ferramentas simuladas | Proposta documental v0.1

> **SITUAÇÃO: F104-D01 a D10 (alternativas A) RATIFICADAS DOCUMENTALMENTE em 2026-11-08.** O responsável respondeu **“sim”** à pergunta específica de ratificação das dez decisões documentais. Esta aprovação **NÃO AUTORIZA** branch de código, programação, execução dos testes F1-04, merge de código, implantação ou integrações reais; todos os gates permanecem ABERTOS.
>
> Repositório: `rafhael100/imagine-ai-agents`. Branch **documental**: `docs/ft01-f1-04-catalogo-capacidades-ferramentas-mock-2026-11-08`. Base: `dev/ft01-aia-core-mocks-2026-11-06`.
>
> **Preservações permanentes:** `main` inalterada; gates R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. Sem APIs, HTTP, tokens, bancos, SQL, ALLOW, filas, CRM real, instalações PHP/Composer, GitHub Actions, serviços pagos, VPS/Cloudflare/n8n, dados pessoais, credenciais, deploy, F1-05 ou alteração de outros repositórios.

## 1. Precedência técnica e escopo realmente disponível

O F1-01 entregou modelos de domínio, contratos PHP puros e gateways de **CRM simulado**. O F1-02 acrescentou `SupervisedCoordinator` e `MockSupervisedGateway` para supervisão da intenção, aprovação e reconciliação local; o F1-03 adicionou `MockFaultGateway` e `ReadOnlyTraceReview` para falhas compostas e leitura conservadora de rastros sintéticos.

**F1-01, F1-02 e F1-03 foram integrados somente à branch dev** por PRs #71, #76 e #80, respectivamente. A evidência histórica do F1-03 registra **22/22 arquivos PHP sem erros de sintaxe e 34/34 testes sintéticos PASS** em ambiente PHP 8.4.24 isolado. Essa evidência **não** comprova ACL real, autenticação de serviço, contratos homologados, dados duráveis, conformidade LGPD operacional ou qualquer integração CRM/AIA.

Esta proposta reconcilia AIA-04 (ferramentas/capabilities e autoridade final do produto), AIA-07/08/09 (execução, alçadas e laboratório), R04/S01 e R04/S02 (**catálogo/schema de operações exclusivamente conceituais**) e gates R01/R02/R03. Nenhuma operação mencionada aqui constitui endpoint, permissão ou método real já existente.

**Problema que F1-04 visa simular:** hoje temos intenção, aprovação e efeito fake, mas falta modelar de forma verificável **como uma ferramenta candidata é selecionada, validada por catálogo, limitada por escopo e encaminhada de modo controlado**, sem transformar sugestão da IA ou claims autoafirmados em autorização.

## 2. Recorte recomendado para F1-04 — AINDA NÃO AUTORIZADO PARA CÓDIGO

**Lote candidato:** pequeno catálogo in-memory de ferramentas sintéticas e um despachante em modo laboratório. As regras de contexto/capacidade e validação de parâmetros devem ser exercitadas antes de invocar somente mocks já autorizados dos lotes anteriores.

| Componente conceitual candidato | Comportamento estritamente sintético | Proibição |
| --- | --- | --- |
| `MockToolCatalog` | Descrever ferramentas fictícias com `tool_id`, versão, categoria leitura/sugestão/mutação, campos permitidos e classificação de risco | Sem instalar SDK, descobrir operações por rede ou alegar catálogo homologado |
| `MockCapabilityPolicy` | Avaliar *fixture externa* de concessão simulada associada a tenant, produto, principal, finalidade, recurso e versão; negar ausência, expiração ou incompatibilidade | Não criar login, JWT, ACL real nem aceitar claims fornecidos pelo LLM como prova |
| `MockToolRequestValidator` | Verificar esquema mínimo local, tipo, limites de campo, parâmetros adicionais e versão sob política `fail-closed` | Não afirmar validação de schema homologado em CRM |
| `MockToolDispatcher` | Separar leitura mínima e sugestão sem efeito de mutação fake, exigindo aprovação válida e gateway fake com controle existente | Não enviar HTTP, SQL, ação real, mensagem, campanha, pagamento ou chamada de LLM |
| `MockToolDecisionTrace` | Registrar apenas códigos fechados e referências sintéticas, distinguindo `requested`, `denied`, `approved`, `submitted`, `confirmed`, `unknown` | Não registrar prompts brutos, valores de leads, credenciais, respostas integrais |
| `tests/Unit/tool_dispatch.php` (nome candidato) | 12 novos casos unitários puramente sintéticos, preservando os 34 existentes | Não instalar dependências, rodar Actions ou alegar testes já executados |

Os nomes são **propostas**, sujeitos a revisão de colisão com os contratos atuais antes de qualquer implementação. Priorizar composição das classes F1-01 a F1-03 sem reescrever `SupervisedCoordinator`, `CrmEffectGateway` ou `TenantContext`. Se for necessária alteração desses contratos/modelos existentes, **interromper e pedir extensão formal do escopo**.

## 3. Modelo de fronteiras e propriedade da operação

- **AIA identifica uma capacidade candidata**, mas não outorga permissão. A seleção sugerida pelo modelo, mesmo acompanhada de justificativa, **não** é decisão de autorização.
- **Identidade e concessão em F1-04 são fixtures sintéticas**, injetadas separadamente da entrada não confiável. O sistema real precisará futuramente de emissor, audience, vinculação de tenant, escopo, revogação e prova de autenticidade homologados bilateralmente.
- **O produto dono da informação e do efeito mantém a autorização final em produção.** O catálogo de laboratório não pode contornar a validação do backend CRM nem transformar uma simulação em grant real.
- **Leitura e sugestão:** leituras fictícias devolvem somente campos de uma whitelist de teste; sugestão não invoca gravação.
- **Mutação:** só pode alcançar `MockCrmEffectGateway` / `MockSupervisedGateway` sob intenção aprovada e revisão das regras já existentes, com distinção de resultado indeterminado e sem retentativa automática.
- **Ciclos de vida:** ferramenta ausente/desativada, versão desconhecida, concessão vencida, contexto divergente, material alterado após aprovação ou quota simulada insuficiente geram negativa, sem fallback permissivo.
- **Lógica comercial:** quota e uso simulados **não** substituem autorização, créditos, cobrança ou plano do CRM; não criar motor de automações configuráveis para clientes no AIA.

## 4. Contratos mínimos de laboratório — NÃO são API/schema homologados

Cada chamada candidata carrega apenas valores **fictícios e explicitamente tipados** para exercício do laboratório:

| Grupo | Campos candidatos | Regra sintética |
| --- | --- | --- |
| Catálogo | `tool_id`, `tool_version`, `operation_kind`, `allowed_fields`, `risk_class` | Ferramenta conhecida, versão exata e ativo na fixture |
| Contexto | `product_ref`, `tenant_ref`, `principal_ref`, `purpose_ref`, `resource_ref`, `logical_version` | Escopo completo, coincidência de contexto e versão |
| Requisição | `request_ref`, `intent_ref`, `parameter_schema`, `input_fields` | Entrada mínima tipada; rejeitar valores extras, desconhecidos ou desproporcionais |
| Concessão fake | `fixture_grant_ref`, `capability_ref`, `issued_at`, `expires_at`, `revoked` | Injetada por fixture separada; não confiável como identidade real |
| Decisão fake | `decision_code`, `approval_ref`, `effect_ref`, `evidence_state` | Códigos fechados; não transformar status de execução em confirmação |

O vínculo entre os rótulos OP-01..OP-09 de R04/S02 e as operações do laboratório é **provisório**, para testar a estrutura de contratos sem assumir que essas operações existem. A arquitetura definitiva de schema, autenticação e autorização deverá ser ratificada também pelo proprietário do CRM.

## 5. Critérios de falha segura e auditoria

1. `tool_id` desconhecido, desativado ou versão incompatível → negar sem usar ferramenta substituta por inferência.
2. Conteúdo vindo de prompt, lead, documento ou resposta de ferramenta é **dado não confiável**; não pode criar capability ou aprovação.
3. Concessão de fixture sem contexto/escopo, revogada ou expirada → negar. Nunca tratar o prefixo `SYNTHETIC_` como autenticação.
4. Leitura só devolve campos permitidos; desconhecidos e restritos não são expostos nem em erro.
5. Chamada de sugestão não produz `submit` no gateway fake.
6. Mutação fake exige intenção, chave idempotente, contexto e objeto `Approval` válido para **a mesma** intenção/material e janela temporal.
7. Timeout/estado indeterminado → status mockado e negativa à repetição cega de mutação; conservar comprovante fake independente.
8. Incidentes de isolamento cross-tenant ou função/capability incorreta falham fechados e não revelam recurso de outro tenant.
9. Trace mínimo usa referências sintéticas e códigos fechados; hash sem chave de valores arbitrários não é anonimização legal.
10. Sem chamadas remotas, escrita persistente, mensageria, worker, agendamento, token real ou desembolso.
11. Quota técnica/limite sintético é distinta de créditos/valores comerciais; não faturar nem decrementar contratos reais.
12. Nenhum caso de teste mockado encerra R01-G01 a G11, CORE/SEG/AUT ou habilita `main`.

## 6. Cenários futuros propostos U04-01..U04-12 — NENHUM EXECUTADO

A proposta é **12 novos casos sintéticos + 34 regressões F1-01/F1-02/F1-03**, total de **46 cenários futuros**. São critérios, não evidências.

| Teste | Entrada/condição sintética | Resultado esperado |
| --- | --- | --- |
| U04-01 | Ferramenta de leitura conhecida, concessão fixture compatível, whitelist válida | Leitura fake mínima, sem mutação |
| U04-02 | Sugestão fake com instrução adversarial de executar mutação | Nenhum `submit`, nenhuma aprovação presumida |
| U04-03 | Ferramenta ausente, desativada ou `tool_version` desconhecida | `DENIED`, sem fallback |
| U04-04 | Parâmetro extra, tipo incorreto, campo proibido ou payload excessivo | Negar sem vazar campos |
| U04-05 | Concessão ausente, revogada, expirada ou emitida para outra capability | Negar; contador de writes zero |
| U04-06 | Produto/tenant/principal/finalidade/recurso divergentes | Negar sem revelar o objeto alheio |
| U04-07 | Mutação fake com aprovação válida e mesmo material/contexto | Um único efeito fake, prova sintética |
| U04-08 | Aprovação vencida, revogada ou material alterado após concessão | Nenhuma mutação fake |
| U04-09 | Mesma intenção e chave idempotente; repetição do despacho | Consultar status fake sem segundo submit |
| U04-10 | Chave idempotente reutilizada com material incompatível | Conflito tipado, sem gravação adicional |
| U04-11 | Timeout seguido de falha em status mockado | Indeterminação, sem confirmação e sem retry cego |
| U04-12 | Logs/saída com dados adversariais, token ou marcador sintético | Rastro contém somente códigos/IDs mínimos, sem payload |

**Regressão obrigatória futura:** preservar e reexecutar os **34** casos existentes sem alteração oportunista. Meta `46/46` é uma **hipótese de aceite futuro**, não resultado obtido. Testes somente depois de autorização executiva separada e em ambiente com PHP 8.1+ já disponível; sem instalar PHP, Composer ou usar GitHub Actions. Caso o Codex não disponha de PHP, registrar `NÃO EXECUTADOS`, posteriormente validar o código exato com SHA verificável em ambiente independente autorizado.

## 7. Branch e fluxo futuros — bloqueados até nova autorização

- **Branch dev base:** `dev/ft01-aia-core-mocks-2026-11-06`, contendo F1-01 a F1-03.
- **Branch de código CANDIDATA NÃO CRIADA:** `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`.
- **Branch atual:** somente documentação. Nenhum commit PHP deve ser criado nela.
- Para execução futura: conferir Git autenticado, SHA da base e `main`, ausência de branch homônima/alterações, ler `AGENTS.md`, `STATUS-ATUAL.md`, AIA-04, AIA-07/08/09, R01/R02/R03 e R04/S01–S02, além dos documentos F1-01–F1-04.
- **Superfícies candidatas de código** a ratificar em futura ordem: `src/MockTooling/`, `src/Mocks/`, tipos imutáveis novos em `src/Domain/` somente se indispensáveis, `tests/Unit/`, `README.md`, `STATUS-ATUAL.md`. Preservar `src/Contracts/`, `SupervisedCoordinator`, `ReadOnlyTraceReview`, `AGENTS.md` e os documentos de decisões ratificadas, salvo nova autorização.
- Código futuro deverá ficar em **PR draft filha → branch dev**, sem merge automático. Revisão de escopo, testes reais e autorização de merge **separada**, ainda exclusivamente na dev.
- Sem F1-05, integração real, `main`, deploy, banco, filas ou alterações em outros repositórios.

## 8. Decisões F104-D01 a D10 — RATIFICADAS DOCUMENTALMENTE

| Decisão | Alternativa A — RECOMENDADA para ratificação documental | Alternativa B — NÃO recomendada |
| --- | --- | --- |
| **F104-D01 — finalidade** | Laboratório de catálogo/despacho somente mockado | Conectar ferramentas reais já neste lote |
| **F104-D02 — autoridade** | Capability declarada ≠ permissão; dono CRM autoriza operação real | Tratar sugestão/claim textual como autorização |
| **F104-D03 — catálogo** | Ferramentas fictícias versionadas e tipadas, whitelist fechada | Descoberta dinâmica de endpoints e grants |
| **F104-D04 — escopo** | Fixture injetada separadamente; checar produto, tenant, principal, finalidade e recurso | Aceitar identidade vinda de prompt |
| **F104-D05 — efeitos** | Leitura/sugestão isoladas de mutação fake com Approval e idempotência | Disparar mutações automáticas sem supervisão |
| **F104-D06 — falhas** | Fail-closed para versão, esquema, timeout e concessão inválidos | Fallback permissivo e retry cego |
| **F104-D07 — auditoria** | Trace referencial mínimo e sem dados pessoais | Gravar prompt/lead/token integral |
| **F104-D08 — testes** | Propor 12 novos casos mais 34 regressões, execução somente após autorização | Declarar 46 PASS sem execução |
| **F104-D09 — custo/arquitetura** | PHP puro in-memory, zero infra/Actions/dependências, branch e `main` separados | Introduzir serviços pagos, API real, deploy |
| **F104-D10 — governança** | Ratificação APENAS documental; autorização executiva e merge de código em atos próprios | Aprovação genérica implica execução automática |

**F104-D01 a D10: alternativas A RATIFICADAS DOCUMENTALMENTE em 2026-11-08.** As alternativas B não foram adotadas. Esta ratificação **não permite** criar branch de código, executar Codex ou novos testes, instalar runtime, fazer merge de código, alterar gates ou iniciar F1-05.

## 9. Critérios para aceite documental e próximos atos

- [x] O responsável **ratificou somente documentalmente** as alternativas A F104-D01 a D10 em 2026-11-08.
- [ ] Depois, preparar **minuta executiva separada** com branch, SHA, arquivos, exclusões e 46 testes para avaliação.
- [ ] Somente autorização expressa de execução poderá liberar futura branch de código e Codex.
- [ ] Registrar testes efetivamente rodados, diferenças e regressões antes de solicitar merge específico somente na branch dev.
- [ ] Preservar `main`, infraestrutura, demais repositórios e F1-05 bloqueados; R01-G01 a G11 e CORE/SEG/AUT **ABERTOS**.

**Estado do lote nesta data:** F104-D01 a D10 **APROVADAS DOCUMENTALMENTE**; **F1-04 NÃO AUTORIZADO PARA PROGRAMAÇÃO, TESTES OU MERGE DE CÓDIGO.** Próximo ato: propor e submeter ordem executiva em decisão separada.
