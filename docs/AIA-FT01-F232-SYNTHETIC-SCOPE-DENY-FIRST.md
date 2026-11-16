# F232 — pré-validação de escopo sintético com negação por padrão

**15/11/2026 — laboratório offline / NO-GO / NO-MERGE / NO-DEPLOY.**

## Entrega

- `MockSyntheticScopePreflight::matches` verifica, somente em dados `SYNTHETIC_*`, que tenant, principal, lead/recurso, finalidade e subconjunto de campos de uma requisição canônica correspondem **exatamente** a uma configuração de escopo de teste previamente injetada.
- Nenhum campo adicional é aceito. A lista de campos é limitada a `ref` e `stage`, sem duplicatas ou coerção.
- A configuração de confiança é **externa à requisição** e precisa ter formato fechado e ordem canônica. Valores malformados negam sem exceções observáveis.
- `tools/check_f232_scope_preflight.php` inclui 2 casos positivos de comparação **puramente sintética** e 20 negativos; execute: `php -l src/MockBridge/MockSyntheticScopePreflight.php && php -l tools/check_f232_scope_preflight.php && php tools/check_f232_scope_preflight.php`.

## Limites inegociáveis

A saída `true` comprova só igualdade de fixture: **não é uma decisão IAM/ACL**, não autoriza leitura, não representa consentimento ou titularidade e jamais pode liberar uma chamada ao CRM. Nada conecta o preflight F232 ao tráfego real ou ao preflight Ed25519 F231. A autoridade efetiva continua exclusivamente no backend CRM após autenticação do serviço, tenant derivado do lado servidor, política de principal/recurso/campo e nonce durável compartilhado.

O código não cria endpoints, usuários, segredos, credenciais, base de dados, internet, deploy ou gastos. `main` e VPS permanecem intocadas.

## Próximos gates

1. Revisão de diff e execução CI da HEAD exata desta branch; não declarar testes executados antes de evidência.
2. Revisão independente do contrato CRM CORE/SEG/AUT, incluindo divergências em política de campo, finalidade, delegação e revogação.
3. Rejeição de replay entre processos e em falhas de armazenamento; teste com armazenamento compartilhado atômico **somente em staging aprovado**, sem credenciais de produção.
4. Homologação real OP-01 (14 gates, 7 decisões e 13 cenários ainda não atestados) antes de qualquer decisão GO.
