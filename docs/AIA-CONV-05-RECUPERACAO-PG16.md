# AIA-CONV-05 — Recuperação PostgreSQL e proteção contra duplicidade

Integração laboratorial de [CONV-04 #367](https://github.com/rafhael100/imagine-ai-agents/pull/367), seguindo [issue #359](https://github.com/rafhael100/imagine-ai-agents/issues/359). O objetivo é garantir que as salvaguardas de idempotência e isolamento de empresa **sobrevivam ao backup/restauração**.

**Fonte P12 original fixada no commit `aacbfdaa9fbfd878834d3c7b46eeaf99b4b8fdd9`**, sem uso de branch mutável.

## Teste automatizado (GitHub Actions, banco inteiramente descartável)

1. Cria PostgreSQL 16, o catálogo, as roles de laboratório, a inbox com FORCE RLS e o ingresso assinado; habilita uma chave HMAC **sintética** exclusiva do ensaio.
2. Usando o código de outbox original P12, registra eventos da empresa fictícia A e B e obtém ACK com geração/fencing depois que a inbox confirma persistência assinada; mantém um evento de aprovação **não mapeado** em dead-letter após três tentativas.
3. Realiza `pg_dump -Fc` do banco descartável e `pg_restore --exit-on-error` em **segundo banco** criado no próprio runner GitHub. O banco original não é alterado.
4. Verifica as permissões restauradas, FORCE RLS, catálogo 16 NO_AI, ingressos persistidos sem duplicidade, duas mensagens ACKED e uma DEAD na outbox original P12.
5. Simula replay assinado da empresa A no banco restaurado, que deve retornar `LAB_DUPLICATE_NO_DELIVERY` sem nova gravação; empresa B não pode ler o evento A e vice-versa.

Nenhum arquivo de backup é publicado como artefato ou enviado para fora do runner. O backup contém apenas referências e segredos **fictícios**, armazenados temporariamente. Nenhuma prova desta etapa equivale a recuperação real da VPS, segredos verdadeiros, IAM operacional ou validação de plano de continuidade em produção.

## Gates restantes
O teste não ativa o n8n, não cria endpoints HTTP, não consome IA, não altera CRM/Portal/Sites, não autentica uma empresa real e não libera cobrança. Backup/restore em host real, identidade/segredos, egress, custos, revisão independente e rollback de serviços continuam pendentes. Revisões P12 e RC02 devem convergir antes de implantar qualquer coisa.

**NO-GO: sem merge, deploy, VPS ou dados reais.**
