# AIA-R02/S05 — Estados, Aprovações, Idempotência e Reconciliação | Proposta documental v0.1

> **Estado:** diretrizes **R02-S05-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06**, exclusivamente preliminares; os gates R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.  
> **Data:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01 e R02/S01 a S04 ratificados exclusivamente documentalmente.  
> **Gates:** R01-G01 a G11 TODOS ABERTOS, particularmente G03, G04, G06 e G08. CORE/SEG/AUT e demais gates do CRM preservados. Nenhum código, ALLOW, SQL, testes reais, infraestrutura, credencial ou deploy autorizado.

## 1. Objetivo

Definir como o núcleo de agentes e o **backend proprietário de cada produto** coordenarão tarefas com efeitos reais, mantendo quatro acontecimentos distintos:

1. **Pedido recebido:** um serviço autorizado solicitou uma tarefa.
2. **Proposta aprovada:** aprovador habilitado autorizou **uma ação específica** dentro de limites e prazo.
3. **Tentativa de execução:** a ferramenta do produto recebeu um comando com idempotência, autorização e parâmetros verificáveis.
4. **Efeito confirmado:** o backend dono da operação informou resultado verificável.

**Não equiparar aceitação do pedido, conclusão de resposta da IA, aprovação humana, entrega do webhook ou HTTP 200 à confirmação de efeito de negócio.** Um comando pode ter sido efetivado mesmo com timeout da resposta; classificá-lo falsamente como falha e repeti-lo pode gerar duplicidade.

## 2. Responsabilidades e fronteiras

| Domínio | Fonte de verdade | Papel proposto |
| --- | --- | --- |
| AIA-07 (núcleo) | Execução IA e etapas de orquestração | Gerar run/step, estado, checkpoint, dependências e pedido de ação |
| AIA-08 (central/aprovações de produto) | Decisão e política de autorização, conforme dono | Registrar proposta, revisão, decisão vinculada e validade |
| AIA-04 (ferramentas) | Contrato de ferramenta e autorização de uso | Validar schema, escopos e risco; invocar backend competente |
| Backend CRM | Lead, atendimento, tarefa e efeito de gravação | Autorizar recurso no momento da ação, deduplicar e confirmar efeito |
| Portal/Performance/Universidade | Seus recursos, contratos e efeitos | Confirmar ações locais sob suas permissões; sem SSO presumido |
| AIA-10/11 | Telemetria técnica, custos, privacidade e rastreabilidade | Correlacionar sem transformar tentativa em sucesso |
| AIA-12 | Contratos de API/eventos e compatibilidade | Definir envelopes versionados, autenticação e reconciliadores |

As automações configuráveis do cliente continuam no motor **nativo do CRM**; n8n não assume poderes de produto nem implementa livremente automações do cliente. O núcleo não tem acesso direto a bancos dos demais projetos.

## 3. Estados de execução do agente

Estados candidatos, conciliados com AIA-07:

| Estado | Semântica | Ações seguras permitidas |
| --- | --- | --- |
| `RECEBIDA` | Pedido autenticado recebido, ainda não aceito para processamento | Verificação inicial e deduplicação |
| `VALIDANDO` | Conferindo identidade, contrato, elegibilidade, risco e quotas | Rejeitar sem efeito externo se requisitos falharem |
| `ENFILEIRADA` | Pedido aceito e aguardando orçamento/capacidade | Cancelamento de novas etapas |
| `EXECUTANDO` | Etapas de IA/ferramentas em processamento | Checagens por etapa, checkpoints e limites |
| `AGUARDANDO_APROVACAO` | Efeito sensível ainda não liberado | Suspender operação; não inferir aprovação por silêncio |
| `AGUARDANDO_DEPENDENCIA` | Serviço/provedor ou verificação indisponível | Pausar com política de timeout e falha segura |
| `CONCLUIDA` | Resultado esperado confirmado segundo contrato | Responder com evidências e estado de efeito |
| `FALHOU` | Falha classificada sem conclusão bem-sucedida; efeitos prévios discriminados | Explicitar efeitos confirmados e remanescentes |
| `CANCELADA` | Nenhuma nova ação será iniciada, sem apagar efeitos anteriores | Registro e eventual compensação autorizada pelo produto |
| `EXPIRADA` | Prazo de execução excedido; não autoriza retomar automaticamente | Reavaliação e reconciliação |
| `INDETERMINADA` | Não foi possível confirmar se determinado efeito ocorreu | Conciliação com a fonte de verdade antes de retry |

Os estados são **propostas de contrato funcional**, não constantes, tabelas ou endpoints implementados. Uma run pode conter várias etapas com efeitos confirmados e outras ainda pendentes; o resultado agregado deve declarar essa diferença.

