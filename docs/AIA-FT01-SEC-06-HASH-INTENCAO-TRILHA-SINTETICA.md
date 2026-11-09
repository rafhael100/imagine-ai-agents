# FT01/SEC-06 — Integridade referencial de intenções nas trilhas sintéticas

**Status:** alteração proposta em branch filha, condicionada à CI do commit revisado. Não é homologação de auditoria real, CRM, identidade nem produção.

## Falha de vínculo tratada

A assinatura de `Intent` (reforçada em SEC-05) já incorpora `idempotencyKey`, execução, etapa, contexto, operação e conteúdo. Contudo, o coordenador registrava somente hashes de **IDs** nos eventos, sem hash da assinatura. Duas intenções com o mesmo ID e etapa, mas materiais ou chaves distintos, eram indistinguíveis na trilha. Ao receber uma prova fictícia de outra intenção, o revisor não tinha campo suficiente para detectar a substituição.

## Alteração

Em `SupervisedCoordinator::trace`, eventos vinculados a uma intenção agora incluem `intent_signature`: o valor `SYNTHETIC_` seguido do SHA-256 da assinatura completa local; nenhum conteúdo, chave ou payload é registrado diretamente. Eventos `STARTED`, `STEP_CREATED` e `SUGGESTED` sem intenção continuam sem esse campo.

`ReadOnlyTraceReview::inspect` passa a exigir `intent_signature` em `APPROVAL_DENIED`, `SUBMITTED`, `STATUS_REQUESTED`, `TIMEOUT` e estados de efeito; exige também que o hash corresponda à intenção efetivamente revisada. A ausência, troca ou incompatibilidade devolve `INDETERMINADO / INTENT_CONFLICT` ou rejeição estrutural, sem transformar uma trilha antiga em prova válida. É permitida a inclusão opcional de relógio monotônico de laboratório após a assinatura.

## Regressão e limitações

Nove testes novos em `tests/Unit/resilience.php` (U03-19..U03-27) cobrem confirmação válida, ausência de assinatura em envio ou efeito, mudança somente de conteúdo ou chave de idempotência, assinatura falsa bem formatada, minimização do material, trilhas antigas sem assinatura e relógio monotônico. `tests/Unit/supervised.php` valida o formato dos eventos. A expectativa da suíte oficial passa a **266 testes PHP e 75 arquivos lint**; esses números só são aceitos após a CI confirmar o commit.

**Limites:** SHA-256 não é assinatura digital nem mecanismo de autenticidade da origem. Qualquer parte capaz de fabricar a trilha também pode calcular hashes; auditoria real exige identidade de emissor, armazenamento inviolável, controles de retenção, permissões e revisão independente. Nenhuma API, banco, credencial, orçamento, `main` ou VPS será ativada. CRM PR #57 ainda DRAFT e todos os gates R01/CORE/SEG/AUT continuam abertos: **NO-GO operacional**.
