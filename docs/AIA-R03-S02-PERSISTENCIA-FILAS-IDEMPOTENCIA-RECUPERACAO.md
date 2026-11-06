# AIA-R03/S02 — Persistência, Filas, Idempotência e Recuperação | Proposta documental v0.1

> **Estado:** diretrizes **R03-S02-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente preliminares; R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.  
> **Data de referência:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05, AIA-R02/R01–R06 e AIA-R03/S01 ratificados apenas como **diretrizes documentais preliminares**.  
> **Gates:** R01-G01 a G11 TODOS ABERTOS, sobretudo G03/G05/G06/G07/G08/G10/G11; CORE/SEG/AUT e demais bloqueios próprios do CRM permanecem abertos.  
> **Restrições:** nenhum banco, fila, cache, provedor, servidor, região, framework ou padrão físico está homologado. Não implementar código, SQL, ALLOW, APIs, credenciais, rotinas executáveis, testes reais, usar dados privados, contratar, provisionar ou implantar.

## 1. Objetivo e princípios

Comparar **modelos técnicos candidatos** para armazenar estados de agentes de IA, organizar trabalho assíncrono e preservar idempotência, recuperação, isolamento multiempresa e rastreabilidade sob custo controlado. O recorte cobre a **responsabilidade arquitetural** do núcleo AIA, não a execução do motor de automações do cliente, que permanece nativo no CRM.

O primeiro fluxo futuro permanece **CRM → AIA → CRM**, com:
- leitura de dados autorizados de lead/atendimento;
- resumo e classificação **sugeridos**, sem escrita automática;
- eventual registro de resumo/criação de tarefa com contrato, alçada e autorização específicos do backend CRM;
- confirmação de efeitos somente pelo CRM;
- nenhuma transmissão de dados reais ou integração operacional nesta etapa.

**Invariante de negócio:** uma tentativa de ferramenta, uma confirmação de transporte ou uma resposta do LLM não equivale à confirmação do efeito no CRM.

## 2. Domínios de persistência: separar dados e autoridade

| Domínio lógico | Fonte de verdade | Dados documentais candidatos | Política mínima |
| --- | --- | --- | --- |
| Execução AIA | Núcleo | `run_ref`, `step_ref`, versão, estado, tenant/produto/finalidade e timestamps | Isolamento, ACL, estados duráveis e expiração por finalidade |
| Intenção de efeito | AIA + produto executor sob contrato | `intent_ref`, idempotency key, payload material referencial, destino, política | Não duplicar ações nem reutilizar autorização de outro tenant |
| Aprovação | Sistema designado pelo produto | Referência, objeto/material versionado, alçada, decisor, vigência e revogação | Aprovação não confirma efeito e não amplia permissão |
| Tentativas de ferramenta | Núcleo | `attempt_ref`, destino, estado de transporte, erro seguro e correlação | Retries não devem recriar intenção |
| Efeito confirmado | CRM para tarefa/resumo | `effect_ref` e confirmação oficial | Fonte de verdade permanece no CRM |
| Uso/custo técnico | Núcleo | Referência de run/tentativa, modelo/versão, unidades de consumo e status | Diferenciar uso efetivo e crédito comercial CRM |
| Eventos e auditoria | Produtor e custodiante definidos | `event_ref`, versão, contexto autorizado, decisão, resultado | Payload mínimo, acesso restrito e integridade |
| Contexto/derivados | Produto e custodiante formal | Trechos autorizados, memória, cache, RAG e checkpoint | Minimização, classificação, retenção e expurgo |
| Conhecimento CRM | CRM | Leads, mensagens, anexos, tarefas, planos e vínculos de usuário | **Nunca** replicar banco inteiro para resolver fila AIA |

**Nenhum identificador ou campo acima representa tabela, coluna, SQL, payload API ou permissão real.**

## 3. Alternativas para armazenamento durável (comparação, sem escolha)

