# AUTO-IA-RC03 — Revisão integrada de convergência P12 ↔ RC02, sem n8n em produção

**Diretriz aprovada:** [#339](https://github.com/rafhael100/imagine-ai-agents/issues/339). **Plano de convergência:** [#359](https://github.com/rafhael100/imagine-ai-agents/issues/359).

## Escopo de revisão, no mesmo commit
- [AUTO-IA-RC02 #358](https://github.com/rafhael100/imagine-ai-agents/pull/358) e [CONV-02 #360](https://github.com/rafhael100/imagine-ai-agents/pull/360): catálogo, HMAC sintético, inbox PostgreSQL16, regras de consumo e preparação n8n sem instalação.
- [CONV-04 #367](https://github.com/rafhael100/imagine-ai-agents/pull/367): prova de ponta a ponta entre outbox original P12, adaptador de operações, inbox HMAC e ACK com fencing, perda de ACK/replay, isolamento entre empresas, dead-letter de aprovação não mapeada.
- [CONV-05 #369](https://github.com/rafhael100/imagine-ai-agents/pull/369): backup/restauração do estado P12+AUTO-IA em banco secundário descartável, confirmando RLS, idempotência, ACK e dead-letter.
- **P12 original fixado exatamente em `aacbfdaa9fbfd878834d3c7b46eeaf99b4b8fdd9`**, commit já validado por GitHub CI nativa ([R01 integral](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43819303680), [suíte P12](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43819310103)). **Não há merge** entre as bases P12 e RC02: a verificação usa checkout secundário somente leitura.

## Organização dos testes
O workflow `auto-ia-rc03-convergence.yml` executa três jobs sobre a mesma HEAD da RC03. Os dois bancos PostgreSQL são efêmeros e independentes: um para entrega/ACK/replay, outro para snapshot/restauração. O terceiro job confirma regras NO_AI, validação do Compose **sem subir contêiner**, fluxo n8n inativo e relatório de auditoria com alterações adversariais simuladas.

A verificação `check_rc03_release.py` é propositalmente **sempre NO-GO**: bloqueia regressões básicas de assinatura, concorrência, recuperação, pin de fonte e ações de infraestrutura que poderiam ser confundidas com aprovação de produção. Ela não substitui revisão humana nem prova segurança completa.

## Bloqueadores para integração operacional (NO-GO)
1. Identidade real Portal/CRM/AI Agents por organização e serviço, com revogação e segregação real; sem confiar em tenant auto declarado, role configurável ou segredo fictício.
2. Revisão independente AppSec/DBA/CORE/IAM/LGPD e resolução formal de compatibilidade com P12, principalmente `mock.approval.requested` sem mapeamento autorizado.
3. Backups/restore, logs/alertas, segredo/rotação, TLS e rede privada **homologados na VPS** (o ensaio PG CI não cobre a VPS).
4. n8n Community instalado apenas após checar licença, versão/digest, capacidade, porta, rollback e acesso administrativo seguro. Não conceder edição de workflows a clientes.
5. Uma transação DB válida ou ACK de outbox **não autoriza pagamento, publicação, comunicação externa, IA paga ou efeito de CRM**; a permissão continua no serviço proprietário.

**Decisão: DRAFT / NO-GO PARA MERGE, DEPLOY, API DE IA, CRÉDITOS COMERCIAIS E DADOS REAIS. VPS INALTERADA.**
