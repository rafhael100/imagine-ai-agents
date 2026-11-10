# FT01/SEC-28 — Revisões independentes sem crescimento ilimitado de memória

**Data:** 10/11/2026. **Escopo:** laboratório PHP e dados sintéticos; sem autenticação real, CRM ou promoção de agentes. **Decisão operacional: NO-GO.**

## Risco corrigido

A implementação `MockReviewBoard` guardava votos por digest de pacote, com no máximo dois papéis por pacote (`PRODUCT` e `SECURITY`), mas sem limite na quantidade de digests distintos. Um fluxo ininterrupto de pacotes de avaliação poderia fazer esse histórico volátil crescer sem limite em memória.

## Comportamento SEC-28

- **Máximo de 128 pacotes distintos com votos** por instância em memória; cada pacote mantém no máximo dois revisores de papéis diferentes.
- Um 129º pacote não consegue registrar seu primeiro voto e recebe `REVIEW_CAPACITY_DENIED`. Não são apagados pacotes ou decisões anteriores.
- Uma revisão **já iniciada** pode obter o segundo voto independente mesmo após atingir os 128 pacotes.
- Repetição exata de voto continua idempotente; tentativa de trocar papel, revisor ou decisão registrada continua `REVIEW_CONFLICT`.
- Rejeições permanecem bloqueantes mesmo em capacidade máxima. Inspecionar `status` nunca gera permissão: `promotion_allowed=false` e `real_authorization=false`.

## Regressões U50-01..06

Limite de 128 digests com primeira tentativa adicional negada; conclusão de segundo parecer após saturação; repetição idempotente e conflito; persistência de rejeição; voto inválido sem consumir a última vaga; status de pacote desconhecido sem autorizar promoção.

**Gate esperado:** 452 testes PHP, lint em 82 arquivos PHP e verificações Python do contrato, isolamento, pacote bilateral e estado canônico. Merge somente na `dev` após CI verde no HEAD exato.

## Limites de produto

Este registro é volátil, local e fictício, sem IAM, assinatura de pessoas ou autorização de produção. A revisão bilateral real do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT. R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO; nenhuma mudança em `main`, VPS, produção, segredos, dados reais ou custos.
