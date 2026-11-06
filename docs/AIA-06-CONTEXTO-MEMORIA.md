# AIA-06 — Contexto, Memória e Continuidade dos Agentes | Proposta funcional v0.1

> **Situação:** diretrizes **AIA06-D01 a D10 APROVADAS FUNCIONALMENTE em 2026-11-06**. Detalhes técnicos e implementação seguem bloqueados.  
> **Data:** 2026-11-06.  
> **Precedência:** AIA-01 a AIA-05 possuem diretrizes funcionais aprovadas; não há autorização geral de programação, integrações, testes reais, gastos nem deploy.

## 1. Objetivo

Dar aos agentes acesso ao **contexto certo, na finalidade certa e somente durante o período permitido**, permitindo continuidade entre atendimentos e tarefas sem misturar clientes, produtos ou usuários. A memória deve refletir registros autorizados e auditáveis, e nunca funcionar como autorização para agir.

Diferenciar:
- **Contexto de execução:** informações temporárias necessárias à tarefa atual.
- **Estado da tarefa:** progresso, checkpoints, ações pendentes e confirmações.
- **Histórico do produto:** registros sob custódia do CRM, Portal, Performance AI ou Universidade (fonte de verdade).
- **Memória persistente derivada:** fatos resumidos e validados, quando necessários e autorizados.
- **Base de conhecimento AIA-05:** documentos e políticas recuperáveis, distintos de memória de pessoas ou atendimentos.
- **Instruções AIA-02:** orientação e estilo, que memória ou documento externo não podem sobrescrever.

## 2. Camadas de contexto

| Camada | Conteúdo | Duração candidata | Proprietário |
|---|---|---|---|
| C0 — Solicitação | Evento, tarefa, identidade, produto, empresa, finalidade e escopos autenticados | Execução | Produto e núcleo |
| C1 — Conversa ativa | Mensagens necessárias para responder e operar | Sessão autorizada | Produto |
| C2 — Estado operacional | Etapa do fluxo, status, checkpoints, tentativas, aprovações vinculadas | Até encerramento e retenção aplicável | Serviço de execução e produto |
| C3 — Histórico consultado | Leads, interações, atendimentos e outros dados vivos | Consulta sob demanda | Produto proprietário |
| C4 — Memória derivada | Resumo curado, preferências e fatos autorizados relevantes | Conforme política específica | Produto ou repositório segregado |
| C5 — Conhecimento externo | Manuais, FAQ e fontes vigentes (AIA-05) | Consulta rastreada | Proprietário da base |

**Princípio:** não copiar indiscriminadamente C3/C5 para C4; armazenar o mínimo necessário com origem, finalidade, validade e mecanismo de revogação.

## 3. Construção do contexto por execução

1. Receber a solicitação com contexto autenticado e verificável: serviço chamador, produto, empresa, agente/versão, finalidade, usuário autorizado e correlação.
2. Revalidar plano, permissões, escopos e categoria de dados; não confiar em tenant, papel ou aprovação declarados no texto recebido.
3. Definir orçamento de contexto/tokens, fontes permitidas e limites de sensibilidade.
4. Recuperar histórico e conhecimento apenas via ferramentas e mecanismos autorizados (AIA-04/05), com verificações de ACL antes e depois da recuperação.
5. Priorizar informação atual do produto, fontes vigentes e registros de alta pertinência; sinalizar contradições.
6. Resumir trechos extensos preservando origem e limites; sumários gerados por IA são hipóteses verificáveis, não prova de acontecimentos.
7. Montar contexto mínimo, separado em instruções privilegiadas, pedido autenticado, dados recuperados e conteúdo externo não confiável.
8. Registrar metadados da composição, versão, fontes e finalidade; minimizar conteúdo pessoal persistido em observabilidade.
9. Após execução, decidir explicitamente se há estado a persistir, onde e por quanto tempo; não gravar automaticamente todos os prompts e respostas.
10. Permitir auditoria, correção, revogação e expurgo conforme política.

## 4. Continuidade e memória de conversas

### Exemplo no CRM

O lead informa que procura um sistema para sua empresa e pede contato na próxima semana. A interação é registrada no CRM pelo fluxo oficial. Na próxima conversa, o agente consulta o registro autorizado e pode retomar o assunto, desde que o usuário e a finalidade permitam.

O resumo gerado pela IA no histórico do atendimento segue a decisão do CRM já aprovada: **armazenamento controlado, com versões e acesso restrito**. AIA-06 não pode se tornar uma cópia paralela indiscriminada de todas as conversas do CRM.

### Exemplo no Performance AI

Um agente analisa a campanha e propõe mudanças. A continuidade deve consultar o estado oficial de campanhas, histórico de aprovações e resultados atuais; não assumir que um plano anterior foi executado apenas porque apareceu na memória.

### Exemplo na Universidade

O tutor pode recuperar progresso pedagógico autorizado. Mantém as diretrizes da Universidade de aprendizagem voluntária e sem provas, notas ou certificação.

