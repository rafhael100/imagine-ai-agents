# F251 — candidato final de revisão bilateral Agents ↔ CRM

**15/11/2026 — candidato técnico sintético. NO-GO / NO-MERGE / NO-DEPLOY.**

## Referências verificadas

**Imagine Agentes de IA:** F229–F250 da árvore de revisão. A implementação F231–F240 inclui Ed25519 original, escopo sintético, nonce local, prova de concorrência apenas no host, envelope inerte, resposta HTTP 404 e compatibilidade com CRM. A F250 valida, em GitHub CI com checkout read-only, o código do CRM funcional F249 na revisão imutável `c67aaa0440ac4f9f1c607883d0c1347f5a067f92`, [PR CRM #628](https://github.com/rafhael100/imagine-crm/pull/628). A CI CRM F249 completa [#43795878769](https://github.com/rafhael100/imagine-crm/actions/runs/43795878769) passou.

**CI requerida da HEAD desta PR:** R01 completo `tools/run_r01_offline_quality_gate.sh` com 879 testes PHP, lint de 117 arquivos de aplicação, testes Python/DOM e conformance histórica; F250 com fonte CRM F249 fixada e proibição de rotas Agents. **Não** declarar esse candidato aprovado antes dos resultados exatos. O stack F229–F240 passou anteriormente na PR Agents #332 com CI R01 [#43792258477](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43792258477); a verificação F250 original foi aprovada na [#43796303030](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43796303030).

## Entregas e limites

- Provas originais de assinatura e resposta: sem rede, segredos operacionais, token IAM, parser HTTP genérico ou gateway de pagamento.
- CRM funcional F249 integra o teste HTTP Laravel, nonce PG16, keyring server-owned com role de leitura exclusiva sob FORCE RLS e row-lock de revogação. **São apenas provas sintéticas no PG16 efêmero**.
- Nenhum ALLOW real, leitura de lead, endpoint de serviço, auditoria com PII, cliente ou deploy fica disponível.
- A versão de negócio com autenticação S2S/ACL/consentimento exige parecer independente CORE/SEG/IAM/DBA/LGPD, decisões de titularidade/escopo/retenção, credenciais mTLS protegidas e homologação de rollback/infra. Os 14 gates, 7 decisões e 13 cenários reais da CRM #57 permanecem **não homologados**.

A utilização deste dossiê como evidência de CI **não** autoriza promoção à `main`, VPS, produção ou cobrança.
