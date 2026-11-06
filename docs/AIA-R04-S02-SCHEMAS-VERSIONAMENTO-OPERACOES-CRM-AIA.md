# AIA-R04/S02 — Schemas, Versionamento e Validações dos Contratos CRM/AIA | Proposta documental v0.1

> **Situação:** proposta de especificação **documental e não executável**; **R04-S02-D01 a D10 ABERTAS** para avaliação do responsável.  
> **Referência:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 funcionais aprovados; AIA-R01, AIA-R02, AIA-R03 e AIA-R04/S01 ratificados **apenas como diretrizes documentais preliminares**.  
> **Gates e restrições:** R01-G01 a G11 **TODOS ABERTOS**; CORE/SEG/AUT e demais gates do CRM **ABERTOS**. Nenhum schema, endpoint, ACL, contrato real, provedor, stack ou payload foi homologado. **Sem código, ALLOW, SQL, criação de API, credenciais, dados reais, execução de testes, instalação, contratações ou deploy.**

## 1. Finalidade, precedência e interpretação

Converter o catálogo conceitual de operações do R04/S01 em **contratos tipados e versionáveis propostos**, ainda sujeitos à concordância **bilateral CRM e AIA**. Separar rigorosamente *esquema descrito*, *contrato aprovado pelo dono do produto*, *implementação autorizada* e *comportamento comprovado por teste*. Uma assinatura ou exemplo neste arquivo **não comprova** suporte no backend CRM.

O piloto futuro permanece CRM → AIA → CRM. O CRM é fonte de verdade de empresas, usuários, lead, atendimento, histórico, recursos, permissões, planos, créditos, tarefas e efeitos. AIA é responsável por agentes, sugestões, execução técnica, correlação, consumo e observabilidade mínima.

**Premissa de segurança:** a autorização final de leitura/mutação pertence ao CRM. O serviço AIA não recebe acesso SQL ou superadmin. A saída de IA é sugestão, não alteração automática.

## 2. Catálogo mínimo de operações — rótulos ainda conceituais

| ID | Nome lógico candidato | Categoria | Dono do dado/efeito | Estado de implementação |
| --- | --- | --- | --- | --- |
| OP-01 | `crm.lead.read_minimal` | leitura filtrada | CRM | NÃO VERIFICADO / NÃO IMPLEMENTADO NESTE ESCOPO |
| OP-02 | `crm.attendance.read_allowed` | leitura de atendimento | CRM | NÃO VERIFICADO |
| OP-03 | `aia.summary.suggest` | sugestão sem efeito | AIA | NÃO IMPLEMENTADO |
| OP-04 | `aia.classification.suggest` | sugestão sem efeito | AIA | NÃO IMPLEMENTADO |
| OP-05 | `crm.summary.persist` | mutação condicionada | CRM | NÃO VERIFICADO |
| OP-06 | `crm.task.create` | mutação idempotente | CRM | NÃO VERIFICADO |
| OP-07 | `aia.run.status.read` | consulta técnica | AIA | NÃO IMPLEMENTADO |
| OP-08 | `crm.intent.effect.status` | consulta/reconciliação | CRM | NÃO VERIFICADO |
| OP-09 | `aia.usage.technical.read` | uso técnico | AIA | NÃO IMPLEMENTADO |

Esses rótulos **não são rotas, funções, nomes de métodos, endpoints ou grants existentes**. Disponibilidade e semântica serão verificadas com o dono do CRM antes de qualquer integração.

## 3. Envelope de identidade, versões e correlação proposto

| Grupo | Atributos lógicos candidatos | Regra obrigatória futura |
| --- | --- | --- |
| Contrato | `contract_name`, `contract_major`, `contract_minor`, `message_kind` | Nenhuma interpretação permissiva de versão desconhecida |
| Origem/destino | `issuer_ref`, `audience_ref`, `environment_ref`, `service_subject_ref` | Conferência criptográfica/identidade homologada por produto e ambiente |
| Empresa/principal | `product_ref`, `tenant_binding_ref`, `principal_ref`, `delegation_ref` | Vinculação oficial, principal verificável e revogação atual |
| Operação | `operation_ref`, `purpose_ref`, `resource_ref`, `fields_scope_ref` | ACL por operação/recurso/campo no backend proprietário |
| Execução | `request_ref`, `correlation_ref`, `run_ref`, `step_ref` | Uma execução não implica mutação |
| Intenção/efeito | `intent_ref`, `idempotency_ref`, `material_params_digest_ref`, `approval_ref`, `effect_ref` | Aprovação é distinta de execução; efeito só confirmado por CRM |
| Contexto temporal | `issued_at`, `expires_at`, `replay_ref`, `policy_version_ref` | Falha segura e revalidação ao retomar de fila |
| Segurança dos dados | `data_class`, `retention_policy_ref`, `source_refs` | Minimização, segregação e custódia homologadas |

