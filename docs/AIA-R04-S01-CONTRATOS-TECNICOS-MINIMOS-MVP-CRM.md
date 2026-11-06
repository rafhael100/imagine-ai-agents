# AIA-R04/S01 — Contratos Técnicos Mínimos do MVP CRM: Operações, Campos, Erros e Confirmações | Proposta documental v0.1

> **Situação:** diretrizes **R04-S01-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, apenas como contratos conceituais preliminares. R01-G01 a G11 e CORE/SEG/AUT do CRM continuam abertos.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents, com **CRM Imagine como proprietário das operações e dos dados**.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05, AIA-R02/R01–R06 e AIA-R03/S01–S05/R01 ratificados **somente documentalmente**.  
> **Bloqueios:** **R01-G01 a G11 TODOS ABERTOS**; CORE/SEG/AUT e demais gates CRM **ABERTOS**. Não há API, schema, token, permissão real, credencial, código, SQL, ALLOW, teste funcional, dado pessoal real, consumo pago ou deploy autorizado.

## 1. Intenção da proposta

Transformar diretrizes já ratificadas em um **catálogo mínimo de operações e compromissos de interface a negociar e homologar bilateralmente** com o CRM, antes de escolher endpoints e tecnologias ou programar. A proposta não afirma que as operações já existem no CRM.

**Piloto lógico:** CRM envia pedido autenticado ao núcleo Imagine AI Agents; AIA verifica serviço/empresa/principal, limites e agente; uma ferramenta homologada consulta o CRM com permissão final do próprio CRM; AIA devolve resumo/classificação **sugeridos**. Somente contrato futuro, distinto e autorizado, pode persistir resumo ou criar tarefa; o backend CRM confirma o efeito. AIA não executa SQL no CRM e não acessa um banco administrativo.

## 2. Separação de responsabilidades e limites bilaterais

| Domínio | Autoridade | Contrato a homologar |
| --- | --- | --- |
| Organização, vínculo, suspensão, plano e usuários | CRM | Referências autenticadas de empresa, principal e direitos vigentes |
| Recurso lead, atendimento, histórico, campos e anexos | CRM | Leitura filtrada por ACL, finalidade e restrição de campo |
| Agente, versão, modelo, prompt, ferramentas e política IA | AIA | Capacidades homologadas, classificação, quota técnica e saída validada |
| Registro de resumo no histórico de atendimento | CRM | Operação de escrita distinta, autorização final e confirmação de persistência |
| Criação/atribuição de tarefa | CRM | Validação de lead, responsável, conteúdo, prazo, alçada e idempotência no efeito |
| Runs, passos, intenções, tentativas e custo técnico | AIA | Correlação, estado e consumo sob controle de tenant |
| Créditos comerciais, mensalidade e plano | CRM | Regras comerciais, limites e conciliação com uso técnico |
| Dados privados, expurgo, memória e terceiros | Donos de fluxo e custodiantes designados | Minimização, finalidade, retenção, base legal e auditoria |
| Identidade técnica e segurança de comunicação | CRM e AIA em suas fronteiras | Emissor, audience, ambiente, validade, revogação, anti-replay e versão |
| Homologação | Responsáveis técnicos de ambos os produtos | Contratos reais, casos negativos, evidências e aceite separado |

**Não objetivos:** introduzir automações configuráveis pelo cliente em n8n, conectar Portal/Performance/Universidade automaticamente, alterar a política comercial, programar uma integração, definir endpoint ou fechar qualquer gate.

## 3. Operações candidatas do MVP e seus efeitos

Nomes abaixo são **rótulos de contrato lógico**, NÃO endpoints, funções existentes, grants nem APIs liberadas.

