# Imagine AI Agents — F1-10: revisão independente e evidências sintéticas

**Data:** 09/11/2026. **Estado:** módulo PHP sintético e offline, desenvolvido em branch filha da dev. **Não** é motor real de autorização, assinatura criptográfica ou implantação.

## 1. Objetivo

Prosseguir após o F1-09 de comparações A/B determinísticas, incluindo uma **etapa simulada de encaminhamento humano** com segregação de funções, vínculo exato ao resultado avaliado, revogação, validade temporal e decisão por dois papéis independentes. A implementação está alinhada ao módulo funcional AIA-09 e à governança AIA-11.

O novo mecanismo **não habilita a promoção** de agentes. Mesmo quando os dois revisores fictícios reconhecem o relatório sintético, o estado máximo permitido é `READY_FOR_HUMAN_HANDOFF` (pronto apenas para encaminhamento humano futuro). **`promotion_allowed=false` e `real_authorization=false` sempre.**

## 2. Componentes entregues

- `src/MockReview/MockReviewPacket.php`: recomputa internamente o relatório usando o `MockEvaluationComparator` do F1-09, sem aceitar score ou gate informado pelo chamador. Vincula referência fictícia, contexto integral, autor fictício, janela lógica, dataset, baseline, candidata e todos os resultados a um hash SHA-256 determinístico de conteúdo. Possui revogação em memória e visualização sem payload sensível.
- `src/MockReview/MockReviewBoard.php`: guarda votos apenas em memória, identificados pelo digest exato do pacote e com funções `PRODUCT` e `SECURITY`. Restringe decisão a `ACKNOWLEDGE` ou `REJECT`; exige dois identificadores distintos e diferentes do autor; proíbe ACKNOWLEDGE se houver falha ou caso não executado; bloqueia mudanças conflitantes de voto e rejeições posteriores.
- `tests/Unit/review_handoff.php`: 14 casos U10-01 a U10-14, vinculados ao runner principal `tests/Unit/run.php`.
- `examples/f110-review-demo.php`: processo PHP local que demonstra estados antes/depois de dois reconhecimentos independentes, sem rede e sem qualquer efeito real.

## 3. Estados e regras de negócio deste mock

| Estado | Condição | Permissão de produção |
| --- | --- | --- |
| `AWAITING_INDEPENDENT_REVIEWS` | Resultado `REVIEW_REQUIRED`, ainda faltam papéis distintos | Nenhuma |
| `READY_FOR_HUMAN_HANDOFF` | Dois reconhecimentos fictícios distintos, Produto e Segurança | Nenhuma |
| `BLOCKED_EVALUATION` | Relatório bloqueado, inconclusivo ou com falhas | Nenhuma |
| `REJECTED` | Qualquer revisor fictício rejeita o pacote | Nenhuma |

O objeto de avaliação expira na fronteira temporal, pode ser revogado entre atos, compara o contexto completo produto+empresa+principal+finalidade+recurso e recusa digest divergente/obsoleto. Repetir **exatamente** o mesmo voto é idempotente; mudar revisor ou decisão para um papel já registrado é conflito.

## 4. Testes e evidências reais

- **PHP CLI 8.4.24** no ambiente local, sem instalação.
- `php tests/Unit/run.php`: **111 cases, 0 failures, exit 0** (97 anteriores + 14 novos).
- `php -l`: **52/52 arquivos PHP PASS**.
- **52/52 hashes SHA-1 Git blob conferidos** entre cópia local executada e árvore remota do GitHub; impressão digital ordenada FNV32 `1e3ddd48`, com pares caminho/hash da árvore.
- `php examples/f110-review-demo.php`: `AWAITING_INDEPENDENT_REVIEWS` → `READY_FOR_HUMAN_HANDOFF`, dois revisores fictícios, `promotion_allowed=false`, `external_calls=0`. Exit 0.
- O novo F1-10 **não altera arquivos HTML ou JS** do F1-08/F1-09; não existe um navegador novo a validar para este recorte.

## 5. Contratos e limites

- Identificadores de revisores são apenas strings fictícias; **não há login, autenticação, verificação de papel real, assinatura digital, banco, persistência ou trilha inviolável**. SHA-256 é um hash determinístico, **não uma assinatura, autenticação ou atestado externo**.
- A memória é volátil; instâncias podem ser reiniciadas. Portanto, **não** empregar este mock em autorização de agentes, modelos, permissões, contratos, CRM ou produção.
- Votos simulados são `ACKNOWLEDGE`, não aprovações jurídicas, técnicas ou comerciais. Não há escrita no `MockPromotionGate`, acesso SQL, `ALLOW`, recursos externos, provedores, prompts de cliente, ferramentas reais ou pagamentos.
- O critério de validação acima se aplica exclusivamente à implementação sintética. Segurança operacional, autenticação, trilha auditável imutável, matriz de alçadas, proteção LGPD, homologação integrada, emergência/rollback e publicação precisam de recortes específicos e autorizações críticas futuras.
- Gates **R01-G01 a G11** e **CORE/SEG/AUT CRM** permanecem todos abertos. `main`, produção, VPS, n8n, Cloudflare e demais repositórios não podem ser tocados.

## 6. Execução e aceite de desenvolvimento

```sh
php tests/Unit/run.php
php examples/f110-review-demo.php
```

No checkout completo, exigir SHA do head, **111 casos sem falhas**, **52 lints sem erro**, identidade dos blobs de código e diff limitado aos componentes descritos. Depois, permitir apenas **merge controlado na branch dev**. Não registrar nem inferir que uma avaliação sintética passou a ser autorização para produção.

**Próximo recorte possível:** desenho de interface e contratos de auditoria/entrega a revisores reais, **sem conectar autenticação ou sistemas externos sem gates próprios**.
