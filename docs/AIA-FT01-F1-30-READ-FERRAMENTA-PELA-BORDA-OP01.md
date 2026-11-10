# FT01/F1-30 — Ferramenta READ via borda OP-01, sem atalho direto

**Data:** 10/11/2026. **Ambiente:** PHP offline, dados fictícios, nenhuma API/segredo. **Decisão operacional:** NO-GO para CRM real.

## Entrega funcional verificável
A SEC-15 removeu a leitura direta sem owner policy do `MockToolDispatcher`. A F1-30 acrescenta uma nova operação explícita, `readThroughBoundary`, que utiliza um `MockCrmReadEdgeBoundary` **injetado**. Não há `MockCrmReader` no despachante; a leitura somente é feita pela borda proprietária de recurso OP-01:

1. Exige ferramenta `READ` na versão aceita pelo catálogo e `MockCapabilityGrant` válido;
2. Exige uma instância da borda explicitamente injetada, sem fallback de leitura direta;
3. Faz parse de `MockCrmReadRequest` com schema fechado, vincula contexto confiável de laboratório e valida a projeção solicitada no catálogo;
4. Envia requisição, envelope e evidência do owner à borda OP-01, que executa owner policy, trust, auditoria e telemetria sintéticos;
5. Retorna apenas `ref/stage` fictícios do contrato público estrito, se a decisão for permitida. Qualquer falha retorna `DENIED_OR_NOT_FOUND` uniforme, sem ids, motivos internos, tokens ou dados de CRM;
6. O antigo `dispatch(READ)` continua bloqueado com `READ_BOUNDARY_REQUIRED`, mesmo quando uma borda está injetada; migração é **opt-in** e explícita.

## Regressões U36-01..10
Leitura mínima permitida, replay, owner sem concessão, empresa divergente, campo de contato indevido, concessão da ferramenta ausente/revogada, envelope ou prova do owner ausentes, operação não-READ, ordem dos campos e negativa do atalho legado. Também verificam auditoria/telemetria e ausência de `submit/status` de efeitos mutáveis.

**Gate exigido:** 380 testes PHP, lint em 82 arquivos PHP, além dos verificadores Python de contrato e isolamento. Validar na CI por SHA exato antes de integrar à `dev`.

## Limites
`readThroughBoundary` é um adaptador **inteiramente sintético**, não uma API HTTP, cliente Laravel, autenticação mTLS/JWT, ACL real, transação distribuída ou concessão de dados de clientes. O `TenantContext` é um fixture, não identidade de usuário autenticada. A política executada no laboratório não substitui o autorizador do CRM. CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT, e R01-G01..G11 / CRM CORE-SEG-AUT **ABERTOS / NO-GO**. Nenhuma modificação em `main`, VPS, produção, credenciais, clientes ou fornecedores pagos.
