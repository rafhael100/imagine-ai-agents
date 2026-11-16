# CENTRAL-AUTO-P21 — Prova de restauração real de um pacote CMS criptografado

Base: [CENTRAL-AUTO-P20 #451](https://github.com/rafhael100/imagine-ai-agents/pull/451). Esta entrega acrescenta um ensaio de recuperação **de bancos PostgreSQL reais descartáveis** ao empacotador criptográfico P20, sem tocar na VPS Leaseweb nem em dados de clientes.

## Por que avançar
O P20 validava formato `PGDMP`, integridade do manifesto e criptografia CMS AES-256-GCM, mas os dumps dos testes eram fixtures de cabeçalho fictício. Isso **não comprova que um arquivo descifrado possa ser restaurado pelo `pg_restore`**. O P21 cobre exatamente esse risco, preservando o escopo offline.

## Etapas automatizadas
1. GitHub Actions inicia duas instâncias efêmeras oficiais: **PostgreSQL17 para o banco sintético n8n** e **PostgreSQL16 para dois bancos sintéticos separados, CRM e AI Agents**. Não conecta bancos operacionais, não publica portas no host, não utiliza armazenamento externo ou credenciais de clientes.
2. Em cada banco cria somente tabela `p21_synthetic_records` com uma linha pseudônima, escopo `SYNTHETIC_TENANT_CI`, `ai_tokens=0` e `credits_debited=0`. Usa `pg_dump -Fc --no-owner --no-acl` **real**. Verifica `PGDMP` e `pg_restore --list` antes de empacotar.
3. Cria volume TAR sintético de workflow nativo e chave de criptografia **nova e fictícia** de 64 caracteres, arquivos `0600` em diretório `0700`. Gera certificado temporário de recuperação para receber CMS. O código `seal_backup.py` da P20 deve produzir ciphertext autenticado e manifesto fechado, sem sobrescrever arquivos nem declarar backup externo.
4. Faz uma cópia do ciphertext para uma **segunda pasta no mesmo runner** (não um serviço de backup). Confere SHA-256, rejeita tentativa de decriptação por chave errada e rejeita ciphertext adulterado. Somente após decriptação com exit code zero chama `verify_decrypted` para verificar os hashes do manifesto antes de extrair.
5. Restaura os três dumps usando `pg_restore --exit-on-error` em **três bancos NOVOS e descartáveis**, separados das fontes. Confere as linhas, tokens de IA zero, créditos zero, volume sintético e igualdade dos bytes da chave de laboratório.
6. A saída sanitizada declara `external_backup_verified=false`, `different_host_restore_verified=false`, `real_customer_credentials_restored=false`, `vps_modified=false` e `go_for_installation=false`. Nenhum backup ou segredo é publicado em artefatos GitHub.

## Limites e pré-condições
- A recuperação ocorre em um único runner GitHub Actions. A cópia do ciphertext para outra pasta **não equivale a cópia externa** nem mede RPO/RTO/retention. Os esquemas de dados são sintéticos mínimos, não as migrations reais CRM/Agents/n8n.
- Não exercita restauração de credenciais cifradas reais nem recuperação em outro host físico. Esses casos ainda precisam de ensaio específico aprovado, com custodiante e armazenamento externo autorizado.
- A consistência distribuída de outbox/inbox não é demonstrada por três dumps independentes; é necessário checkpoint ou protocolo de replay coordenado e revisão DBA/AppSec.
- Para instalação na Leaseweb permanecem gates humanos de licença n8n, custódia off-host, IAM empresarial S2S e operador MFA, revisão de host, segurança de rede, backup externo e autorização de staging.

**PR DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-PAID-AI.**
