# AIA-R02/R03 — Máquina de Estados, Alçadas, Efeitos e Reconciliação | Proposta documental v0.1

> **Situação:** planejamento documental preliminar; **R02-R03-D01 a D10 ABERTAS** para deliberação do responsável.  
> **Data de referência:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, R02/S01 a S05, R02/R01 e R02/R02 aprovados exclusivamente como diretrizes documentais preliminares.  
> **Restrições:** R01-G01 a G11 permanecem TODOS ABERTOS, em especial G03/G04/G06/G07/G08. CORE/SEG/AUT e demais gates do CRM permanecem intactos. Este documento não autoriza código, ALLOW, SQL, APIs reais, credenciais, testes funcionais, despesas, infraestrutura ou deploy.

## 1. Finalidade

Especificar a **semântica verificável** de uma execução de agente que pode realizar consultas, gerar propostas e solicitar efeitos em um produto Imagine. Separar seis conceitos:

1. **Execução IA (run):** trabalho orquestrado, com etapas e resultados.
2. **Intenção (intent):** operação de negócio exatamente delimitada, ainda não executada.
3. **Aprovação (approval):** decisão humana ou política competente sobre a intenção.
4. **Tentativa (attempt):** invocação técnica de uma ferramenta sob identidade, limites e versão.
5. **Efeito (effect):** alteração ou consulta efetivamente confirmada pelo backend dono.
6. **Uso/custo (usage):** consumo técnico medido, independente de confirmar efeito e da cobrança comercial.

**Regra de precedência:** resposta do modelo, aprovação humana, aceitação em fila e confirmação de entrega de evento **não provam** conclusão de efeito. A fonte da confirmação é o produto proprietário (CRM no piloto).

## 2. Responsabilidades por domínio

| Componente | Tem autoridade para | Não tem autoridade para |
| --- | --- | --- |
| Backend CRM | Autenticar usuários/empresas, autorizar recursos, validar limites e confirmar tarefa/gravação | Aceitar direitos inventados pelo modelo |
| Núcleo AIA-07 | Orquestrar runs/etapas, estados, checkpoints, dependências e tentativas | Confirmar efeitos CRM sem evidência |
| Políticas AIA-08 / CRM | Solicitar, atribuir, registrar e verificar alçada de aprovação | Substituir validação do CRM sobre o recurso |
| Ferramentas AIA-04 | Encaminhar somente operações homologadas, tipadas e autenticadas | Chamar SQL ou API privilegiada geral por improvisação |
| AIA-10 e produto | Medir consumo técnico e conciliar com política comercial do produto | Cobrar créditos novamente por webhook duplicado |
| AIA-11/12 | Auditoria minimizada, contratos/versões/eventos e isolamento | Compartilhar dados entre tenants por padrão |

O CRM mantém seu **motor nativo de automações do cliente**. n8n, se futuramente homologado/licenciado, permanece controlado pela Imagine e não é atalho de superusuário.

## 3. Contratos conceituais de identidade e correlação

Campos **candidatos, não schemas**:
- `run_ref`, `step_ref`, `correlation_ref` e `parent_run_ref` (se houver) identificam fluxo.
- `product_ref`, `tenant_binding_ref`, `principal_ref`, `delegation_ref` e `purpose_ref` remetem a S01/S02 e R02/R02.
- `agent_ref/version`, `tool_ref/version` e `policy_ref/version` fixam contexto das decisões.
- `intent_ref`, `idempotency_ref`, `material_parameters_ref` identificam uma operação com possível efeito.
- `approval_ref` e `approval_policy_ref` relacionam decisão humana, alçada e prazo.
- `attempt_ref` representa cada tentativa de ferramenta, mesmo quando a intenção é a mesma.
- `effect_ref` pertence ao backend CRM que confirma a operação.
- `usage_ref` individualiza métricas de consumo técnico sem equivaler a crédito comercial.

Essas referências não substituem autenticação, autorização, titularidade de recurso nem prova de resultado.

## 4. Máquina de estados da run e de suas etapas

