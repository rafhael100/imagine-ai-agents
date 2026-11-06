# AIA-10 — Monitoramento, Custos e Observabilidade | Proposta funcional v0.1

> **Situação:** planejamento documental; decisões **AIA10-D01 a D10 ABERTAS** para aprovação.  
> **Data de referência:** 2026-11-06.  
> **Precedência:** AIA-01 a AIA-09 possuem diretrizes funcionais aprovadas. A arquitetura técnica, fornecedores, custos específicos e implementação continuam bloqueados.

## 1. Objetivo

Dar à Imagine capacidade de **acompanhar cada execução de agente, diagnosticar falhas, medir o custo real e estimado, aplicar limites, gerar alertas e investigar incidentes**, preservando a separação por empresa e por produto. As interfaces de cada produto devem mostrar ao cliente somente os dados e créditos que lhe pertencem e são autorizados.

AIA-10 cuida de **observabilidade técnica**. Não substitui:
- O mecanismo de cobrança, planos, créditos e saldos comerciais dos produtos.
- A autorização de operações e aprovação de gastos prevista em AIA-04/AIA-08.
- A gestão de contratos e evidências formais de conformidade de AIA-11.
- Os registros transacionais dos produtos, que permanecem fontes de verdade.

## 2. Classes de observabilidade

| Camada | Exemplos de indicadores | Responsabilidade |
|---|---|---|
| Saúde técnica | Latência, disponibilidade observada, taxas de erro e filas | Núcleo Imagine AI Agents |
| Execução | Agente/versão, steps, status, bloqueios, retries e confirmações | AIA-07 e AIA-10 |
| Modelos | Provedor/modelo/versão, tokens/unidades, timeouts, fallback e preços de referência | AIA-03 e AIA-10 |
| Ferramentas | Chamadas, autorizações negadas, respostas, duplicidades e idempotência | AIA-04, AIA-08 e produto dono |
| Qualidade | Resultados de avaliação, regressões e versões reprovadas | AIA-09 |
| Conhecimento/memória | Processamento de fontes, recuperação, falhas, expurgo e retenção | AIA-05/06, AIA-11 |
| Comercial | Créditos disponíveis, plano, excedentes e preço ao cliente | Produto responsável; AIA-10 informa consumo técnico |
| Segurança | Acessos negados, falhas de isolamento, incidentes e política aplicada | AIA-08/11 + segurança dos produtos |

## 3. Modelo de eventos e correlação

Cada execução deve ter um **identificador de correlação** e eventos encadeáveis: entrada, validação de permissão, reserva de orçamento, preparação de contexto, seleção de modelo, chamada, ferramenta, solicitação de aprovação, confirmação, falha e encerramento.

Campos candidatos:
- `run_id`, `trace_id`, `span_id`, `event_id` e referências a execução pai/delegação.
- Produto, identificador seguro de empresa, agente, instância, versão e operação.
- Tipo de evento, data e hora padronizada, duração, status, origem e destino.
- Provedor/modelo/versão, tokens ou outras unidades quando fornecidas.
- Custo estimado, custo confirmado/conciliado, moeda, tarifa e vigência de preço.
- Política/escopo de autorização aplicado, referência da aprovação quando houver.
- Categoria de falha e ação de recuperação, sem segredos nem dados pessoais desnecessários.
- Referências a versões de prompts e conhecimento, sem copiar o conteúdo integral por padrão.

A estrutura não implica armazenamento universal de prompts, documentos privados, segredos ou textos completos de atendimento. A granularidade e retenção seguem finalidade, riscos e AIA-11.

## 4. Custos: três conceitos separados

1. **Custo técnico do fornecedor:** o que a Imagine gasta com provedor, modelo, processamento, armazenamento ou ferramentas, em moeda e vigência específicas.
2. **Consumo técnico do agente:** tokens, chamadas, processamento, indexação, armazenamento e outros recursos associados à operação.
3. **Créditos/preço comerciais:** o que o produto contabiliza para o cliente segundo seu plano, limites, promoções e autorizações.

É proibido tratar essas medidas como se fossem iguais. AIA-10 encaminha dados técnicos para o produto aplicar sua política comercial, **sem movimentar saldos ou cobrar o cliente por conta própria**.

Proposta de fluxo:
- Antes da execução, consulta/validação de elegibilidade e orçamento do produto quando aplicável.
- Reserva técnica controlada para evitar concorrência excessiva e excedentes inesperados.
- Registro do consumo informado pelo fornecedor, diferenciado de estimativa.
- Cálculo de custo pela tabela vigente e homologada.
- Conciliação posterior com fatura/relatório do fornecedor quando suportado, preservando divergências e ajustes.
- Envio de eventos idempotentes ao produto para contabilização comercial.
- Tratamento de duplicações, eventos atrasados, uso sem medição e contestação.
- Apresentar custos como `estimado`, `medido` ou `conciliado`, nunca como fatura definitiva sem evidência.

Não há preços fixados nem provedores contratados nesta etapa.

## 5. Limites e proteção de gastos

