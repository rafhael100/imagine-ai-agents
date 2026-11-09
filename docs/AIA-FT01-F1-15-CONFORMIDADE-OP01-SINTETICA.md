# FT01/F1-15 — Kit bilateral ilustrativo de conformidade CRM OP-01

## Estado vigente — F1-15 integrado exclusivamente à dev (09/11/2026)

- **PR [#112](https://github.com/rafhael100/imagine-ai-agents/pull/112) MERGED por squash somente em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `4a2a3b31bb422446a62e563616bd98b45ca3536c`. A `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; sem deploy, VPS, CRM real, segredos ou fornecedor pago.
- **Prova executada:** [GitHub Actions run #42581627932](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42581627932), head SHA-fixado `efa6a3b9584c15705716f54f1fb47dbe31d0dab2`, PHP 8.3.6, **180 cases, 0 failures**, **66/66 lint PHP PASS**, `VALIDATION_PASS`.
- **Conformidade somente fictícia:** 12 cenários OP-01, dos quais 10 negam alterações inválidas de versão/operação/escopo/campo/contexto e 2 permitem apenas projeção sintética `ref/stage`; não existe autorização real do CRM.
- **Gates e responsabilidades:** R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS**. O CRM PR #57 permanece proposta documental DRAFT, sem API ou permissão real. Antes do piloto é indispensável definir identidade verificável, ACL no CRM por recurso/campo, contrato bilateral, retenção/privacidade, implantação isolada e responsável/aceite.
- **Próximo trabalho seguro sem aprovação crítica:** pacote documental de critérios de aceite e fixtures de falhas; não substituir homologação de segurança por testes mockados.

**Precedência:** quaisquer passagens inferiores que indiquem F1-15 em revisão e sem regressão completa são registros históricos anteriores ao CI e squash #112.

---


**Data:** 09/11/2026. **Status antes dos testes:** branch filha de desenvolvimento, **não integrado à dev nem a main**. Código e dados exclusivamente sintéticos; esta etapa NÃO representa aceite do CRM, revisão jurídica, segurança operacional ou uma API acessível.

## Finalidade
Fazer a equipe futura de CRM e Agents revisar uma suíte de requisições fictícias reproduzíveis para a operação exclusiva `crm.lead.read_minimal` — sem copiar dados de clientes ou presumir que a API CRM já exista.

## Entregáveis
1. `contracts/fixtures/f115-crm-op01-conformance.synthetic.json`: suite versionada `SYNTHETIC_F115_CONFORMANCE_V1` contendo **12 cenários**: 10 negações por versão, escrita, ampliação de autoridade, campo privado, projeção inválida ou troca de contexto; 2 leituras fictícias aceitas somente com projeção `ref/stage`.
2. `tests/Unit/op01_conformance.php`: consome a fixture com PHP nativo e verifica que o parser/bridge **F1-12 existentes** obedecem ao conjunto de erros esperados. Sem criar novo autorizador e sem duplicar o mock do CRM.
3. Runner oficial e validador offline com expectativas de **180 casos** e **66 arquivos PHP**; esses números são **alvos** até haver prova efetivamente executada no CI.

## Critérios de segurança desta etapa
- Os próprios nomes `SYNTHETIC_*` são identidades fictícias, não tokens de serviço.
- A fixture não inclui dados pessoais, CPF/CNPJ, leads reais, contatos, credenciais ou aprovações de negócio.
- A camada Agents não decide permissões reais. O CRM é dono final dos recursos, campos, vínculos de empresa e autorização backend.
- Operações de escrita, campos `email` e troca de tenant/principal/resource são rejeitados em modo sintético.
- Não há HTTP, SQL, migração, proveedor LLM, Redis, n8n, VPS, deploy, custos nem alteração do CRM.
- **Gates R01-G01..G11 e CORE/SEG/AUT CRM permanecem ABERTOS**. O CRM PR #57 segue apenas documento DRAFT; aprovação bilateral efetiva é futura.

## Pré-merge exclusivamente na dev
- Conferir diff contra a dev e garantir apenas fixture, testes, docs e workflow público grátis.
- Exigir GitHub Actions com checkout SHA exato, `180 cases, 0 failures` e `php_lint=66` em `tools/validate_ft01_offline.sh`.
- Não interpretar PASS em fixture como aceite formal do produto CRM ou autorização de serviço.