| ID | Operação conceitual | Entrada mínima pretendida | Natureza | Saída/efeito | Verificação final |
| --- | --- | --- | --- | --- | --- |
| **OP-01** `crm.lead.read_minimal` | tenant autenticado, principal/delegação, `lead_ref`, finalidade e conjunto permitido de campos | Leitura | Campos autorizados e proveniência/versão de leitura; nunca lead inteiro por padrão | CRM |
| **OP-02** `crm.attendance.read_allowed` | tenant, solicitante, `attendance_ref`, finalidade e filtros | Leitura | Trechos/itens autorizados, com restrição de anexo/campo | CRM |
| **OP-03** `aia.summary.suggest` | referências de dados já autorizados, agente/versão, política e quota | Inferência/proposta | Texto/estrutura sugerida, qualidade/limites e `run_ref`; **sem escrita** | AIA sobre entrada permitida |
| **OP-04** `aia.classification.suggest` | campos autorizados e regra/versionamento da classificação | Inferência/proposta | Classificação/score sugerido, versão/justificativa, sem alteração de lead | AIA; CRM decide uso |
| **OP-05** `crm.summary.persist` | intenção material/versão, `attendance_ref`, conteúdo validado, identidade, idempotência e aprovação se exigida | **Mutação** | ID do histórico e versão **somente após confirmação CRM** | CRM |
| **OP-06** `crm.task.create` | intenção, lead, responsável, conteúdo/prazo, empresa, idempotência e alçada quando aplicável | **Mutação** | ID da tarefa/efeito com estado oficial CRM | CRM |
| **OP-07** `aia.run.status.read` | run_ref e identidade autorizada do produto/tenant | Metadados | Run, step, eventual intenção/efeito em nível permitido, sem dados de terceiros | AIA |
| **OP-08** `crm.intent.effect.status` | intenção idempotente vinculada ao tenant, produto e recurso | Reconciliação | CONFIRMADO/NEGADO/PENDENTE/INDETERMINADO e prova referencial | CRM |
| **OP-09** `aia.usage.technical.read` | escopo autorizado de consumo por produto/tenant/período | Metadados de custo | Unidades técnicas, tentativas, referência de conciliação | AIA, não crédito CRM |

**Nota de compatibilidade:** a lista precisa ser confrontada com documentação oficial do CRM antes de se converter em contrato concreto. AIA não define unilateralmente nomenclaturas, campos obrigatórios, papéis, regimes de planos ou APIs do CRM.

## 4. Envelope lógico de solicitação (não é payload JSON)

**Grupo S — confiança e identidade:**
`issuer_ref`, `audience_ref`, `environment_ref`, `service_subject_ref`, `contract_version`, `crm_org_ref`, `aia_tenant_binding_ref`, `principal_ref`, `delegation_ref` quando aplicável.

**Grupo O — operação/recurso:**
`operation_ref`, `purpose_ref`, `resource_ref`, `fields_requested`, `agent_ref`, `agent_version`, `tool_ref`, `tool_version`, `policy_version_ref`.

**Grupo T — tempo/estado:**
`request_ref`, `run_ref`, `correlation_ref`, `issued_at`, `expires_at`, `replay_protection_ref`; para mutações: `intent_ref`, `idempotency_ref`, `material_parameters_ref`, `approval_ref` quando exigido.

**Grupo D — dados/custos:**
classificação da entrada/saída, referência à fonte autorizada, janela de consumo e política de minimização.

Todos os grupos são **atributos conceituais a definir tecnicamente**. Não estão selecionados JWT, OAuth2/OIDC, mTLS, headers, assinatura, schema, formato ou TTL; não transmitir token do usuário/credencial do CRM à IA por pressuposição.

## 5. Matriz de campos candidatos por finalidade (não substitui a ACL CRM)

| Finalidade | Candidatos condicionais | Evitar/impedir por padrão |
| --- | --- | --- |
| Identificar o lead para resumo | Referência interna, etapa do funil autorizada, histórico textual permitido | Contato completo, anexos integrais, segredos, dados sensíveis desnecessários |
| Resumir atendimento | Mensagens autorizadas, com identificadores reduzidos e proveniência, janela de atendimento elegível | Toda a caixa postal/histórico de outros leads, anexos protegidos |
| Classificar oportunidade | Atributos comerciais e eventos já permitidos por política do CRM | Campos privados de outros funcionários ou empresas |
| Propor tarefa | Lead/atendimento referenciado, responsável elegível, descrição e prazo autorizados | Permitir agente escolher destinatário arbitrário fora das regras CRM |
| Registrar resumo oficial | Conteúdo revisado ou elegível, referência e versão de atendimento, confirmação de alçada | Gravação silenciosa de texto do modelo em histórico oficial |
| Auditar | IDs de run/intent/approval/effect, decisões e versões, timestamps e resultado | Prompts completos, tokens, telefones, mensagens integrais em logs |
| Medir custo | Modelo/versão, unidades técnicas, tentativas e referências por tenant | Detalhes pessoais no registro de faturamento técnico |