| Abordagem | Vantagem potencial | Tradeoff/atenção | Verificação antes de decisão |
| --- | --- | --- | --- |
| **P-A: banco transacional relacional para estados e intenções** (diretriz candidata) | Integridade de estados e deduplicação; auditoria relacional; operações condicionais | Contenção de escrita e gerenciamento de conexões; escalabilidade de workers | Carga, custos, transações, níveis de isolamento e recuperação |
| P-B: documentos/NoSQL para todos os estados | Flexibilidade e escalabilidade de certos acessos | Semântica de transição/idempotência e integridade depende do produto escolhido | Garantias condicionais, concorrência, custo e consistência |
| P-C: somente memória/cache volátil | Simplicidade aparente e latência | Perda de estado/intenções após reinício, risco de efeitos duplicados | **Não atende** aos requisitos de durabilidade das ações sensíveis |
| P-D: estado externo hospedado pelo produto | Produtos mantêm controle completo | Acoplamento alto, integração complexa e replicação de orquestração | Limites contratuais entre CRM e AIA |

PostgreSQL é um **candidato conhecido no ecossistema Imagine**, mas **não foi selecionado** para o núcleo AIA por este documento. A diretriz é exigir **estado durável e semântica transacional comprovável**, independentemente do fornecedor.

## 4. Alternativas para filas e execução assíncrona

| Abordagem | Benefícios | Riscos/limites | Situação |
| --- | --- | --- | --- |
| **Q-A: fila durável com workers controlados e DLQ/quarentena** (diretriz candidata) | Separação entre pedido e execução, retries controlados, backpressure e monitoramento | Exige deduplicação no consumidor e política por tenant, operação e retenção | Sem tecnologia escolhida |
| Q-B: fila acoplada a banco transacional | Menor número potencial de serviços para MVP | Capacidade e concorrência podem competir com estado | Avaliar limites e custo na arquitetura |
| Q-C: broker/event bus dedicado | Escala e acoplamento por eventos, potencial de replay | Operação/custo/contratos/ordenação mais complexos | Avaliar depois de requisitos e demanda |
| Q-D: executar sempre de modo síncrono por HTTP | Simples para inferência curta | Longas operações, timeout e indisponibilidade causam riscos | Não usar como solução geral de tarefas duráveis |

**Q-A é princípio lógico**, não autorização de Redis, RabbitMQ, Kafka, SQS, Laravel Queue, n8n ou qualquer instalação. O contrato deve admitir entrega repetida e fora de ordem: **efeito idempotente**, não promessa de entrega exatamente uma vez.

## 5. Ciclo de vida de mensagens e workers

1. **Recepção:** autenticar emissor, tenant, principal e versão de contrato; aplicar limites de tamanho, quotas e classificação.
2. **Deduplicação do pedido:** resolver identidade de `run/intent` para a mesma solicitação autorizada; nova tentativa não vira nova intenção por padrão.
3. **Registro durável:** persistir estado inicial e intenção necessária antes de confirmar aceite durável, com atomicidade adequada.
4. **Enfileiramento:** disponibilizar etapa ao worker sob contrato consistente com registro durável, sem perder pedido no intervalo.
5. **Retirada por worker:** obter reserva/lease limitada, bloquear concorrência indevida e verificar identidade/tenant antes do processamento.
6. **Revalidação:** conferir vínculo, delegação, recurso, aprovação, política, ferramenta, cota e prazo antes de leitura ou efeito.
7. **Execução:** registrar progresso/checkpoint com payload mínimo; aplicar timeout/limites por operação.
8. **Confirmação:** resultado de inferência sem escrita não equivale a efeito CRM; para ferramenta com efeito, só CRM fornece confirmação.
9. **Reconciliação:** em timeout de operação potencialmente efetiva, consultar CRM por intenção; não retentar cegamente.
10. **Encerramento/quarentena:** registrar conclusão/falha/expiração/cancelamento ou indeterminação, com escalonamento e retenção permitidos.

Em ambiente futuro, a coordenação entre estado e fila deverá impedir tanto **job perdido após aceite** quanto **job duplicado gerando efeito duplicado**. Avaliar outbox/inbox ou alternativa equivalente, **sem aprovar implementação específica**.

## 6. Identidade de intenção, atomicidade e idempotência

**Regra funcional já ratificada:** separar `run`, `step`, `intent`, `approval`, `attempt`, `effect` e `usage`.

