# CENTRAL-AUTO-P3 — Contrato CRM ↔ Portal ↔ AI Agents com fontes originais fixadas

Continua a diretriz aprovada de automações [Agents issue #339](https://github.com/rafhael100/imagine-ai-agents/issues/339) e complementa a [RC04 #387](https://github.com/rafhael100/imagine-ai-agents/pull/387). Evita duplicar o motor nativo do CRM e o Gateway já desenvolvidos.

## Fontes originais, não simulações de código
- **CRM [PR #634](https://github.com/rafhael100/imagine-crm/pull/634)** — commit dd88a88b29f9c659786793bbb677355a06ea83bd. Carrega diretamente as classes originais RuleDefinition e AutomationSettings.
- **Portal [PR #904](https://github.com/rafhael100/imagine-portal/pull/904)** — commit 9d3877dfaa81524faf2c08f80244b485968316ec. Executa diretamente AutomationStateReader, que valida grants antes e depois da consulta.
- **AI Agents [PR #384](https://github.com/rafhael100/imagine-ai-agents/pull/384)** — commit 21c820b56d53bce243969a4fdd440bf1e26644e5. Executa MockS2SEnvelopeVerifier sem reinvenção de assinatura.

Checkout das três fontes é somente leitura, com verificação SHA exata. Não há merge entre repositórios, alteração de main, URL real ou credenciais.

## Verificações concretas
- CRM: regra nativa `followup_qualified` cria comando tipado `CRM_NATIVE_COMMAND_V1` com `ai_calls=0`. Reenvio preserva idempotência; mudar a versão gera outra chave; operação de webhook configurável é rejeitada; evento de outra empresa não passa.
- Portal: contrato `CRM_AUTOMATION_STATE_V1` de laboratório com ação sanitizada, sem PII, é aceito com dois grants; revogação antes e durante leitura bloqueia, sem fallback a zero; rejeita empresa trocada, consumo de IA declarado, alegação operacional, timestamp vencido, segredo injetado ou ação desconhecida.
- Agents: contrato `AUTO_IA_S2S_V1` HMAC em fixture sintética verifica a operação `lead.ingest`, mas **não concede IAM operacional**; `task.followup.create` **não pode ser enviado automaticamente ao n8n** nem ignorar o autorizador nativo do CRM. Alterar empresa, chave e destino invalida o ensaio.
- Toda operação de teste declara zero IA/cobrança; não faz chamada ao provedor, banco real, Laravel real, HTTP entre apps ou n8n.

## Limites fundamentais
1. **Não é uma aplicação de ponta a ponta conectada.** O estado é uma fixture construída a partir do contrato exportado pelo CRM, não uma chamada real ao endpoint `/automacoes/estado`, e não há fonte autenticada registrada no Portal.
2. O identificador da empresa no CRM/Portal é UUID, enquanto o envelope fictício Agents usa `SYNTHETIC_TENANT_A`. **Não existe mapeamento IAM real**, e não se deve converter identificadores por texto, inferência ou browser.
3. O efeito nativo `task.followup.create` pertence ao CRM e não se converte silenciosamente em evento n8n. O contrato de origem e a autorização do destino precisam de análise independente.
4. A PR Agents #384 está separada da RC04. O teste importa a classe original fixada e não resolve sozinho sua convergência com a linha de regras/segredos da RC04.
5. Para conexão real faltam certificado/identidade serviço-serviço, autorização do CRM por escopo, receiver privado no Portal, anti-replay persistente, segredo protegido, revalidação de revogação e plano de reversão.

**Status:** contrato cruzado testado em CI com dependências fixas; **NO-GO para merge na main, instalação n8n, VPN/VPS, API paga, pagamento e dados reais**.
