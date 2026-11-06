# AIA-12 — SDK, APIs, Eventos e Contratos com os Produtos Imagine | Proposta funcional v0.1

> **Situação:** proposta exclusivamente documental. **AIA12-D01 a D10 ABERTAS** para avaliação do responsável.  
> **Data:** 2026-11-06. **Produto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-11 aprovados como diretrizes funcionais; arquitetura técnica, autenticação, homologação, custos, implementação e deploy permanecem bloqueados.

## 1. Objetivo

Definir como o núcleo compartilhado Imagine AI Agents se integra a CRM, Portal, Performance AI e Universidade Imagine, com **contratos claros, interfaces estáveis, identidade verificável, permissões por produto/empresa, eventos auditáveis e compatibilidade versionada**, sem transferir a propriedade dos dados ou das regras de negócio.

O módulo AIA-12 é a fronteira técnica/funcional entre os produtos e o núcleo: estabelece propostas de APIs, eventos e **SDKs/adaptadores opcionais**. Nenhum exemplo deste documento é endpoint ativo, especificação de código aprovada ou chamada autorizada.

## 2. Autoridades e separação de sistemas

| Sistema | Responsabilidade mantida | Interação pretendida com AIA |
| --- | --- | --- |
| CRM Imagine | Empresas, usuários, leads, funis, histórico, regras comerciais e motor nativo de automações | Solicitar qualificação/sumarização e consultar resultado; autorizar leitura e criação controlada de tarefas |
| Portal Imagine | Conta do cliente no Portal, contratos, serviços, cobrança, suporte e suas permissões | Solicitar assistência e exibir resultados pertinentes ao serviço contratado |
| Performance AI | Gestão de campanhas, integrações de canais, gastos e aprovação de mídia | Solicitar análise e rascunhos; executar operações somente após validações e autorizações do produto |
| Universidade Imagine | Elegibilidade de clientes ativos, acesso a conteúdos e progresso educacional | Consultar assistente/tutor dentro das regras pedagógicas e de acesso do produto |
| Imagine AI Agents | Catálogo de agentes, orquestração IA, ferramenta homologada, políticas, execuções, métricas e evidências | Receber pedidos autenticados, aplicar seus controles e devolver respostas/estados conforme contrato |

O núcleo **não deve** consultar diretamente os bancos de dados dos produtos, substituí-los ou se tornar um login universal por pressuposição. Integrações entre produtos exigem contratos e autorização específicos; não pressupor SSO entre CRM e Portal.

## 3. Famílias de contratos propostas

| Família | Exemplos conceituais | Condições |
| --- | --- | --- |
| Catálogo e elegibilidade | Consultar agentes/versões permitidos por produto e plano; consultar configurações autorizadas | Filtragem pelo produto, tenant, plano e identidade |
| Execução | Solicitar execução, obter ID, consultar estado, cancelar quando aplicável | Orçamento/escopo, idempotência, autenticação e finalidade |
| Resultados e eventos | Buscar resposta permitida, receber conclusão/falha/pendência por evento | Controle de destinatário, assinatura, ordem e retries |
| Ferramentas do produto | Consulta/alteração de recursos por adaptador homologado | Backend de origem faz autorização final |
| Aprovações | Solicitação, decisão, expiração, revogação e confirmação de execução | Alçadas e autoridade de cada produto; AIA-08 |
| Conhecimento e memória | Referências e eventos de fontes/memórias autorizadas | Políticas AIA-05/06/11; não copiar bancos inteiros |
| Uso e custos | Consumo técnico e evento de conciliação | Produto conserva créditos comerciais e cobrança |
| Administração interna | Homologação, versão, suspensão, incidentes e observabilidade | Somente operadores Imagine autorizados |

**Os nomes e divisões são funcionais.** Os recursos reais, campos físicos e protocolos precisam de versão posterior e aprovação técnica.

## 4. Identidade, autorização e relação de confiança

Cada solicitação deve trazer um contexto **confiável/verificado**:
- Produto emissor homologado e identidade de serviço.
- Identificador canônico da empresa **no âmbito do produto**, com mapeamento seguro, sem confiar em ID declarado no prompt.
- Solicitante autenticado, tipo (humano/serviço) e autorização delegada quando aplicável.
- Agente e versão elegíveis, finalidade, operação/recurso e escopos.
- ID de correlação, requisição, idempotência e janela de validade.
- Plano/cota e autorização de processamento, validados com o domínio proprietário.
- Proveniência de aprovações de ações sensíveis e condições de revogação.