## 4. Estados de aprovação, distintos da execução

A solicitação de aprovação possui ciclo próprio:
`CRIADA -> PENDENTE -> APROVADA / RECUSADA / EXPIRADA / CANCELADA / REVOGADA`.

A decisão aprovada **não deve ser reescrita para significar execução concluída**. O resultado da ação pertence ao produto executor e deve ser vinculado à aprovação e à run.

Conteúdo mínimo da proposta:
- Produto, tenant, usuário/serviço solicitante, ferramenta/versão e recurso exatos.
- Ação, parâmetros materiais, destinatário e impacto/valor máximo aplicável.
- Justificativa, risco, prazo de validade, política de alçada e aprovador elegível.
- Identificador e versão/hash canônico da proposta, com tratamento seguro de dados.
- Dependências e eventuais custos, sem revelar credenciais nem conteúdo privado excessivo.

Ao modificar parâmetro material, recurso, valor, conteúdo enviado, empresa, ferramenta ou alçada, **nova aprovação** será necessária quando a política determinar. Qualquer aprovação revogada/expirada ou concedida por pessoa sem direito deve impedir execução.

## 5. Idempotência e delimitação da operação

Cada **tentativa de operação com efeito**, especialmente `crm.summary.record` ou `crm.task.create`, deve possuir uma identidade estável de intenção, sob contrato:

- Produto e tenant de origem verificados.
- Operação e versão, recurso, finalidade e referência de aprovação.
- Identidade da intenção/ação, com chave de idempotência estável entre retries equivalentes.
- Identificador/canonicalização do payload material para detectar reutilização indevida da mesma chave com comando diferente.
- Prazo e política de retenção da deduplicação adequados ao risco e ao ciclo de vida, a definir tecnicamente.
- Fonte de verdade do resultado e referência do efeito confirmado.

**Regra:** mesma chave com mesma intenção deve retornar/reconciliar resultado existente, sem recriar efeito; mesma chave com parâmetros materialmente divergentes deve gerar conflito, não executar outra operação silenciosamente. Chaves de tenants diferentes não se confundem.

Não presumir “exactly once” na rede. O objetivo é **efeito de negócio idempotente** sob contrato com o backend de destino, mesmo com entrega ou chamada repetida.

## 6. Fluxo CRM → AIA → aprovação → ferramenta CRM

1. CRM autentica usuário/serviço e autoriza acesso ao lead e finalidade, seguindo R02/S01–S03.
2. CRM solicita a execução com identidade e idempotência do pedido, planos e quotas verificáveis.
3. AIA valida emissor, tenant, agente/versão e contrato; cria ou recupera execução para a mesma intenção.
4. AIA consulta apenas recursos permitidos; o CRM revalida cada consulta no backend.
5. IA produz sugestão de resumo, classificação ou tarefa; a sugestão não modifica CRM.
6. Se a ação exigir aprovação, criar proposta **exata** e aguardar decisão de humano habilitado.
7. Antes de invocar ferramenta, revalidar direitos, alçada, estado contratual, orçamento, dados e aprovação.
8. Adaptador homologado envia a operação com identificador de intenção/idempotência e parâmetros validados.
9. CRM verifica novamente organização, principal, recurso, autorização final e chave de efeito.
10. CRM devolve resultado **confirmado**, **negado**, **pendente** ou **indeterminado**, segundo contrato a homologar.
11. AIA registra estado e referência de confirmação, informando ao CRM e conciliando eventos duplicados/fora de ordem.
12. Em falha de comunicação após envio, consultar fonte de verdade do CRM **antes** de decidir retentar.

**Nota:** criar tarefa, salvar resumo e enviar comunicação externa são operações de risco e escopos diferentes. Esta proposta não autoriza envio de WhatsApp/e-mail, alteração de lead, exclusão, cobrança, orçamento ou campanha.

## 7. Retentativas, concorrência e reconciliação

### 7.1 Retentativa de etapa sem efeito

Uma etapa de inferência sem efeito externo pode ser repetida sob limites técnicos, políticas de privacidade e cotas. Repetir pode gerar nova cobrança técnica: registro e conciliação devem diferenciar tentativa/custo efetivo de operação comercial do cliente.

### 7.2 Retentativa de etapa com efeito

Antes de reenviar ação que pode ter alterado um recurso, verificar idempotência e estado no **backend dono da operação**. Resultado indeterminado impede repetição cega.

### 7.3 Corrida entre eventos

Aprovação, revogação, cancelamento, mudança de plano, reatribuição do lead, execução e confirmação podem ocorrer simultaneamente. Exigir validação na fronteira do efeito e regras de ordenação, identidade de evento e rejeição de versões antigas.

