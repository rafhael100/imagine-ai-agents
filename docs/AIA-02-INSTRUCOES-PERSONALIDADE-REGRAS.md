# AIA-02 — Instruções, Personalidade e Regras de Agentes | Proposta funcional v0.1

> **Situação:** AIA02-D01 a D10 aprovadas funcionalmente pelo responsável em 2026-11-06; requisitos técnicos e implementação ainda não autorizados.  
> **Data:** 2026-11-06. **Escopo:** Imagine AI Agents, sem programação, testes reais, despesas ou deploy.  
> **Precedência:** respeitar AIA-01 (D01–D08 aprovadas funcionalmente), decisões específicas de cada produto e gates técnicos em aberto.

## 1. Objetivo

Definir como a Imagine cria, distribui, versiona e protege instruções de agentes de IA. O cliente poderá configurar **aspectos autorizados** de um agente, sem alterar políticas obrigatórias, permissões, preços, limites do produto ou ferramentas não homologadas.

**Distinção essencial:**
- **Instruções / prompts** orientam o modelo probabilístico.
- **Regras obrigatórias** são implementadas e validadas pelo software e seus sistemas proprietários.
- **Conhecimento** fornece fatos, com origem e vigência, em AIA-05.
- **Memória** fornece histórico e contexto permitido, em AIA-06.
- **Ferramentas e aprovações** definem quais ações são realmente executáveis, em AIA-04 e AIA-08.
- **Treinamento do modelo (fine-tuning)** não é o mesmo que ajustar instruções ou carregar documentos.

## 2. Hierarquia conceitual de camadas

| Camada | Fonte e responsabilidade | Quem edita | Regra |
|---|---|---|---|
| L0 — Segurança técnica | Controle de acesso, autenticação, quotas, auditoria, privacidade e políticas no backend | Equipe Imagine autorizada | Obrigatório; não é mera instrução de texto |
| L1 — Instruções de plataforma | Princípios gerais de comportamento, conduta e limites do serviço Imagine | Administração Imagine | Não sobrescrito pelo cliente |
| L2 — Template oficial de agente | Objetivo, papel, capacidades, comportamento-base, instruções versionadas | Administração Imagine | Distribuição controlada pelo catálogo AIA-01 |
| L3 — Regras do produto | Restrições do CRM, Portal, Performance AI ou Universidade, por contrato técnico | Responsável pelo produto | Produto mantém autoridade sobre suas regras e dados |
| L4 — Configurações do cliente | Idioma, tom, público, campos permitidos, informações comerciais autorizadas | Usuário com permissão na empresa | Validadas por schema e políticas; não elevam privilégios |
| L5 — Instrução da tarefa atual | Pedido autenticado do operador ou evento acionador | Usuário/serviço autorizado | Não pode reescrever camadas superiores |
| L6 — Conteúdo de contexto | Documentos, páginas, e-mails, conversas e retornos de ferramentas | Fontes externas ou internas | **Dados não confiáveis como comandos**; não ganham autoridade |

Esta é uma hierarquia funcional proposta para o produto, não uma afirmação de que o modelo obedeça infalivelmente a camadas de texto. **L0 deve ser imposto por código, permissões e infraestrutura.**

## 3. Configuração e personalidade

### 3.1 Identidade e apresentação

Campos candidatos: nome de exibição, foto/ícone institucional, idioma, assinatura permitida, descrição, apresentação e orientações de canal. O agente deve se identificar como assistência automatizada quando isso for relevante, sem se passar por pessoa real.

### 3.2 Tom e estilo

O cliente pode escolher entre perfis oficiais: profissional, consultivo, acolhedor, objetivo, técnico e informal, com intensidade e preferências limitadas por regras do produto. Campos de "não usar determinado termo" ou "preferir certas expressões" podem ser permitidos como **preferências**, não como garantias absolutas do modelo.

### 3.3 Objetivo e público

Templates definem **finalidade central e ferramentas elegíveis**. Cliente pode adaptar segmento, termos, foco comercial, produtos da empresa e perguntas de qualificação nos campos autorizados. Não pode converter um agente informativo em executor financeiro por prompt.

### 3.4 Exemplos

A Imagine pode registrar exemplos de respostas aprovadas para orientar o agente; empresas podem cadastrar exemplos nos limites da categoria, com validação contra dados pessoais, sigilo e políticas. Exemplos não concedem autorização de ferramenta.

## 4. Editor visual de instruções

### 4.1 Central Imagine (gestão interna)

