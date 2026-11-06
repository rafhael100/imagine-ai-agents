# AIA-01 — Catálogo Central de Agentes | Proposta funcional v0.1

> **Situação:** diretrizes AIA01-D01 a D08 aprovadas funcionalmente em 2026-11-06; detalhes técnicos, integrações e autorização de programação continuam pendentes.  
> **Data:** 2026-11-05. **Repositório:** `rafhael100/imagine-ai-agents`.  
> **Relacionamentos:** AIA-02 (instruções/regras), AIA-03 (modelos), AIA-04 (ferramentas), AIA-05/06 (conhecimento/memória), AIA-07 (execução), AIA-08 (aprovações), AIA-09 (testes), AIA-10/11 (custos/auditoria), AIA-12 (contratos entre produtos).

## 1. Resultado esperado

Disponibilizar um catálogo gerido exclusivamente pela Imagine com **agentes oficiais versionados**, dos quais os produtos consumidores podem criar **instâncias configuráveis por empresa**, sem duplicar a implementação do núcleo. Um agente não recebe privilégios somente por existir ou estar ativo; a execução exige identidade, permissão, ferramenta homologada e política válida.

## 2. Conceitos e limites

| Conceito | Significado |
| --- | --- |
| **Modelo/Template oficial** | Definição canônica administrada pela Imagine: propósito, produto(s) elegíveis, capacidades, campos configuráveis, regras e requisitos de publicação. |
| **Versão publicada** | Snapshot imutável de uma definição aprovada; versões antigas permanecem identificáveis para auditoria e rollback. |
| **Instância de empresa** | Adoção de um template por empresa e produto, com opções permitidas, plano, responsáveis e ciclo de vida próprios. |
| **Agente especializado interno** | Configuração administrada pela Imagine para operações de sua responsabilidade, não configurável livremente pelo cliente. |
| **Capacidade** | O que o agente pode pedir (analisar, resumir, criar tarefa etc.); depende de ferramenta e permissão efetiva em tempo de execução. |
| **Vínculo de produto** | Contrato versionado que determina disponibilidade, entrada de contexto, ferramentas e interfaces específicas do CRM/Portal/Performance/Universidade. |
| **Autonomia** | Política de ações e aprovações efetivas, controlada pelo backend e por AIA-08; não equivale ao nível descritivo N1–N5. |

Não se propõe disponibilizar um editor de código, prompt de sistema irrestrito ou acesso direto à base de dados dos produtos aos clientes. O fluxo visual de automações do CRM continua pertencendo ao CRM.

## 3. Jornada do administrador Imagine

1. Abrir **Central Imagine de Agentes > Catálogo Oficial**.
2. Criar template em rascunho com identificador estável, nome, descrição, objetivo, owner, público e produto(s) habilitados.
3. Selecionar capacidades e dependências pré-homologadas (ferramentas, provedores, conhecimento e políticas), **sem liberar acesso por mera seleção na interface**.
4. Definir campos que empresas poderão personalizar e limites admissíveis; separar campos imutáveis e variáveis.
5. Solicitar revisão técnica, funcional, privacidade e segurança conforme risco.
6. Avaliar a versão em laboratório e registrar evidências, custos estimados, resultados e compatibilidade.
7. Aprovar e publicar uma versão imutável, com possibilidade de disponibilização gradual por produto/plano/empresa.
8. Monitorar adesão, falhas e consumo; suspender uma versão quando necessário e criar nova versão para qualquer mudança relevante.

**Papéis propostos:** Gestor de catálogo; Curador de conhecimento; Responsável técnico; Segurança/compliance; Operador/auditor (consulta); Aprovador de publicação. Papéis devem refletir autorização real e admitir segregação de funções nas operações de maior risco.

## 4. Jornada do cliente dentro do produto

1. Entrar em **Inteligência Artificial > Catálogo de Agentes** no próprio produto, sem login novo obrigatório.
2. Ver somente templates liberados para o seu produto, empresa, plano, região e status de conformidade.
3. Visualizar finalidade, capacidades, pré-requisitos, consumo previsto e avisos de uso.
4. Selecionar **Ativar**; o sistema valida permissão, cota, vínculo e pré-requisitos no backend.
5. Personalizar **somente** os parâmetros expostos: nome de exibição, tom, idioma, horários, eventos de acionamento, fontes autorizadas, limites de uso e responsáveis, quando disponibilizados.
6. Testar em ambiente de simulação sem ações externas antes de habilitar funções operacionais.
7. Ativar ou suspender a instância, acompanhar histórico e solicitar suporte/alteração conforme política do produto.
8. Mudanças de versão e de ferramenta que ampliem poderes pedem revalidação; mudanças críticas nunca são impostas silenciosamente.

O cliente não poderá redefinir permissões globais, criar ferramentas arbitrárias nem burlar regras comerciais do produto.

