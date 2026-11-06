# AIA-08 — Autonomia, Permissões e Central de Aprovações | Proposta funcional v0.1

> **Status:** proposta documental para revisão; **AIA08-D01 a D10 ABERTAS**.  
> **Data de referência:** 2026-11-06.  
> **Precedência:** AIA-01 a AIA-07 com diretrizes funcionais aprovadas; os gates de arquitetura técnica, contratos de produto, custos, segurança detalhada e implementação permanecem abertos.

## 1. Objetivo

Definir **o que um agente poderá propor, ler ou executar**, **em nome de quem**, em **qual empresa e produto**, **sob quais alçadas** e **com qual comprovação de autorização**. O núcleo Imagine AI Agents oferece orquestração e aplicação central de políticas; o **produto proprietário do recurso tem a última palavra** sobre a operação.

**Autonomia não equivale a privilégio.** Um agente classificado como avançado (N4/N5) não pode publicar campanhas, gastar, alterar cobranças, apagar dados, modificar permissões ou aprovar a própria ação sem direitos específicos e política de risco válida.

A central compartilhada não deve criar um segundo sistema comercial independente de aprovação que contradiga o CRM. Devem existir **contratos comuns de solicitação/evidência**, com fluxos locais e alçadas de cada produto; integrar a Central de Aprovações já planejada no CRM, sem duplicar a autoridade sobre seus registros.

## 2. Conceitos

| Conceito | Definição |
| --- | --- |
| Capacidade do agente | Recurso atribuído pelo catálogo AIA-01; não garante permissão |
| Permissão efetiva | Interseção de template, produto, empresa, plano, identidade, recurso, política e aprovação |
| Ação candidata | Proposta contextualizada de operação, produzida pelo agente ou usuário |
| Aprovação | Evidência explícita de decisão por pessoa habilitada, com validade e objeto fixos |
| Alçada | Política determinística que indica quem pode aprovar determinado risco/valor/ação |
| Delegação | Concessão específica, restrita, auditada e revogável; não herança irrestrita de poderes |
| Autonomia operacional | Modo permitido: informar, recomendar, pedir aprovação ou executar ferramenta previamente autorizada |
| Confirmação | Evidência do produto executor de que o efeito ocorreu, distinta de aprovação da proposta |
| Revogação | Bloqueio imediato de novas operações com permissão ou aprovação invalidada |

## 3. Categorias de autonomia propostas

- **A0 — Informar:** responde apenas com conteúdo e consultas públicas elegíveis; sem mutações.
- **A1 — Analisar e sugerir:** consulta registros autorizados, elabora recomendações e rascunhos; não aplica efeitos.
- **A2 — Operar ações limitadas:** executa ferramentas de baixo risco previamente liberadas pelo produto, com quotas e auditoria.
- **A3 — Solicitar aprovação:** monta plano e proposta verificável; suspende efeito externo até aprovação válida.
- **A4 — Orquestrar especialistas:** delega subtarefas mantendo interseção de permissões, orçamento e escopos. Não confere autonomia adicional.

São **perfis de operação**, não posições hierárquicas automáticas; podem coexistir em diferentes capacidades de um mesmo agente. O nível de capacidade N1–N5 da AIA-01 não autoriza a classe A0–A4 sem avaliação.

## 4. Matriz de risco — alinhamento preliminar com AIA-04

| Risco | Ações ilustrativas | Controle mínimo proposto |
| --- | --- | --- |
| R0 — Informativa/pública | Consultar FAQ pública | Escopo/finalidade e limites |
| R1 — Leitura privada | Consultar lead da própria empresa | Permissão real sobre empresa e recurso, sem expor dados de terceiros |
| R2 — Mutação reversível | Criar tarefa ou nota interna | Autorização por ferramenta, idempotência, limite, auditoria; aprovação se produto exigir |
| R3 — Sensível/externa | Enviar mensagem, publicar conteúdo, alterar configuração de campanha | Aprovação explícita ou política de autorização previamente homologada e limitada; não presumir autorização genérica |
| R4 — Crítica/privilegiada | Alterar credenciais, conceder direitos, excluir dados, movimentar valores | Negar por padrão; excepcionalmente fluxo dedicado do produto com alçada e revisão elevada |

