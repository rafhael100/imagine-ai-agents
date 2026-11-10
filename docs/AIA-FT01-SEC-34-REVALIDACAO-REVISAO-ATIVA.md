# FT01/SEC-34 — Revalidação da revisão da versão selecionada

**Data:** 10/11/2026. **Escopo:** seleção de versões de agentes exclusivamente sintéticos, PHP offline e sem rede. **Decisão operacional: NO-GO.**

## Problema

O `MockPromotionGate` conferia a revisão da versão apenas no momento da promoção ou rollback. Depois, a consulta `current()` continuava mostrando a seleção como ativa mesmo quando a revisão fictícia correspondente havia sido revogada. O catálogo e as ferramentas já eram rechecados, mas a revisão utilizada na última seleção não era preservada.

## Mudança

- O gate mantém uma referência privada à revisão da **última seleção efetiva**. A seleção por promoção ou rollback substitui essa referência, mas replays de decisões antigas não trocam a revisão atual.
- `current()` revalida a revisão contra o snapshot e as versões de dataset/regras fixadas. Uma revisão posteriormente revogada faz a seleção **deixar de ser visível**, sem alterar o histórico ou fingir nova suspensão.
- `current($now)` aceita um **relógio sintético fornecido explicitamente** para verificar início e expiração da revisão. **Sem argumento de relógio, `current()` detecta revogação, mas não é prova de validade temporal presente**; não existe fonte de tempo autenticada.
- A suspensão explícita continua disponível após revogação/expiração/desabilitação, incluindo a última posição reservada para auditoria; a seleção interna só é encerrada pelo fluxo de suspensão, e leituras não escrevem eventos.
- Revogação da ferramenta e suspensão explícita limpam também a referência da revisão selecionada.

## Regressões U56-01..07

Revogação oculta versão e permite suspensão; expiração com tempo informado; tempo anterior à emissão; nova revisão independente da antiga; rollback com a própria revisão; replays antigos sem troca de revisão; e suspensão com capacidade reservada após revogação.

**Gate requerido:** **492 testes PHP, lint de 82 arquivos PHP e verificadores Python**, revisão no SHA exato e CI pós-merge.

## Restrições e dependências

As revisões continuam objetos de laboratório, não assinaturas de pessoas reais, autenticação ou autorização de publicação. Nenhuma versão é enviada a um runtime. O CRM PR #57 segue OPEN/DRAFT e os gates R01-G01..G11 / CORE/SEG/AUT continuam ABERTOS/NO-GO. Sem `main`, VPS, produção, clientes, credenciais, dados reais ou despesas.