## 5. Estados e ciclo de vida

### 5.1 Template oficial

`RASCUNHO -> EM_REVISAO -> APROVADO -> PUBLICADO -> DESCONTINUADO -> ARQUIVADO`.

- Rejeição retorna a rascunho com parecer.
- **Suspensão de segurança** é uma condição que impede novas execuções, aplicável a qualquer versão publicada.
- Cada alteração relevante exige **nova versão**, nunca edição silenciosa de snapshot publicado.

### 5.2 Instância de empresa

`DISPONIVEL -> EM_CONFIGURACAO -> EM_TESTE -> ATIVA <-> PAUSADA -> ENCERRADA`.

- A ausência de assinatura ou cota pode levar a **BLOQUEADA_POR_POLITICA**, sem excluir automaticamente dados ou histórico.
- Instância encerrada deixa de executar; retenção/expurgo seguem políticas de AIA-11 e do produto proprietário.
- O estado visual não substitui a autorização efetiva no momento da execução.
- Proposta: distinguir pausa voluntária, bloqueio por política e suspensão de segurança na interface.

## 6. Campos funcionais do cadastro

| Grupo | Campos candidatos | Quem controla |
| --- | --- | --- |
| Identificação | ID técnico imutável, nome, categoria, descrição, proprietário, tags | Imagine |
| Elegibilidade | Produtos, planos, segmentos, requisitos, países e versões compatíveis | Imagine + regra de produto |
| Comportamento | Objetivo, políticas-base, idioma padrão, opções expostas | Imagine; parte personalizável pelo cliente |
| Capacidades | Intenções permitidas, tools homologadas, leitura/escrita e limites | Imagine; produto faz autorização final |
| Autonomia | Sugerir, pedir aprovação ou executar operações de baixo risco | Políticas Imagine/produto + AIA-08 |
| Conhecimento/memória | Tipos de fontes, escopos e persistência permitidos | Imagine + autorização da empresa |
| Operação | Gatilhos elegíveis, taxas, timeout, concorrência, cotas | Produtos e serviço central |
| Controle | Versão, aprovadores, data de vigência, evidências, changelog, rollback | Imagine |
| Observabilidade | Métricas, eventos, custos, falhas e auditoria | Serviço central com segregação por tenant |

## 7. Separação entre template, instância e permissão

**Regra principal:** `permissão_efetiva = interseção(template, produto, tenant, plano, usuário/serviço, ferramenta, política de risco, aprovação vigente)`.

Exemplo: o template oficial **Qualificador de Leads** contém a capacidade `criar_tarefa`. A instância do cliente só executa se o plano permitir, a empresa autorizar, a credencial da integração tiver escopo, a ação passar pela política e o CRM validar o lead como pertencente àquela empresa.

**Proibido:** guardar um token permanente de administrador do CRM em cada agente; aceitar tenant_id informado pelo texto do usuário como autorização; selecionar diretamente conexão de outra empresa; consultar bases alheias por IDs adivinhados.

## 8. Distribuição entre produtos

- **Catálogo compartilhado, contratação e operação locais.** Cada produto decide como exibir agentes, cobrar, aplicar cotas e expor fluxos à sua base de clientes.
- **CRM Imagine:** agentes de qualificação, resumo e apoio a vendedores; motor visual de automação e dados dos leads continuam no CRM.
- **Portal Imagine:** agentes de suporte, orientação e auxílio operacional; contratos e pagamentos continuam sob regras do Portal.
- **Performance AI:** análise e propostas de campanha; gasto de mídia e alterações críticas dependem das políticas e APIs do Performance AI.
- **Universidade Imagine:** tutor IA com supervisão pedagógica; a Universidade continua vinculada à elegibilidade de serviços Imagine, segundo sua política específica.
- **Autenticação:** não pressupor SSO entre CRM e Portal. Respeitar a decisão de logins independentes e utilizar identidade de serviço/autorização do produto em cada chamada.

## 9. Governança de mudanças e rollout

1. Versões com `major.minor.patch` como convenção proposta, e changelog compreensível.
2. `major` para alterações de compatibilidade/permissão, `minor` para capacidades opcionais e `patch` para correções sem alteração de privilégio (a classificação é validada por política, não pelo autor).
3. Publicação em etapas: laboratório -> grupo interno -> piloto restrito -> elegíveis, com evidências e reversão.
4. Instâncias podem estar fixadas em versão compatível; correções de segurança podem exigir suspensão forçada, notificação e migração aprovada.
5. Versão arquivada mantém identificadores e trilhas necessárias, conforme retenção.
6. Não reaproveitar a aprovação antiga quando mudar dados acessíveis, ferramenta, gastos ou autonomia.

## 10. Modelo conceitual de dados (não é migração)

