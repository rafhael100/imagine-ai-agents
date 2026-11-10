# FT01/SEC-29 — Capacidade reservada para decisões e auditoria de versões

**Data:** 10/11/2026. **Escopo:** somente PHP, catálogo e estados sintéticos offline. **Integração real e produção: NO-GO.**

## Problema e correção

O `MockPromotionGate` guardava um histórico ilimitado de decisões e eventos. Ao executar `promote`, `rollback` e `suspend`, registrava a decisão e alterava a seleção antes de gerar o evento de auditoria, sem garantir espaço. Isso permitia crescimento não limitado e abria caminho para mudanças de estado sem histórico suficiente, caso fosse criado um limite apenas na função `trace`.

A SEC-29 estabeleceu uma reserva **antes de gravar uma decisão inédita**, que cobre o registro de decisão, o evento de auditoria e uma futura suspensão:

- **Máximo de 128 decisões distintas** por instância; uma posição é sempre reservada para a próxima suspensão explícita enquanto houver uma seleção ativa.
- **Máximo de 256 eventos de auditoria**; uma posição é reservada para suspensão quando uma nova promoção ou rollback é permitido.
- Nova promoção ou rollback que consumiria a vaga de suspensão é recusado com `PROMOTION_CAPACITY_DENIED`, **antes** de alterar o catálogo/seleção, registrar decisão ou emitir histórico.
- Suspensão explícita pode utilizar a última vaga e continua funcionando mesmo que a versão esteja desabilitada no catálogo.
- Repetições de decisões antigas mantêm a semântica idempotente anterior, sem gravar novamente. Uma resposta legada de repetição **não reativa** uma seleção suspensa: inspecionar `current()` continua mostrando ausência de versão ativa.
- A revogação de uma ferramenta elegível continua desativando a versão em memória e registrando a suspensão quando há vaga; o método de `trace` também exige espaço como última proteção.

## Testes de regressão U51-01..06

127 promoções fictícias aceitas e a próxima rejeitada sem alteração; suspensão com a última vaga; repetição idempotente após esgotar as 128 decisões; rollback novo negado sem trocar versão; falha de avaliação não consome reserva; revogação de capacidade ainda suspende e deixa auditoria. Nenhuma operação cria grants, autenticação real, publicação de agente, chamadas ao CRM ou acesso ao cliente.

**Gate esperado:** **458 testes PHP, lint de 82 arquivos PHP e verificadores Python** (contrato, prontidão, isolamento e pacote bilateral). A CI precisa ser aprovada no SHA da revisão e pós-merge na `dev`.

## Dependências de produto

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT**. O pacote EVID-19 mantém **sete decisões bilaterais, 14 gates e 13 cenários não executados**. Autenticação serviço-serviço, ACL de tenant/recurso/campo no backend CRM, IAM, LGPD, persistência e rollback reais e revisão independente continuam bloqueados. Nenhuma alteração em `main`, VPS, produção, clientes, credenciais ou custos.