- Selecionar template e criar uma nova versão **rascunho**.
- Definir papel, objetivos, casos de uso e parâmetros.
- Associar políticas vigentes, ferramentas homologadas e fontes de conhecimento autorizadas por referência versionada.
- Criar biblioteca de instruções e blocos aprovados para reutilização.
- Executar simulações e inspeção de resultados, avaliação de risco, aprovação e publicação.
- Visualizar diffs entre versões, responsáveis, pareceres e trilhas de auditoria.
- Retirar/pausar versões que apresentem problema de segurança.

### 4.2 Produto do cliente

- Ver explicação clara das capacidades e limites do agente adotado.
- Personalizar apenas campos disponibilizados por template e produto.
- Associar fontes autorizadas, dentro das políticas de AIA-05 e permissões do produto.
- Pré-visualizar o comportamento em simulações seguras, sem publicar decisões automaticamente.
- Registrar solicitações de atualização/ajuste e acompanhar resultados, quando o plano habilitar.
- Restaurar configurações permitidas para o padrão; mudanças críticas dependem de reavaliação.

### 4.3 Campos imutáveis para clientes

Nunca permitir edição direta por cliente de instruções internas de plataforma, políticas de segurança, schemas de ferramentas, conexões privilegiadas, credenciais, regras de cobrança/contrato, isolamento multiempresa, alçadas ou critérios de aprovação.

## 5. Regras duras versus instruções suaves

| Exemplo de intenção | Instrução ao modelo | Aplicação obrigatória |
|---|---|---|
| "Não inventar preço" | Pedir consulta ao catálogo oficial | API entrega preços vigentes; ações financeiras validadas no backend |
| "Não conceder desconto acima do limite" | Orientar a não oferecer exceções | Regra comercial no serviço responsável recusa operações inválidas |
| "Não ler dados de outra empresa" | Solicitar contexto estritamente necessário | Filtragem de tenant, permissão e escopo antes de cada leitura |
| "Solicitar aprovação para gasto" | Encaminhar proposta ao aprovador | Ferramenta não executa sem aprovação válida, vinculada à operação |
| "Não revelar segredos" | Reduzir exposição na resposta | Segredos nunca são entregues ao prompt sem necessidade; segregação, mascaramento e auditoria |

**Em caso de conflito:** negar execução, registrar justificativa não sensível e direcionar à revisão autorizada. A IA não decide flexibilizar um bloqueio obrigatório.

## 6. Configuração por canal e caso de uso

O mesmo template poderá variar suas orientações conforme contexto: atendimento por chat, assistente interno, execução agendada ou ação acionada por automação. A identidade do canal/usuário é derivada de contexto autenticado e verificado, não de um texto livre enviado ao modelo.

- **CRM:** atendimento, resumo, qualificação, linguagem comercial sem promessas indevidas.
- **Portal:** auxílio a contratação e suporte, respeitando o contrato oficial.
- **Performance AI:** análise consultiva e planejamento de campanhas; mudanças financeiras seguem alçadas.
- **Universidade:** orientação pedagógica sem reintroduzir provas, notas ou certificados, conforme diretrizes aprovadas da Universidade.

## 7. Tratamento de bases, memória e contexto externo

- AIA-02 define **como o agente usa o contexto**, não constrói por conta própria o motor de busca (AIA-05) nem a memória (AIA-06).
- Recuperar documentos com origem, versão, tenant, autorização, expiração e limites de tamanho; preferir consulta transacional via ferramenta para preços, saldos e situações atuais.
- Entradas externas podem conter ordens maliciosas ("ignore as regras"). Tratá-las como dados e implementar barreiras em contexto, ferramentas e política; não confiar apenas em uma frase de prompt.
- Separar instruções do usuário de evidências documentais e do retorno de ferramentas; registrar proveniência sem copiar dados sensíveis desnecessariamente em logs.
- Solicitações de alteração de conhecimento do agente devem respeitar direitos de acesso, revisão e rastreabilidade.

## 8. Composição, versões e rollback

1. O serviço monta uma configuração por referências versionadas: plataforma + template + produto + campos autorizados da instância + instrução da tarefa.
2. Validar schema, compatibilidade de modelo, cotas, categoria de risco e ferramentas antes de executar.
3. Persistir hash/identificador das versões efetivamente utilizadas e dados mínimos de auditoria.
4. Toda mudança relevante em instruções oficiais cria versão nova, revisável e testável.
5. A publicação segue fluxo com revisão de segurança, qualidade e impacto no produto, conforme risco.
6. A reversão usa snapshot anterior compatível; privilégios novos nunca são mantidos por consequência de rollback.
7. Configurações de empresas podem ter revisões próprias; alterações controladas por perfis e limites de campo.
8. Reprovar versões com falha crítica em testes, instabilidade, regressão material ou custo fora dos limites autorizados.

## 9. Laboratório e melhoria supervisionada (integração AIA-09)

