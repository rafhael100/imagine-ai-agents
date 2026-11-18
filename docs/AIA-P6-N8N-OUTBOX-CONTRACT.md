# Agents P6 — outbox idempotente para futura comunicação com n8n

**16/11/2026 — contrato e banco PostgreSQL 16 SOMENTE EM CI; não há n8n instalado nem conectado por este recorte.**

O Agents já possui ensaios de cotas duráveis (P4) e um pré-roteador que prefere automações sem IA (P5). O P6 prepara a infraestrutura de eventos **sem rede** necessária para, futuramente, o n8n central receber notificações autorizadas com idempotência. Não é um webhook real.

## Contrato fechado

`MockN8nOutboxContract` emite um JSON canônico, com exatamente sete chaves em ordem fixa: `contract`, `event_ref`, `tenant_ref`, `run_ref`, `kind`, `reference_ref`, `occurred_at`. Só são válidos `mock.approval.requested`, `mock.run.completed` e `mock.quota.threshold`. As referências são sintéticas, sem contato, telefone, e-mail, conteúdo de prompt, credenciais nem URL. Payload arbitrário e campos adicionais são proibidos; JSON duplicado ou não canônico é recusado.

## Padrão outbox em PostgreSQL

No banco **efêmero** `imagine_agents_ci`, a classe `tools/labs/PgN8nOutboxLab.php` prova armazenamento idempotente por `(tenant_ref,event_ref)`, detecção de mesmo ID com conteúdo diferente, seleção com `FOR UPDATE SKIP LOCKED`, **lease de 60 segundos**, até **três tentativas** e retenção de dead letters. `ACKED` só pode ser registrado pelo mesmo worker dentro do lease; um segundo ACK falha. Diferentes empresas sintéticas não compartilham estado. O CI trava uma linha em conexão PostgreSQL e confirma que outra conexão consegue buscar o evento seguinte sem esperar a primeira. Tudo é dado de laboratório, sem envio externo.

## Responsabilidades quando integrar ao n8n de verdade

1. **CRM/Portal:** resolver identidade empresarial/serviço por fonte confiável e autorizar gatilhos, sem confiar em `tenant_ref` enviado pelo cliente.
2. **Agents:** validar contrato versionado, gravar outbox na mesma transação do evento de negócio, classificar tarefa pelo roteador P5 e reservar créditos apenas se uma chamada a IA realmente for necessária e autorizada.
3. **n8n:** receber comunicação S2S autenticada, processar por empresa com idempotência e limites, devolver ACK explícito e nunca receber conteúdo sensível não aprovado.
4. **Operação:** definir TLS/mTLS, rotação de chaves, timeouts, alarmes, política de egress, isolamento de contêiner, retenção, dead letters, revogação e rollback.

**Limites:** a implementação CI usa o superusuário de banco descartável, não possui identidade operacional, assinatura, receptor, DNS, UI n8n ou autorização para executar jobs. Falta revisão independente SEG/CORE/DBA/IAM/LGPD e homologação de OP-01 [CRM #57](https://github.com/rafhael100/imagine-crm/pull/57). Sem custos, merges, deploy na VPS ou dados reais.
