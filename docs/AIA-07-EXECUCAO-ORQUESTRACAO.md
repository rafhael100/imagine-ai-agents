# AIA-07 — Execução, Filas e Orquestração de Agentes | Proposta funcional v0.1

> **Situação:** proposta documental, **AIA07-D01 a D10 ABERTAS** para avaliação.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-06 com diretrizes funcionais aprovadas; todos os gates de arquitetura técnica, segurança detalhada, fornecedores, custos e desenvolvimento seguem abertos.

## 1. Objetivo e fronteiras

O módulo AIA-07 coordenará **execuções de agentes** individuais e, em fases posteriores, **equipes de agentes especialistas**. Receberá solicitações autenticadas dos produtos, decidirá o encadeamento de etapas autorizadas, acompanhará estado, erros, custos e confirmações.

O serviço não assume o comando direto de bancos de dados, campanhas, contratos nem do motor de automações nativo do CRM.

**Divisão proposta:**
- **CRM Imagine:** dono do construtor visual, gatilhos, condições e automações definidas por clientes. Invoca capacidades IA por contrato.
- **Imagine AI Agents:** executor e coordenador de tarefas IA, ferramentas homologadas, estados, políticas e métricas.
- **n8n central Imagine:** orquestração de rotinas internas controladas pela Imagine, quando apropriado e sujeito à homologação/licenciamento comercial; não disponibilizado como editor irrestrito aos clientes.
- **Portal, Performance AI e Universidade:** donos das permissões, dados, fluxos comerciais e interfaces de seus produtos.

## 2. Modos de execução

| Modo | Quando usar | Requisitos |
|---|---|---|
| Síncrono | Resposta curta sob tempo máximo | Autorização, limite de custo/latência, timeout |
| Assíncrono | Processamento mais longo, análises e filas | ID de execução, status consultável, retries controlados |
| Agendado | Tarefa em data/hora autorizada | Gatilho pertence ao produto/motor responsável; validade, fuso e cancelamento |
| Acionado por evento | Lead recebido, atualização de campanha, ação do operador | Fonte autenticada, idempotência, validação e escopo |
| Com aprovação | Ação sensível exige decisão humana | Pausa com estado verificável; AIA-08 define alçadas e decisão |
| Multiagente (fase posterior) | Especialistas cooperam sob supervisor | Divisão de papéis, permissões por agente, limites de delegação e custo |

Agente não pode criar agendamentos indefinidos, reexecutar a si mesmo recursivamente ou abrir agentes auxiliares sem quotas e permissão explícita.

## 3. Estados do ciclo de vida da execução

Proposta de máquina de estados:
- `RECEBIDA`: solicitação com identificador e origem verificáveis.
- `VALIDANDO`: identidade, tenant, produto, finalidade, elegibilidade, cotas e versão do agente.
- `ENFILEIRADA`: aguardando capacidade, com prioridade e expiração.
- `EXECUTANDO`: ação de inferência e/ou ferramentas autorizadas em andamento.
- `AGUARDANDO_APROVACAO`: fluxo de AIA-08; não executar ações pendentes por timeout.
- `AGUARDANDO_DEPENDENCIA`: serviço indisponível, evento ou condição externa; retorno controlado.
- `CONCLUIDA`: resultado e efeitos confirmados.
- `FALHOU`: erro terminal registrado, sem confirmação de conclusão.
- `CANCELADA`: solicitações futuras interrompidas; efeitos já confirmados não são revertidos implicitamente.
- `EXPIRADA`: prazo ultrapassado antes de conclusão autorizada.
- `INDETERMINADA`: resultado externo não confirmado; exige reconciliação antes de repetir.

As transições precisam ser explícitas, testáveis, autorizadas e auditáveis. Proibir transição diretamente de erro ou aprovação pendente para sucesso sem comprovação.

## 4. Pipeline de execução

1. **Recepção:** produto envia solicitação assinada/autenticada com `product`, empresa, versão do agente, solicitante, finalidade, correlação e chave de idempotência.
2. **Autorização inicial:** validar escopos e plano junto ao domínio proprietário, sem confiar em texto livre do prompt.
3. **Reservas e limites:** pré-verificar crédito, cotas, concorrência, janela, políticas e orçamento máximo.
4. **Preparação:** montar contexto mínimo conforme AIA-02/05/06, respeitar residência/transferência de dados e modelos homologados AIA-03.
5. **Planejamento:** definir etapas viáveis; cada chamada externa deve passar pelas políticas e pelo catálogo AIA-04.
6. **Execução:** rodar somente operações aprovadas, com limite de passos, tokens, tempo, custo, repetições e delegações.
7. **Aprovação quando aplicável:** pausar, gerar solicitação de AIA-08 vinculada à ação exata, retomar só após verificação de validade.
8. **Confirmação:** reconciliar resultados oficiais com o produto; timeout não equivale a falha sem efeito.
9. **Finalização:** emitir resultado estruturado, estado final e métricas técnicas, preservando privacidade.
10. **Auditoria:** registrar timestamps, versões, decisões de autorização, uso de ferramentas, custos e incidentes mínimos necessários.