**Inventário real de campos permanece PENDENTE.** Os nomes, regras de campos/visibilidade e status de titularidade devem ser harmonizados com a documentação real do CRM e requisitos LGPD; nem todo campo candidato será permitido para todos os usuários.

## 6. Pré-condições por classe

**Qualquer leitura:** identidade de serviço e tenant verificados; principal/delegação atual; recurso pertence à organização; finalidade elegível; política/escopo vigente; filtros por campo; quotas; minimização antes de enviar dados ao modelo. Falha de autorização ou validação de vínculo → negar sem revelar existência de recursos de outra organização.

**Sugestão de IA:** dados já autorizados; modelo/provedor homologado para classe e finalidade; limite de custo e versão; não tratar prompt recuperado como comando; validar formato e indicação de incerteza relevante.

**Mutação CRM:** intenção material distinta da sugestão, ferramenta tipada, permissão atual do principal, recurso e destinatário verificados pelo CRM, aprovação quando aplicável, idempotência estável e prova de efeito. Se condição falhar → NEGADO/AGUARDANDO, nunca fallback superadmin.

**Consulta de efeito:** autorizada por produto/tenant/principal; não enumerar intenções de outra empresa; retornar estado oficial do CRM e metadados mínimos, sem confundir aceite em fila com persistência.

## 7. Contratos de resultado, estados e erros candidatos

**Saída de sugestão:** `run_ref`, `result_version`, `source_refs`, `suggestion_payload_ref` ou conteúdo permitido, `validation_status`, `limits_ref` e `usage_ref`. Não incluir `effect_ref` de CRM como se a sugestão tivesse gravado dados.

**Saída de mutação:** `intent_ref`, `idempotency_ref`, `operation_ref`, `effect_state`, `effect_ref` somente quando confirmado pelo CRM, `confirmation_source_ref`, `correlation_ref`, `policy_version_ref`.

**Estados mínimos de efeito a alinhar com R02/R03:**
- `NAO_SOLICITADO` — existe proposta, mas ferramenta não foi autorizada.
- `SOLICITADO` — backend recebeu comando; não comprova efeito.
- `NEGADO` — backend recusou sem confirmar efeito.
- `PENDENTE` — processamento em curso/aceito assíncronamente.
- `CONFIRMADO` — CRM comprova operação executada.
- `INDETERMINADO` — resultado potencialmente efetivo ainda não demonstrado.
- `COMPENSADO` — operação compensatória **distinta**, autorizada e comprovada.

**Famílias de erro conceituais:**
- `UNAUTHENTICATED`, `UNTRUSTED_ISSUER`, `AUDIENCE_MISMATCH`, `REVOKED`, `EXPIRED`.
- `TENANT_BINDING_DENIED`, `RESOURCE_UNAVAILABLE_OR_FORBIDDEN`, `FIELD_DENIED`, `SCOPE_DENIED`.
- `AGENT_OR_TOOL_NOT_ALLOWED`, `PLAN_NOT_ELIGIBLE`, `QUOTA_EXCEEDED`, `DATA_POLICY_DENIED`.
- `APPROVAL_REQUIRED`, `APPROVAL_EXPIRED`, `IDEMPOTENCY_CONFLICT`, `VERSION_CONFLICT`.
- `DEPENDENCY_UNAVAILABLE`, `RESULT_PENDING`, `EFFECT_INDETERMINATE`, `SCHEMA_UNSUPPORTED`.

Mapeamento de erros para HTTP, códigos de retry, severidade, observabilidade, enumeração de recursos e compatibilidade de versões permanece **não definido**. Em recurso de outro tenant, mensagens não devem revelar sua existência.

## 8. Idempotência, incerteza e confirmação

1. Uma **intenção de negócio** tem referência estável para efeitos equivalentes; retentativa técnica não cria nova intenção.
2. O CRM precisa fornecer mecanismo próprio de deduplicação no **efeito**, não apenas no worker AIA.
3. Mesmo identificador com parâmetros materiais diferentes deve gerar **conflito**, nunca mutação silenciosa.
4. Timeout depois de enviar comando → **INDETERMINADO** quando houver possibilidade de efeito. Consultar OP-08 sob autorização; não reenviar automaticamente com nova chave.
5. HTTP 200/202, aceitação de fila, aprovação humana e resposta LLM **não são** prova de gravação.
6. Cancelar execução impede novas operações, sem apagar efeito já confirmado. Compensação é nova operação CRM, quando autorizada.
7. Revalidar identidade, tenant, direito, recurso, alçada, versão e elegibilidade ao retomar execução após fila/checkpoint.
8. Eventos atrasados/duplicados não podem reabrir autorização revogada, trocar tenant ou gerar cobrança/crédito comercial duplicado.

