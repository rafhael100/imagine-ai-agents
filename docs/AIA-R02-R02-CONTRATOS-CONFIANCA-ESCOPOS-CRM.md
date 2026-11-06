# AIA-R02/R02 — Contratos Detalhados de Confiança e Escopos do Piloto CRM | Proposta documental v0.1

> **Estado:** diretrizes **R02-R02-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente como orientações preliminares; todos os gates R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.
> **Data:** 2026-11-06. **Repositório:** rafhael100/imagine-ai-agents.
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05 e AIA-R02/R01 ratificados apenas documentalmente.
> **Bloqueios:** R01-G01 a G11 e todos os gates CORE/SEG/AUT do CRM permanecem ABERTOS. Não há autorização para código, SQL, ALLOW, credenciais, APIs, uso real de dados, testes funcionais, despesas, VPS ou deploy.

## 1. Objetivo e não objetivos

Definir a fronteira de confiança **CRM → núcleo Imagine AI Agents → CRM**, seus contextos de identidade, declarações verificáveis, autorização por recurso/campo e resposta segura. Este recorte aprofunda as diretrizes de S01 (identidade/isolamento), S02 (autenticação), S03 (escopos), S04 (privacidade) e S05 (execução), especialmente as tensões C01, C02, C03, C07 e C08 da revisão AIA-R02/R01.

A proposta não inventa papéis reais do CRM, não cria grants, regras `ALLOW`, tokens, permissões, endpoints ou telas, nem pressupõe que o CRM já possui tais capacidades. A implementação técnica depende de aprovação separada do **projeto CRM** e seus respectivos gates.

**Autoridades invariáveis:** o CRM é fonte de verdade para organização, usuário/serviço, vínculo de lead/atendimento/tarefa, plano e regras de acesso; o núcleo IA valida seu próprio contrato/agent/tool e devolve informações autorizadas. Nenhum dos dois concede automaticamente privilégio do outro.

## 2. Fronteiras e identidade confiável

| Momento | Responsável | Verificação requerida |
| --- | --- | --- |
| Início de ação | Backend CRM | Autenticar usuário ou serviço, organização, plano e permissão da operação no recurso |
| Pedido para IA | Emissor CRM → AIA | Identidade homologada do serviço, destinatário, ambiente, janela de validade e integridade |
| Resolução de tenant | Integração CRM/AIA | Vínculo explícito `CRM tenant ↔ contexto AIA`, escopo e estado verificáveis |
| Pré-execução de agente | AIA | Agent/template e versão, ferramenta, quota, finalidade, risk class, delegação |
| Consulta de lead/histórico | Ferramenta oficial CRM | Reautorização CRM no recurso e nos campos, no momento da consulta |
| Aprovação de ação | Produto/central responsável, conforme contrato | Aprovador, papel, objeto exato, parâmetros, escopo, vigência e revogação |
| Gravação/criação de tarefa | Backend CRM | Revalidar identidade, permissões, elegibilidade, recurso, atribuição, aprovação e idempotência |
| Confirmação/auditoria | Produto executor + AIA | Distinguir pedido, tentativa, negação, pendência, efeito confirmado e indeterminação |

A identidade de quem chama a API não substitui a identidade de quem tem autorização comercial/funcional. Identificadores declarados em prompts, navegador, documentos, caches, filas ou respostas do LLM são **dados**, nunca provas suficientes de direito.

## 3. Contrato lógico de requisição — campos candidatos

Uma mensagem de solicitação conceitual carrega **referências verificáveis** dos seguintes grupos, sem exigir que todos estejam em um token:

**A. Serviço e contrato:** `issuer_ref`, `audience_ref`, `service_subject_ref`, `product_ref=CRM`, `environment_ref`, `contract_version`.

**B. Domínio e delegação:** `crm_org_ref`, `aia_tenant_binding_ref`, `principal_ref`, `principal_kind`, `delegation_ref` quando aplicável; indicação de quem autorizou o serviço agir no contexto.

**C. Operação e política:** `purpose_ref`, `agent_template_ref`, `agent_version`, `tool_ref`, `tool_version`, `operation_ref`, `resource_ref`, `scope_refs`, `policy_version_ref`.

**D. Controle temporal e correlação:** `issued_at`, `not_before`, `expires_at`, `request_id`, `nonce` ou mecanismo equivalente, `correlation_id`; para efeitos, `intent_ref`, `idempotency_key` e `approval_ref` quando exigido.

**E. Dados e custo:** somente payload minimizado ou referências a dados autorizados, `data_classification_ref` e `budget_policy_ref` conforme fluxo. Não encaminhar token de cliente, dados pessoais completos ou credenciais desnecessárias.