**Medidas propostas:** autenticação forte serviço-serviço, certificados ou credenciais de curto prazo com rotação, escopos mínimos, revisão de audience/issuer, proteção de replay, mapeamento de tenants e validação final no produto de destino. Os mecanismos concretos (mTLS, JWT/OAuth2, assinatura de mensagens etc.) **não estão escolhidos**.

Não aceitar permissão, vínculo de empresa, papel de administrador, URL de callback ou identidade apenas por texto enviado pelo modelo ou navegador.

## 5. Contrato funcional de execução

### 5.1 Solicitação

Campos candidatos: `request_id`, `correlation_id`, `idempotency_key`, `product_ref`, `tenant_ref`, `principal_ref`, `agent_template/version`, `purpose`, `task_type`, `input_reference` ou payload mínimo autorizado, `budget_policy_ref`, `deadline` e `callback_ref` homologado.

Não expor chaves de provedor, prompts internos ou dados privados de outros clientes.

### 5.2 Resposta

Padrão conceitual: aceitar e atribuir `run_id` e status; para operação curta e síncrona, retornar resultado final apenas quando realmente concluído. Para assíncrona, retornar endereço de consulta autorizado/evento ou callback. O produto deve poder consultar o estado sem depender exclusivamente de notificações.

### 5.3 Estados e semântica

Usar estados de AIA-07: recebida, validando, enfileirada, executando, aguardando aprovação/dependência, concluída, falhou, cancelada, expirada ou indeterminada. Mudanças de estado precisam ser explícitas e compatíveis; eventos de sucesso só após confirmação necessária.

### 5.4 Cancelamento

Cancelar significa bloquear novas etapas possíveis; não desfaz ação já confirmada. Resposta deve identificar efeitos concluídos, pendentes e resultado indeterminado. Retomada só com revalidação de direitos e estado.

## 6. Eventos, entrega, replay e idempotência

- Nomear eventos com namespaces e versões, por exemplo `agent.run.completed.v1`, `agent.run.failed.v1`, `agent.approval.requested.v1` e `agent.usage.recorded.v1`, sem presumir contratos definitivos.
- Envelope mínimo: `event_id`, tipo/versão, `occurred_at`, origem, produto, referência de tenant, `correlation_id`, referência da execução, referências de recurso, classificação e payload mínimo autorizado.
- Entrega assíncrona preferencialmente **ao menos uma vez com deduplicação**; não prometer entrega exatamente uma vez.
- Receptores idempotentes e trilha para reconciliar mensagens perdidas, duplicadas, atrasadas ou fora de ordem.
- Retries com limites, expiração, backoff e fila de falhas, sem repetir indiscriminadamente operações externas.
- Não inventar confirmação de execução a partir de entrega do evento: a evidência pertence ao produto executor.
- Webhooks/callbacks: destinos **previamente cadastrados e homologados**, identidade de origem verificável, assinatura/autenticação e proteção contra SSRF/replay.
- Dados pessoais nos eventos devem ser minimizados; preferir referências seguras com consulta posterior autorizada.
- Definir período e política de retenção de eventos por produto e finalidade em AIA-11, ainda sem prazos concretos.

## 7. Contratos de ferramentas e autorização final

1. Núcleo propõe a chamada com ferramenta homologada (AIA-04), justificativa e risco classificado.
2. Política central verifica agente, versão, tenant, recurso, plano, orçamento e escopos.
3. AIA-08 gera aprovação humana vinculada a parâmetros materiais quando necessária.
4. O adaptador autenticado envia pedido ao **backend dono da operação**, com escopo mínimo.
5. Backend de origem revalida empresa, usuário/serviço, recurso, limites, aprovação e regras comerciais.
6. Backend retorna `negado`, `confirmado`, `pendente` ou `indeterminado` (ou estados tipados homologados).
7. Núcleo registra evento e reconcilia efeito; resposta do modelo não substitui confirmação.
8. Repetições usam chave e contrato de idempotência do serviço proprietário.

Especificar tratamento de concorrência e cancelamento entre aprovação, execução e confirmação. Não permitir aos agentes chamar diretamente endpoints privilegiados de CRM/Portal/Performance/Universidade.

## 8. SDKs e adaptadores

**Estratégia recomendada:** contrato API-first, documentação versionada, conformance e **SDKs oficiais somente quando reduzirem esforço/risco de integração**. SDK não substitui política do servidor e não é pré-requisito para todos os produtos.