| Estado | Semântica | Condições relevantes |
| --- | --- | --- |
| `RECEBIDA` | Pedido identificado, antes de aceitação final | Não atribuir efeito nem despesa automaticamente |
| `VALIDANDO` | Verifica emissor, empresa, recurso, agente, ferramenta, quota e política | Falha segura; recusar ou suspender |
| `ENFILEIRADA` | Pronta para agendamento com limites | Ainda exige direitos atuais antes das ferramentas |
| `EXECUTANDO` | Etapas ativas | Checkpoints, limites, timeouts e revalidações |
| `AGUARDANDO_APROVACAO` | Intenção sensível depende de alçada | Nenhuma ferramenta de efeito até decisão válida |
| `AGUARDANDO_DEPENDENCIA` | Recurso, validador ou provedor indisponível | Sem fallback permissivo |
| `CONCLUIDA` | Critérios e efeitos necessários foram comprovados | Evidência/resultado de cada etapa de efeito |
| `FALHOU` | Execução não concluiu conforme contrato | Declarar qualquer efeito parcial já confirmado |
| `CANCELADA` | Impede novos passos | Não reverte efeito prévio por inferência |
| `EXPIRADA` | Prazo de execução finalizado | Não reativar sem novo contexto e direito |
| `INDETERMINADA` | Resultado de etapa/efeito não pode ser confirmado | Reconciliação antes de retentar |

Estados representam proposta funcional do contrato AIA-07/S05. Uma run pode ter múltiplas etapas; **run falhou** não significa que todos os efeitos parciais falharam. Consulta posterior deve diferenciar estado global da run e estado individual do effect.

### 4.1 Transições de referência

| Origem | Destino aceito por hipótese documental | Proteções |
| --- | --- | --- |
| RECEBIDA | VALIDANDO | Deduplicação da solicitação e identidade confiável |
| VALIDANDO | ENFILEIRADA ou EXECUTANDO | Direitos e limites verificados |
| VALIDANDO | FALHOU/EXPIRADA | Recusa segura, sem efeito de negócio |
| ENFILEIRADA | EXECUTANDO | Revalidar elegibilidade, revogação e quotas |
| EXECUTANDO | AGUARDANDO_APROVACAO | Proposta material imutável; sem executar ainda |
| EXECUTANDO | AGUARDANDO_DEPENDENCIA | Sem converter ausência de resposta em sucesso |
| AGUARDANDO_APROVACAO | EXECUTANDO | Decisão ainda válida e autorização revalidada |
| AGUARDANDO_APROVACAO | CANCELADA/EXPIRADA/FALHOU | Recusa, revogação ou prazo encerrado |
| AGUARDANDO_DEPENDENCIA | EXECUTANDO | Dependência recuperada e direitos revalidados |
| EXECUTANDO | INDETERMINADA | Efeito possivelmente ocorrido sem confirmação |
| INDETERMINADA | EXECUTANDO/CONCLUIDA/FALHOU | Somente com reconciliação e evidência do produto |
| EXECUTANDO | CONCLUIDA/FALHOU/CANCELADA/EXPIRADA | Persistir evidência de efeitos parciais |

**Vedado por padrão:** modificar estado terminal por callback atrasado sem regra e evidência; trocar empresa/produto durante run; alterar aprovação já decidida para outra intenção; marcar `CONCLUIDA` só porque o LLM declarou sucesso.

## 5. Máquina de estados da aprovação e alçadas

Aprovação é objeto independente da run e da intenção:

| Estado | Significado | Regra |
| --- | --- | --- |
| `CRIADA` | Intenção apresentada para avaliação | Objeto e parâmetros vinculados |
| `PENDENTE` | Aguarda autoridade competente | Não pode executar por decurso de prazo/silêncio |
| `APROVADA` | Aprovador elegível autorizou a intenção vigente | Ainda requer revalidação no momento do efeito |
| `RECUSADA` | Autoridade negou | Bloquear esta intenção |
| `REVOGADA` | Aprovação previamente concedida retirada | Impedir novos efeitos futuros |
| `EXPIRADA` | Prazo encerrado | Exigir nova decisão quando cabível |
| `CANCELADA` | Solicitante/processo retirou proposta | Não reativar sem nova solicitação |

Uma aprovação deve referenciar **produto, tenant, principal, recurso, operação, parâmetros materiais, versão de ferramenta, finalidade, risco/alçada, aprovador, período de validade e intenção específica**. O CRM define papéis/alçadas de suas operações; o núcleo não os inventa.