**Os nomes são apenas conceitos.** Algoritmo, formato JSON/schema, validação criptográfica, mecanismo de token, headers, endpoint, IDs canônicos e TTL não estão escolhidos. Contratos concretos dependerão de decisão técnica.

## 4. Ordem de validação, com negação por padrão

1. Verificar que o emissor serviço CRM é autenticado, homologado e adequado ao ambiente/audience.
2. Validar integridade, versão, validade, anti-replay e eventual revogação da identidade.
3. Resolver vínculo de organização CRM/AIA; negar ausência, conflito, suspensão ou ambiguidade.
4. Resolver quem solicita e em nome de quem: principal local ou conta técnica com delegação válida e restrita.
5. Confirmar finalidade, agente, versão, recurso e operação solicitada.
6. Intersectar permissões do CRM com limites do produto, plano/quotas, agente, ferramenta, risco, aprovação e políticas.
7. Para leitura, solicitar ao CRM somente recurso/campos minimizados com revalidação final no seu backend.
8. Para mutação, vincular a intenção exata à aprovação vigente e reautorizar no CRM imediatamente antes do efeito.
9. Registrar resultados e erros mínimos com rastreabilidade, sem armazenar conteúdo privado indiscriminadamente.
10. Não realizar fallback para identidade técnica global, tenant padrão ou escopo mais permissivo.

Na dúvida sobre identidade, acesso, expiração, titularidade ou estado comercial: **NEGAR ou AGUARDAR**, nunca executar em modo permissivo.

## 5. Matriz funcional do primeiro piloto — não são escopos ativos

| Operação conceitual | Acesso mínimo | Natureza | Fonte de confirmação |
| --- | --- | --- | --- |
| `crm.lead.read_minimal` | Empresa/principal têm acesso ao lead e aos campos mínimos | Leitura confidencial | Backend CRM confirma leitura autorizada |
| `crm.attendance.read_allowed` | Acesso ao atendimento/histórico permitido | Leitura confidencial | Backend CRM |
| `aia.summary.suggest` | Contexto mínimo autorizado, plano e agente elegíveis | Sugestão sem mutação | AIA entrega saída; CRM decide destino |
| `aia.classification.suggest` | Contexto autorizado e regras de versão | Sugestão sem mutação | AIA entrega proposta; CRM decide regra oficial |
| `crm.summary.persist` | Direito específico de gravação e regras do histórico | Mutação | CRM valida, persiste e confirma |
| `crm.task.create` | Permissão de criar tarefa no recurso/destinatário, idempotência e aprovação aplicável | Mutação | CRM confirma ID de efeito |
| `aia.run.status.read` | Direito de consultar execução daquele produto/organização/solicitante | Consulta de metadados | AIA, sob ACL |
| `aia.usage.technical.read` | Perfil autorizado a consultar uso técnico | Consulta restrita | AIA fornece métricas; CRM conserva cobrança |

Não declarar **qualquer** usuário CRM com acesso integral aos leads, nem presumir que papel de administrador do serviço Imagine confira visão sobre dados privados de clientes. Unidades, funis, lead owner, equipes e níveis de acesso devem ser confrontados posteriormente com a documentação oficial do CRM.

## 6. Vínculo entre tenant, principal, recurso e escopo

- **Tenant:** sempre referenciado pela organização autenticada no CRM; um `tenant_id` passado pelo LLM não troca o escopo.
- **Principal:** humano autenticado ou serviço sob delegação formal, vinculado ao contexto do CRM. Não fundir identidades por e-mail, CNPJ ou mesmo nome.
- **Recurso:** lead e atendimento possuem regras próprias. Direito de ver lead não concede necessariamente acesso ao histórico integral ou a anexos.
- **Campos:** aplicar redução de dados antes do envio ao provedor; identificar campos não elegíveis.
- **Escopos:** cada ferramenta tem capacidade explícita e limites. Nunca incluir curinga equivalente a `*` para o cliente final por conveniência.
- **Delegação:** não pode durar além da autorização original, ser renovada pelo agente nem crescer ao delegar tarefa a outros agentes.
- **Plano:** trial, vencimento, suspensão, crédito e contratação são regras do CRM, não manipuladas arbitrariamente pelo núcleo.
- **Revogação:** alterações de papel, tenant, escopo, plano, contrato, versão ou autorização precisam ser revalidadas antes de novas ações.

## 7. Operação assíncrona e efeitos

### 7.1 Leitura e sugestão

CRM inicia uma `run` com dados mínimos e contexto autenticado; AIA consulta ferramenta CRM, recebe apenas campos autorizados, gera resumo/classificação sugeridos e devolve resultado versionado. AIA não persiste por si só no histórico do CRM.

