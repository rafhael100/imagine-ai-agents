# FT01/F1-16 — Revalidação de política fictícia pertencente ao CRM

## Estado vigente — F1-16 integrado exclusivamente à dev (09/11/2026)

- **PR [#114](https://github.com/rafhael100/imagine-ai-agents/pull/114) MERGED por squash apenas em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `447672eda494ff365f74e2e39fbaae76a57d115f`. A `main` foi conferida em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem VPS, CRM real ou deploy.
- **Prova efetivamente executada:** [GitHub Actions #42582545247](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42582545247), head testado `b7866bb8dac2973025569c4a76a84082c28377a2`, PHP CLI 8.3.6, **198 casos, 0 falhas**, **68/68 lint PHP**, `VALIDATION_PASS` de SHA fixado.
- **Entregue:** mock `MockCrmOwnerPolicy` de decisão atual do dono dos recursos, injetado opcionalmente no F1-12; 18 cenários U16 de revogação, suspensão, campo, cross-tenant, falta de política e auditoria. **Não** existe ACL, membership, serviço CRM, autorização ou integração real. O parâmetro opcional mantém retrocompatibilidade apenas para laboratório, não produção.
- **Gates:** R01-G01..G11, CRM CORE/SEG/AUT, revisão LGPD e contrato bilateral CRM **ABERTOS**; o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT, somente documental. Nenhuma operação `ALLOW`, credencial, dado privado, fornecedor pago ou `main` ativada.
- **Próxima frente segura:** simular envelope de resposta pública que não revele motivos internos de negação; persistência e autorização reais ainda dependem de aprovação específica.

**Precedência:** abaixo podem permanecer trechos históricos de F1-16 em revisão; este estado pós-merge é o vigente.

---


**Data:** 09/11/2026. **Estado de abertura:** desenvolvimento isolado, testes ainda pendentes. **Este recorte não implementa ACL real, autenticação, permissões CRM ou API.**

## Objetivo

Exercitar a regra de segurança segundo a qual um pedido autorizado previamente pelo agente **não basta**: no instante da leitura, o serviço proprietário dos dados deve conferir novamente o vínculo empresa/operador/recurso, a suspensão vigente e a autorização campo a campo. No mundo real, **só o backend do CRM** poderá implementar e decidir essas verificações.

## Arquivos e comportamento

- `src/MockBridge/MockCrmOwnerPolicy.php`: fixture **somente em memória**. Começa sem grants e nega por padrão; um teste configura manualmente direitos fictícios `SYNTHETIC_*`, podendo revogá-los ou suspender empresa/operador. A indisponibilidade nega acesso; campo fora da lista retorna a **mesma recusa de recurso ausente**, sem revelar sua existência.
- `src/MockBridge/MockCrmReadBoundary.php`: acrescenta dependência **opcional** à política simulada, chamada depois do envelope F1-11 e **antes** do `MockCrmReader` F1-12. A ausência da dependência mantém apenas a compatibilidade de testes anteriores — **não é configuração segura para produção**.
- `src/MockAudit/MockCrmReadAuditedBoundary.php`: categoriza a falta de política fictícia como `OWNER`, sem lançar detalhes sensíveis na trilha.
- `tests/Unit/owner_policy_recheck.php`: **18 cenários U16**, incluindo revogação posterior ao envelope, suspensão e retomada fictícias, campo negado, dois tenants, substituição de principal/finalidade, indisponibilidade, log mínimo e erro indistinguível.
- Runner e validador SHA-fixado exigirão **198 testes** e **68 arquivos PHP**, sujeitos à prova efetiva na CI antes do merge.

## Limites, lacunas e bloqueios

- `grantFixture()` **não é ALLOW real**; a autorização é uma simulação em uma classe PHP sem rede, identidade verificável, bancos ou dados pessoais.
- Não há execução Laravel/CRM, RLS/SQL, membership canônico, lock de concorrência, sessão, persistência, prova de autoria, revogação entre processos nem tratamento LGPD operacional.
- A simulação reforça critérios para R01-G02, mas **não fecha** G02, nem G01-G03..G11 ou CRM CORE/SEG/AUT. O CRM PR #57 permanece apenas uma proposta bilateral documental em rascunho.
- A resposta pública real precisará normalizar indisponibilidade e negações para não vazar recursos, com auditoria segura, autenticação e contratos aprovados.
- `main`, CRM, Portal, VPS, produção e fornecedores pagos permanecem intocados.

## Gate de validação

Usar somente workflow de verificação em repositório **público**, sem segredos, checkout fixado no commit exato, PHP lint em 68 arquivos e runner oficial com 198 casos sem falhas. Revisar o diff e conferir o HEAD da `dev` imediatamente antes de qualquer squash exclusivo na `dev`; sem autorização implícita para integração funcional.