- Adapter de CRM pode consumir contrato de execução e expor ferramentas CRM específicas, sem fornecer acesso genérico ao PostgreSQL.
- Portal pode integrar por serviço backend, respeitando identidade local e contratos; não herdar autenticação do CRM.
- Performance AI pode ter adaptadores de campanhas autorizados, com ferramentas e alçadas próprias, sem converter a IA em dona da conta de anúncios.
- Universidade pode consultar agentes autorizados sem abrir indevidamente dados de desempenho/curso a outros produtos.
- Linguagem, pacotes, clientes HTTP, geração automática de SDK, frameworks, gateways e infra **a definir em estudo técnico posterior**.

SDKs futuros deverão incluir exemplos com dados fictícios, contratos de erro, versionamento, testes de conformidade e política de descontinuação.

## 9. Erros e compatibilidade

Taxonomia funcional proposta:
- `AUTHENTICATION_REQUIRED`, `FORBIDDEN`, `TENANT_SCOPE_DENIED`.
- `PLAN_NOT_ELIGIBLE`, `QUOTA_EXCEEDED`, `BUDGET_EXCEEDED`.
- `VALIDATION_ERROR`, `VERSION_UNSUPPORTED`, `RESOURCE_NOT_FOUND`.
- `APPROVAL_REQUIRED`, `APPROVAL_EXPIRED`, `APPROVAL_REVOKED`.
- `RATE_LIMITED`, `DEPENDENCY_UNAVAILABLE`, `TIMEOUT`.
- `CONFLICT`, `DUPLICATE_REQUEST`, `RESULT_INDETERMINATE`.

Erro deve trazer código estável, mensagem segura, `correlation_id`, indicativo de retentativa segura ou não, e metadados mínimos. Não expor stack trace, segredos, dados internos ou existência de recursos inacessíveis.

**Versionamento:** contratos publicados imutáveis; mudanças compatíveis documentadas, alterações quebradoras com nova versão, janela de convivência e migração coordenada. `main` dos outros repositórios e interfaces existentes não são alteradas por uma nova versão do contrato em AIA.

## 10. Observabilidade e faturamento

- AIA-10 mantém métricas/consumo técnico e custos do modelo; cada produto é responsável por seus créditos de IA, preços, descontos, contratos e faturas.
- Consumir eventos de uso com identificadores únicos e conciliação para evitar consumo duplicado de créditos em callbacks/retries.
- Valor em dinheiro e consumo em tokens/unidades são registros distintos e versionados; eventos técnicos não devem operar como cobranças automáticas sem contrato.
- Métricas por produto e tenant devem respeitar ACL e minimização.
- Divergência de contagem/custo, orçamento excedido ou fatura inconsistente gera alerta e reconciliação, não ajuste automático arbitrário na cobrança do cliente.
- Consumo da IA não se mistura com o orçamento de mídia do Performance AI, que segue regras próprias.

## 11. Integrações de produto — exemplos funcionais

### 11.1 CRM piloto

Evento do CRM informa atualização de lead autorizado (sem dados excessivos) -> automação nativa do CRM ou operador solicita execução do agente -> núcleo consulta contexto permitido por ferramenta CRM -> retorna qualificação e resumo -> CRM valida e grava histórico conforme sua política -> criação de tarefa, se aplicável, exige ferramenta, permissão, idempotência e confirmação CRM.

AIA não duplica o motor nativo de automações de clientes nem sua central de cobrança/aprovação.

### 11.2 Portal

Cliente solicita orientação sobre serviço ativo. Portal autentica a sessão, verifica contrato e disponibilidade do agente e envia tarefa contextualizada. AIA retorna resposta; Portal controla interface, histórico, direitos de uso e informações comerciais.

### 11.3 Performance AI

O núcleo pode gerar recomendação de campanha; Performance AI mantém o controle sobre contas Google/Meta, orçamento e aprovação do cliente. Uma proposta aprovada ainda depende de execução e confirmação do serviço proprietário.

### 11.4 Universidade

Tutor consulta cursos e progresso autorizados, respeitando acesso restrito a clientes Imagine elegíveis e diretrizes pedagógicas, sem criar provas, notas ou certificados pela integração.

## 12. Segurança, testes e homologação do contrato

