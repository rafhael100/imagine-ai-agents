# AIA-P53 — Contrato de leitura para a futura Central de Aprovações

16/11/2026. Adaptação de apresentação sobre os lotes P51 e auditoria P52; não cria endpoint público.

## Entrega

src/Automation/ReviewQueueReadModel.php transforma exclusivamente o retorno fechado de PgDeterministicDraftRegistryLab::readPending() em um JSON de leitura para interface administrativa futura. O contrato AIA_REVIEW_QUEUE_READ_V1 inclui empresa sintética, quantidade total de rascunhos pendentes, três contadores por tipo (revisão humana, sugestão de tarefa CRM e sugestão de aviso ao operador) e até 128 itens com referência de solicitação, proposta, regra, métrica, observado, limiar e estado.

O adaptador recusa dados de outra empresa, campos adicionais, referências malformadas, valores numéricos fora dos limites, propostas duplicadas, estado de aprovação fingido, strings numéricas não canônicas e resultados que insinuem chamadas de IA ou efeitos externos.

**Importante:** a saída marca can_approve=false, can_execute=false, operational_authorization=false e NO_GO. Propostas de tarefa ou notificação são só rascunhos; a classe não concede qualquer privilégio nem contém mecanismos de votação/autenticação. A futura interface só poderá evoluir com IAM real do CRM/Portal, autorização e auditoria de revisores, proteção CSRF, segregação de empresas e consentimentos aplicáveis. O MockReviewBoard já existente trata de revisão de modelos e não foi reaproveitado como autorização de rascunhos de negócio, pois são processos diferentes.

## Verificação

CI com leitura de 0, 3 e 128 itens, 1.800 vetores aleatórios, teste de isolamento entre tenants, campos extras, estados inválidos, IDs duplicados e limiares inválidos. A P53 herda as regressões P13–P52 e n8n oficial/PG17 independente.

Nenhuma operação em VPS, merge, dado real, API de IA ou cobrança. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
