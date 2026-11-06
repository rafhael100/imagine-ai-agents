# AIA-R02/S03 — Matriz de Permissões e Escopos do CRM | Proposta documental v0.1

> **Estado:** proposta documental preliminar; **R02-S03-D01 a D10 ABERTAS** para decisão do responsável.  
> **Data:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01-D01 a D08, R02-S01-D01 a D10 e R02-S02-D01 a D10 aprovados apenas documentalmente.  
> **Gates:** R01-G01 a G11 permanecem TODOS abertos. A documentação **não aprova nem modifica gates CORE/SEG/AUT do CRM**, incluindo decisões pendentes de autorização e isolamento. Não há autorização de código, ALLOW, SQL, testes reais, credenciais ou deploy.

## 1. Objetivo e responsabilidade

Este recorte descreve quais **capacidades do agente de apoio ao CRM** poderão ser propostas na primeira integração CRM → Imagine AI Agents → CRM e sob quais condições: leitura de lead autorizado, consulta de histórico elegível, sumarização, sugestão de classificação e criação **controlada** de tarefa.

**O CRM é a autoridade final** sobre usuários, organizações, unidade/atribuição, leads, tarefas, contratos, planos e histórico de atendimento. AIA coordena execução de inteligência artificial, mas não deve possuir autorização global para consultar leads ou escrever em CRM. O motor nativo de automações do CRM pertence ao CRM; o núcleo IA não o substitui.

O recorte inclui somente **contratos e critérios documentais para futura homologação**, sem prometer que todas as funcionalidades já estejam implementadas ou que os recursos técnicos do CRM estejam liberados.

## 2. Identidades e condições necessárias

Cada solicitação precisa ter referências verificáveis de:
- `crm_org_ref`: organização do CRM autenticada e ativa; não obtida do texto do usuário.
- `crm_principal_ref`: identidade humana ou serviço autorizado, com papel e escopos atuais.
- `crm_resource_ref`: lead, atendimento ou tarefa pertencente à organização e visível ao principal.
- `aia_tenant_binding_ref`: vínculo oficial CRM↔AIA, incluindo finalidade, elegibilidade e estado.
- `agent_template_ref` + versão: agente publicado e habilitado para produto/organização/plano.
- `operation_ref`: ferramenta específica homologada e versão.
- `purpose_ref`: finalidade determinada pelo CRM, dentro do contrato e do escopo.
- `delegation_ref`: autorização limitada e revogável quando um serviço age em nome de um usuário.
- `correlation_id`, `request_id` e `idempotency_key` para rastreio e ações que possam ter efeito.

**Não escolher agora esquema físico dos identificadores, JWT claims, papéis técnicos do CRM ou nomes finais de permissões.** Tudo depende da matriz oficial do produto e de R02/S02 e AIA-12.

## 3. Matriz de operações candidatas ao piloto

| Código lógico (não API) | Operação | Efeito | Condições mínimas | Responsável pelo dado/efeito |
| --- | --- | --- | --- | --- |
| `crm.lead.read` | Consultar dados mínimos de lead autorizado | Leitura privada, R1 | Principal e organização podem ver lead/recurso; campos minimizados | CRM |
| `crm.interaction.read` | Consultar histórico permitido do atendimento | Leitura privada, R1 | Direito de visualizar o atendimento e suas mensagens permitidas | CRM |
| `aia.lead.summarize` | Gerar sugestão de resumo a partir de contexto autorizado | Inferência, sem escrita | Contexto mínimo, finalidade e cota aprovados | AIA gera; CRM decide persistência |
| `aia.lead.classify.suggest` | Propor classificação/score com justificativa | Inferência, sem mutação | Fontes autorizadas, versão, limites e formato esperado | AIA propõe; CRM valida regra comercial |
| `crm.summary.record` | Registrar resumo no histórico do atendimento | Mutação controlada, R2/R3 conforme produto | Direito de registrar nota/resumo, validação no CRM, versão e origem | CRM |
| `crm.task.create` | Criar tarefa vinculada a lead/usuário autorizado | Mutação controlada, R2 ou mais restritiva pelo CRM | Permissão específica, escopos do recurso, destino validado, idempotência e aprovação se requerida | CRM |
| `crm.ai.execution.status` | Consultar estado de execução autorizada | Leitura de metadados | Origem, organização, principal e direito sobre execução | AIA, com filtro do CRM |
| `crm.ai.usage.read` | Consultar consumo permitido da organização | Leitura restrita | Política comercial e perfil no CRM | AIA fornece métrica técnica; CRM rege plano/créditos |