Antes de qualquer implementação/rollout real:
- Matriz de identidades, scopes, tenants, recursos e produtos assinada pelos responsáveis.
- Contract tests para APIs, eventos, mudanças de versão e compatibilidade.
- Testes negativos: tenant incorreto, agente sem permissão, contrato cancelado, aprovação vencida, assinatura inválida e replay.
- Testes de falha: timeout após execução, eventos duplicados/atrasados, versão descontinuada, crise em provedor e conflito de crédito.
- Auditoria de privacidade: minimizar payload, evitar vazamentos em callbacks, logs e filas.
- Política de revisão, aprovação e rollback para novas versões dos contratos.
- Conformidade de segurança AIA-11 e critérios do laboratório AIA-09, inclusive gates eliminatórios.
- Revisão do licenciamento de n8n e fornecedores de APIs antes de uso comercial, sem presumir autorização pelo simples caráter interno da operação.

## 13. Entidades conceituais (não são tabelas SQL)

- `product_integrations`: produto e domínio proprietário, finalidade, ambiente e status homologado.
- `integration_contracts`: família, versão, escopos, compatibilidade e responsáveis.
- `integration_identities`: referências de identidades de serviço e política de credenciais.
- `integration_tenant_mappings`: correspondência verificada entre empresa/produto e núcleo.
- `integration_requests`: pedido, idempotência, correlação, contrato e finalidade.
- `integration_callbacks`: destino homologado, assinatura, retries e resultado.
- `integration_events`: envelopes e entrega/deduplicação.
- `integration_conformance`: casos de teste, evidência, homologação e versão.
- `integration_usage_reconciliation`: rastreio e conciliação de consumo.
- `integration_incidents`: falhas de contrato, correção e descontinuação.

Schemas, bancos, frameworks e tecnologias seguem abertos.

## 14. Matriz AIA12-D01 a D10 — propostas de decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA12-D01 | Contratos de integração | API-first com contratos estáveis, versionados, testáveis e documentação oficial | Integrações improvisadas diretamente entre sistemas |
| AIA12-D02 | Propriedade dos dados | Produto conserva autenticação, autorização, dados, regras e efeitos externos; AIA coordena | Núcleo assume bancos e permissões globais |
| AIA12-D03 | Identidade/tenant | Serviço autenticado, tenant/usuário/recurso verificáveis, menor privilégio e revalidação | IDs ou papéis informados pelo prompt bastam |
| AIA12-D04 | Execução | Suportar solicitação síncrona/assíncrona, run ID, consulta de estado e cancelamento controlado | Todas as respostas sem ID/status formal |
| AIA12-D05 | Eventos | Envelopes versionados, deduplicação, retries seguros, assinaturas e reconciliação | Callbacks livres sem autenticação nem controle |
| AIA12-D06 | Ferramentas/aprovações | Operações por adaptadores homologados; aprovação e autorização final no produto | Agentes acessam diretamente APIs privilegiadas |
| AIA12-D07 | SDKs | SDKs/adaptadores oficiais opcionais sobre contratos, conforme necessidade e linguagem | SDK obrigatório e acoplamento rígido de todos os produtos |
| AIA12-D08 | Mudanças | Evolução compatível, versões paralelas, testes de contrato, descontinuação e rollback | Atualização que quebra integrações sem aviso |
| AIA12-D09 | Custos/privacidade | Telemetria técnica segregada; créditos/preços sob domínio de cada produto; payload mínimo | Cobrança e dados indiscriminados numa conta global |
| AIA12-D10 | MVP | Contratos mínimos com CRM: execução, resultado/estado, ferramentas limitadas, aprovação e uso técnico | Integrar todos os produtos e fluxos na primeira entrega |

**AIA12-D01 a D10: ABERTAS — aguardam aprovação funcional expressa do responsável.** Nenhuma linguagem, endpoint, padrão de token, banco, modelo, provedor, preço ou infraestrutura está aprovada.

## 15. Critérios documentais de conclusão e próximos gates

- [ ] AIA12-D01 a D10 aprovadas ou revisadas.
- [ ] Definir e ratificar matriz de responsabilidades por produto/tenant/usuário.
- [ ] Definir identidade/autorização de serviços, revogação, mapeamentos e segregação.
- [ ] Detalhar schemas de contrato, erros, eventos, idempotência, ordem e reconciliação.
- [ ] Concluir avaliação cruzada com AIA-01 a AIA-11.
- [ ] Preparar especificação de MVP, contratos técnicos e plano de homologação para o piloto CRM.
- [ ] Validar licenças, LGPD, provedores, custos, stack, hospedagem e rotas de implantação.
- [ ] Autorizar programação em decisão separada, com PR, testes, controle de branches e deploy condicionado.

**Bloqueios preservados:** apenas documentação; sem programação de produto, alterações em repositórios CRM/Portal/Performance/Universidade, acesso a dados reais, criação de APIs/credenciais, contratação, testes funcionais reais, SQL, infraestrutura ou deploy.