- Políticas por empresa, produto, plano, agente, provedor/modelo, operação e período.
- Limites de custo por execução, por período e por categoria de tarefa, incluindo subagentes e retries.
- Alertas de aproximação de orçamento e bloqueio seguro após esgotamento, conforme regras homologadas.
- Respeitar excedentes comerciais somente quando autorizados pelo domínio do produto.
- Definir reserva/liberação de orçamento atômica, evitando que execuções concorrentes excedam o limite.
- Tratamento de custo de tarefas falhadas, canceladas ou com resposta indeterminada, com trilha e reconciliação.
- Evitar que um agente aumente por conta própria suas quotas ou troque de provedor para fugir do limite.

**Thresholds quantitativos** (ex.: percentual para alertar, valor por mês, limites de tokens) são decisões comerciais/técnicas futuras; não presumir número único.

## 6. Alertas, incidentes e escalonamento

### Classes propostas
- **Informativo:** aumento de uso, tendência de custo, nova versão observada.
- **Atenção:** degradação de latência, erro recorrente, custo acima do projetado, fila crescente.
- **Crítico:** suspeita de vazamento de dados, ferramenta executando sem autorização, gastos não permitidos, indisponibilidade grave, duplicação de efeito externo ou perda de auditabilidade.

Os alertas deverão ser deduplicados, correlacionados, priorizados e enviados a responsáveis adequados, respeitando janelas, plantões e confidencialidade. Um incidente crítico pode **suspender preventivamente** ferramenta/agente conforme política de segurança, mas a IA não inventa uma aprovação ou alega que efeitos externos foram revertidos.

Fluxo candidato:
1. Detectar evento/anomalia por regra e evidência.
2. Classificar gravidade e impacto (produto, empresas afetadas, recursos).
3. Abrir incidente com responsável, horário, trilha e contexto mínimo.
4. Aplicar bloqueios/contingência previstos sem ultrapassar alçadas.
5. Investigar, reconciliar resultados externos e registrar mitigação.
6. Confirmar restauração, avaliar causas e produzir ações de prevenção.
7. Incorporar falha como cenário de regressão AIA-09.

A ausência de métricas não é evidência de saúde. Perda de telemetria deve ser observável.

## 7. Painel interno e painéis dos produtos

### Central Imagine

- Visão geral de saúde por produto, agentes, provedores e filas.
- Taxa de erro, latência p50/p95, jobs concluídos, pendentes, bloqueados e indeterminados.
- Consumo técnico e custo estimado/conciliado por provedor, operação, empresa e período, respeitando acessos.
- Alertas de orçamento, tendência, falhas e incidentes com filtros e responsáveis.
- Drill-down por `run_id` e `trace_id` sem exposição indiscriminada de mensagens privadas.
- Histórico de versões, publicações, suspensão de agentes, ferramentas e problemas de qualidade.
- Auditoria de visualizações, exportações e alterações de configuração.

### Dentro do CRM, Portal, Performance AI e Universidade

- Consumo e saldo **calculados pelo próprio produto** conforme sua regra comercial.
- Quantidade de execuções, agente, período, status, resultados e alertas pertinentes.
- Limites, avisos e solicitação de autorização de excedentes, quando previstos pelo plano.
- Histórico dos próprios agentes; não permitir consultar telemetria, custos internos da Imagine ou dados de outros clientes.
- Controles de exportação e acesso sujeitos ao produto e às políticas de retenção.

**Não duplicar** a contabilidade comercial de cada produto nem obrigar todos os produtos a compartilhar o mesmo painel de clientes.

## 8. Segurança, privacidade e retenção

- Aplicar ACL por empresa, produto, papel, finalidade, operação e recurso em consultas e dashboards.
- Minimizar e mascarar dados nos logs; jamais armazenar tokens, chaves, documentos privados completos ou prompts integrais por padrão.
- Separar rastreamento técnico, histórico de produto e evidência de auditoria para evitar retenção indiscriminada.
- Definir prazos por categoria de evento, finalidade, obrigação legal e contrato (AIA-11); não estabelecer prazo global sem análise.
- Auditar acesso e exportação de métricas e históricos, inclusive operadores internos.
- Estruturas de backup e recuperação devem preservar integridade e políticas de exclusão, sem reintroduzir dados indevidos.
- Garantir que o produto receba somente eventos necessários e não uma cópia irrestrita de toda a telemetria central.
- Proteger consultas agregadas contra revelação indireta de dados de empresas pequenas.

## 9. Qualidade das métricas e confiabilidade operacional

- Medidas devem diferenciar eventos recebidos, tentativas, ações confirmadas e estados indeterminados.
- Indicadores de execução concluída não podem ser derivados só de uma resposta textual do modelo.
- Agregações e relatórios devem tratar relógios fora de sincronia, eventos duplicados/atrasados, campos ausentes e correções retroativas.
- Manter regras de deduplicação, correlação e conciliação com referência aos eventos originais.
- Testar observabilidade sob falhas: serviço de métricas indisponível, fila de eventos atrasada, trace parcial, divergência de consumo e mudanças de versão.
- Alertas operacionais nunca devem se tornar vetor de vazamento de credenciais ou dados sensíveis.
- Observabilidade do próprio mecanismo de observabilidade deve ser planejada: falha de coleta, atraso e perda de eventos.