Mudanças de destinatário de tarefa, texto enviado, valor, recurso, tenant, operação ou ferramenta devem exigir reavaliação da aprovação quando afetarem sua abrangência. Permissão ou contrato revogado prevalece sobre aprovação anterior.

### 5.1 Exemplos de classes de risco (não fixam política CRM)

- **Consulta autorizada:** leitura sem mutação; depende de permissão e finalidade.
- **Sugestão:** resumo/classificação em resposta de IA, não persiste automaticamente.
- **Mutação interna restrita:** gravar resumo ou criar tarefa; requer permissão específica, idempotência e aprovação se definida pelo CRM.
- **Efeito externo/financeiro ou irreversível:** comunicação enviada, mudança de campanha ou gasto; fora do piloto, exige alçada própria e novo contrato.

Não deduzir que uma ação é aprovada só por pertencer à classe de risco baixa.

## 6. Contrato da intenção idempotente

Para operação com efeito, a intenção deve ser vinculada ao escopo:
`produto + tenant + operação + recurso + identidade de intenção + versão/identidade do payload material`.

**Semântica de referência:**
- Mesma intenção e mesma chave, com dados materiais equivalentes: consultar/devolver o mesmo estado/efeito, sem duplicação.
- Mesma chave com parâmetros materialmente divergentes: **conflito**, nunca executar silenciosamente.
- Intenções distintas em empresas diferentes: isolamento de IDs, permissões e resultados.
- Retry após timeout: referenciar intenção original; verificar primeiro estado do efeito no CRM.
- Não presumir entrega exatamente uma vez da rede; a deduplicação deve alcançar o **efeito de negócio no backend**.
- Aprovação é vinculada à intenção, não à tentativa de rede, salvo reaprovação exigida por política.
- Prazo de deduplicação, canonicalização do payload e armazenamento atômico são **decisões técnicas futuras**; nenhum TTL, schema ou algoritmo foi homologado.

**Não gerar nova chave** simplesmente porque o agente reexecutou uma etapa ou porque o callback atrasou.

## 7. Máquina de estados do efeito e fonte de confirmação

| Estado de efeito | Significado |
| --- | --- |
| `NAO_SOLICITADO` | Proposta existe, mas ferramenta não recebeu autorização de invocação |
| `SOLICITADO` | Comando admitido para análise do serviço executor; não confirmado |
| `NEGADO` | Backend do produto recusou a operação, sem confirmação de efeito |
| `PENDENTE` | Backend aceitou processamento assíncrono, sem conclusão |
| `CONFIRMADO` | Backend dono fornece evidência de ação realmente efetivada |
| `INDETERMINADO` | Resposta ausente/inconclusiva após tentativa potencialmente efetiva |
| `COMPENSADO` | Ação de compensação **distinta e autorizada** comprovada pelo dono |

O contrato técnico deverá discriminar **efeito parcial** quando uma run tiver várias intenções. HTTP 200 pode sinalizar recebimento, não persistência; evento de entrega pode sinalizar transporte, não resultado; saída do LLM não é evidência.

A consulta de status pelo CRM precisa ser autorizada por tenant, usuário e recurso, com erros seguros que não revelem registros de terceiros.

## 8. Concorrência, revogação e recuperação

### 8.1 Corridas

Entre elaboração da proposta e execução podem mudar: responsável pelo lead, organização, permissão, plano, saldo, alçada, aprovação e ferramenta publicada. Imediatamente antes do efeito o CRM deve revalidar seu domínio e a IA o contrato aplicável. Se a verificação falhar, **bloquear ou aguardar**, não continuar com contexto antigo.

### 8.2 Timeout

Falha de conexão **não significa ausência de efeito**. Marcar intenção como indeterminada quando aplicável e consultar o CRM via referência idempotente. Evitar reenviar operação até conciliar o resultado e o prazo de retenção da chave.

### 8.3 Cancelamento

Cancelar run suspende passos futuros, mas tarefa já criada continua existindo. Se o CRM autorizar remoção/alteração, isso será **nova operação** com permissão, política e auditoria próprias, não rollback implícito.

### 8.4 Retomada

