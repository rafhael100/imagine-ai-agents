# FT01/F1-15 — Kit bilateral ilustrativo de conformidade CRM OP-01

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