**Formato físico permanece indefinido:** não há JSON Schema, OpenAPI, banco, JWT, protocolo, assinatura, algoritmo, TTL nem cabeçalho escolhido. `*_ref` aqui expressa referência lógica, não nomenclatura de coluna, URL ou contrato final.

## 4. Tipos lógicos de mensagem e validações por operação

### 4.1 Leitura mínima do lead — OP-01

**Pré-condições:** fonte CRM verifica tenant, principal, delegação, recurso, finalidade, campos solicitados, vínculos e elegibilidade no momento da leitura.  
**Pedido candidato:** identidade verificada, referência opaca de lead, finalidade, contrato/versão, campos permitidos requisitados, correlação.  
**Resposta permitida:** campos estritamente autorizados, versão/fonte e indicador de restrições; **nunca** conjunto completo por padrão.  
**Falhas obrigatórias:** negação indistinguível para recurso inexistente/de outro tenant quando apropriado; campo não autorizado não chega à IA.

### 4.2 Histórico de atendimento — OP-02

**Pré-condições:** atendimento vinculado ao lead/tenant e ao principal autorizado; política de exposição de mensagens/anexos e classificação.  
**Pedido candidato:** atendimento opaco, finalidade, janela/limite autorizados, projeção de campos e correlação.  
**Resposta permitida:** trechos elegíveis e proveniência sob classificação; nenhuma consulta global a mensagens de outros atendimentos.  
**Falhas obrigatórias:** material de anexo, dado de alta sensibilidade, histórico de outro tenant e vazamento entre permissões devem ser negados/minimizados.

### 4.3 Sugestões de IA — OP-03 e OP-04

**Pré-condições:** origem autorizada, agente/modelo/provedor elegíveis para classe e finalidade, limite de uso e validação de formato/escopo.  
**Saída de resumo:** `suggestion_ref`, `source_refs`, `proposal_version`, texto ou conteúdo permitido, `validation_ref`, `run_ref` e consumo técnico; nenhum `crm_effect_ref` inventado.  
**Saída de classificação:** rótulo/score sugerido e versão de critério, referências de origem autorizada e indicação de limitações; não alterar funil, lead scoring oficial ou responsável.  
**Erros:** formato inválido, provedor incompatível, quota ou classe negada; nunca reutilizar dados do tenant A no B.

### 4.4 Persistência de resumo e criação de tarefa — OP-05 e OP-06

**Pré-condições:** intenção material específica e versionada, operação tipada, dados/lead/atendimento elegíveis, ACL CRM vigente, aprovação exigida pela política se aplicável, idempotência estável e custo/plano elegível.  
**Pedido candidato:** `intent_ref`, `idempotency_ref`, recurso, parâmetros materiais, versão, alçada, correlação e identidade autenticada.  
**Resposta candidata:** `effect_state` e `confirmation_source_ref`; somente incluir `effect_ref` como **CONFIRMADO** após comprovação do CRM.  
**Contrato negativo:** silêncio do aprovador, autorização vencida, idempotência conflituosa, payload distinto ou timeout nunca equivalem a ação aprovada/completa.  
**Compatibilidade:** resumo persistido e tarefa criada são efeitos distintos, ainda que derivem da mesma execução AIA.

### 4.5 Status e reconciliação — OP-07 e OP-08

`aia.run.status.read` retorna somente estado de execução ao solicitante autorizado. `crm.intent.effect.status` consulta o **resultado oficial no CRM** por intenção, sob ACL do recurso; deve distinguir `PENDENTE`, `NEGADO`, `CONFIRMADO`, `INDETERMINADO` sem revelar dados de outro tenant. A consulta precisa representar também casos de ausência de prova, sem presumir inexistência de efeito a partir de timeout.

### 4.6 Uso técnico — OP-09

Retorna unidades/tentativas/capacidades técnicas minimizadas sob tenant, modelo e período autorizados, com correlação de `run` e `attempt`. **Não** representa saldo, fatura ou cobrança comercial. CRM define créditos e seus eventos de conciliação mediante contrato futuro.

## 5. Estados propostos e contratos de transição

