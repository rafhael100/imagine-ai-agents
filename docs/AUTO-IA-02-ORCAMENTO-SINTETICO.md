# AUTO-IA-02 — Pré-validação sintética de orçamento de IA

Herda [AUTO-IA-01](AUTO-IA-01-AUTOMACAO-PRIMEIRO.md) e a [diretriz #339](https://github.com/rafhael100/imagine-ai-agents/issues/339). Nenhum provedor é acionado.

O `MockAiBudgetGate` recebe evento estritamente sintético e uma fixture separada de orçamento. Apenas `AI_REVIEW_REQUIRED` de AUTO-IA-01 passa para a inspeção. Rejeita tenant/evento/produto/operação divergentes, dados extras, política inválida, desativada ou expirada, mais tokens que o teto e estimativa financeira acima do saldo.

O resultado positivo chama-se **LAB_BUDGET_WITHIN_LIMIT**, com reason **EXTERNAL_AUTHORIZATION_NOT_IMPLEMENTED**: não é permissão para gastar, reserva, débito nem autenticação. Toda resposta contém `provider_calls=0`, `model_tokens=0`, `billed_minor=0`. Operações NO_AI nunca precisam de saldo.

## Testes
```bash
php -l tools/auto_ia/MockAiBudgetGate.php
php -l tools/auto_ia/check_auto_ia_02.php
php tools/auto_ia/check_auto_ia_02.php
```

## Bloqueios para próxima fase
- IAM verdadeiro, consentimento/escopo vigentes, origem do orçamento e assinatura verificável.
- Reserva de custos e cotas atômica em armazenamento transacional compartilhado; proteção contra concorrência, repetição e rollback.
- Medição de tokens reais e conciliação com custos do provedor em unidade monetária explícita; créditos comerciais e tokens são grandezas distintas.
- Gestão de credenciais e modelos autorizados, observabilidade sem PII, timeout e recuperação de falhas.
- Integração n8n e serviços por APIs privadas após revisão independente, sem acoplar aplicativos aos bancos.
- Sem merge, deploy, VPS ou consumo de IA nesta entrega.