### 7.2 Persistência de resumo

É uma **operação separada** do processamento de IA. O CRM deve verificar se solicitante/serviço têm direito de registrar o resumo no atendimento, observando histórico, versão e condições de aprovação. Só o CRM confirma a gravação.

### 7.3 Criação de tarefa

AIA propõe intenção e parâmetros materiais. Quando exigida, aprovação humana vincula produto, tenant, lead, responsável, conteúdo, prazo, risco e versão. A ferramenta passa `idempotency_key` estável entre retentativas; o CRM verifica permissões, destinatário e referência de intenção, executa ou recusa e retorna evidência de efeito.

### 7.4 Timeout ou resposta incerta

Se a ferramenta recebeu o pedido, mas a resposta falha, estado **INDETERMINADO** exige consulta/conciliador no CRM. Não criar tarefa repetida presumindo falha. Aprovação humana **não é confirmação** da tarefa.

### 7.5 Cancelamento e revogação

Cancelar run impede passos futuros, não apaga tarefas confirmadas; reverter tarefa exige outra operação autorizada. Revogação de usuário/plano bloqueia novos efeitos mesmo com checkpoint anterior.

## 8. Contrato lógico de erros e auditoria

Categorias candidatas para versão futura:
- Identidade/contrato: `UNTRUSTED_ISSUER`, `AUDIENCE_MISMATCH`, `EXPIRED_CREDENTIAL`, `REVOKED_CREDENTIAL`, `REPLAY_DETECTED`.
- Tenant/recurso: `TENANT_BINDING_DENIED`, `CRM_RESOURCE_FORBIDDEN`, `CRM_FIELD_RESTRICTED`, `PRINCIPAL_NOT_AUTHORIZED`.
- Plano/agente: `AGENT_DISABLED`, `PLAN_NOT_ELIGIBLE`, `QUOTA_EXCEEDED`, `TOOL_SCOPE_DENIED`.
- Aprovações/efeito: `APPROVAL_REQUIRED`, `APPROVAL_EXPIRED`, `IDEMPOTENCY_CONFLICT`, `EFFECT_INDETERMINATE`.
- Dependência: `CRM_UNAVAILABLE`, `VALIDATION_UNAVAILABLE`, com decisão segura de reprocessamento.

Evitar mensagens que revelem a existência de lead de outro tenant, detalhes de infraestrutura, conteúdo de credenciais ou stack trace.

Evidência mínima referenciada: produto, tenant, identidade técnica e delegação auditáveis, agente/versão, finalidade, recurso referenciado, etapa, autorização e aprovação (ou negativa), correlação, intenção/idempotência, estado, efeito confirmado quando houver, consumo técnico e eventos de retenção/expurgo. Respeitar classificação LGPD e não logar integralmente prompts como padrão.

## 9. Casos adversariais para homologação posterior

| Teste documental | Resultado necessário |
| --- | --- |
| Serviço CRM autenticado usa vínculo de tenant desconhecido | Negar execução |
| Mesma organização identificada por CNPJ no Portal e CRM | Não criar vínculo interproduto automático |
| Usuário com acesso ao lead não pode ver mensagens restritas | Filtrar/recusar histórico protegido |
| Operador perde direito enquanto execução está em fila | Revalidar antes de ferramenta; não executar |
| Agente sugere classificação e afirma ter alterado lead | CRM não considerar mutação sem ferramenta e confirmação |
| Serviço tenta criar tarefa como outro usuário sem delegação válida | Negar |
| Repetir criação de tarefa com mesma chave após timeout | Retornar/conciliar efeito existente, sem duplicar |
| Retry com mesma chave e payload material distinto | Conflito, não executar silenciosamente |
| Aprovação antiga aplicada a recurso/tenant alterado | Negar e solicitar nova aprovação se aplicável |
| Consultar `run_id` de outro tenant | Negar e impedir enumeração |
| Falha de validador de token/permissões | Bloquear ou aguardar, jamais fallback permissivo |
| Dados privados aparecem em logs/avaliações de tenant vizinho | Reprovação grave de segurança |
| CRM suspende empresa após geração de sugestão e antes da gravação | Não gravar sem nova revalidação |
| Ferramenta sem homologação tenta chamada privilegiada | Negar |

Os cenários são **propostos**, não provas de teste executado.

## 10. Contratos técnicos e interfaces ainda pendentes