### 7.4 Consistência entre serviços

Não pressupor transação ACID distribuída entre AIA e todos os produtos. Em futura arquitetura técnica, avaliar mecanismos como registro durável do comando, padrões outbox/inbox, reconciliação e compensação específica, sem aprovar tecnologia nesta etapa.

### 7.5 Reconciliador

Uma operação indeterminada precisa ter consulta autorizada de status pelo ID de intenção/efeito no sistema de origem, prazo de tratamento e escalonamento a humano quando não for possível confirmar. A evidência de sucesso deve vir do executor, não da resposta do modelo.

## 8. Cancelamento, compensação, expiração e falha segura

- **Cancelar execução:** impede novos passos; não retroage automaticamente operações concluídas.
- **Expirar aprovação:** bloqueia efeito futuro, sem apagar a decisão histórica de aprovação.
- **Revogar permissão:** bloqueia novas ações na revalidação, mesmo após checkpoint.
- **Cancelar tarefa do CRM:** seria outra operação, com permissão própria e efeito explícito; não é sinônimo de cancelar a run IA.
- **Compensar:** requer comando próprio autorizado pelo backend do produto; não presumir rollback automático do mundo externo.
- **Indisponibilidade do serviço de aprovação/autorização:** estado de espera ou negativa segura; nunca aprovar automaticamente.
- **Retomar execução:** revalidar contrato, tenant, principal, ferramenta, cotas e aprovação; não reutilizar autorização expirada.
- **Fila sem consumidor:** manter prazo e política de expiração a homologar; não processar indefinidamente dados privados.
- **Incidente de duplicidade:** bloquear reenvios, correlacionar efeitos, notificar responsáveis e iniciar reconciliação sem destruir evidências.

## 9. Eventos e evidências de execução

Envelope conceitual:
`event_id`, `event_type`, `schema_version`, `occurred_at`, `correlation_id`, `run_ref`, `step_ref`, `product_ref`, `tenant_ref`, `intent_ref`, `approval_ref`, `effect_ref`, `status` e mínimo de metadados permitidos.

Eventos candidatos:
- `agent.run.received.v1`, `agent.run.state_changed.v1`;
- `agent.approval.requested.v1`, `agent.approval.decided.v1`, `agent.approval.revoked.v1`;
- `agent.tool.intent_created.v1`, `agent.tool.execution_requested.v1`;
- `product.effect.confirmed.v1`, `product.effect.denied.v1`, `product.effect.unknown.v1`;
- `agent.reconciliation.started.v1`, `agent.reconciliation.resolved.v1`.

São **nomes ilustrativos**. Eventos duplicados, atrasados, ausentes ou fora de ordem não devem corromper estados. O produto deve poder consultar o estado permitido sem confiar apenas na entrega do webhook. Verificar identidade e destino de todo callback.

As evidências devem distinguir **custo técnico**, **consumo de crédito comercial** e **efeito de negócio**. Repetição de evento não pode gerar consumo comercial duplicado; faturamento pertence ao CRM/produto.

## 10. Casos negativos e testes documentais

| Caso | Comportamento exigido |
| --- | --- |
| Criar tarefa sofre timeout depois de persistir no CRM | Consultar/reconciliar efeito; não criar segunda tarefa |
| Mesma idempotency key com payload alterado | Gerar conflito e exigir nova intenção/autorização |
| Mesma chave utilizada por tenant diferente | Isolar escopo por tenant e negar associação cruzada |
| Aprovação humana válida, mas usuário perde permissão depois | Revalidar antes do efeito e negar se necessário |
| Aprovação de campanha expira enquanto agente está em fila | Bloquear execução futura |
| CRM cancela run após criação de tarefa confirmada | Cancelar passos futuros; informar que tarefa já existe |
| Callback duplicado de efeito confirmado | Não produzir nova gravação nem dupla contabilização comercial |
| Evento de conclusão chega antes de evento de início | Aplicar regras de versionamento/ordem e reconciliar |
| Backend CRM responde 200 apenas ao aceitar fila | Não declarar efeito de negócio confirmado |
| Ação externa apresenta resultado indeterminado | Bloquear retry cego e escalar reconciliação |
| Agente altera parâmetros entre aprovação e execução | Invalidar aprovação e solicitar revisão humana |
| Falta saldo/plano elegível entre validação e efeito | Bloquear novas ações conforme CRM |
| Agente do tenant A recebe ID do tenant B em callback | Negar, auditar, proteger a existência do recurso |
| Falha no backend de aprovações | Não aprovar por silêncio; aguardar/encerrar com estado seguro |
| Recuperação de checkpoint após suspensão de ferramenta | Não executar ferramenta suspensa sem nova homologação |
| Resultado LLM afirma que a tarefa foi concluída sem confirmação | Não registrar sucesso |