**Esses códigos são placeholders funcionais**, não escopos ativos, endpoints, ALLOW, grants ou autorizações implementadas.

## 4. Papéis e permissão efetiva

**Não inventar papéis definitivos do CRM.** Para planejamento documental, considerar categorias abstratas:
- **Administrador da organização:** administra configurações permitidas pelo CRM, mas não herda acesso a outros tenants.
- **Gestor autorizado:** consulta/gerencia recursos dentro de sua alçada, unidade e escopo.
- **Operador responsável por lead/atendimento:** acessa somente registros de que possua direito.
- **Serviço de automação CRM:** pode solicitar trabalho IA em contexto limitado; não é superadministrador.
- **Administrador Imagine da infraestrutura:** gerencia serviço IA segundo atribuições internas, **sem leitura irrestrita de leads de clientes**.
- **Agente/worker:** executa capacidade específica; não pode ampliar direito do chamador.

A autorização efetiva é a **interseção** de (a) identidade e direito local atual, (b) empresa, unidade/atribuição e recurso, (c) contrato e plano habilitado, (d) finalidade, (e) ferramenta e versão, (f) risco e alçada, (g) limites financeiros/comerciais e (h) validade da delegação e aprovação. Qualquer negativa prevalece sobre o prompt e sobre recomendações da IA.

## 5. Controle de campos e mínimo necessário

Acesso ao lead não equivale a direito de consultar **todos** os seus campos ou exportar todo o histórico.

- O CRM define os campos e anexos elegíveis por caso de uso e perfil; proteger dados pessoais e categorias sensíveis.
- Para resumo/classificação, usar somente trechos/atributos necessários para a finalidade explícita, com filtros antes do envio.
- Resumos gerados são **saídas sugeridas** até registro validado pelo CRM; devem preservar incertezas e não inventar acontecimentos.
- A gravação de resumo no histórico, já contemplada nas diretrizes funcionais do CRM, deve ser autorizada e versionada, com acesso restrito a pessoas habilitadas.
- Não duplicar indiscriminadamente as conversas na memória permanente de AIA-06; manter referências, retenção e minimização.
- Dados de uma organização não podem alimentar respostas, prompts, exemplos ou datasets de outra empresa.
- Compartilhamento Portal/CRM depende de contrato e vínculo específicos; não presumir permissão por CNPJ, e-mail ou marca Imagine.

## 6. Fluxo 1: qualificar e resumir sem mutação automática

1. CRM recebe comando de usuário ou gatilho de automação nativa.
2. CRM autentica organização e principal, verifica recurso e direito da operação.
3. CRM solicita execução ao núcleo pelo contrato R02/S02/AIA-12, com payload mínimo e referência válida.
4. AIA valida o produto emissor, a ligação de tenant, a versão do agente e cotas/limites.
5. AIA solicita contexto via ferramenta específica de leitura; CRM **revalida** o direito no momento da consulta.
6. AIA gera resumo/classificação, com origem/proveniência e incerteza relevantes.
7. Resultado retorna como **sugestão estruturada** ao CRM; o produto decide se exibe, aceita, revisa ou grava conforme política.
8. Registrar resultado técnico, estado, consumo e correlação; nenhuma escrita de lead ou classificação ocorre por simples resposta de IA.

## 7. Fluxo 2: registrar resumo e criar tarefa com efeito

Antes de qualquer alteração no CRM:
1. Determinar operação exata, organização, lead, atendimento, responsável designado e dados materiais.
2. Validar se o principal e a identidade técnica possuem direitos sobre **todos os recursos envolvidos**, inclusive atribuição da tarefa.
3. Definir classificação de risco, necessidade de aprovação e alçada conforme CRM/AIA-08.
4. Gerar chave de idempotência com escopo de empresa, recurso e operação (contrato físico ainda pendente).
5. Executar somente pelo backend CRM, após sua validação final.
6. Receber confirmação explícita, falha ou estado indeterminado com correlação.
7. Em timeout com possível efeito, **consultar estado e reconciliar antes de retentativa**.
8. Não permitir que agente delegue tarefa a usuário externo à empresa, burle visibilidade do lead ou use usuário genérico privilegiado.
9. Registrar no CRM o histórico do efeito conforme permissões e política de retenção local.
10. Após revogação, suspensão ou cancelamento, interromper novos passos; não alegar desfazimento de ações confirmadas.

