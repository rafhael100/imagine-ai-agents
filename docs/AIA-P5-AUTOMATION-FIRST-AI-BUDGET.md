# Agents P5 — priorizar automações determinísticas e evitar gasto desnecessário de IA

**16/11/2026 — política pura de pré-classificação, SEM chamadas de fornecedor, créditos ou n8n real.**

O produto Imagine precisa preferir processamento determinístico sempre que possível, e chamar um modelo generativo somente quando a finalidade realmente exigir compreensão de linguagem ou criação de texto. Este recorte formaliza essa decisão em código PHP puro `src/MockOrchestration/MockAiOrAutomationRouter.php`, sem SQL/HTTP/filesystem, executores nem chamadas externas.

## Política inicial de laboratório

| Operação permitida | Rota de pré-classificação | Créditos de IA |
|---|---|---|
| `mock.lead.normalize_stage` | `DETERMINISTIC_ONLY` | 0 |
| `mock.lead.route_approval` | `DETERMINISTIC_ONLY` | 0 |
| `mock.funnel.estimate` | `DETERMINISTIC_ONLY` | 0 |
| `mock.quota.check` | `DETERMINISTIC_ONLY` | 0 |
| `mock.run.deduplicate` | `DETERMINISTIC_ONLY` | 0 |
| `mock.copy.draft` | `MODEL_ELIGIBLE_NOT_EXECUTED` se todas as condições forem satisfeitas | 0 neste pré-check; apenas estimativa |
| `mock.text.classify` | `MODEL_ELIGIBLE_NOT_EXECUTED` se todas as condições forem satisfeitas | 0 neste pré-check; apenas estimativa |
| Operação não reconhecida, efeito externo, dado sensível, escopo errado | `DENIED` | 0 |

Para uma tarefa semântica aparecer como candidata, é necessário: `ai_opt_in` explícito, consentimento e aprovação do uso de modelo registrados no contrato de teste, provedor simulado como configurado, ledger com medição confiável, limite por chamada e saldo estimado suficientes, operação permitida e conteúdo **somente fictício**. Qualquer condição ausente nega. Mesmo `MODEL_ELIGIBLE_NOT_EXECUTED` **não concede permissão de execução**: o resultado sempre possui `execution_allowed=false`, `credits_consumed=0`, `external_calls=0`, `operational_decision=NO_GO`.

`MockUsageLedger` e `MockUsagePolicy` já existentes no produto seguem responsáveis pelos ensaios de medição e reconciliação. A F4/PR #347 foi criada para provar idempotência/quota PG16 real apenas em CI. **Essas provas não são um ledger operacional de créditos** e não autorizam que uma estimativa seja cobrada. A execução real futura exigirá reserva atômica de crédito por empresa, tarifação por provedor/versão, contabilização observada, reconciliação em timeout, reversão e prova audível de custo antes da implementação de API.

## Validação e próximo passo

O CI P5 testa cinco operações gratuitas, duas semânticas elegíveis e trinta vetores adversariais: tenant diferente, ID malformado, operação desconhecida, efeito externo, texto sensível, condições IAM ausentes, consentimento, saldo/limite, ledger incerto e injeção de chaves. Também verifica ausência de métodos de IO no arquivo.

**Não há instalação n8n, worker, endpoint, cobrança, chamada a API de IA ou publicação nesta PR.** P6 deverá preparar contrato de evento de automação independente e idempotente, sem rede e sem autorização operacional, para posterior integração supervisionada ao n8n central.