A tabela define cenários de homologação **futura**; nenhum teste funcional real está autorizado ou foi executado.

## 11. Diretrizes R02-S05-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-S05-D01 | Estados | Máquina de estados versionada para run/step, distinguindo efeito confirmado e indeterminado | Estado único “concluído/erro” sem evidências |
| R02-S05-D02 | Aprovação vs execução | Aprovação humana separada da execução, confirmação e seus ciclos de vida | Considerar “aprovado” como “executado” |
| R02-S05-D03 | Idempotência | Identidade estável de intenção por produto/tenant/operação, com conflito de payload e deduplicação de efeito | Retry com nova mutação a cada tentativa |
| R02-S05-D04 | Autorização | Revalidar permissões, alçadas, contrato e aprovação imediatamente antes de cada efeito | Confiar apenas na verificação inicial |
| R02-S05-D05 | Confirmação | Produto dono do recurso fornece evidência oficial do efeito; núcleo não infere sucesso | A resposta da IA ou o webhook confirmam sozinhos |
| R02-S05-D06 | Falha e reconciliação | Estado indeterminado, consulta de resultado, reconciliação e escalonamento antes de retry | Repetir ação automaticamente após timeout |
| R02-S05-D07 | Cancelamento | Cancelar próximos passos sem apagar efeitos já confirmados; compensação exige permissão | Cancelamento desfaz automaticamente operações externas |
| R02-S05-D08 | Eventos | Envelopes com IDs, versão, correlação e deduplicação, sem prometer exactly once | Eventos sem identidade, ordem e controle |
| R02-S05-D09 | Auditoria e custos | Histórico técnico minimizado, distinguir tentativas/efeitos/créditos, conciliar eventos | Logs livres, cobrança por callback duplicado |
| R02-S05-D10 | MVP CRM | Detalhar primeiro resumo/classificação sem mutação e tarefa idempotente sob autorização do CRM | Autonomia ampla e escrita irrestrita desde o piloto |

**R02-S05-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação apenas **documental preliminar**, sem fechar R01-G01 a G11, escolher fila, banco, event bus ou protocolo e sem autorizar código, testes funcionais ou efeitos reais.

## 12. Matriz de dependências e gates de homologação futuros

| Gate aberto | Contrato documental ainda necessário |
| --- | --- |
| R01-G01/G02 | Identidade de serviço/tenant e escopo por recurso (R02/S01–S03) |
| R01-G03 | Estados, idempotência, confirmação, retry, expiração e reconciliação |
| R01-G04 | Alçada, revisão humana, validade e cancelamento de aprovações |
| R01-G05 | Categorias de dados, logs, retenção e expurgo de derivados (R02/S04) |
| R01-G06 | Consumo técnico, custos, créditos comerciais e deduplicação |
| R01-G07 | Casos negativos sintéticos e critérios eliminatórios (AIA-09) |
| R01-G08 | Contratos CRM→AIA→CRM, versionamento, erro e rollback |
| R01-G09 | Licenças e condições efetivas de terceiros/n8n |
| R01-G10 | Arquitetura técnica, filas, persistência, observabilidade e segurança |
| R01-G11 | Definição do MVP, orçamento, aceite e autorização de implementação |

## 13. Critérios de aprovação documental e próximos passos

- [x] R02-S05-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Conciliar semântica de estados e aprovação com AIA-04/07/08/10/12 e contrato CRM.
- [ ] Definir critérios futuros de idempotência, validade, concorrência e confirmação de efeito.
- [ ] Definir contrato de reconciliação e limites para estados indeterminados.
- [ ] Harmonizar regras de consumo e cobrança comercial com CRM, preservando direitos do produto.
- [ ] Revisar casos negativos de segurança, LGPD e ferramentas com AIA-09/11.
- [ ] Manter **R01-G01 a G11 todos abertos**; aprovação documental não homologa gates.
- [ ] Manter **CORE/SEG/AUT e demais gates próprios do CRM abertos**, sem concessão de ALLOW nem criação de SQL.
- [ ] Implementação, testes reais, credenciais, custos, servidores e deploy somente após autorização expressa separada.

**Próxima etapa recomendada:** revisão transversal **AIA-R02/R01 — Consolidação de Segurança e Contratos (S01 a S05)**, registrando contradições, critérios faltantes e sequência de homologação. Trata-se de título sugerido para nova revisão documental, **não** de autorização para desenvolvimento.

**Condições imutáveis:** não modificar repositórios, main, PRs ou VPS de CRM, Portal, Performance AI e Universidade; sem infraestrutura, n8n, Cloudflare, ferramentas pagas, contas externas, migrações ou deploy.
