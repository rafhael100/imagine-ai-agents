# AUTO-IA-10 — Ensaio de ingresso assinado em PostgreSQL

Próximo passo da [AUTO-IA-09 #353](https://github.com/rafhael100/imagine-ai-agents/pull/353). **Somente no runner descartável GitHub Actions.**

## Modelo de segurança demonstrado
- Usa `pgcrypto` em PostgreSQL 16 para verificar HMAC SHA-256 com um segredo sintético que os papéis de tenant não podem consultar.
- Tabela de chaves separada por emissor e key-id, chave A ativa e B desativada para provar revogação.
- O mesmo contrato de assinatura de 14 campos é gerado por PHP e validado pelo banco.
- A função de ingresso **sem assinatura** da AUTO-IA-08 deixa de ser executável pelos papéis de tenant. Eventos só chegam pelo wrapper HMAC.
- Rejeita divergência entre tenant assinado e papel de serviço, mudança de payload, identidade, assinatura, janela temporal, chaves revogadas e tentativa de operação que requer IA.
- Ainda persiste somente no inbox de laboratório: **nenhum dispatch para n8n, nenhum worker, nenhuma operação de negócio nem custo**.

## Gaps operacionais
O login PostgreSQL e a identidade de CRM/Portal continuam fictícios. Em produção será preciso identidade serviço-serviço mutuamente autenticada, secret store e rotação, isolamento de usuários sem SUPERUSER nem livre SET ROLE, TLS, revogação e provas negativas contra replay distribuído. As funções deste laboratório jamais podem ser copiadas diretamente para banco de produção sem revisão DBA/AppSec.

**NO MERGE / NO DEPLOY / NO VPS / NO PROVIDER.**