**Diferenciar:** criar uma tarefa interna (candidata a R2) não é enviar mensagem a lead, modificar funil comercial, redistribuir lead, excluir contato ou disparar campanha. Essas ações exigem **outras ferramentas e decisões**, não estão automaticamente incluídas nesta proposta.

## 8. Plano, consumo e elegibilidade

- O CRM controla a elegibilidade ao uso de IA por plano, empresa, créditos e períodos de faturamento.
- O núcleo AIA registra custos técnicos e consumo por execução/tenant/produto, sem ajustar livremente saldo ou cobrar clientes.
- Requisições sem direito, limite ou saldo aplicável devem ser negadas/pausadas conforme política formal do CRM.
- Cancelamento, suspensão, trial expirado ou troca de plano precisam ser refletidos antes de novas consultas e ações.
- Contabilização deve ter eventos únicos, deduplicação e reconciliação; retries não podem consumir créditos comerciais indevidamente.
- Provedores/modelos/contratos e preços ainda aguardam homologação AIA-03/10.

## 9. Casos negativos obrigatórios para homologação futura

| Cenário documental | Resultado esperado |
| --- | --- |
| Atendente do tenant A solicita lead do tenant B | Negar sem revelar existência/dados de B |
| Usuário possui acesso à organização, mas não ao lead | Negar leitura e resumo daquele recurso |
| Consulta de lead autorizada, mas histórico restrito | Excluir campos/histórico não autorizados |
| Administrador Imagine tenta consultar lead privado por painel técnico | Negar na ausência de direito específico sobre o dado |
| Agente solicita atualização de classificação sem ferramenta aprovada | Retornar sugestão, **não** alterar registro |
| Automação do CRM solicita tarefa após revogação do operador | Revalidar autoridade delegada ou negar |
| Operador solicita tarefa para responsável sem elegibilidade | Negar ou exigir alteração validada no CRM |
| Resumo de IA contradiz histórico oficial | Exigir revisão ou sinalização, não persistir como fato confirmado automaticamente |
| Retry após timeout da gravação de resumo/tarefa | Reconciliar estado, evitar mutação duplicada |
| Plano/cota do tenant expira durante execução | Bloquear novas ações sob política do CRM |
| Callback contém outro tenant ou assinatura inválida | Negar e auditar; não alterar estado comercial |
| Agente tenta acessar diretamente tabelas PostgreSQL do CRM | Negar; usar apenas contrato de ferramenta homologada |
| Mesmo usuário ou CNPJ aparece no Portal | Não reutilizar direitos Portal↔CRM automaticamente |
| Contatos privados são sugeridos para treinamento de outro cliente | Negar e aplicar segregação/LGPD |

**Não executar testes reais agora.** Cenários servem para futura revisão de contrato e aprovação de laboratório AIA-09.

## 10. Contrato de erro, auditoria e estados (conceitual)

Códigos candidatos: `TENANT_SCOPE_DENIED`, `CRM_RESOURCE_FORBIDDEN`, `CRM_ROLE_INSUFFICIENT`, `PLAN_NOT_ELIGIBLE`, `AGENT_NOT_ENABLED`, `TOOL_NOT_HOMOLOGATED`, `APPROVAL_REQUIRED`, `QUOTA_EXCEEDED`, `RESULT_INDETERMINATE`, `DUPLICATE_REQUEST`, `CRM_DEPENDENCY_UNAVAILABLE`.

Os erros devem ser seguros, tipados e correlacionáveis; não expor a outro tenant os detalhes de recursos restritos. O CRM deve poder consultar estado autorizado da execução sem confiar só em webhooks.

Registrar metadados mínimos de:
- produto e tenant (referências verificadas), principal e delegação;
- ferramenta/versão, recurso, finalidade, evento, decisão de autorização;
- aprovação/alçada quando necessária, idempotência, status e efeito confirmado;
- custos técnicos e consumo conciliável;
- retenção, acesso auditável e mecanismos de expurgo conforme AIA-10/11.

A solução física de schemas, log, banco, tokens e transporte não está homologada.

## 11. Limites do MVP e escopo não incluído