Para operações sensíveis (`crm.summary.persist`, `crm.task.create`), exigir:
- escopo de identidade: produto + tenant + operação + recurso + intenção;
- chave de intenção estável entre tentativas equivalentes e correlacionável com aprovação;
- verificação de parâmetros materiais, de modo que mesma chave com comando materialmente diferente seja conflito;
- proteção de concorrência no sistema que de fato grava o efeito (CRM), não apenas na fila AIA;
- deduplicação de evento/callback independente da deduplicação da ação;
- consulta de status por intenção quando resposta do destino for ausente ou incerta;
- decisão de retenção de chave e evidência coerente com risco, ciclo de vida, obrigações legais e privacidade.

Não escolher agora **janela temporal, chave criptográfica, índice, constraints SQL, algoritmo, isolation level ou TTL**. AIA-R02/R03 e R01-G03/G08 exigem formalização bilateral e testes de corrida antes de homologação.

## 7. Concorrência, prioridade, quotas e proteção entre empresas

- **Controle por empresa e produto:** limite de runs ativas, jobs em fila, throughput e consumo; um tenant não deve bloquear todos os outros.
- **Classes de serviço:** operações interativas e tarefas demoradas podem demandar filas ou prioridades distintas; evitar que trabalho volumoso sufoque respostas do CRM.
- **Ordem e versão:** eventos atrasados não reabrem estados terminados nem contornam revogação; checkpoints devem registrar contexto de versão.
- **Justiça e degradação:** políticas de backpressure, rate limits, circuit breaker e suspensão, protegendo quotas do CRM e custos de modelos.
- **Escopo do worker:** o serviço técnico pode processar vários tenants, mas cada execução precisa de identidade/escopo verificável; nunca herda privilégio universal.
- **Retries:** limites, atrasos e jitter por classe de falha, sem repetir efeitos já possivelmente concretizados; prazos a homologar tecnicamente.
- **Falha em uma empresa:** não deve vazar contexto, tokens, caches ou resultados a outras empresas no mesmo worker.
- **Eventos externos:** não usar timestamps de clientes ou prompts como prova suficiente de ordenação; consulta final ao produto permanece soberana.

Nenhum número de workers, quota, prioridade, SLA, prazo, custo ou tecnologia está decidido.

## 8. Erros, DLQ/quarentena e indeterminação

Classificar falhas para tratamento seguro:

| Tipo de falha | Comportamento candidato |
| --- | --- |
| Identidade, tenant, escopo ou aprovação inválidos | Negar, auditar e não enfileirar operação de efeito |
| Violação de classificação/privacidade | Bloquear transmissão e acionar revisão/incidente |
| Quota/plano esgotado | Suspender ou negar conforme regras oficiais do CRM |
| Provedor IA indisponível sem efeito externo | Retry sob limites, cotas, retenção e critérios de contingência |
| Ferramenta CRM devolve negativa definitiva | Encerrar intenção como negada, sem tentar outra identidade |
| Timeout CRM após envio de comando com possível efeito | Estado **INDETERMINADO**, conciliar no CRM antes de novo envio |
| Mensagem malformada, incompatível ou repetida | Isolar em quarentena técnica ou erro seguro; não adaptar permissivamente |
| Excesso de tentativas ou expiração | Encerrar/escalar, minimizando payload retido |
| Worker cai antes de confirmar etapa | Recuperar estado/lease e revalidar antes de retomar |
| Falha de armazenamento durável | Não declarar pedido confirmado sem garantia exigida pelo contrato |

A **fila de falhas** não deve virar armazenamento permanente de leads ou prompts; reprocessar só enquanto houver direito e finalidade. Seu período de retenção ainda requer homologação, não herda automaticamente as **432h dos Sites Imagine**, que pertencem a fluxo comercial diferente.

## 9. Backups, restauração, RTO/RPO e proteção de dados

O plano técnico futuro deve especificar:
- quais estados, intenções, aprovações e referências de efeito precisam de recuperação;
- cópias de segurança e segregação por ambiente/produto/tenant, com segredos tratados separadamente;
- criptografia, acesso e auditoria de leitura/restauração sob contrato homologado;
- objetivos de recuperação **RTO/RPO candidatos**, de acordo com plano/criticidade, sem números inventados;
- restauração consistente com registros de efeitos já confirmados no CRM, sem gerar novos efeitos;
- conciliação de revogações e solicitações de exclusão/expurgo de dados e derivados após restore;
- retenção e descarte de contextos, payloads em falha, caches, embeddings, logs e backups por classe/finalidade;
- ensaios de restauração com dados sintéticos e autorização própria, com evidência de segurança;
- monitoramento de falhas de backup e alertas, sem presumir que backup existente significa restauração validada.

