# FT01/SEC-13 — Política do produto dono obrigatória em qualquer leitura sintética

**Data:** 09/11/2026. **Escopo:** remoção de bypass legado dentro do laboratório offline. **Decisão operacional: NO-GO**.

## Falha estrutural removida
A classe `MockCrmReadBoundary` permitia `?MockCrmOwnerPolicy $ownerPolicy = null` e só executava `assertAllowed` quando uma política era injetada. Assim um chamador interno poderia reutilizar a leitura fictícia apenas com um envelope de confiança sintético, **sem verificar a concessão do CRM dono do dado**. A fachada pública já impunha a política, mas o construtor interno permanecia permissivo.

**SEC-13 exige `MockCrmOwnerPolicy` não nula no construtor e executa `assertAllowed` em todos os pedidos antes de consultar `MockCrmReader`.** A tentativa de construir o bridge sem política falha, e uma política presente mas sem grant também nega a leitura. Não existe opção de desligar esse controle.

Fixtures históricas F1-12/F1-13 passaram a criar explicitamente concessões para contextos sintéticos válidos, preservando os testes antigos sem manter o bypass. Contextos deliberadamente inválidos não recebem grant. Os testes da trilha compartilhada F1-14 também injetam a política de owner antes da auditoria.

## Regressões
- U33-01: construtor sem `MockCrmOwnerPolicy` não pode ser criado;
- U33-02: confiança válida com owner policy vazia não confere acesso;
- U33-03: revogação de grant impede leitura posterior com nova prova de trust;
- U16-17: a fixture antiga só é compatível após injeção explícita da política.

**Gate esperado de revisão:** 362 testes PHP e lint dos 82 arquivos PHP, além dos testes Python do contrato. Nenhuma dessas provas comprova ACL real, autenticação, membership ou RLS no CRM.

## Restrições preservadas
Somente `dev`; `main`, produção, credenciais, VPS, CRM real, dados pessoais e despesas não são afetados. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece `OPEN/DRAFT`, sem aceite bilateral; R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS / NO-GO**. Para integração real, a política final deve ser aplicada pelo backend CRM, e não delegada ao Agents.