- `agent_templates`: identidade canônica, finalidade, produtos e owner.
- `agent_template_versions`: definição publicada, checksum, aprovações e status.
- `agent_capabilities` / `agent_version_capabilities`: catálogo e vínculos versionados.
- `product_agent_bindings`: contrato de distribuição por produto, versão e requisitos.
- `tenant_agent_instances`: empresa, produto, versão, estado e responsável.
- `tenant_agent_settings`: parâmetros configuráveis, validados contra schema permitido.
- `agent_publication_reviews`: pareceres e evidências de homologação.
- `agent_rollouts`: critérios e etapas de liberação.
- `agent_lifecycle_events`: trilha cronológica de mudanças.

Segredos de integração devem ficar em mecanismo seguro de credenciais gerenciado por domínio apropriado, não como campos em texto do agente. Os nomes acima são ilustrativos e não determinam a stack.

## 11. Contratos e eventos candidatos (para AIA-12)

- Leitura do catálogo elegível para produto/empresa autenticada.
- Criação, validação, teste, ativação, pausa e encerramento de instância.
- Solicitação de avaliação/aprovação de versão e publicação.
- Eventos: `agent.template.published`, `agent.version.suspended`, `agent.instance.activated`, `agent.instance.paused`, `agent.instance.blocked`.
- Eventos e chamadas com idempotência, versionamento, IDs de correlação, escopo e autorização, **sem payloads pessoais além do necessário**.

Não há endpoints ou mensagens efetivamente implementados neste documento.

## 12. Matriz de decisões funcionais aprovadas (2026-11-06)

| ID | Tema | Diretriz funcional aprovada | Alternativa não adotada |
| --- | --- | --- | --- |
| AIA01-D01 | Catálogo | Templates oficiais Imagine + instâncias configuráveis nos produtos | Agentes criados inteiramente por cada cliente |
| AIA01-D02 | Administração | Central técnica interna compartilhada + telas locais | Uma central única para todos os usuários, com SSO desde o início |
| AIA01-D03 | Edição | Cliente edita somente campos autorizados | Cliente pode editar qualquer prompt e ferramenta |
| AIA01-D04 | Versão | Snapshot imutável; publicação e rollback aprovados | Alteração direta e imediata em produção |
| AIA01-D05 | Autonomia | Interseção de permissões + alçadas de AIA-08 | Autonomia definida só no prompt |
| AIA01-D06 | Distribuição | Vínculo por produto, plano, empresa e versão | Todos os agentes em todos os produtos |
| AIA01-D07 | Lançamento | Um piloto no CRM com qualificação de lead e ações de baixo risco | Lançamento simultâneo em todos os produtos |
| AIA01-D08 | Ciclo de vida | Distinguir rascunho, teste, publicação, pausa, bloqueio e desativação | Ativo/inativo apenas |

**AIA01-D01 a D08: APROVADAS FUNCIONALMENTE em 2026-11-06, por confirmação do responsável na conversa de planejamento.** Esta aprovação não valida detalhes técnicos, não implica contratação de provedores, nem libera implementação, integrações, testes ou deploy.

## 13. Fluxos de exceção e segurança

- Se expira assinatura ou cota: bloquear novas execuções, informar motivo e manter histórico conforme retenção.
- Se falha validação de permissão: negar execução e auditar sem revelar dados privados.
- Se uma versão é suspensa: novas execuções bloqueadas; execuções pendentes obedecem à política de cancelamento/compensação do domínio.
- Se integração fica indisponível: erro explícito e recuperação controlada; sem executar ações duplicadas.
- Se o operador solicita alteração de privilégio: revisão, nova aprovação e versionamento.
- Se um cliente tenta usar documento malicioso para alterar instruções: tratar conteúdo como dado não confiável e não como nova autoridade.

## 14. Critérios de aceite documental de AIA-01

- [ ] Conceitos de template oficial, versão e instância de empresa acordados.
- [ ] Campos visíveis, campos imutáveis e configurações permitidas revisados.
- [ ] Estados, autorização, bloqueio, arquivamento e retenção alinhados aos produtos.
- [ ] Matriz de papéis e responsabilidades revisada.
- [ ] Publicação/rollback e critérios de reaprovação definidos.
- [ ] Contratos com AIA-02, 04, 08, 10, 11 e 12 delimitados.
- [x] D01–D08 aprovadas como diretrizes funcionais em 2026-11-06.
- [ ] Gate de implementação continua fechado até aprovação adicional da arquitetura e MVP.

## 15. Próximos artefatos após revisão

1. AIA-01 v0.2 consolidada com decisões explícitas.
2. Esboços funcionais de telas da Central Imagine e configurações locais.
3. Contratos conceituais de cadastro, elegibilidade e distribuição (AIA-12).
4. Casos de teste documental e matriz RACI para AIA-01.

**Bloqueios mantidos:** não escrever código de produto; não executar testes reais; não comprar serviços; não alterar `main` nem outros repositórios sem autorização.
