# AIA FT01/F1-13 — Auditoria redigida de leitura CRM, exclusivamente sintética

## Estado vigente — F1-13 integrado exclusivamente à dev (09/11/2026)

- **PR [#108](https://github.com/rafhael100/imagine-ai-agents/pull/108) MERGED por squash apenas em `dev/ft01-aia-core-mocks-2026-11-06`**, commit `92a2f0a5f01a0014051594f24d96988e111f85c7`. A `main` foi preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`; nenhum deploy, VPS, fornecedor, dados privados, CRM real ou gasto foi executado.
- **Validação efetiva:** [GitHub Actions run #42579753806](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42579753806), commit exato `d09b3c57ac7d9c601afcd9a5cbd6d4b61b895620`, PHP CLI 8.3.6, **156 cases / 0 failures**, **63/63 PHP lint PASS**, validador SHA-fixado `VALIDATION_PASS`.
- **Escopo integrado:** duas classes `src/MockAudit/`, 12 casos U13, documentação e verificador offline; trilha volátil de categorias de decisão e fingerprints de IDs fictícios, sem conteúdo de lead ou credenciais. O workflow de teste usa executor GitHub padrão apenas quando o repositório é público.
- **Não se confundir com segurança real:** fingerprints de laboratório não são credenciais ou pseudonimização de produção; não há autenticação, ACL CRM, storage durável, anti-replay distribuído, gateway real, LGPD operacional, IA ou serviços. O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue apenas documental e draft.
- **Gates:** R01-G01 a G11 e CRM CORE/SEG/AUT ainda **ABERTOS**. A próxima fase autorizada pode fazer somente simulações offline de falhas entre instâncias, sem uso de clientes, produção, `main` ou serviços externos.

**Precedência:** os registros inferiores de F1-13 “ainda em revisão/sem merge” são históricos, anteriores à aprovação da suíte e ao squash #108.

---


**Data:** 09/11/2026. **Estado:** implementação em branch filha e PR para revisão; **não homologada, não integrada à dev nem à main**. Não cria serviço de auditoria real.

## Escopo técnico
- `src/MockAudit/MockCrmReadAuditTrail.php`: trilha **volátil em memória**, capacidade 1–100 (padrão 32). Guarda operação fixa `crm.lead.read_minimal`, dois fingerprints SHA-256 de **referências fictícias**, resultado `READ_SYNTHETIC_ONLY` ou `DENIED`, razão de uma lista fechada e flags fixas `real_authorization=false` e `external_calls=0`.
- `src/MockAudit/MockCrmReadAuditedBoundary.php`: decorador do F1-12, sem modificar nem contornar `MockCrmReadBoundary`, `MockTrustGateway` ou a autorização proprietária do CRM. Verifica capacidade **antes** de avaliar a leitura; se faltar espaço, nega. Registra categoria fechada de negação, nunca a mensagem bruta da exceção; conserva a exceção original e não faz fallback de leitura.
- `tests/Unit/crm_read_audit.php`: 12 casos U13-01..12 para sucesso mínimo, privacidade, negações por tenant/prova/identidade, replay, capacidade, entrada malformada, cópia defensiva e preservação da projeção F1-12.
- O runner oficial e o validador SHA-fixado passam a **esperar** 156 casos e 63 arquivos PHP. Isso é **critério de validação futura**, não resultado homologado.

## Dados ausentes por desenho
Não armazena conteúdo do lead, campos projetados, dados pessoais, token, credencial, nome da empresa, usuário, mensagem livre de erro, nem payload de requisição/resposta. As referências de laboratório são inteiramente `SYNTHETIC_*`. Os fingerprints são SHA-256 **não autenticados e sem segredo**, portanto **não são pseudonimização adequada para produção** nem prova de identidade. A memória é perdida no fim do processo; inexiste persistência, expurgo operacional, log imutável ou integração com SIEM.

## Evidências e pendências
- PHP CLI 8.4.24 local: os **3 arquivos PHP novos** passaram `php -l`.
- Foram executados **12/12 testes U13 em um harness focal com dublês locais da F1-12**; esse resultado valida a lógica isolada e **não substitui** o runner do repositório.
- Os três blobs publicados no GitHub foram confrontados com `git hash-object` dos mesmos arquivos executados localmente (iguais).
- **Pendente:** checkout íntegro de HEAD, execução real de `php tests/Unit/run.php` com **156/156**, lint de **63/63**, `EXPECTED_SHA=<SHA> bash tools/validate_ft01_offline.sh`, revisão de diff, verificação de branch base e mergeabilidade. Não executar GitHub Actions pagos nem criar infraestrutura para suprir a prova.
- O PR permanecerá **DRAFT / NO MERGE** até passar o preflight completo; não anunciar 156/156 como resultado já atingido.

## Limitações intencionais e próximos gates
- Uma requisição inválida que nem chega a ser instanciada como `MockCrmReadRequest` não é registrada por este decorador; uma API real precisará de observabilidade na sua borda.
- Não há anti-replay distribuído, identidade verificável, ACL, autorização do CRM, banco, rede, custódia de segredos, retenção LGPD ou tolerância a falhas entre processos.
- Os gates **R01-G01–G11** e **CRM CORE/SEG/AUT** permanecem **abertos**. A proposta bilateral [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é documentação em rascunho. Nunca conectar dados/clientes, LLM, APIs, `main` ou produção sem decisões críticas e homologação real.