Checkpoints de execução só restauram progresso, **não permissões expiradas**. Revalidar produto, tenant, solicitante, vínculo, operação, versão, aprovação e quota, respeitando a política de consumo, dados e retenção.

### 8.5 Reconciliação

Uma operação `INDETERMINADA` exige consulta segura da fonte de verdade do produto por `intent/effect_ref` idempotente, com resultado `CONFIRMADO`, `NEGADO`, `PENDENTE` ou continuação da indeterminação. Ausência de evidência não gera sucesso nem autoriza novo efeito sem política. Casos não conciliáveis devem ter escalonamento humano, vencimento e registro de incidente.

## 9. Eventos, trilha e custos

Envelope de evento **conceitual**: `event_ref`, `schema_version`, `occurred_at`, `producer_ref`, `product_ref`, `tenant_binding_ref`, `correlation_ref`, `run_ref`, `step_ref`, `intent_ref`, `approval_ref`, `attempt_ref`, `effect_ref`, `status`, `policy_version_ref` e payload mínimo protegido.

Contratos de consumidores devem prever repetição e desordem de eventos, replay malicioso, revogação de canais e compatibilidade de versão. Não enviar callbacks para URL fornecida pelo agente/usuário sem homologação.

**Separar três contabilizações:**
1. Tentativas e uso técnico de IA, inclusive retries de inferência realmente custosos.
2. Créditos comerciais do plano CRM, cuja regra é do produto, com deduplicação/reconciliação.
3. Efeitos de negócio CRM, confirmados pelo produto independentemente de consumo de IA.

AIA não emite cobrança ao cliente por callback e não altera saldos do CRM automaticamente sem contrato. Auditoria deve preservar decisões e provas sem copiar prompts/áudios/históricos privados desnecessariamente.

## 10. Exemplos documentais do piloto CRM

### 10.1 Resumo de atendimento

CRM autoriza leitura de atendimento e campos → AIA produz sugestão e marca execução informativa concluída → CRM decide validação e eventual `summary.persist` como **intenção própria** → backend verifica direitos e confirma gravação. Sem confirmação, resumo não pode ser apresentado como registro oficial gravado.

### 10.2 Classificação de lead

Agente propõe score/classificação com versão e justificativa. O resultado é uma **proposta**, não altera cadastro automaticamente. Alteração de classificação oficial exigiria ferramenta de gravação e política CRM que **não são autorizadas por esta proposta**.

### 10.3 Criação de tarefa

CRM valida o lead, o solicitante e destinatário; agente prepara `intent_ref` de tarefa; quando aplicável, aprovação humana permite aquela intenção; CRM revalida e cria tarefa idempotente; retorno traz referência de efeito confirmado. Em timeout, consulta de status antes de reenvio.

### 10.4 Suspensão de tenant

Empresa fica suspensa após intenção e antes da ferramenta: novos passos são bloqueados na revalidação. Se tarefa já estiver confirmada, registrar fato e não afirmar exclusão retroativa.

## 11. Casos sintéticos e critérios negativos

| Cenário | Comportamento esperado |
| --- | --- |
| Aprovação humana concedida, token do solicitante revogado | Não executar efeito após revalidação |
| Mesma intenção de tarefa reenviada após falha na rede | Consultar/recuperar efeito existente, sem duplicar |
| Mesma chave idempotente com lead/destinatário diferente | Conflito, sem mutação |
| Callback de evento confirmado chega duplicado | Não gerar segundo efeito nem crédito comercial duplicado |
| Callback de outra organização chega em run existente | Rejeitar e auditar sem revelar dados de terceiros |
| Operação aceita em fila retorna 202/200, mas ainda não foi executada | Manter PENDENTE, nunca CONFIRMADO |
| Aprovação vencida antes do processamento da fila | Bloquear a ação e solicitar nova aprovação quando cabível |
| Provedor fica indisponível durante execução | Aguardar/errar de modo seguro; contabilizar uso técnico real |
| Agent propõe alteração de orçamento de anúncio | Não executar sob escopo do piloto CRM |
| Cancelar run após gravar resumo | Não presumir exclusão do resumo no CRM |
| Ferramenta tentou agir sem permissão do recurso | Backend CRM nega independentemente de autorização AIA |
| Resumo de atendimento contém dados sensíveis não necessários | Minimizar/recusar antes de transmissão ou persistência |
| Resposta LLM “tarefa criada”, sem confirmação CRM | Nunca marcar efeito CONFIRMADO |
| Retry de evento fora da ordem de versão | Reconciliação/rejeição de transição inválida |