**O sistema proprietário pode exigir controles mais restritivos** que essa matriz. Limites comerciais, financeiros, elegibilidade de plano, opt-ins e regras regulatórias não podem ser diminuídos pela escolha de um perfil pelo cliente.

## 5. Modelo de autorização em cada execução

A autorização efetiva deve combinar, no mínimo:
1. **Agente/template/versão** publicados e elegíveis (AIA-01).
2. **Produto e instância da empresa** autenticados, status de contrato e plano.
3. **Usuário ou identidade de serviço** que solicita, com escopo válido, segregação e finalidade.
4. **Ferramenta e versão** homologadas (AIA-04).
5. **Recurso específico** pertencente ao produto/empresa e operação autorizada.
6. **Risco, valor, limite e alçada** aplicáveis à ação concreta.
7. **Aprovação válida** quando necessária, vinculada à exata ação/versão, a quem aprovou e ao prazo.
8. **Validação final no backend dono da operação**, imediatamente antes de executar.

O texto livre do usuário, a memória e a resposta do modelo **não podem** acrescentar permissões, afirmar aprovação ou reclassificar a própria ação. Toda identidade e vínculo da empresa devem vir de contexto autenticado, não de IDs inventados pelo agente.

## 6. Fluxo da Central de Aprovações

### 6.1 Solicitação

O agente prepara proposta estruturada contendo: produto, empresa, solicitante, agente/versão, ferramenta, recurso, ação, parâmetros materiais, impacto esperado, risco, custo previsto, motivo, expiração e evidências pertinentes. Nenhum segredo ou dado excessivo deve constar da notificação.

O serviço **pausa** a execução em `AGUARDANDO_APROVACAO` (AIA-07) sem disparar a ação.

### 6.2 Encaminhamento

O produto identifica aprovadores habilitados conforme alçada vigente. Definir regras para ausência, substituição temporária, dupla aprovação em riscos especiais e impedimento de autoaprovação quando houver conflito de interesse.

A Central Imagine poderá consolidar **metadados de aprovações para operação/auditoria** quando permitido, sem conceder a um operador global o poder de aprovar indiscriminadamente todas as ações de clientes.

### 6.3 Decisão

Aprovador revisa resumo, efeito, limite, recursos e custo, e escolhe **Aprovar**, **Recusar** ou **Solicitar ajustes**. A decisão deve ser explícita, identificada, rastreável, temporal e compatível com os direitos do aprovador no momento da decisão.

"Silêncio", status visual, mensagem do agente ou e-mail não autenticado não são aprovação.

### 6.4 Retomada segura

Antes de executar, AIA-07/AIA-08 e o produto devem **revalidar** a aprovação, o vínculo entre conteúdo e ação, a validade da identidade, as permissões e o estado do recurso. Alteração relevante de dados ou parâmetros invalida a aprovação antiga. Timeout sem confirmação final exige reconciliação, não repetição cega.

### 6.5 Encerramento

Registrar status `APROVADA`, `REPROVADA`, `EXPIRADA`, `REVOGADA` ou `EXECUTADA` (com evidência do produto). **Aprovar não significa executar**: os dois momentos e o resultado real precisam ser auditados separadamente.

## 7. Estados e expiração

Máquina de estados proposta da **solicitação de aprovação**:

`CRIADA -> PENDENTE -> APROVADA / REPROVADA / EXPIRADA / CANCELADA / REVOGADA`

Após aprovação, acompanhar transição derivada a `EM_EXECUCAO -> CONFIRMADA / FALHA / INDETERMINADA`, com vínculo à execução AIA-07; a decisão original deve permanecer imutável.

Políticas obrigatórias:
- **Prazos configurados por produto, classe e alçada**; não fixar duração arbitrária única.
- Após expiração ou revogação, negar novas ações; não "reativar" aprovação antiga por retentativa.
- Mudança de orçamento, conteúdo publicado, destinatário, parâmetros comerciais, tenant ou ferramenta deve produzir **nova solicitação**, se material.
- Delegações são limitadas no tempo e não sobrevivem a revogação ou encerramento dos direitos originais.
- Ações executadas antes da revogação exigem trilha e eventual compensação pelo produto, nunca reversão automática presumida.

