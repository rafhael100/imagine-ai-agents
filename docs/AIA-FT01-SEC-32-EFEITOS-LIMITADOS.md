# FT01/SEC-32 — Limite de evidências sintéticas do gateway

**Data:** 10/11/2026. **Escopo:** apenas PHP, testes offline e contratos fictícios. **Estado operacional: NO-GO.**

## Problema e correção

`MockCrmEffectGateway` acumulava objetos `Effect` em um mapa sem limites. Esse armazenamento guarda a prova simulada da identidade/idempotência e não pode expulsar registros anteriores: perder uma entrada antiga permitiria repetir uma operação com a mesma chave como se fosse inédita.

A implementação estabelece teto de **256 efeitos por instância**. Uma tentativa de submissão nova além do teto falha com `EFFECT_CAPACITY_DENIED` **antes da criação e gravação** de um efeito. Operações já registradas mantêm replay idempotente e leitura de status no limite; a mesma chave com assinatura alterada continua retornando `IDEMPOTENCY_CONFLICT`. Aprovações inválidas e recursos proibidos falham antes da reserva. O teto é por instância, não por tenant, e contextos de duas empresas fictícias permanecem segregados.

Como `MockSupervisedGateway` utiliza esse gateway como armazenamento subjacente, a lista de provas falsas não pode superar os efeitos aceitos; a simulação de timeout e a injeção posterior de evidência continuam disponíveis para chaves previamente submetidas.

## Regressões U54-01..07

1. Aceita 256 efeitos, recusa 257º e não fabrica status.
2. Preserva replay e consulta de evidências prévias após saturação.
3. Mantém precedência de conflito de assinatura sobre capacidade.
4. Rejeita aprovações/recursos inválidos sem consumir última vaga.
5. Verifica teto compartilhado e isolamento de tenant.
6. Mantém reconciliação de timeout/evidência supervisionada mesmo no limite.
7. Não fabrica prova terminal quando o armazenamento está cheio.

**Gate esperado:** **479 testes PHP, lint de 82 arquivos PHP e validadores Python**, revisão no SHA exato e CI pós-merge na `dev`.

## Limites e não-autorização

O gateway não envia solicitações ao CRM real, não cria registros reais, não implementa filas duráveis, faturamento, IAM, autorização ou persistência. A [proposta CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua sem homologação, e R01-G01..G11/CRM CORE-SEG-AUT seguem ABERTOS/NO-GO. Não alterar `main`, VPS, produção, dados reais, segredos nem gerar custos.