## 5. Orquestração multiagente (etapa posterior)

Padrão recomendado: **agente coordenador** divide a tarefa em subetapas e delega a especialistas homologados, com políticas de escopo e orçamento explícitas.

Exemplo conceitual Performance AI:
1. Analista produz evidências e métricas autorizadas.
2. Estrategista propõe ajuste com justificativa.
3. Criativo prepara rascunho de texto/peça conforme modalidade liberada.
4. Supervisor de política verifica compatibilidade e necessidade de aprovação.
5. Executor solicita ferramenta de publicação somente após autorização válida; o produto confirma o resultado.
6. Auditor consolida eventos e alerta para falhas.

**Não permitir:** especialista elevar seu próprio privilégio; aprovação verbal de outro agente como substituto de autorização humana; transferência irrestrita de histórico entre agentes; loops de delegação sem limite.

Agentes especialistas podem usar o mesmo modelo de IA com instruções diferentes; criar múltiplos processos físicos não é requisito.

## 6. Filas, prioridades e concorrência

- Isolar e limitar concorrência por empresa, produto, agente, ferramenta e provedor; proteger um tenant contra sobrecarga causada por outro.
- Definir filas por categoria de urgência e requisitos de recursos, sujeitas a limites e fairness.
- Prioridade alta não pode ignorar autorização, limite de gastos ou aprovações.
- Evitar starvation de tarefas de baixa prioridade; prever envelhecimento, janela de execução e descarte seguro de tarefas vencidas.
- Tratar eventos duplicados, falhas na fila, worker interrompido, deploy, corrida entre pausa e confirmação e consumo de limite.
- Controlar backpressure, taxa por provedor e circuit breaker de dependências.
- Retenção de payload e logs segundo AIA-11, sem persistir informações sensíveis além do necessário.

## 7. Idempotência, retries e confirmação externa

- Toda solicitação com potencial de efeito colateral deve ter identificação inequívoca e chave de idempotência vinculada a recurso/tenant/ação.
- O produto destino realiza validação final e protege contra duplicação de efeitos.
- Timeout, indisponibilidade ou resposta perdida devem resultar em reconciliação com a origem, nunca reenvio cego de ações financeiras, publicações ou mensagens.
- Retentativas com limite, backoff, jitter e condições homologadas; não presumir entrega exatamente uma vez por infraestrutura.
- Estados `INDETERMINADA` e `AGUARDANDO_DEPENDENCIA` precisam de alertas, expiração e responsáveis claros.
- Compensação ou reversão de efeitos é competência do serviço dono da operação e depende de ação autorizada.
- Sinalizar ao usuário resultados parciais e pendências sem apresentar sucesso artificial.

## 8. Autonomia, risco e aprovação (AIA-08)

- O agente pode propor etapas, mas cada tool exige capacidade autorizada e validação de risco antes de executar.
- Operações R0/R1/R2/R3/R4 seguem a política de AIA-04 e os fluxos de AIA-08.
- A aprovação deve estar vinculada a versão da ação, parâmetros relevantes, tenant, solicitante, prazo e aprovador; alteração relevante invalida aprovação antiga.
- Suspensão, revogação de autorização ou encerramento de contrato interrompem novas etapas, mesmo que o contexto seja válido.
- Botão de parada operacional para execuções e agentes deve estar disponível à equipe autorizada; sua ação não desfaz efeitos já confirmados.
- Sem autoalteração de regras, aumento de limites, criação de ferramentas ou publicação de novas versões pela IA sem fluxo de aprovação.

## 9. Visibilidade e interface

**Central Imagine:**
- Painel de execuções, filas, dependências, falhas, recursos e custos.
- Histórico da execução com etapas, timestamps, versões, ferramentas, aprovações e decisões.
- Painel de filas/limites, incidentes, pausa e retomada controlada; indicadores por produto sem quebrar o isolamento.
- Administração de políticas e templates pela Imagine, com segregação de funções.

**Dentro do produto:**
- Status: em andamento, aguardando aprovação, concluído, falhou, cancelado, resultado parcial/indeterminado.
- Linha do tempo simples e justificativas de bloqueio; sem exibir segredos nem prompts internos.
- Controles permitidos: solicitar execução, cancelar, rever aprovação, consultar resultados e reexecutar quando seguro.
- Notificações por eventos/SLAs sob responsabilidade do produto, sem depender da IA para decidir se houve sucesso.

## 10. Observabilidade, custo e qualidade

- Medir execução e subexecução: empresa, produto, agente/versão, modelo, tokens/unidades, custos, duração, uso de ferramentas e estado final.
- Limites por execução, por empresa e por período; custo técnico controlado pelo núcleo, créditos comerciais sob responsabilidade do produto.
- Métricas de qualidade: taxa de conclusão confirmada, falha, intervenções humanas, retries, timeout, duplicidades evitadas, custo e latência.
- Avaliar cenários de erro, interrupção, concorrência, tenant trocado, execução duplicada, aprovação expirada e custos excedidos antes de implementar.
- Trilha de auditoria nunca deve guardar segredos ou conteúdo pessoal além do necessário.
- Roteamento para modelos e provedores passa por AIA-03; falha segura quando não existir alternativa homologada.