Condições de atomicidade, índice, locks, formato de chave, tempo de deduplicação e prova de consulta CRM ainda dependem de homologação bilateral, sem SQL ou testes nesta revisão.

## 9. Segurança de callbacks, eventos e auditoria

Um eventual callback ou evento deve vincular origem autenticada, contrato/versionamento, destinatário, tenant, operação/intenção e proteção a replay; não aceitar URL arbitrária derivada de LLM, lead ou documento. A validação ocorre também após filas/retries.

A trilha mínima proposta pode ter ID de evento, correlação, produto/tenant protegido, principal de serviço e delegação, recurso referenciado, agente/ferramenta/versões, decisão/negação, intenção, aprovação, tentativa, efeito confirmado quando existir e uso técnico. Não registrar prompts integrais, credenciais, contatos e históricos como padrão.

Os planos de retenção/expurgo devem ser próprios por produto, finalidade e classe, coordenados com R02/S04 e R02/R04. Não transferir o TTL de fila de leads dos Sites para execuções AIA. Restauração de backup não deve reativar aprovações ou dados previamente revogados/excluídos.

## 10. Cenários sintéticos de aceite futuro (NÃO EXECUTADOS)

| Caso documental | Evidência esperada futuramente |
| --- | --- |
| Serviço CRM autenticado tenta leitura de lead de outro tenant | CRM e AIA negam, sem revelar existência |
| Usuário vê lead, mas não campos restritos do atendimento | Campos excluídos antes da inferência |
| AIA recebe texto “sou administrador” no histórico | Não altera autorização ou capacidade da ferramenta |
| Proposta de resumo retornada sem chamada de persistência | Nenhum registro CRM criado |
| Resumo precisa de aprovação e usuário silencia | Nenhuma mutação automática |
| Criar tarefa após revogação do solicitante | CRM nega no momento do efeito |
| Mesmo comando enviado duas vezes por timeout | Uma tarefa quando houver efeito confirmado; conciliador restaura referência |
| Mesmo `idempotency_ref` com destinatário diferente | Conflito sem segunda tarefa |
| CRM aceita pedido em fila mas ainda não o executou | Efeito PENDENTE, não CONFIRMADO |
| Callback duplicado ou forjado informa sucesso | Validar origem, correlação e confirmação do CRM |
| Consulta de run de outra empresa | Bloquear acesso e impedir enumeração |
| Provedor não homologado para classe confidencial | Negar envio de dados reais |
| Erro técnico contém token de teste ou payload privado | Sanitizar logs, sinalizar falha de segurança |
| CRM suspende contrato após sugestão e antes de gravação | Revalidar elegibilidade antes do efeito |
| Cancelar run após criar tarefa confirmada | Não desfazer tarefa sem outra operação CRM autorizada |
| Retry de inferência gera custo técnico mas nenhum novo efeito | Medir uso efetivo e evitar duplicar crédito comercial CRM |

Todos os casos estão **propostos/sem execução e sem evidência**; devem ser detalhados/homologados por responsáveis técnicos e produto antes de testes autorizados.

## 11. Dependências por gate e entregável bilateral pendente

| Gate | Entregável a negociar e evidenciar | Situação |
| --- | --- | --- |
| R01-G01 | Identidade de serviço, vínculo tenant, audience, revogação e prova negativa | ABERTO |
| R01-G02 | ACL oficial CRM por operação/recurso/campo e revalidação final | ABERTO |
| R01-G03 | Contrato `intent/effect`, idempotência/concorrência/timeout | ABERTO |
| R01-G04 | Aprovação e alçada material com versão e prazo | ABERTO |
| R01-G05 | Inventário, minimização, LGPD, derivados, provedor e expurgo | ABERTO |
| R01-G06 | Custo técnico/quotas e crédito comercial sob CRM | ABERTO |
| R01-G07 | Casos e oráculos sintéticos, evidências observadas e aceite | ABERTO |
| R01-G08 | Operações/erros/schemas/versionamento e confirmação CRM bilateral | ABERTO |
| R01-G09 | Licenciamento e terceiros realmente utilizados | ABERTO |
| R01-G10 | Topologia, segredo, filas, logs, backup e operação | ABERTO |
| R01-G11 | Escopo técnico/financeiro do MVP e autorização por etapa | ABERTO |