| Objeto | Estados lógicos candidatos | Observação obrigatória |
| --- | --- | --- |
| Run | `RECEBIDA`, `EM_EXECUCAO`, `AGUARDANDO`, `CONCLUIDA`, `FALHA`, `CANCELADA` | Run concluída não prova que efeito externo ocorreu |
| Aprovação | `REQUERIDA`, `APROVADA`, `NEGADA`, `EXPIRADA`, `REVOGADA` | Aprovação vinculada à ação exata e versão |
| Tentativa | `PREPARADA`, `ENVIADA`, `RESPOSTA`, `ERRO`, `TIMEOUT` | HTTP de transporte não é efeito |
| Efeito CRM | `NAO_SOLICITADO`, `SOLICITADO`, `PENDENTE`, `NEGADO`, `INDETERMINADO`, `CONFIRMADO`, `COMPENSADO` | CONFIRMADO demanda fonte/prova oficial CRM |
| Consumo técnico | `ESTIMADO`, `OBSERVADO`, `AJUSTADO`, `CONCILIADO` | Projeção não é custo realizado nem crédito CRM |

**Estados são vocabulário candidato**, sujeitos a checagem de conflitos com R02/S05, R02/R03 e contratos reais. Não gerar enum, tabela ou máquina de estados implementada com esta ratificação.

## 6. Famílias de erro e comportamento seguro

| Família | Casos candidatos | Política a homologar |
| --- | --- | --- |
| Autenticação e confiança | `UNAUTHENTICATED`, `ISSUER_DENIED`, `AUDIENCE_MISMATCH`, `TOKEN_EXPIRED` | Rejeitar antes de consultar dados |
| Tenant, recurso e campo | `TENANT_DENIED`, `RESOURCE_DENIED_OR_MISSING`, `FIELD_DENIED` | Não enumerar outros tenants/recursos |
| Política e delegação | `SCOPE_DENIED`, `APPROVAL_REQUIRED`, `APPROVAL_REVOKED`, `PLAN_INELIGIBLE` | Negar ou aguardar; sem fallback permissivo |
| Dados e provedores | `DATA_POLICY_DENIED`, `MODEL_NOT_HOMOLOGATED`, `QUOTA_EXCEEDED` | Não transmitir dados/gerar gasto sem elegibilidade |
| Contrato e estado | `VERSION_UNSUPPORTED`, `VALIDATION_FAILED`, `STATE_CONFLICT` | Falha tipada, auditar, sem autocorreção insegura |
| Idempotência e efeito | `IDEMPOTENCY_CONFLICT`, `EFFECT_PENDING`, `EFFECT_INDETERMINATE` | Conciliação oficial antes de repetir operação sensível |
| Dependências | `DESTINATION_UNAVAILABLE`, `MODEL_UNAVAILABLE`, `INTERNAL_FAILURE` | Sem retry cego; alertas mínimos e controle de prazo |

**Sem decisão de códigos HTTP, número de retries, janela de deduplicação ou formato final de erro.** O contrato futuro precisará impedir exposição de tokens, stack traces, prompts completos ou referências de tenant alheio.

## 7. Versionamento, compatibilidade e alterações de contrato

- **Major:** quebra material de campos, semântica, identidade, autorização, confirmação ou estado; exige nova negociação e aceite CRM/AIA.
- **Minor:** adição compatível explicitamente delimitada e sem ampliação implícita de permissões; validar leitores/consumidores.
- **Correções editoriais:** sem mudar semântica contratual ou aceitação.
- Mensagens/eventos de versão desconhecida devem falhar de modo seguro ou ficar em quarentena, nunca ser interpretados como versão mais permissiva.
- Contratos, agentes, ferramentas, prompts, modelo, provedor, política e evidências precisam de versão correlacionada.
- Aprovação de parâmetros da versão anterior não vale automaticamente para material de outra versão.
- Mudanças relevantes exigirão avaliação de impacto, casos sintéticos de regressão e revisão dos gates apropriados antes de implantação.
- Período de convivência de versões, compatibilidade retroativa, descontinuação, rollback e migração **não estão definidos**.

## 8. Idempotência, ordenação, autorização e erros indeterminados

