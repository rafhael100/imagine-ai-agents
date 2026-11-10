# FT01/SEC-38 — Concessões sintéticas de ferramentas com referências limitadas

**Data:** 10/11/2026. **Escopo:** somente `MockCapabilityGrant`, despacho simulado e testes offline. **Decisão operacional: NO-GO.**

## Problema e correção

A SEC-36 passou a limitar IDs de ferramentas no despacho, mas o objeto `MockCapabilityGrant` ainda aceitava identificadores arbitrariamente longos (ou sintaticamente inválidos) na criação e os mantinha em memória. Um chamador poderia construir concessões fictícias volumosas mesmo quando a ferramenta nunca seria executada.

O construtor agora valida **ambos** `id` e `toolId` com tamanho máximo de **90 bytes** (prefixo `SYNTHETIC_` + 1..80 caracteres) **antes do regex e do armazenamento**. O formato permitido é somente `SYNTHETIC_[A-Z0-9_]{1,80}`. Entradas grandes, referências vazias após o prefixo, minúsculas e caracteres fora do padrão falham com `VALIDATION_FAILED`. As regras de revogação, vínculo de tenant/escopo e validade temporal anteriores permanecem.

## Regressões U60-01..06

1. ID de concessão fictícia de 1 MiB negado, sem ações ou alterações de auditoria.
2. Alvo `toolId` de 1 MiB negado.
3. Fronteira de 90 bytes aceita, com checagem de expiração.
4. Fronteira de 91 bytes negada em ambas referências.
5. Prefixo vazio, minúsculas, espaços, hífens e marcador não permitido rejeitados.
6. Fluxo regular `SUGGEST` ainda funciona, e revogação impede uso subsequente sem mutações.

**Gate esperado: 518 testes PHP, lint de 82 arquivos e verificadores Python**, CI no SHA exato e CI pós-merge.

## Limites

O construtor permanece inteiramente fictício e não autentica concessionários ou tokens reais. Sem integração com CRM real, HTTP, IAM, SQL, VPS, publicação ou custos. CRM PR #57 OPEN/DRAFT; R01-G01..G11 / CORE-SEG-AUT ABERTOS/NO-GO. Não alterar `main`, segredos, clientes nem produção.
