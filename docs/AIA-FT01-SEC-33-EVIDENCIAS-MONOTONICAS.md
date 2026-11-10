# FT01/SEC-33 — Evolução monotônica das evidências supervisionadas

**Data:** 10/11/2026. **Escopo:** `MockSupervisedGateway` e regressões exclusivamente offline. **Decisão operacional: NO-GO.**

## Problema

O gateway supervisionado permitia sobrescrever um resultado `PENDENTE` por uma observação mais antiga `INDETERMINADO`, o que poderia degradar a reconciliação em memória depois de um timeout. Também criava objetos `Effect` novos para observações não terminais idênticas, embora a repetição não acrescentasse informação.

## Correção

- `INDETERMINADO → PENDENTE → CONFIRMADO/NEGADO` permanece elegível no laboratório; passagem direta `INDETERMINADO → CONFIRMADO/NEGADO` também.
- `PENDENTE → INDETERMINADO` é recusado com `STATE_CONFLICT`, preservando o objeto e estado existentes.
- Observações idênticas não terminais (`PENDENTE` e `INDETERMINADO`) retornam sem substituir os registros existentes.
- Efeitos terminais `CONFIRMADO/NEGADO` continuam imutáveis: tentativas de substituí-los são negadas, incluindo repetições terminais, preservando o contrato prévio.
- `status` permanece vinculado à chave e assinatura de intenção validadas pelo gateway fictício; nenhuma nova submissão real é executada.

## Regressões U55-01..06

Cobre bloqueio de regressão, repetições idempotentes, preservação da referência original, evolução até confirmação após timeout, separação de chaves e proibição de resultados forjados. **Gate esperado: 485 testes PHP, 82 arquivos no lint e contratos Python.**

## Restrições

Esta correção não implementa fontes reais de evidência, autenticação, auditoria durável, CRM operacional ou políticas de acesso. CRM PR #57 DRAFT; R01-G01..G11 e CORE/SEG/AUT ABERTOS/NO-GO. Sem alterações na `main`, VPS, produção, clientes, segredos nem custos.