## 8. Permissões por papel e interface

### Central Imagine — administração técnica

Papéis candidatos: Administrador de Políticas, Responsável de Segurança, Curador de Ferramentas, Auditor, Operador de Incidentes. Segregar criar, aprovar e publicar política crítica conforme risco.

Telas propostas:
- Políticas de autonomia por produto/agente/ferramenta/empresa.
- Matriz de risco e alçadas versionadas.
- Visão agregada de aprovações pendentes, expiradas, recusadas e incidentes dentro das permissões da Imagine.
- Auditoria de decisões, alterações e justificativas.
- Suspensão emergencial de ferramenta/agente/operação, sem acesso ampliado às bases de clientes.

### Dentro de cada produto

**CRM:** aproveitar sua Central de Aprovações funcional já planejada. Portal, Performance AI e Universidade terão decisões e permissões locais. Não pressupor SSO entre CRM e Portal.

Telas:
- Meu painel de aprovações permitidas e notificações.
- Proposta com finalidade, ação exata, impacto e prazo.
- Aprovar/Recusar/Solicitar ajustes, quando a política habilitar.
- Histórico de decisão, execução confirmada, falha ou indeterminação.
- Controles de autonomia que o plano/produto efetivamente permita, **sem editar regras globais nem alçadas reservadas**.

## 9. Medidas de defesa e segurança

- Política aplicada no backend: nunca confiar apenas em instruções do agente.
- Verificar isolamento multiempresa, usuário, produto e recurso em **todas** as consultas e mutações.
- Usar tokens de curta duração, escopo mínimo, rotação e auditoria; segredos não são expostos no prompt, UI ou logs.
- Proteger contra prompt injection, engenharia social, aprovações fabricadas e conteúdo externo que tente alterar hierarquia.
- Revisar limites por ação, volume, valor, intervalo e categoria; evitar abuso acumulado por pequenas execuções repetidas.
- Mitigar condições de corrida: aprovação, cancelamento, alteração do recurso e execução podem ocorrer simultaneamente.
- Registrar vínculo imutável entre proposta, parâmetros materiais/hash, revisor, decisão, hora, evidência e resultado.
- Suspensão de segurança/kill switch por ferramenta/agente/versão e, quando necessário, produto/empresa, sem alegar revogação de efeitos já confirmados.

## 10. Custos, auditoria e falhas

- Separar custos técnicos da IA (AIA-03/10), créditos comerciais dos produtos e ações externas com gasto financeiro.
- Alçadas financeiras aplicadas ao impacto concreto da operação, não ao custo apenas da inferência.
- Sem autorização de gasto de mídia pelo simples fato de o cliente contratar Performance AI; responsabilidade pela mídia permanece com a conta/plataforma e as políticas do produto.
- A decisão humana não substitui os controles finais da API destino nem garante sucesso.
- Quando houver falha na Central de Aprovações, deixar execução em espera/bloqueada e sinalizar incidente, não aprovar automaticamente.
- Em caso de falha após executar, identificar estado indeterminado e reconciliar com a fonte de verdade (AIA-07).
- Auditoria proporcional e com minimização de dados, respeitando retenções a definir em AIA-11.

## 11. Interfaces contratuais com outros módulos

| Integração | Contrato funcional esperado |
| --- | --- |
| AIA-01/02 | Template e instruções não criam direitos de execução |
| AIA-03 | Política de custo de modelo/provedor não libera gastos externos |
| AIA-04 | Ferramenta descreve risco/escopos; backend destino decide |
| AIA-05/06 | Conhecimento/memória como evidência, nunca permissão |
| AIA-07 | Pausa, expiração, retomada e confirmação de execução |
| AIA-09 | Testes de matriz de risco, alçada, revogação e corrida |
| AIA-10 | Consumo/alertas/observabilidade sem exposição excessiva |
| AIA-11 | Auditoria, LGPD, políticas e retenção |
| AIA-12 | APIs/eventos versionados de solicitação, decisão, revogação e resultado |

**Contratos e eventos candidatos:** `approval.requested`, `approval.decided`, `approval.expired`, `approval.revoked`, `action.execution.confirmed`; apenas propostas, não APIs implementadas.

