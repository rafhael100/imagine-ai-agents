# CENTRAL-AUTO-P7 — Backup e restauração do replay persistente mTLS

Complementa [P5 #397](https://github.com/rafhael100/imagine-ai-agents/pull/397) e [P6 #399](https://github.com/rafhael100/imagine-ai-agents/pull/399). Não refaz ledger P16/P31 dos agentes; trata exclusivamente o replay do **contrato de leitura administrativa** `CENTRAL_AUTO_MTLS_REQUEST_V1` entre CRM sintético e Portal original.

## Ensaio PostgreSQL16 em GitHub Actions
1. Reexecuta o teste HTTPS/mTLS com classes CRM e Portal originais, em processos separados com certificados de laboratório e conta de banco limitada.
2. Comprova que nonce usado antes do reinício do servidor não pode ser readmitido, e que oito operações paralelas têm apenas uma admissão.
3. Salva `pg_dump -Fc` do banco **descartável** em `/tmp` somente no runner. Cria `imagine_restored_ci`, uma segunda base inteiramente separada, e executa `pg_restore --exit-on-error` sem alterar a original.
4. Confere exatamente cinco entradas de teste, hash agregado idêntico antes/depois do restore, existência dos nonces originais, FORCE RLS, privilégios SQL mínimos e função de admissão.
5. Realiza replay depois da restauração e tenta gravar empresa não autorizada usando a role fictícia restrita; ambas tentativas são negadas e a ledger permanece com as mesmas cinco linhas.

## Limites
- São dados, CA, certificados, roles, chaves e senhas **somente sintéticos**; nenhuma cópia de backup é compartilhada, publicada ou aplicada na VPS.
- O procedimento não constitui disaster recovery operacional. Não valida armazenamento externo, rotação/custódia de segredos, plano de retenção, migrações de produção, disponibilidade na Leaseweb, degradação de rede ou revalidação real de identidade CRM/Portal.
- A regra `task.followup.create` pertence ao CRM e não passa por n8n; nenhuma API paga de IA é chamada.
- Revisão AppSec/DBA/LGPD, estado do host, licença n8n, segredos/credenciais, monitoração, alta disponibilidade e rollback seguem NO-GO.

**PR DRAFT / NO-GO / SEM MERGE, DEPLOY, CLIENTES OU CUSTOS.**
