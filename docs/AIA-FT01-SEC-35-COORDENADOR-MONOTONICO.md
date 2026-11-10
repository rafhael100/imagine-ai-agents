# FT01/SEC-35 — Evidência monotônica no coordenador supervisionado

**Data:** 10/11/2026. **Escopo:** PHP offline, apenas objetos e gateways sintéticos. **Decisão operacional: NO-GO.**

## Problema

A SEC-33 protegeu o `MockSupervisedGateway`, mas `SupervisedCoordinator` recebe qualquer objeto que implemente `CrmEffectGateway`. Um adaptador volátil ou defeituoso ainda podia devolver `INDETERMINADO` depois de `PENDENTE`, fazendo `remember()` substituir a evidência já conhecida por incerteza. Respostas `NAO_SOLICITADO` e `SOLICITADO` também eram normalizadas tarde, depois da comparação com o estado prévio. Respostas não terminais repetidas podiam substituir os objetos guardados, embora não acrescentassem informação.

## Implementação

- `remember()` normaliza `NAO_SOLICITADO` e `SOLICITADO` em `INDETERMINADO` **antes** de confrontar o estado armazenado.
- Se houver prova sintética prévia `PENDENTE`, uma resposta `INDETERMINADO` é recusada com `STATE_CONFLICT` e a evidência anterior não é substituída. `reconcile()` registra `STATUS_REJECTED` sem um segundo `submit()`.
- Repetições de `PENDENTE` e `INDETERMINADO` preservam a referência à primeira evidência; ainda registram a conclusão da checagem de status. Isso torna o teste conservador sem apagar a auditoria.
- Evoluções `INDETERMINADO → PENDENTE → CONFIRMADO/NEGADO` permanecem válidas; resultados terminais continuam imutáveis e inconsistências são rejeitadas.

## Regressões U57-01..08

Usam um `CrmEffectGateway` de teste intencionalmente inconsistente para exercitar proteção **no coordenador**, independente da SEC-33: regressão de `PENDENTE`; ausência/solicitação tardia; replays de `PENDENTE` e `INDETERMINADO`; evolução até confirmação; imutabilidade de negação; timeout sem resubmissão; e normalização antes da primeira observação pendente.

**Gate exigido:** **500 testes PHP, lint de 82 arquivos PHP e verificadores Python**, com CI no SHA exato e pós-merge.

## Limites e não-autorização

Esta correção endurece a representação de estados em memória, **não cria prova real de efeito**. Respostas não têm assinaturas, timestamps confiáveis ou fontes externas: uma reconciliação verdadeira precisará de contrato bilateral, identidade e provas do backend CRM. CRM PR #57 segue OPEN/DRAFT; gates R01-G01..G11 e CORE/SEG/AUT ABERTOS/NO-GO. Nenhum dado real, VPS, main, produção, credenciais ou despesas.
