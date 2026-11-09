# AIA FT01/F1-13 — Auditoria redigida de leitura CRM, exclusivamente sintética

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
