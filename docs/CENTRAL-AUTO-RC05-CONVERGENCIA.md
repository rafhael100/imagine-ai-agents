# CENTRAL-AUTO-RC05 — Convergência da revogação de chaves e contratos cruzados

Continua [PR Agents #384](https://github.com/rafhael100/imagine-ai-agents/pull/384), [AUTO-IA-RC04 #387](https://github.com/rafhael100/imagine-ai-agents/pull/387) e [CENTRAL-AUTO-P3 #389](https://github.com/rafhael100/imagine-ai-agents/pull/389). Implementação aprovada de diretriz, mas com **NO-GO operacional**.

## Problema e correção efetiva
O receptor PostgreSQL podia ler a chave HMAC ativa sem manter lock até o commit. Uma revogação poderia confirmar após verificação e antes de o evento ser inserido ou deduplicado. Nesta candidata importamos a correção **literal, byte a byte**, da PR #384: `FOR SHARE` na leitura da linha de chave, obrigando revogação concorrente por UPDATE a esperar o fim da transação de ingresso.

**Testes:** sessões PostgreSQL16 concorrentes e dados fictícios: ingressar com chave válida mantém transação, revogação com lock_timeout não consegue confirmar antes do commit; após confirmar revogação, repetir mesmo envelope é negado, e há apenas um evento. Reexecuta testagem da inbox assinada original e controles de RLS existentes. SHA Git de três arquivos comparado aos hashes da PR #384 na própria CI, evitando deriva.

## Contratos cruzados integrados
Outro job executa os testes das classes **reais pinadas** CRM #634, Portal #904 e Agents #384 da [PR #389](https://github.com/rafhael100/imagine-ai-agents/pull/389), com autorização antes/depois, revogação, idempotência do CRM e negação do mapeamento `task.followup.create` para n8n.

## Limitações
- A linha de chaves e assinatura usa dados sintéticos em banco descartável; nenhum login de serviço ou identidade de empresa real está implantado.
- `FOR SHARE` ordena transações de ingresso e revogação, mas não cancela efeitos já confirmados. Tempo máximo de transação e rotação real ainda precisam de revisão DBA/AppSec.
- A PR RC04 contém n8n real **somente em runner GitHub Actions sem rede**, não há n8n instalado na Leaseweb.
- Não existe conexão HTTP operacional entre CRM e Portal; o estado do Portal ainda é fixture e não uma concessão de serviço.
- Separação de licenças, segredo, mTLS, egress, backup/restore na VPS e aprovação de release continuam bloqueios.

**DRAFT / NO-GO / SEM MERGE NA MAIN / SEM DEPLOY / SEM GASTO COM IA / SEM DADOS REAIS.**
