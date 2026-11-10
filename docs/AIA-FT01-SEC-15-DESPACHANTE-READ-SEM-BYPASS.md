# FT01/SEC-15 — Retirada do leitor direto no despachante de ferramentas

**Data:** 09/11/2026. **Escopo:** PHP puro, dados sintéticos, sem CRM real. **Decisão operacional: NO-GO**.

## Falha identificada
Mesmo após as SEC-13/14 exigirem e verificarem `MockCrmOwnerPolicy` na leitura OP-01, o despachante legado `MockToolDispatcher` ainda oferecia a ferramenta `READ` por `(new MockCrmReader())->lead(...)`, diretamente, apenas com um `MockCapabilityGrant`. Isso **não passava pela política do CRM dono**, nem pelo envelope de trust, prova do owner, auditoria de borda ou proteção de replay. Na composição integrada, essa rota permitia contornar a fronteira de autorização do laboratório, ainda que os valores fossem fictícios.

## Correção fail-closed
O despachante não instancia nem chama mais `MockCrmReader`. Solicitações `READ` são validadas pelo catálogo e pela concessão de capacidade, mas retornam `READ_BOUNDARY_REQUIRED`, com evento minimizado de negação; não existe leitor interno alternativo. O único caminho de leitura mínima destinado à demonstração integrada é **`MockCrmReadEdgeBoundary` → `MockCrmReadPublicFacade` → `MockCrmReadBoundary`**, utilizado no `MockReadOnlyPilot`, com owner policy obrigatória e prova de trust sintética. Na integração real, essa borda deverá ser substituída por API autorizada no CRM, com revisão bilateral e política no backend CRM.

O caminho `SUGGEST` continua disponível para dados já validados. O caminho de `MUTATE` continua exigindo aprovação no laboratório, sem promoção para produção.

## Regressões
- U04-01 passa a garantir que o acesso legado READ **falha fechado** em vez de devolver projeção.
- U35-01: campos válidos e grant de capacidade não são suficientes para leitura direta.
- U35-02: tentativas repetidas só deixam trilhas de negação, sem qualquer efeito CRM.
- U35-03: desabilitar READ direto não desativa as sugestões supervisionadas que já operam com dados fornecidos separadamente.
- O teste Python de guardrails rejeita reintrodução de `MockCrmReader` ou chamada `->lead(` em `MockToolDispatcher` e mantém o caminho de borda do piloto como referência.

**Gate esperado:** 370 testes PHP e lint dos 82 arquivos PHP, além dos testes Python de contrato, isolamento e demonstração, verificados na CI do SHA exato.

## Limites
**Não** implementa autorização real do CRM; o leitor e dados usados pelo piloto permanecem artificiais. A alteração representa retirada de interface de teste permissiva, portanto consumidores do READ legado devem migrar para a borda OP-01; não há contrato de produção dessa ferramenta. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece `OPEN/DRAFT` e R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS / NO-GO**. Sem alteração na `main`, VPS, clientes, credenciais, fornecedores, custos ou produção.