- Definir emissores, audiencia, políticas e credenciais efetivas do serviço CRM/AIA após escolha da arquitetura.
- Confirmar estrutura de papéis, unidades, visibilidade de campos, empresas e recursos com documentação do CRM **sem alterar suas decisões**.
- Definir esquema de payload por operação, tipos de erro, interfaces de consulta de estado, versão e autorização de callback.
- Especificar revogação, validade e política de cache, incluindo condições de indisponibilidade.
- Fixar contrato de idempotência/estado de efeito, garantindo chave estável e reconciliador do CRM.
- Definir política de retenção e LGPD por classe, origem, derivado, logs, backups e eventuais provedores (S04).
- Definir regras de crédito comercial CRM vs consumo técnico AIA, unidades de conciliação e custos (AIA-10).
- Preparar suítes negativas sintéticas no laboratório AIA-09, com critérios eliminatórios e responsável por homologação.
- Resolver conflitos C01–C08 de R02/R01 e gates R01-G01/G02/G03/G04/G05/G06/G07/G08 antes de qualquer integração funcional.
- Nenhum desses pontos fica homologado automaticamente por aprovar esta proposta.

## 11. Diretrizes R02-R02-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R02-D01 | Domínio do contrato | CRM permanece autoridade dos dados/efeitos; AIA executa capacidade limitada por contrato | Núcleo assume administração direta de recursos CRM |
| R02-R02-D02 | Confiança serviço-serviço | Emissor/destinatário/ambiente homologados, integridade, validade e proteção a replay | Aceitar identidade declarada em prompt ou chave global |
| R02-R02-D03 | Identidade do tenant | Mapeamento verificado por produto/organização/finalidade, sem vínculo por e-mail/CNPJ | Mesmos dados cadastrais implicam mesma autorização |
| R02-R02-D04 | Principal e delegação | Referências verificáveis a solicitante, alçada, autorização delegada e revogação | Conta técnica ilimitada age por qualquer funcionário |
| R02-R02-D05 | Permissão por recurso/campo | CRM verifica lead, atendimento, dono/atribuição, campo e operação no momento de cada acesso | Permissão geral da empresa libera histórico integral |
| R02-R02-D06 | Sugestão vs escrita | Resumo e classificação são sugestões; persistir ou criar tarefa exige ferramenta e validação separadas | Resposta do modelo altera registro automaticamente |
| R02-R02-D07 | Aprovação e efeito | Aprovação vinculada a ação exata, revalidação e confirmação oficial CRM | Aprovação ou HTTP 200 equivale a efeito concluído |
| R02-R02-D08 | Execução e idempotência | Run/intent/effect distintos, chave estável, retries seguros e reconciliação após timeout | Toda nova tentativa produz outra tarefa |
| R02-R02-D09 | Privacidade e auditoria | Dados minimizados, ACL em resultados/status/logs, erros seguros e trilha auditável | Logs indiscriminados ou resultados visíveis entre tenants |
| R02-R02-D10 | Limites do piloto | Planejar leitura/sugestão e tarefa controlada no CRM, com contratos técnicos posteriores, sem código agora | Integração ampla e ALLOW já liberados |

**R02-R02-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação exclusivamente documental preliminar, sem homologação técnica, fechamento de gates R01-G01 a G11 ou CORE/SEG/AUT, concessão de permissão CRM, implementação, ALLOW, SQL, testes reais ou deploy.

## 12. Gates e próximos passos

- [x] R02-R02-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Matriz oficial de papéis, autorização e recursos do CRM confirmada pelo produto, preservando CORE/SEG/AUT e outros gates.
- [ ] Contrato lógico de identidade, claims, validação, revogação e mapeamentos especificado, sem criar credenciais.
- [ ] Schemas e erros/versionamento de leitura, sugestão, gravação e tarefa definidos em contrato próprio, sem API real.
- [ ] Máquina de estados, aprovação e idempotência reconciliadas com AIA-04/07/08/10/12.
- [ ] Inventário de dados pessoais e derivados, retenção, terceiros e controles LGPD revisado.
- [ ] Critérios sintéticos de homologação/negação prontos, sem execução de testes reais.
- [ ] R01-G01 a G11 seguem **todos ABERTOS**, com foco em G01/G02/G03/G04/G05/G08.
- [ ] CORE/SEG/AUT e demais bloqueios do CRM seguem **todos preservados**.
- [ ] Autorização futura, separada e explícita para qualquer código, API, SQL, ALLOW, teste funcional, credencial, compra, VPS ou deploy.

**Proposta de próximo bloco:** `AIA-R02/R03` — Máquina de Estados, Alçadas, Efeitos e Reconciliação: detalhamento documental com regras de concorrência e recuperação, sem implementação.

**Proteções inalteradas:** `main` e PRs de CRM/Portal/Performance/Universidade; VPS, Cloudflare, n8n, fornecedores, credenciais e bancos.
