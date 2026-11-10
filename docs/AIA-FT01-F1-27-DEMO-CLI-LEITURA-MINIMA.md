# FT01/F1-27 — Demonstração CLI controlada do fluxo de leitura mínima

**Data:** 09/11/2026. **Estado:** offline sintético, NO-GO para CRM real.

## Entrega
`examples/f127-read-only-pilot-demo.php` é um cenário **determinístico de linha de comando** que utiliza o código integrado F1-26 sem solicitar parâmetros, tokens, conexão, biblioteca de terceiros, ambiente de homologação ou dados de clientes.

```bash
php examples/f127-read-only-pilot-demo.php
```

O JSON público contém categorias de cinco cenários: `authorized_minimal_read`, `nonce_replay`, `cross_tenant_request`, `private_field_request` e `missing_owner_grant`. A primeira retorna `SUGGESTION_SYNTHETIC_ONLY` e apenas a frase fixa `SYNTHETIC summary proposal only`. Todos os demais retornam `DENIED_OR_NOT_FOUND`. O relatório final fixa `NO_GO`, `real_authorization=false`, `external_calls=0`, `write_calls=0`.

**Evidência verificável:** o script inspeciona auditoria sintética, tentativas de borda e ausência de `submit/status` antes de emitir qualquer JSON. Se essas invariantes forem quebradas, encerra com mensagem genérica e código não zero.

## Regressões adicionais

`tests/Contracts/test_f127_offline_pilot_demo.py` executa o PHP e verifica:

1. Exatamente os cinco cenários, estados previstos e ausência de chamadas mutáveis ou externas;
2. Saída byte-a-byte determinística mesmo com texto adversarial no `stdin`, que o demonstrador não interpreta;
3. Ausência de referências sintéticas internas, identificadores de empresa/pessoa, nonce e informações de autorização na resposta;
4. Ausência dos mecanismos de transporte ou credenciais proibidos no ponto de entrada.

O gate `tools/run_r01_offline_quality_gate.sh` passa a exigir esses testes, e `tools/validate_ft01_offline.sh` inclui o novo exemplo na lista de arquivos obrigatórios. Baseline esperado: **351 casos PHP, 82 arquivos PHP com lint**, mais testes de contrato Python existentes.

## O que isto não demonstra

Não existem APIs reais, autenticação/SSO, autorizações do CRM, RLS, filas, banco de dados, chamada a LLM, pagamento, hospedagem ou persistência. O conteúdo é fictício e o resultado `NO_GO` não muda com teste verde. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua proposta não homologada; R01-G01..G11, CORE/SEG/AUT continuam pendentes. Nenhuma alteração em `main`, VPS ou produção.