**Tabela documental:** não foram executados testes reais; cenários serão critérios de homologação posterior com dados sintéticos.

## 12. Matriz R02-R03-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R03-D01 | Identidade dos objetos | Separar run, step, intenção, aprovação, tentativa, efeito e consumo com referências correlacionadas | Usar um único status livre para tudo |
| R02-R03-D02 | Estados | Máquinas versionadas de run/etapa e efeito, com estados e transições explícitos, inclusive indeterminação | Registrar somente sucesso ou erro |
| R02-R03-D03 | Aprovações | Objeto de aprovação independente, com alçada, ação exata, prazo, recusa e revogação | Aprovação genérica permanente para qualquer ação |
| R02-R03-D04 | Revalidação | Verificar autorização do produto, tenant, recurso, versão e alçada antes de todo efeito e retomada | Confiar somente na checagem inicial |
| R02-R03-D05 | Idempotência | Chave de intenção estável por produto/tenant/operação, detectar conflito de payload e deduplicar efeito | Retry cria tarefa/registro duplicados |
| R02-R03-D06 | Confirmação | Somente backend proprietário comprova efeito; aprovação, HTTP aceito e resposta LLM não bastam | Considerar entregue/aprovado como confirmado |
| R02-R03-D07 | Reconciliação | Timeout ou estado incerto exigem consulta e escalonamento antes de reenviar operações sensíveis | Repetir automaticamente após qualquer falha |
| R02-R03-D08 | Cancelamento | Bloquear próximos passos sem presumir reversão; compensação é nova operação autorizada | Cancelar run desfaz automaticamente tudo |
| R02-R03-D09 | Auditoria/custos | Eventos versionados, trilha mínima e separação de tentativa, efeito, uso técnico e crédito comercial | Logs livres e contagem comercial por callback |
| R02-R03-D10 | MVP e gates | Contratos de resumo/classificação sugeridos e tarefa idempotente do CRM, mantendo todos os gates abertos | Autonomia de escrita ampla e desenvolvimento imediato |

**R02-R03-D01 a D10: ABERTAS para aprovação documental preliminar.** Não foram escolhidos estado físico, banco, event bus, garantia transacional, SQL, framework, endpoints ou TTLs; não há homologação nem autorização de implementação.

## 13. Evidências pendentes e próximos passos

- [ ] Avaliar e aprovar/corrigir R02-R03-D01 a D10.
- [ ] Confrontar estados e alçadas com critérios oficiais e papéis do CRM (sem modificar repositório CRM).
- [ ] Formalizar esquemas e semântica de concorrência, transição terminal, replay, retentativa e recuperação.
- [ ] Documentar owner de `intent/effect`, prova de confirmação e mecanismo de consulta/reconciliação no backend CRM.
- [ ] Definir contratos de aprovação vinculada a parâmetros e revalidação, incluindo alçadas e expiração.
- [ ] Conciliar métricas de uso técnico AIA-10 com regras de créditos do CRM e possíveis preços de provedores.
- [ ] Definir LGPD, retenção, expurgo e proteção de logs/derivados AIA-11 e R02/S04.
- [ ] Elaborar critérios de reprovação eliminatória e casos sintéticos AIA-09.
- [ ] Preservar **R01-G01 a G11 todos abertos**, em especial G03/G04/G06/G07/G08.
- [ ] Preservar **CORE/SEG/AUT e demais gates próprios do CRM abertos**, sem ALLOW, SQL ou autorização cruzada.
- [ ] Autorizar separadamente eventuais códigos, testes funcionais, credenciais, despesas, infraestrutura e deploy.

**Próximo bloco recomendado:** `AIA-R02/R04 — Dados, Auditoria, Retenção e Privacidade nos Fluxos e Efeitos`, sem aprovação automática de fornecedores, políticas jurídicas ou prazo técnico.

**Condições inalteradas:** `main` e PRs de CRM, Portal, Performance AI e Universidade; VPS, Cloudflare, n8n, provedores de IA, contratos pagos, bancos e pipelines.