### Exemplo no Portal

Atendimentos e solicitações podem ser retomados a partir do histórico oficial do Portal, respeitando login independente do CRM e permissões do produto.

## 5. Política de gravação de memória derivada

Memória persistente proposta como **opt-in governado pela finalidade e pelo produto**, quando necessária e lícita. A gravação depende de:
- Tipo de dado e finalidade permitida, empresa/produto/recurso e papel autorizado.
- Evidência de origem, data de registro, responsável, vigência e classificação.
- Qualidade mínima e revisão humana quando a decisão ou o conteúdo forem sensíveis.
- Não transformar inferências especulativas da IA em fatos permanentes.
- Não memorizar automaticamente senhas, tokens, credenciais, dados pessoais sensíveis ou informações confidenciais desnecessárias.
- Atualizar/substituir registros antigos com controle de versão; evitar conflitos entre duas memórias sobre o mesmo fato.
- Expor mecanismos de consultar, corrigir, suspender e excluir memórias dentro de políticas legais e contratuais.

Exemplo de memória permitida, após validação: "A empresa prefere receber relatórios internos às segundas-feiras", com finalidade e fonte. Não inferir atributos privados de pessoas a partir de texto livre.

## 6. Segurança, segregação e privacidade

- Isolamento obrigatório por empresa, produto, finalidade, agente, usuário/recurso e nível de sensibilidade.
- Nenhum tenant pode ler, recuperar ou influenciar memória de outro, inclusive em cache, índice vetorial, batch, métricas e filas.
- Integrar por APIs oficiais dos produtos, mantendo responsabilidade sobre o dado na origem; não criar banco central com cópia total e irrestrita de clientes.
- Acesso interproduto somente com contrato e autorização explícitos, não pressupor SSO entre CRM e Portal.
- Prompts, mensagens e documentos externos podem conter instruções maliciosas: tratá-los como **dados**, nunca como políticas.
- Não reutilizar informações privadas de uma empresa para treinar modelos ou melhorar respostas a outra sem fundamento e autorização específica.
- Proteção no transporte e no armazenamento conforme decisão técnica, controle de acesso, minimização, rotação de credenciais e logs sem segredos.
- Atender solicitações legais de acesso, correção, oposição e exclusão conforme LGPD e responsabilidades de cada produto.
- Definir compatibilidade de retenção com provedores externos e contratos antes de transmitir dados sensíveis ou privados.

## 7. Retenção, expiração e exclusão

**Não fixar prazos genéricos agora.** Os prazos deverão ser aprovados por classe de dado, produto, finalidade, contrato, exigência legal e condições de atendimento.

Regras candidatas:
- Contexto temporário: eliminar ao concluir ou após expiração técnica aprovada, salvo evidências mínimas necessárias.
- Estado pendente: manter até encerramento/expiração do fluxo, com prazos e recuperação consistentes.
- Memória persistente: validade explícita e revisão periódica de pertinência e exatidão.
- Exclusão: remover das consultas futuras e encaminhar expurgo de derivados e caches; backups seguem retenção legítima, acesso controlado e política de restauração sem reintroduzir dados excluídos indevidamente.
- Auditoria: armazenar metadados mínimos para comprovar ações sem retenção excessiva de mensagens pessoais.
- Suspensão de produto, fim de contrato ou revogação de autorização: interromper imediatamente novas consultas sem presumir exclusão física instantânea.

## 8. Estados e fluxo de gestão da memória

Estados propostos: `CANDIDATA -> EM_REVISAO -> ATIVA -> DESATUALIZADA -> SUBSTITUIDA / EXCLUIDA`.  
Outras condições: `SUSPENSA` por política, autorização revogada, incidente ou determinação legal.

Fluxos:
- Usuário autorizado visualiza memórias registradas, origem, agente e finalidade.
- Solicita correção, suspensão ou exclusão.
- Sistema aplica política do produto, avalia obrigação legal e registra decisão.
- Impede novos usos de memória revogada e invalida referências, cache e derivados elegíveis.
- Mantém eventos mínimos de auditoria com proteção de privacidade.

## 9. Conflitos, alucinação e fonte de verdade

- Se o modelo lembrar "cliente já aprovou" mas o backend informar aprovação pendente, **prevalece o status oficial**.
- Memória armazenada pela IA não é autorização, comprovação de contratação nem fonte definitiva de preço, saldo ou status.
- Se dados históricos e atuais divergirem, sinalizar e priorizar a informação transacional verificada.
- Resumos devem preservar fatos e incertezas relevantes, sem aumentar privilégios ou inferir dados não fornecidos.
- Após falha/timeout, o estado de execução deve ser reconciliado pelo sistema; não presumir sucesso.
- Dados não autenticados do usuário não podem alterar a identidade de empresa no contexto.

## 10. Interface administrativa e do cliente

