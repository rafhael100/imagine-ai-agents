# FT01/SEC-14 — Avaliar política do CRM antes de consumir nonce

**Data:** 09/11/2026. **Escopo:** laboratorio offline PHP com recursos sintéticos. **NO-GO operacional**.

## Problema
Após SEC-13, toda leitura passava obrigatoriamente pela `MockCrmOwnerPolicy`. Porém, a ordem ainda era `assertMatches → gateway.authorize (consome nonce) → ownerPolicy.assertAllowed → read`. Uma consulta já negada por concessão ausente, usuário suspenso, política indisponível ou campo não permitido consumia o nonce volátil antes de retornar negação. Em uma integração real esse padrão pode desperdiçar autorização de uso único e permitir abuso de capacidade pela camada não autorizada.

## Mudança
A ordem passou a ser `request.assertMatches → ownerPolicy.assertAllowed → gateway.authorize → reader.lead`. A política do **CRM dono do recurso** é obrigatória em todas as entradas e bloqueia o consumo de nonce sempre que a leitura está previamente negada. Em caso de política indisponível não existe fallback permissivo.

A resposta pública continua uniforme e sem diferenciação de recurso. Uma prova de trust **não se torna autorização** apenas porque a política permitiu: ela é verificada normalmente depois, incluindo issuer, audience, escopo, validade, owner evidence, revogação e replay. O mecanismo não abre uma segunda leitura nem qualquer mutação.

## Testes novos
- U34-01: sem grant, mesma evidência de nonce continua não consumida e somente permite leitura após grant explícito.
- U34-02: revogação impede consumo; restauração explícita habilita pedido subsequente.
- U34-03: suspensão de empresa nega antes de consumir recurso de confiança.
- U34-04: indisponibilidade de política preserva o nonce até a política voltar.
- U34-05: campo não coberto pelo owner é negado sem consumo; nova concessão permite somente os campos pedidos.

**Gate esperado:** 367 testes PHP e lint dos 82 arquivos PHP, além dos verificadores de contrato Python. Resultados dependem da CI no SHA revisado.

## Limites
O mecanismo é uma simulação volátil em um processo: uma verificação anterior ao consumo não elimina corrida real entre autorização e leitura (TOCTOU). No CRM real, política, nonce, isolamento por empresa e leitura devem ser validados com uma transação/protocolo apropriado, revisão independente de SEG/IAM e CORE, e evidência de homologação. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece apenas proposta DRAFT; R01-G01..G11 e CORE/SEG/AUT **ABERTOS / NO-GO**. Sem `main`, VPS, clientes, credenciais, custos nem produção.