## 12. Entidades conceituais — sem SQL

- `agent_autonomy_policies`: escopos, classes, produtos/empresas e versões.
- `agent_approval_policies`: alçadas e regras de aprovação.
- `agent_approval_requests`: operação proposta, versão/hash, solicitante, prazo, estado.
- `agent_approval_decisions`: identidade do aprovador, decisão, justificativa e tempo.
- `agent_approval_delegations`: delegação limitada, revogação e validade.
- `agent_authorization_events`: verificações e negativas, sem dados excessivos.
- `agent_approval_executions`: vínculos com run AIA-07, tentativas e confirmações.
- `agent_policy_incidents`: violações, bloqueios e ações de resposta.

Nenhum schema, banco, endpoint, serviço ou linguagem de programação é aprovado por esta lista.

## 13. Matriz AIA08-D01 a D10 — propostas para aprovação

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA08-D01 | Princípio de autonomia | Autonomia graduada por ação e risco, independente do nível descritivo do agente | Nível N1–N5 concede privilégios automaticamente |
| AIA08-D02 | Autorização efetiva | Interseção de template, produto, tenant, plano, usuário, recurso, ferramenta, risco e aprovação | Permissão derivada de prompt ou contexto sem validação |
| AIA08-D03 | Autoridade do produto | Produto valida e executa cada operação, núcleo coordena políticas e evidências | Núcleo pode ignorar regras do produto |
| AIA08-D04 | Classes de risco | Matriz R0–R4, com R4 negada por padrão e exceções formais | Todas as ações podem ser automáticas |
| AIA08-D05 | Aprovação | Solicitação explícita, aprovada por identidade autorizada; sem aprovação por silêncio ou IA | Aprovação implícita ou "autorização" do modelo |
| AIA08-D06 | Vinculação e validade | Aprovação amarrada a ação/versão/parâmetros, com prazo e revalidação antes de executar | Aprovação genérica sem expiração |
| AIA08-D07 | Alçadas/delegação | Políticas por produto, valor, risco e perfil; segregação e delegação revogável | Qualquer operador pode aprovar tudo |
| AIA08-D08 | Painéis | Central Imagine administrativa + aprovações locais por produto, reaproveitando CRM | Central independente que altera contratos e direitos do CRM |
| AIA08-D09 | Auditoria e contingência | Evidência separada de proposta, decisão, execução, confirmação e revogação; falha segura | Tratar aprovação como execução e continuar em falhas |
| AIA08-D10 | MVP | Leitura/rascunho/criação de tarefa controlada no CRM; aprovação humana para ampliação sensível | Autonomia irrestrita de agentes já no MVP |

**AIA08-D01 a D10 permanecem ABERTAS.** Nenhuma aprovação funcional presumida.

## 14. Casos de teste documentais e critérios de conclusão

Cenários: lead de outro tenant; agente tenta aumentar próprio escopo; aprovação do orçamento expira; aprovação de campanha alterada após decisão; duas aprovações simultâneas; usuário perde permissão; recusa seguida de retry; autorização por texto malicioso de documento; tenant troca na fila; timeout após execução confirmada; limite de orçamento ultrapassado por chamadas repetidas; desligamento emergencial de ferramenta; produto com login independente; operador tenta aprovar própria solicitação acima de sua alçada.

- [ ] Decisões AIA08-D01 a D10 avaliadas/aprovadas ou ajustadas pelo responsável.
- [ ] Papéis, alçadas, dupla aprovação e prevenção de conflitos de interesse detalhados por produto.
- [ ] Ciclo de vida da aprovação conciliado com AIA-07 e API do executor.
- [ ] Matriz de riscos e permissões testável com AIA-04, AIA-09 e AIA-12.
- [ ] Retenção, auditoria, privacidade e incidentes conciliados com AIA-11.
- [ ] Interfaces locais de CRM, Portal, Performance AI e Universidade aprovadas em seus próprios projetos.
- [ ] Requisitos de segurança, implementação e deploy avaliados futuramente em gates separados.

**Bloqueios mantidos:** documentação apenas; sem programação, testes reais, chaves, APIs, decisões financeiras, mudanças em outros repositórios, infraestrutura, despesas ou deploy.