## 10. Dependências entre módulos

| Módulo | Integração esperada |
|---|---|
| AIA-01/02 | Versões de agentes/instruções vinculadas a execuções e publicações |
| AIA-03 | Tarifas e medições por provedor/modelo, incluindo fallback |
| AIA-04 | Uso de ferramenta, efeito e autorização efetiva |
| AIA-05/06 | Processamento de documentos, contexto/memória e retenção |
| AIA-07 | Estados, checkpoints, filas, tarefas e idempotência |
| AIA-08 | Aprovações, alçadas, suspensões e incidentes |
| AIA-09 | Resultados de avaliação, regressões e baselines |
| AIA-11 | Privacidade, evidências, auditoria, integridade e retenção |
| AIA-12 | Contratos de eventos e telemetria com cada produto |

## 11. Entidades conceituais, sem SQL

- `agent_telemetry_events`: eventos versionados e correlação.
- `agent_metric_aggregations`: agregados e indicadores por escopo.
- `agent_provider_usage`: unidades medidas por provedor/modelo, com status.
- `agent_usage_costs`: tarifa, moeda, estimativa, medição e conciliação.
- `agent_budget_policies`: limites, janelas e ações de alerta/bloqueio.
- `agent_budget_reservations`: reservas e liberações para concorrência.
- `agent_alert_rules`: políticas, canais e responsáveis.
- `agent_alert_instances`: eventos, deduplicação e resolução.
- `agent_incidents`: severidade, impacto, responsável e estado.
- `agent_observability_audit`: acessos, exportações e mudanças de políticas.

Nomes, bancos, formatos de métricas, stack, ferramentas, provedores e preços continuam em estudo.

## 12. Matriz AIA10-D01 a D10 — propostas para aprovação

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA10-D01 | Monitoramento | Observabilidade central de execuções, serviços e provedores, segregada por produto/empresa | Cada agente registra logs isolados, sem correlação |
| AIA10-D02 | Rastreabilidade | Eventos e traces com IDs de correlação, versões, confirmação e erros padronizados | Histórico textual livre sem estados verificáveis |
| AIA10-D03 | Consumo/custo | Medir unidades, custo estimado/medido/conciliado, moedas e preços versionados | Informar apenas custo presumido fixo |
| AIA10-D04 | Créditos comerciais | Núcleo registra consumo técnico; cada produto continua dono de créditos, planos e cobrança | Núcleo altera saldos e preços de todos os produtos |
| AIA10-D05 | Orçamentos | Reservas, limites e bloqueios por empresa/produto/agente/período; excedentes sujeitos à autorização | Consumo ilimitado até a fatura chegar |
| AIA10-D06 | Alertas | Regras por gravidade, deduplicação, notificação e escalonamento | Alertas manuais após reclamação do cliente |
| AIA10-D07 | Incidentes | Processo rastreável de detecção, mitigação, reconciliação e melhoria | Reiniciar automaticamente ações sem confirmação |
| AIA10-D08 | Painéis | Central técnica Imagine e visões locais por produto/cliente, com ACL | Um painel global com dados irrestritos |
| AIA10-D09 | Privacidade | Logs minimizados, acesso auditado, retenção por finalidade e controle de derivados | Guardar prompts e dados privados ilimitadamente |
| AIA10-D10 | MVP | Métricas de execução, tokens, custo, erro, limites e alertas básicos no piloto CRM | Observabilidade e cobrança complexas de todos os produtos desde o lançamento |

**AIA10-D01 a D10: ABERTAS, aguardando aprovação funcional do responsável.**

## 13. Casos de teste documentais e critérios futuros

Cenários: execução do CRM com erro e consumo parcial; dois tenants concorrentes esgotando limites; duplicação de evento de consumo; preço do provedor atualizado; API não informa uso; fatura divergente; ferramenta executada apesar de timeout; aprovação de excedente expirada; alerta crítico sem entrega; painel exibindo dados de outro tenant; métricas ausentes; revogação de acesso a relatório; mudança de status após reconciliação.

- [ ] AIA10-D01 a D10 aprovadas ou corrigidas explicitamente pelo responsável.
- [ ] Dicionário de eventos, estados e custo com AIA-03/04/07/08/12 definido.
- [ ] Política de métricas, ACL, LGPD, retenção e auditoria conciliada com AIA-11.
- [ ] Regras e prazos de alertas/incidentes, responsabilidades e resposta definidos.
- [ ] Metas/limiares de custo e confiabilidade aprovados por produto e caso de uso.
- [ ] Contratos de consumo técnico e conciliação com a cobrança dos produtos detalhados.
- [ ] Piloto CRM e critérios de observabilidade homologados documentalmente.
- [ ] Stack, custos de infraestrutura e provedores escolhidos apenas em aprovação técnica futura.
- [ ] Implementação, uso de APIs reais, despesas e deploy somente por autorização expressa separada.

**Bloqueios mantidos:** documentação somente; sem código, dados reais de clientes, integração técnica, criação de credenciais, contratação, execução de testes reais, provisionamento, alterações em outros repositórios ou deploy.