1. `intent_ref` identifica ação de negócio **independente** das tentativas de rede.
2. `idempotency_ref` estável é protegido pelo CRM na gravação efetiva; AIA deduplica pedidos e eventos tecnicamente.
3. Mesma chave com parâmetros materiais incompatíveis é conflito; não ajustar destinatário, lead ou conteúdo em silêncio.
4. Pedido aceito/colocado em fila não comprova tarefa criada. Resposta do modelo não comprova resumo persistido.
5. Se o CRM pode ter executado e perdeu-se resposta, registrar `INDETERMINADO`, consultar OP-08 e exigir prova antes de retry.
6. Revalidar permissão, suspensão, tenant, alçada, plano e recurso na etapa efetiva, sobretudo após fila/replay.
7. Cancelamento impede novos passos; **não** desfaz registros CRM confirmados. Compensação é operação separada.
8. Duplicidade de callback ou evento de consumo não pode causar nova tarefa ou débito comercial duplicado.
9. Rejeitar eventos atrasados/fora de ordem que reabram acesso ou alterem estado terminal indevidamente.
10. Proteger conteúdo material de aprovação sem copiar payload sensível integral ao log por padrão.

A semântica de concorrência, atomicidade, formato de chave, retenção e prova de deduplicação dependem de contratos técnicos reais e homologação futura. Nenhuma SQL, constraint ou API é autorizada.

## 9. Casos sintéticos para revisão futura (não executados)

| Caso | Oráculo de conformidade futura |
| --- | --- |
| CRM consulta lead de empresa B em solicitação da A | Negação sem enumerar B |
| Usuário pode ler lead mas não telefone/anexo | Campo excluído antes de IA |
| Modelo responde JSON inválido ou texto contraditório | Falha de validação; nenhum efeito CRM |
| Apenas resumo sugerido | Nenhum histórico CRM modificado |
| Tentativa de gravação sem aprovação exigida | Negado; nenhuma mutação |
| Retry de tarefa após timeout de CRM | Reconciliação confirma efeito existente, sem duplicar |
| Chave repetida com outro lead/destinatário | Conflito inequívoco |
| Permissão é revogada com run em fila | Negado na retomada |
| Versão antiga envia campo ampliado | Rejeitar/filtrar sob contrato, sem privilégio novo |
| Callback de tenant diferente declara efeito concluído | Rejeitar e auditar |
| Provedor não homologado para classe privada | Não enviar conteúdo |
| Run concluída, tarefa do CRM indeterminada | Não declarar tarefa CONFIRMADA |
| Evento de custo técnico repetido | Não duplicar crédito comercial CRM |
| Exclusão remove índice mas backup restaura trecho | Impedir disponibilização indevida e conciliar expurgo |
| Consulta técnica por operador Imagine sem ACL sobre lead | Não revelar conteúdo privado |

Os exemplos são **casos propostos**, sem execução, ambiente, evidência de teste, dados reais ou resultado observado.

## 10. Contratos e verificações pendentes com o CRM

**A homologar bilateralmente com responsáveis do CRM, sem alterar seu repositório nesta fase:**

- Operações realmente existentes ou planejadas, schemas oficiais e versões suportadas pelo CRM.
- Matriz CORE/SEG/AUT de principal/serviço, organização, lead, atendimento, campo, função e alçada.
- API oficial de leitura mínima e consulta segura de histórico, com filtros de dados e anexos.
- Fluxos de gravação de resumo versionado e criação de tarefa sob permissões específicas.
- Deduplicação no executor CRM, chave/escopo de intenção, TTL conforme risco e consulta de efeito.
- Erros e códigos de status sem enumeração indevida ou vazamento.
- Revalidação de vínculo, usuário, plano, quota, delegação e suspensão em operação.
- Política de retenção/expurgo por fluxo, responsabilidade e eventual terceiro IA.
- Eventos de uso técnico e conciliação comercial dos créditos do CRM.
- Critérios eliminatórios, donos de evidências e autorizações de laboratório/release.

**Não considerar esta lista uma indicação de que o CRM já possui endpoints correspondentes.** Enquanto não houver contrato bilateral real, `R01-G02`, `G03`, `G05`, `G06`, `G07`, `G08` e `G11` continuam abertos, assim como os demais.

## 11. Prontidão para eventual desenvolvimento controlado — apenas critérios documentais