**Restauração de run não restaura aprovação expirada**: revalidar principal, tenant, recurso e plano antes de qualquer ação. Evidência de tarefa criada permanece fonte do CRM, não da base AIA restaurada.

## 10. Observabilidade, auditoria e custo operacional

Métricas/documentos candidatos:
- entrada, validação, fila, duração, retries, falha, conclusão, cancelamento e indeterminação por produto/tenant;
- profundidade da fila, idade de mensagem, backpressure, taxa de erro do destino e tempo de reconciliação;
- armazenamento de estados, crescimento de logs/índices, falhas de backup e restauração;
- consumo de modelo por tentativa e por operação autorizada, com reconciliação separada de créditos comerciais do CRM;
- incidentes de isolamento, acesso indevido, aprovações expiradas, chaves conflitantes e DLQ;
- resultados seguros e IDs correlacionáveis; **sem prompts completos/credenciais em logs por padrão**.

Não tornar um observador/administrador de infraestrutura capaz de ler irrestritamente leads de clientes. Consultas administrativas precisam ser autorizadas e auditadas. Políticas de retenção de telemetria e acesso dependem do R02/R04 e R01-G05/G10.

## 11. Exemplos documentais de falha e recuperação

| Cenário sintético | Resultado necessário |
| --- | --- |
| Dois workers consomem simultaneamente mesma intenção CRM | Um efeito de negócio, sem duplicação |
| Worker cai após CRM gravar tarefa e antes do acknowledgement | Reconciliação por intenção recupera efeito existente |
| Job volta após tenant ser suspenso | Revalidar e impedir nova consulta/ação |
| Mesma chave reutilizada com payload diferente | Conflito, sem alteração de CRM |
| Quota esgota durante múltiplas tentativas | Bloquear novas etapas segundo política do CRM |
| Callback duplicado/out-of-order de efeito confirmado | Não recontabilizar nem corromper estado |
| Estado é restaurado de backup anterior a exclusão | Reaplicar revogação/expurgo, impedir disponibilização indevida |
| Memória do worker de A é reutilizada para B | Isolamento, limpeza de contexto e reprovação de vazamento |
| Banco/filas não disponíveis após pedido de gravação | Não prometer durabilidade/aceite que não ocorreu |
| Payload de DLQ contém segredo ou dado de alta sensibilidade | Minimizar/proteger ou impedir persistência; incidente se aplicável |
| Uma empresa envia carga excessiva de runs | Backpressure por tenant, sem prejudicar outros |
| Estado de tentativa diz “sucesso” mas CRM não confirma efeito | Indeterminação/reconciliação; jamais sucesso fictício |
| Retry de inferência gera custo extra real | Medir custo técnico sem duplicar efeito ou crédito CRM |
| Alteração de schema durante evento antigo em fila | Compatibilidade de versão ou quarentena controlada |
| n8n interno tenta reexecutar ação CRM por credencial global | Negar; exigir contrato e autorização final CRM |

**Nenhum caso foi executado.** Os exemplos orientam futura homologação em ambiente e com dados sintéticos autorizados.

## 12. Comparação a preparar antes da escolha física

A análise futura de alternativas deverá comparar cada candidato por:
1. Isolamento multiempresa e entre ambientes, incluindo filas, backups, logs e cache.
2. Semântica de concorrência/atomicidade e idempotência fim a fim com CRM.
3. Durabilidade e recuperação verificável, com possibilidade de reconciliação.
4. RTO/RPO, escala, throughput, latência e cargas esperadas, sem números assumidos.
5. Operação, atualizações, custo fixo/variável, fornecedor e condições comerciais.
6. LGPD, custódia, segregação, acesso de operadores e política de expurgo.
7. Compatibilidade e desacoplamento do n8n (interno Imagine) e do motor nativo do CRM.
8. Observabilidade, incidência de falhas, reversibilidade e lock-in tecnológico.
9. Capacidade de iniciar pequeno sem restringir crescimento futuro.
10. Requisitos de integração e governança de versões com AIA-12.

