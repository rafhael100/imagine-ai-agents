# AUTO-IA-04 — Reconciliação sintética, sem crédito ou cobrança real

Continua [AUTO-IA-03 #342](https://github.com/rafhael100/imagine-ai-agents/pull/342). A regra central é **não confundir três unidades diferentes**:

| Medida | Origem do laboratório | Pode debitar? |
| --- | --- | --- |
| Tokens estimados e observados | Reserva/observação sintética do ledger técnico | Não |
| Custo estimado/observado em unidades menores (sem moeda vinculante) | Projeção do ledger técnico | Não |
| Créditos comerciais propostos | Registro independente de uma hipótese de política comercial | Não |

## Implementação
- \`SyntheticReconciliation\` cria uma tabela separada para **propostas** de créditos, com identidade por empresa, produto e evento, rejeição de conflitos e replay idempotente.
- Projeção fechada do evento inclui estimativas, observações, exposição conservadora, diferenças para maior, proposta de crédito e campos imutáveis \`commercial_credits_debited=0\` e \`live_billing_authorized=false\`.
- Um timeout preserva \`observed_provider_cost_minor=null\` (desconhecido) e gera \`REVIEW_REQUIRED_UNCERTAIN_SPEND\`, nunca assume custo zero.
- Um resultado observado com recibo **não assinado** continua \`REVIEW_REQUIRED_CREDIT_POLICY\`: não vira fatura, conversão automática, compra, débito, nem prova do provedor.
- Testes usam somente SQLite temporário e fixtures sem dados reais; não existe implementação n8n nem modelo autorizado.

## Verificação
\`\`\`bash
python3 tools/auto_ia/check_auto_ia_03.py
python3 tools/auto_ia/check_auto_ia_04.py
\`\`\`

## Antes de qualquer operação
Implementar depois contrato real de autorização assinada, persistência PostgreSQL transacional RLS, idempotência distribuída, conciliação verificável do custo do fornecedor em moeda explícita, tabela oficial de conversão de créditos comerciais, regras de tributação/recibos, suporte de rollback e revisão independente. Nenhum serviço pago poderá usar estas fixtures para se autorizar.
