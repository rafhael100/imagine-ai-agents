# F1-12 — Contrato CRM ↔ AI Agents, leitura mínima sintética

> **Estado atual 09/11/2026:** integrado somente à dev do Agents pelo [PR #106](https://github.com/rafhael100/imagine-ai-agents/pull/106), squash `562896763f900a8a264ea46aa35e2aeacd5e0903`. O [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) é apenas proposta documental e está DRAFT, sem merge. Trechos abaixo que descrevem o desenvolvimento em branch isolada são históricos. Sem autenticação, API CRM, ACL real ou homologação de produção.


**Data:** 09/11/2026. **Status:** teste contratual offline, em branch filha da dev. **Não representa integração de API, autenticação real, permissão CRM, homologação de dados ou implantação.**

## Objetivo
Preparar o recorte OP-01 `crm.lead.read_minimal` (AIA-R04/S01 e S02) com um contrato **estrito e versionado** que pode ser analisado bilateralmente pelo CRM e pelo Imagine AI Agents. Nenhum contrato operacional é unilateralmente aprovado pelo Agents.

## Superfícies desta etapa
- `contracts/crm-aia-readonly.v1.synthetic.schema.json` — JSON Schema Draft 2020-12 para **fixtures**, não um OpenAPI publicado. `additionalProperties=false`, versão única `SYNTHETIC_CRM_AIA_READ_V1`, operação exclusivamente `crm.lead.read_minimal`, IDs obrigatórios fictícios e projeção de no máximo dois campos `ref` e `stage`.
- `src/MockBridge/MockCrmReadRequest.php` — parser estrito sem coerção; aceita propriedades JSON em qualquer ordem, mas exige conjunto exato de chaves, tipos, versão e campos. Rejeita campos de escrita, dados pessoais, payload de suposta aprovação, dados do CRM real, arrays aninhados, campos duplicados e versões desconhecidas.
- `src/MockBridge/MockCrmReadBoundary.php` — usa **os contratos F1-11 preexistentes**, não recria um sistema de identidades; compara tenant, principal, finalidade e recurso com um contexto fictício separado, exige evidência do produto dono via `MockTrustGateway` e consulta apenas o `MockCrmReader` que contém dados pré-fixados. Devolve somente a projeção solicitada e sinalizadores `SYNTHETIC_ONLY`, `real_authorization=false`, `external_calls=0`.
- `tests/Unit/crm_read_contract.php` — U12-01..17, casos de negação e projeção; suíte total **144 casos PHP**.
- `tests/Contracts/test_crm_aia_readonly_schema.py` — Python opcional com `jsonschema` pré-instalado; dois pedidos fictícios aceitos e **18 entradas inválidas rejeitadas**. Não instala componentes nem consulta rede.
- `tools/validate_ft01_offline.sh` — `EXPECTED_SHA` obrigatório, **60 arquivos PHP** e **144 casos** por padrão; não tenta buscar dependências.

## Exemplo de mensagem de teste — **NÃO é token nem payload de API**

```json
{
  "contract": "SYNTHETIC_CRM_AIA_READ_V1",
  "request_ref": "SYNTHETIC_REQUEST_A",
  "operation": "crm.lead.read_minimal",
  "tenant_ref": "SYNTHETIC_A",
  "principal_ref": "SYNTHETIC_PRINCIPAL",
  "resource_ref": "SYNTHETIC_A_LEAD",
  "purpose_ref": "SYNTHETIC_SUMMARY",
  "fields": ["ref", "stage"]
}
```

### Erros de contrato mantidos fechados
`BRIDGE_SCHEMA_DENIED` / `BRIDGE_CONTEXT_DENIED` / `BRIDGE_PROJECTION_DENIED`; a fronteira F1-11 mantém `TRUST_DENIED` / `OWNER_DENIED` / `TRUST_REPLAY_DENIED`. O teste não expõe conteúdo de outro tenant nem cria mecanismo de escrita ou efeito. Erro de permissão real e recurso inexistente deverão ser indistinguíveis na API futura, após contrato oficial.

### Evidência efetivamente executada
- PHP 8.4.24 `php tests/Unit/run.php` → **144 cases, 0 failures, exit 0**.
- `php -l` em **60/60** arquivos PHP → PASS.
- Python `jsonschema` 4.x, `python tests/Contracts/test_crm_aia_readonly_schema.py` → `F112_SCHEMA_PASS valid=2 rejected=18`.
- Código-base da F1-11 recuperado do pacote offline previamente verificado. As duas classes PHP, testes PHP e runner F1-12 foram conferidos por hashes Git SHA-1 contra o conteúdo local testado. O JSON Schema publicado no GitHub tem formatação textual própria, porém suas propriedades foram definidas para coincidir com as validadas localmente.
- Nenhuma chamada HTTP, credencial, conexão a CRM, banco, API, LLM, mensagem externa, SQL, migração ou despesa.

## O que falta antes do primeiro uso real
1. **CRM** revisar e aceitar o formato, campos, erro, limites e autoridade final, sem presumir que a rota OP-01 existe.
2. R01-G01 autenticação verificável de serviço (issuer/audience, ambiente, segredos/rotação, TTL, revogação); os IDs fictícios não comprovam identidade.
3. R01-G02/CRM CORE02/SEG: ACL do recurso e campos verificada no backend proprietário, inclusive negações entre tenants e revogação atual.
4. R01-G05/G08/G10/G11 e demais gates: LGPD, contrato versionado bilateral, logs mínimos, infraestrutura, ameaças, plano de pilotos e orçamento.
5. Testes integrados futuros em ambiente isolado **sem produção** com identidades reais de serviço de teste, somente após autorizações e proprietários revisarem a proposta.

**Limite:** F1-12 conclui a **compatibilidade de contratos fictícios** e não habilita nenhuma rota, autenticação, `ALLOW`, dado de cliente ou deploy. A `main`, VPS, produção e gastos permanecem inalterados.