**CORE/SEG/AUT CRM e demais gates de implementação permanecem abertos**, sem alteração de repositório CRM por este documento.

## 12. Diretrizes R04-S01-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R04-S01-D01 | Limite de autoridade | CRM mantém ACL, dados e efeitos; AIA gerencia execução/sugestão com contratos limitados | AIA acessa diretamente banco e governa permissões do CRM |
| R04-S01-D02 | Catálogo de operações | Separar leitura mínima, resumo/classificação sugeridos, gravação/tarefa, status e uso técnico | Operação genérica de texto pode executar qualquer ação |
| R04-S01-D03 | Campos e finalidade | Contratos por empresa, principal, recurso, campo e finalidade, com minimização antes da IA | Enviar histórico e lead integralmente por padrão |
| R04-S01-D04 | Identidade e delegação | Exigir emissor, audience, tenant, principal/delegação e versionamento verificados | Confiar em ID enviado em prompt ou token global |
| R04-S01-D05 | Sugestão versus mutação | Resumo e classificação não alteram CRM; persistência e tarefa são operações distintas com autorização final | Saída do LLM altera diretamente o histórico |
| R04-S01-D06 | Estados e erros | Contratos tipados/versão, erros seguros e estados de efeito distintos de entrega/aprovação | HTTP aceito ou resposta textual significa efeito confirmado |
| R04-S01-D07 | Idempotência/reconciliação | CRM comprova efeito e deduplica por intenção; timeout indeterminado exige consulta | Retentar cegamente e gerar nova tarefa |
| R04-S01-D08 | Privacidade e auditoria | Contexto mínimo, trilha referencial, dados derivados sob retenção e ACL; sem logs privados indiscriminados | Copiar todos os prompts, leads e credenciais aos logs |
| R04-S01-D09 | Homologação bilateral | Confrontar proposta com matriz oficial do CRM, aprovar schemas/casos por responsável e preservar gates | Considerar estes nomes APIs reais já homologadas |
| R04-S01-D10 | Próximo recorte | Detalhar versões, schemas e negativas em documentos posteriores, mantendo código/testes/ALLOW/SQL bloqueados | Criar endpoints, permissões e implantar imediatamente |

**R04-S01-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Aprovação exclusivamente preliminar: não homologa APIs, schemas, endpoints, payloads, SQL, ALLOW, contratos executáveis, mudanças no CRM, testes reais, credenciais, infraestrutura, fornecedor ou deploy; R01-G01 a G11 e CORE/SEG/AUT continuam abertos.

## 13. Critérios de saída documental e sequência futura

- [x] R04-S01-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Validar nomes, campos, direitos, fluxo de plano e papéis **com documentação oficial do CRM**, sem alterá-la.
- [ ] Definir versões e formatos de operações e erros, incluindo obrigações da API CRM, com aceite **bilateral** e sem API funcional ainda.
- [ ] Especificar contrato de autorização/revogação e ferramenta de leitura do CRM sob escopo mínimo.
- [ ] Especificar confirmação CRM e idempotência fim a fim de persistência de resumo e tarefa.
- [ ] Conciliar privacidade/retenção/terceiros e medição técnica AIA com produtos, sem dados reais.
- [ ] Detalhar casos sintéticos de autorização negativa, concorrência, efeito e exposição de dados, sem executá-los.
- [ ] Manter **R01-G01 a G11 TODOS ABERTOS** e **CORE/SEG/AUT CRM ABERTOS**.
- [ ] Exigir autorização explícita e separada antes de implementar código, SQL, ALLOW, credenciais, testes reais, contrato com fornecedor, VPS/n8n/Cloudflare ou deploy.

**Próximo bloco documental recomendado:** `AIA-R04/S02 — Schemas e Versionamento de Operações CRM/AIA`, aprofundando contratos tipados, estados, erros e compatibilidade; a matriz de tecnologias e custo AIA-R03/R02 permanece pendente e independente.

**Preservação:** repositórios, PRs e branches `main` de CRM, Portal, Sites, Performance AI e Universidade; servidores Leaseweb, Cloudflare, n8n, banco CRM e dados de clientes não alterados.
