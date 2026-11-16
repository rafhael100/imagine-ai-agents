# P13 — Verificação recíproca P12 ↔ AUTO-IA sem mesclar implementações paralelas

**16/11/2026 — revisão de compatibilidade, não integração operacional.**

O desenvolvimento do ecossistema Agents tinha duas linhas evoluindo em paralelo:

1. **P7/P12** — interface Assistente Comercial, cotas PG16, outbox de eventos `MockN8nOutboxContract`, prevenção de ACK atrasado, controle de créditos de IA e recibos sintéticos Ed25519; candidata [#364](https://github.com/rafhael100/imagine-ai-agents/pull/364).
2. **AUTO-IA RC02 e CONV-02** — catálogo de operações `NO_AI` para os projetos, inbox de laboratório, envelopes S2S sintéticos, pacote de preparação n8n sem instalação e adaptador explícito P7↔AUTO-IA; candidata [#358](https://github.com/rafhael100/imagine-ai-agents/pull/358), adaptador [#360](https://github.com/rafhael100/imagine-ai-agents/pull/360).

A PR #360 já testa a compatibilidade contra o P7 **fixado** no SHA `4ba4f486faf0f9b4bf8b73be299fa840c54ed6bc`. Mas a P12 avançou além desse ponto. Não devemos assumir que o adaptador permaneceu compatível sem ensaio recíproco.

## O que a CI P13 comprova

O GitHub Actions faz checkout do candidato P12 desta branch e, separadamente, do **exato** SHA `8b04d0e936168c77db028160b5aeb1758db497c4` do adaptador CONV-02. Sem editar, mergear ou fazer cherry-pick, executa o **teste original** `tools/auto_ia/convergence/check_convergence_02.php` com a versão atual do `MockN8nOutboxContract` do candidato P12. Preserva a semântica oficial CONV-02: `mock.run.completed → run.status.project`, `mock.quota.threshold → usage.quota.evaluate`; pedidos de aprovação não mapeados, empresa divergente e referências incompatíveis permanecem negados. A decisão `LAB_NO_AI_PROPOSAL_NOT_SIGNED` é apenas uma proposta e não autorização, assinatura, execução nem consumo de IA.

Também são executados a verificação estática de bloqueios RC02 e testes da bancada comercial P0–P2 e do pré-roteador P5. Qualquer divergência de contratos deve falhar a CI e manter **NO-GO**.

## Próximo passo após CI verde

Antes de uma eventual incorporação das duas linhas, revisão humana dos diffs e definição de repositório/branch de referência; IAM/tenant verdadeiro, assinatura S2S com chave operacional, controle de permissões por evento, inbox/outbox transacional do mesmo ciclo de negócio e n8n autenticado na VPS ainda não foram implementados de modo operacional. O pacote n8n #356 é preparação, **não significa instalação na VPS**, e o fluxo #357 é JSON importável, não execução no n8n real.

Nenhum merge, acesso a clientes, cobrança, deploy, segredos ou serviço externo neste recorte. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