## 11. Entidades conceituais (sem SQL)

- `agent_runs`: identidade da execução, tenant, produto, solicitante, agente/versão, finalidade e estado.
- `agent_run_steps`: etapa, ordem, responsável, dependências, entradas/saídas referenciadas e status.
- `agent_run_checkpoints`: progresso e estado recuperável com segregação.
- `agent_run_events`: trilha de estados, autorizações, aprovações, erros e decisões.
- `agent_run_dependencies`: esperas por ferramentas, eventos ou aprovações.
- `agent_run_budgets`: reservas e limites técnicos.
- `agent_run_idempotency`: chave, escopo, validade e resultado confirmado.
- `agent_delegations`: vínculo coordenador-especialista, permissões, limites e resultado.
- `agent_run_incidents`: classificação, impacto, investigação e resolução.
- `agent_run_notifications`: intenção de notificação e confirmação de entrega pelo produto.

Os nomes são conceituais e não determinam infraestrutura, tabela ou implementação.

## 12. Propostas de decisão AIA07-D01 a D10 (ABERTAS)

| ID | Tema | Alternativa A — recomendada | Alternativa B |
|---|---|---|---|
| AIA07-D01 | Responsabilidade | Núcleo executa tarefas IA; produtos mantêm regras/fluxos e motor de automações CRM | Centralizar todas as automações de clientes no núcleo |
| AIA07-D02 | Modos | Síncrono e assíncrono; agendamentos/eventos originados pelos produtos | Toda execução síncrona e sem rastreio |
| AIA07-D03 | Ciclo de vida | Estados explícitos, checkpoints e resultado externo confirmado | Status simples ativo/concluído sem reconciliação |
| AIA07-D04 | Filas/limites | Concorrência, prioridades, fairness e orçamento por tenant/produto/agente | Filas globais sem limites por cliente |
| AIA07-D05 | Idempotência | Chaves por escopo, retries seguros e reconciliação de efeitos | Repetir toda ação automaticamente após timeout |
| AIA07-D06 | Multiagentes | Coordenador + especialistas homologados, limites de delegação, fase posterior | Delegação irrestrita desde o MVP |
| AIA07-D07 | Aprovações | Pausa verificável e retomada somente com autorização AIA-08 | IA considera aprovação verbal suficiente |
| AIA07-D08 | Operação | Cancelamento/pausa/retomada controlados, auditoria e observabilidade | Fluxos invisíveis sem controles de operador |
| AIA07-D09 | Integração n8n | n8n restrito a fluxos internos Imagine e com licenciamento validado | n8n como motor de automações livre para clientes |
| AIA07-D10 | MVP | Agente único CRM, fila de tarefas, checkpoints, consulta e criação controlada de tarefas | Multiagentes e integrações simultâneas desde o início |

**Todas AIA07-D01 a D10 permanecem ABERTAS**, sem autorização de código, ferramentas, fornecedores, infraestrutura, licenciamento, testes reais ou deploy.

## 13. Exemplos e casos de teste documentais

- Novo lead recebido duas vezes; tarefa no CRM não pode ser duplicada.
- Aprovação expira durante espera e execução não deve prosseguir.
- Provedor IA falha após gerar sugestão, mas antes de ação externa.
- CRM executa criação de tarefa, mas resposta é perdida: reconciliação sem recriar.
- Dois tenants dividem worker/fila, sem vazamento de contexto ou custo.
- Execução é cancelada no momento em que ação externa já foi confirmada.
- Modelo muda entre planejar e executar; compatibilidade e autorização devem ser revalidadas.
- Especialista solicita ferramenta acima do escopo do coordenador: negar.
- Quota/comercial do cliente expira durante fluxo: suspender novas etapas.
- Queda de servidor com checkpoint pendente: retomar conforme política, sem efeitos duplicados.

## 14. Critérios documentais de aprovação

- [ ] AIA07-D01 a D10 aprovadas ou corrigidas.
- [ ] Estados, transições e responsáveis revisados.
- [ ] Fronteiras entre CRM automações, Imagine AI Agents e n8n homologadas.
- [ ] Contratos de tarefas/eventos com AIA-12 definidos.
- [ ] Alçadas de AIA-08, idempotência de AIA-04 e retenção de AIA-11 conciliadas.
- [ ] Política de custos, cotas e qualidade de AIA-03/09/10 revisada.
- [ ] MVP, critérios de teste e recuperação de falhas detalhados.
- [ ] Definição posterior da stack, filas, armazenamento e deploy somente com autorização expressa.

**Gates mantidos:** planejamento exclusivamente documental; sem programação, APIs reais, credenciais, contratação, consumo pago, provisionamento, mudanças nos outros repositórios ou deploy.