Não recomendar Redis, PostgreSQL, RabbitMQ, Kafka, SQS, Docker, Leaseweb ou outro produto como **escolha ratificada** neste momento. A seleção exige matriz comparativa e aprovação técnica/comercial específicas.

## 13. Diretrizes R03-S02-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R03-S02-D01 | Estado durável | Persistir runs/steps/intenções/efeitos em armazenamento durável com consistência comprovável; comparar tecnologias depois | Depender apenas da memória dos workers |
| R03-S02-D02 | Filas | Modelo lógico de fila durável, workers limitados e recuperação de mensagens, sem tecnologia escolhida | HTTP síncrono e jobs sem controle de entrega |
| R03-S02-D03 | Atomicidade | Planejar consistência entre registro de intenção e agendamento, avaliando outbox/inbox ou equivalente | Confirmar pedidos mesmo quando jobs podem ser perdidos |
| R03-S02-D04 | Idempotência | Chaves estáveis por intenção/tenant/operação e deduplicação do efeito no CRM, com conflitos detectados | Criar nova tarefa a cada retry ou callback |
| R03-S02-D05 | Concorrência e isolamento | Políticas de workers/quotas/backpressure por tenant e operação, revalidando permissões | Workers globais sem separação de contexto ou limites |
| R03-S02-D06 | Falhas e reconciliação | Estado indeterminado, consulta oficial de efeito e retentativa segura, com quarentena de falhas | Repetição cega após timeout e perda de estados |
| R03-S02-D07 | Retenção | Definir retenção de mensagens, estados, índices, evidências e DLQ por produto/finalidade, sem prazo universal | Guardar payloads/filas indefinidamente |
| R03-S02-D08 | Backup e recuperação | Projeto de backup, restauração, revogação/expurgo e RTO/RPO a homologar antes de implantar | Backup sem prova de restore e execução com direitos antigos |
| R03-S02-D09 | Observabilidade/custos | Telemetria mínima por empresa/execução, alertas, custos técnicos e créditos comerciais separados | Logs irrestritos e cobrança repetida de retries |
| R03-S02-D10 | Seleção e fase | Comparar tecnologias/fornecedores em documentação posterior, preservando gates, sem código ou instalação | Aprovar banco/fila/VPS e iniciar implementação agora |

**R03-S02-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação exclusivamente documental preliminar, sem escolher banco, broker, queue, cache, schema, provedor, servidor, arquitetura física, preço ou TTL, sem fechar gates e sem autorizar código, SQL, ALLOW, testes reais ou deploy.

## 14. Dependências e próximo recorte

- [x] R03-S02-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06, sem homologação técnica.
- [ ] Confirmar as necessidades de execução/idempotência do CRM, preservando CORE/SEG/AUT e R01-G01/G02/G03/G08.
- [ ] Formalizar contrato lógico de dados/estados, consumo, retenção e controle de concorrência com AIA-04/07/08/10/11/12.
- [ ] Definir matriz futura de custos, capacidade, RTO/RPO e contingência sem compras ou provisionamento.
- [ ] Revisar critérios de falha, expurgo, privacidade, restauração e isolamento, preservando R01-G05/G06/G07/G10.
- [ ] Preservar **R01-G01 a G11 todos abertos**, sem evidências técnicas nem testes reais.
- [ ] Preservar **CORE/SEG/AUT e demais gates CRM abertos**, sem alterar repositórios do produto.
- [ ] Exigir autorizações futuras separadas para código, SQL, ALLOW, APIs reais, credenciais, compras, VPS/n8n/Cloudflare, testes e deploy.

**Próximo bloco documental recomendado:** `AIA-R03/S03 — Identidade, Segredos, Redes e Conectividade Técnica`, incluindo comparação de mecanismos de confiança e isolamento sem credenciais reais.

**Imutáveis:** nenhuma modificação de `main`, PRs ou bloqueios de CRM, Portal, Performance AI e Universidade; sem execução de n8n, infraestrutura, servidores, APIs pagas ou integração de dados reais.