**Candidato ao primeiro piloto documental:**
- Consultar dados mínimos de lead e histórico com autorização do CRM.
- Gerar resumo, classificação sugerida e justificativa, sem atualização automática do lead.
- Propor e, sob política específica, criar tarefa controlada e idempotente.
- Disponibilizar status, erros, correlação e consumo técnico ao CRM.

**Não incluído automaticamente:** campanhas Meta/Google, orçamento de mídia, faturamento, cobrança, alteração de plano, exclusão de lead, envio de WhatsApp/e-mail, mesclagem de empresas, importações massivas, execução de automações livres de cliente em n8n, mudança de dono de lead, agente com privilégios globais ou escrita genérica via SQL.

## 12. Matriz R02-S03-D01 a D10 — propostas para avaliação

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-S03-D01 | Autoridade do CRM | CRM controla identidade, organização, recursos, permissões e efeitos; AIA apenas executa capacidades autorizadas | Núcleo IA administra os recursos do CRM |
| R02-S03-D02 | Escopos | Ferramentas com escopos separados por leitura de lead, histórico, resumo, classificação e tarefa | Um escopo genérico de acesso irrestrito |
| R02-S03-D03 | Recurso e atribuição | Validar direito no nível da organização, lead, atendimento, responsável e campos aplicáveis | Qualquer usuário da empresa acessa todos os leads |
| R02-S03-D04 | Leitura minimizada | Enviar somente campos/históricos autorizados e necessários à tarefa | Replicar integralmente todas as conversas e contatos |
| R02-S03-D05 | Sugestão versus efeito | Resumo/classificação inicialmente como sugestões; gravações apenas por contrato validado do CRM | Resposta do modelo altera lead automaticamente |
| R02-S03-D06 | Criação de tarefa | Permissão específica, validação do destinatário, idempotência, aprovação quando exigida e confirmação | Criação livre, sem controle de repetição |
| R02-S03-D07 | Identidade/delegação | Verificar principal humano/serviço e escopos atuais, sem herdar superadmin técnico | Serviço genérico atua em nome de qualquer usuário |
| R02-S03-D08 | Plano e custo | CRM controla elegibilidade/créditos; AIA fornece consumo técnico e eventos deduplicáveis | Núcleo modifica planos/saldos do CRM diretamente |
| R02-S03-D09 | Auditoria/segurança | Negação por padrão, erros seguros, registro mínimo, revalidação e isolamento | Aprovar ações por prompt, sem trilha |
| R02-S03-D10 | Primeiro recorte | Piloto documental CRM: leitura, resumo, classificação sugerida e tarefa controlada; sem código nesta fase | Habilitar todas as operações de todos os produtos |

**R02-S03-D01 a D10: ABERTAS para aprovação documental preliminar.** Escopos, papéis e nomes de ferramentas são conceituais, sem ALLOW, grants, APIs, SQL ou autorização funcional no CRM.

## 13. Dependências e próximos gates

- [ ] R02-S03-D01 a D10 aprovadas ou corrigidas pelo responsável.
- [ ] Confrontar a matriz com os papéis/escopos reais, regras de atribuição e estrutura de recursos do CRM, quando essa documentação for homologada no próprio projeto.
- [ ] Completar contrato de confiança de R02/S02 e mapeamento de organizações de R02/S01.
- [ ] Formalizar autorização de leitura/histórico, gravação de resumo e criação de tarefa no backend CRM com critérios de aceite por recurso.
- [ ] Definir cenários negativos sintéticos e contrato de erros/idempotência, alinhados a AIA-04/07/08/09/12.
- [ ] Conciliar quotas comerciais, consumo, LGPD, retenção e auditoria AIA-10/11.
- [ ] Preservar gates técnicos **R01-G01 a G11 todos abertos**; não presumir fechamento de G01/G02.
- [ ] Respeitar CORE/SEG/AUT e quaisquer outras pendências próprias do CRM, sem mudança ou aprovação cruzada automática.
- [ ] Obter autorização separada para código, testes reais, credenciais, alteração de repos, custo, servidores ou deploy.

**Próximo bloco documental sugerido:** R02/S04 — Segregação, Retenção, Privacidade e Conformidade de dados/derivados entre o núcleo e os produtos.

**Limites imutáveis:** CRM, Portal, Performance AI e Universidade não são modificados por esta proposta; `main` e PRs desses projetos, servidores, Cloudflare, n8n, chaves, contratos externos, bancos e pipelines ficam inalterados.
