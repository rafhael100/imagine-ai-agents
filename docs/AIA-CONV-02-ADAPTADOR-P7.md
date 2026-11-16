# AIA-CONV-02 — Adaptador estrito P7 → AUTO-IA (sem rede)

Motivação: [Issue #359](https://github.com/rafhael100/imagine-ai-agents/issues/359), [P7 #352](https://github.com/rafhael100/imagine-ai-agents/pull/352) e [AUTO-IA-RC02 #358](https://github.com/rafhael100/imagine-ai-agents/pull/358).

**Contrato P7 fixado:** commit `4ba4f486faf0f9b4bf8b73be299fa840c54ed6bc` (checkout somente leitura em CI).

## Mapeamento revisado, inicialmente restrito
| P7 `kind` | AUTO-IA proposta | Resultado |
| --- | --- | --- |
| `mock.run.completed` | `imagine-ai-agents/run.status.project` | NO_AI, somente proposta sem assinatura |
| `mock.quota.threshold` | `imagine-ai-agents/usage.quota.evaluate` | NO_AI, somente proposta sem assinatura |
| `mock.approval.requested` | **sem equivalente seguro** | DENY até revisão de domínio e autorização humana |

O adaptador exige o JSON canônico validado pela classe original P7, empresa de teste derivada de parâmetro confiável, referências compatíveis com os limites do AUTO-IA e validade de 60 segundos. Ele deriva `event_ref`, `nonce_ref`, `request_ref` e hash de corpo sem dados pessoais; nunca envia diretamente à fila, não assina e não concede autorização. Uma assinatura HMAC gerada **pelo teste** é validada por AUTO-IA-09 para conferir compatibilidade, mas permanece apenas ensaio sintético sem IAM.

## Pendências críticas
- Atualização da versão P7 somente após pin/análise de diff, CI independente e rebase; não usar head mutável.
- Compatibilidade de perfis reais de negócio, aprovação humana, revogação e políticas por empresa continua em análise.
- Falta ponte efetiva entre outbox P7 e inbox RC02; não conectar automaticamente ou habilitar endpoints.
- Sem n8n ativo, dados reais, credenciais, API paga, liberação financeira, merge ou deploy.

**NO-GO de integração operacional.**