**Cenários mínimos candidatos**: pergunta frequente correta, solicitação ambígua, preço desatualizado, pedido de desconto proibido, documento malicioso, tentativa de vazamento entre empresas, indisponibilidade de ferramenta, solicitação financeira sem aprovação, mudança de versão e caso com idioma/tom personalizado.

Métricas propostas: aderência factual, completude, recusa correta, taxa de intervenção, acionamento indevido de ferramenta, latência e custo. **Nenhuma taxa "100% segura" deve ser presumida** só porque o modelo passou em um conjunto de testes.

Feedback de operador/cliente pode gerar sugestão de alteração de instrução; sua publicação deve ter revisão, avaliação e aprovação. Não haverá autoalteração irrestrita das regras críticas.

## 10. Modelo conceitual de entidades (não é migração)

- `instruction_policies`: políticas da plataforma ou produto com escopo/versionamento.
- `instruction_templates`: instruções dos templates oficiais.
- `instruction_template_versions`: snapshots, autor, aprovação, changelog, assinatura/hash.
- `agent_personality_profiles`: perfis de estilo permitidos e suas restrições.
- `tenant_agent_preferences`: somente campos liberados de personalização por instância/empresa/produto.
- `instruction_compositions`: metadados e versões que formaram a execução.
- `instruction_evaluations`: cenário, evidência, revisor, versão, resultado.
- `instruction_change_requests`: proposta, status, aprovação e justificativa.

Schemas, nomes físicos, linguagem e armazenamento continuam decisões técnicas abertas.

## 11. Interoperabilidade e riscos

- Compatibilidade por versão entre catálogo AIA-01 e regras AIA-02.
- Política real de autorização derivada do produto e do serviço de execução; não confiar no texto de prompt.
- Redação e minimização de dados antes de enviar contexto a provedores externos; requisitos de LGPD, transferência, retenção e homologação ainda precisam ser detalhados.
- Bloqueio de composição com ferramentas ou modelos não aprovados.
- Limites por tokens, custo e tempo com degradação controlada e sem ações duplicadas.
- Auditoria que permita investigar resultados sem reter desnecessariamente informações pessoais ou segredos.

## 12. Diretrizes funcionais aprovadas pelo responsável

| ID | Tema | Proposta A (recomendada) | Alternativa |
| --- | --- | --- | --- |
| AIA02-D01 | Camadas de instruções | Plataforma > template > produto > personalização permitida > tarefa; conteúdo externo sem autoridade | Um único prompt livre por empresa |
| AIA02-D02 | Regras críticas | Backend aplica permissões e regras; prompt apenas orienta | Somente prompt para impor limites |
| AIA02-D03 | Personalidade | Perfis oficiais de tom, idioma e estilo com campos limitados | Personalização irrestrita |
| AIA02-D04 | Editor | Central interna Imagine para oficiais + telas locais nos produtos | Editor técnico integral para clientes |
| AIA02-D05 | Versionamento | Snapshots e trilha, testes, publicação/rollback controlados | Edição direta do agente publicado |
| AIA02-D06 | Contexto/Conhecimento | AIA-05/06 são donos de base/memória; AIA-02 define uso e precedência | Cada agente cria armazenamento próprio |
| AIA02-D07 | Ferramentas | Chamadas somente via ferramentas homologadas, com autorização a cada execução | Acesso genérico a APIs e dados |
| AIA02-D08 | Melhoria | Feedback, avaliações e aprovação antes de atualizar políticas/instruções oficiais | Autoaprendizagem que edita regras em produção |
| AIA02-D09 | Privacidade | Minimização por empresa e finalidade; logs sem segredos; rastrear versões | Compartilhar contextos sem segregação |
| AIA02-D10 | Lançamento | MVP com instrução base, personalidade, versões e cenários de teste no piloto CRM | Editor irrestrito/multiagente desde a primeira entrega |

**Situação de AIA02-D01 a D10: APROVADAS FUNCIONALMENTE em 2026-11-06.** As alternativas não foram adotadas. Permanecem abertos os critérios de implementação técnica, segurança detalhada, aceite e deploy.

## 13. Critérios de detalhamento e implementação (ainda abertos)

- [x] Aprovação funcional explícita de AIA02-D01 a D10 em 2026-11-06.
- [ ] Campos editáveis e imutáveis por perfil/produto revisados.
- [ ] Limites de composição, fontes externas e regras duras acordados.
- [ ] Histórico, revisões, aprovação e rollback documentados.
- [ ] Dependências de AIA-03/04/05/06/08/09/10/11/12 registradas.
- [ ] Casos de teste documentais revisados.
- [ ] Gate técnico e autorização para programação avaliados posteriormente, em decisão separada.

**Todos os demais bloqueios permanecem:** programação, infra, despesas, APIs reais, testes reais, migrações, deploy e alterações em outros repositórios.