A possível programação inicial de um núcleo **isolado e sem integração real** precisa de autorização futura expressa contendo:
- branch e repositório corretos, dono técnico, escopo pequeno, arquivos permitidos, limites de alteração e revisão;
- tecnologia mínima escolhida documentalmente e compatibilidade da licença, sem compra automática;
- uso exclusivo de dados sintéticos e ambientes autorizados, sem tokens reais ou acesso CRM/Portal/Performance;
- proibição de APIs externas e alterações de produção enquanto gates correspondentes estiverem abertos;
- contratos de interface **mockados** e rotulados como não homologados; sem simular que CRM real confirmou efeitos;
- testes unitários locais/mocks **apenas se especificamente autorizados** na etapa futura; testes funcionais e integrações reais separados;
- critérios de saída, documentação de resultados, revisão, plano de reversão e bloqueio de merge/deploy conforme alçada;
- gates R01-G01–G11 e CORE/SEG/AUT mantidos no estado oficial até decisão própria, sem criar exceções implícitas.

**Este documento não constitui a autorização.** Uma proposta de fast-track pode ser formulada depois do comparativo de stack inicial e dos critérios acima, com escopo negativo explícito.

## 12. Matriz R04-S02-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R04-S02-D01 | Natureza do schema | Aprovar **modelo lógico tipado e versionado como documento preliminar**, sem criar API/schema executável | Tratar texto como contrato técnico já implantado |
| R04-S02-D02 | Identidade e contexto | Envelope com emissor/audience, ambiente, tenant, principal/delegação, finalidade, recurso, versões e correlação | Token/global ID sem validação por recurso |
| R04-S02-D03 | Leitura e minimização | Projetar campos filtrados por ACL CRM, fonte, classificação e finalidade antes de inferência | Importar base/histórico completo para AIA |
| R04-S02-D04 | Resultado de IA | Diferenciar resultados sugeridos de mutações oficiais e validar formato/versão | Resposta LLM grava automaticamente CRM |
| R04-S02-D05 | Mutação/efeito | Gravação de resumo e tarefa exigem intenção, alçada, idempotência, autorização e confirmação CRM | HTTP aceito/aprovação genérica significam efeito |
| R04-S02-D06 | Erros/estados | Categorias tipadas, resultado indeterminado, falha segura e compatibilidade de versões | Erro genérico permite retry cego |
| R04-S02-D07 | Concorrência/reconciliação | Deduplicação no backend CRM e consulta por intenção antes de reenviar ações | Cada timeout gera nova tarefa |
| R04-S02-D08 | Contratos e gates | Schema final, ACL e testes precisam de homologação bilateral e gates individuais | Encerrar gates do CRM pelo PR AIA |
| R04-S02-D09 | Privacidade/auditoria | Dados mínimos, rastreabilidade referencial, retenção por finalidade, proteção de logs | Persistência indiscriminada de prompts e credenciais |
| R04-S02-D10 | Próxima autorização | Avançar para **matriz de stack/custo e recorte de fast-track isolado apenas documental**, pedindo autorização específica depois | Começar código, integrar CRM e instalar infraestrutura automaticamente |

**R04-S02-D01 a D10: ABERTAS**. Recomenda-se ratificação **documental preliminar**, sem códigos, APIs reais, SQL/ALLOW, schemas concretos, contas, testes, compras, deploy ou fechamento de gates.

## 13. Dependências e sequência priorizada

- [ ] Revisar/aprovar R04-S02-D01 a D10 documentalmente.
- [ ] Conciliar modelos de campos, estados e negativas com documentação oficial e responsáveis do CRM.
- [ ] Aprovar documento de comparação da stack mínima e custos `AIA-R03/R02`, sem escolher fornecedor por inferência.
- [ ] Preparar proposta separada para autorização de desenvolvimento de escopo isolado, com branch, limite, revisão e gates explícitos.
- [ ] Após contratos bilaterais específicos, homologar schemas reais/controle de acesso e critérios de teste aplicáveis.
- [ ] Manter R01-G01 a G11 **TODOS ABERTOS**, especialmente G01/G02/G03/G04/G05/G06/G07/G08/G10/G11.
- [ ] Manter CORE/SEG/AUT e demais gates CRM **ABERTOS**, sem tocar seu repositório.
- [ ] Não programar, implementar SQL/ALLOW, emitir credenciais, testar sistemas reais, configurar VPS/Cloudflare/n8n, contratar fornecedores ou fazer deploy.

**Próxima proposta documental recomendada:** `AIA-R03/R02 — Comparativo de Stack, Custos e Critérios para Fast-Track Técnico Isolado`, seguida da proposta formal de autorização restrita de código, **sem autorização automática**.

**Preservações:** `main`, PRs e bloqueios dos repositórios CRM, Portal, Sites, Performance AI e Universidade; nenhum uso de credenciais, dados privados ou infraestrutura sem autorização expressa.
