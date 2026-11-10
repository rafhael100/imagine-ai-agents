# FT01 / SEC-27 — Limites no domínio de tentativas, medições e provas

**Data:** 10/11/2026. **Escopo:** apenas PHP sintético offline, sem API, SQL, credenciais ou provedores externos. **Operação real: NO-GO.**

## Problema

Mesmo após os controles de medição SEC-25 e a vinculação da identidade da tentativa SEC-26, os construtores de domínio `Attempt` e `Usage` ainda admitiam IDs `SYNTHETIC_` de comprimento arbitrário. `Usage` também permitia diretamente unidades acima do teto aceito no medidor, enquanto `MockUsageProof` admitia um identificador sem sufixo após `SYNTHETIC_PROOF_`. Objetos montados diretamente podiam, portanto, escapar dessas verificações de integridade aplicadas em outras camadas.

## Correções

- `Attempt.id`: prefixo `SYNTHETIC_`, **sufixo obrigatório e tamanho máximo de 128 bytes** antes de armazenar a instância.
- `Usage.id`: mesmo prefixo, sufixo e máximo de **128 bytes**, inclusive fora do ledger.
- `Usage.units`: agora também exige de **0 a 1.000.000**, conforme o limite já aplicado por `MockUsageLedger`.
- `MockUsageProof.id`: exige um sufixo depois de `SYNTHETIC_PROOF_`, mantendo o máximo de **128 bytes**.
- Não cria aprovações, permissões, cobrança nem créditos de verdade. Nenhum dado de cliente é utilizado.

## Regressões U49-01..07

IDs de tentativa excessivos e sem sufixo, tentativa de exatamente 128 bytes com medição válida, ID de `Usage` vazio/excessivo, teto de unidades e casos negativos, ID da prova vazio/excessivo, e reconciliação de prova com 128 bytes exatos.

**Gate exigido:** **446 testes PHP**, lint de **82 arquivos PHP** e verificadores Python de contrato, isolamento e prontidão; merge exclusivamente na `dev` após CI verde no HEAD revisado.

## Limites e dependências

Esta proteção impede alguns abusos de entrada no laboratório, mas não implementa autenticação, billing, persistência, idempotência distribuída ou auditoria real. O [PR #57 do Imagine CRM](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT. Os gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS/NO-GO, sem homologação. Não alterar `main`, VPS ou produção nem assumir custos.