**Central Imagine — Contexto e Memória**
- Políticas por produto/tipo de dado, limites e modelos de retenção.
- Visão de origens e acessos (sem exposição irrestrita de conteúdos privados).
- Rastreamento de composições, qualidade, auditoria, falhas e solicitações de exclusão.
- Gestão de incidentes e revisão dos perfis autorizados.

**Dentro do produto**
- Configuração de continuidade quando habilitada pelo plano e finalidade.
- Consulta de memórias derivadas permitidas e suas fontes, correção e exclusão quando aplicável.
- Histórico oficial permanece no CRM/Portal/Performance/Universidade, com permissões locais.
- Informações sobre que tipo de dado é lembrado, por quanto tempo e para qual finalidade.
- Funcionalidades adicionais de privacidade respeitam perfil e autorização do usuário.

## 11. Entidades conceituais (não são migrações)

- `agent_context_policies`: regras de composição, dados, finalidade e quotas.
- `agent_sessions`: identificadores e metadados de conversas autorizadas.
- `agent_execution_states`: checkpoints e resultado confirmado.
- `agent_context_compositions`: versões, hashes e referências das fontes utilizadas.
- `agent_memory_records`: memória derivada, origem, escopo, validade, classificação e status.
- `agent_memory_revisions`: histórico de revisão e correção.
- `agent_memory_access_events`: trilha de uso e acesso.
- `agent_memory_retention_jobs`: prazos, expurgo, status e falhas.
- `agent_memory_requests`: solicitações e decisões de atualização, revogação e exclusão.

Nomes, storage, tecnologia, provedor e schemas estão em aberto.

## 12. Diretrizes AIA06-D01 a D10 — aprovadas funcionalmente

| ID | Tema | Alternativa A — Recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA06-D01 | Organização | Separar contexto temporário, estado operacional, histórico e memória derivada | Uma memória única de texto para tudo |
| AIA06-D02 | Fonte de verdade | Dados históricos e transacionais sob autoridade dos produtos; núcleo consulta sob autorização | Copiar integralmente todas as bases para o núcleo |
| AIA06-D03 | Composição | Contexto mínimo, versionado, relevante e filtrado por empresa/produto/finalidade | Enviar todo o histórico ao modelo indiscriminadamente |
| AIA06-D04 | Persistência | Memórias derivadas somente quando permitidas, necessárias e com origem verificável | Memorizar automaticamente todas as conversas |
| AIA06-D05 | Segurança | ACL por empresa, produto, agente, usuário e recurso; autorização revalidada | Confiar em identificadores declarados no prompt |
| AIA06-D06 | Retenção | Políticas por classe e produto, vigência e expurgo de derivados; prazos ainda a definir | Retenção indefinida de todo o conteúdo |
| AIA06-D07 | Controle do usuário | Consulta, correção, suspensão e exclusão conforme papel, LGPD e contrato | Memórias invisíveis e imutáveis |
| AIA06-D08 | Continuidade | Retomar tarefas por checkpoints e confirmações oficiais, sem presumir ações concluídas | Memória textual como prova de execução |
| AIA06-D09 | Melhoria e qualidade | Feedback e revisão supervisionados; detectar erros e conflitos | Memória se autocorrige sem validação |
| AIA06-D10 | MVP | Contexto de sessão + resumo/histórico autorizado do CRM; memória derivada controlada apenas quando necessária | Memória global longa em todos os produtos desde o MVP |

**AIA06-D01 a D10: APROVADAS FUNCIONALMENTE em 2026-11-06 pelo responsável.** Aprovação limitada a diretrizes; não autoriza programação, alterações nos sistemas, acesso a dados reais, testes, integrações, despesas ou deploy.

## 13. Casos de avaliação e aceitação documental

Cenários: dois tenants com nomes iguais; usuário sem acesso ao lead; sessão retomada após expiração; resumo incorreto; memória com dado sensível; autorização revogada; conta do CRM desativada; cache com documento excluído; aprovação de campanha expirou; conflito entre informação lembrada e status real; perda de conexão após ação já executada; recuperação após backup; produto com login independente.

- [x] AIA06-D01 a D10 aprovadas funcionalmente pelo responsável em 2026-11-06.
- [ ] Matriz de fontes, permissões e responsabilidades dos produtos revisada.
- [ ] Política de dados, sensibilidade e retenção conciliada com AIA-11.
- [ ] Interfaces de consulta/correção/exclusão detalhadas com os produtos.
- [ ] Estado de execução, idempotência e reconciliação alinhados a AIA-07/08/12.
- [ ] MVP e exemplos de casos de teste documentais homologados.
- [ ] Stack, schemas, prazos exatos e fornecedores definidos posteriormente por decisão técnica.
- [ ] Programação, uso de dados reais, gastos e deploy dependem de aprovação expressa separada.

**Gates mantidos:** somente documentação; sem código, testes reais, segredos, dados privados, contratação, migrações, mudanças nos outros repositórios ou deploy.
