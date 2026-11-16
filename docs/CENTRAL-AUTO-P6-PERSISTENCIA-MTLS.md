# CENTRAL-AUTO-P6 — Replay mTLS durável após reinício com PostgreSQL 16

Continuação de [CENTRAL-AUTO-P5 #397](https://github.com/rafhael100/imagine-ai-agents/pull/397). Corrige a limitação real do servidor de laboratório P5: o cadastro de nonce anterior estava somente na memória e desaparecia após o processo reiniciar.

## Implementação
- Em PostgreSQL 16 descartável do GitHub Actions, tabela `central_auto_lab.mtls_nonce_ledger` com unicidade por issuer+empresa+nonce e issuer+empresa+request_id. `FORCE ROW LEVEL SECURITY` e função `SECURITY DEFINER` negam `SELECT`, `INSERT` e `UPDATE` diretos à role restrita `ca_mtls_lab_service`.
- O serviço sintético usa autenticação de banco própria de CI, sem superusuário. O servidor primeiro verifica **mTLS, certificado do emissor, janela de tempo, audiência/empresa e HMAC**. Só então o ingresso é registrado na função PostgreSQL; erro de banco implica bloqueio do evento, sem resposta de estado.
- O teste completo P5 é reexecutado com a persistência habilitada. Após concluir, o processo Python é encerrado e iniciado do zero, mas o banco PostgreSQL fica intacto. Repetição de nonce anterior retorna **409**, mesmo com processo e memória novos.
- Outro teste faz oito conexões simultâneas com mesmo nonce e requests distintos: **uma** pode ser admitida, sete negadas. Verifica que acesso direto à tabela continua desabilitado e que outra empresa é rejeitada.
- Nenhum SQL é aplicado no CRM/Portal ou na VPS, o PostgreSQL e as chaves são eliminados com o runner CI.

## Limitações obrigatórias
- **Não há IAM operacional real**. A CN do certificado `portal-automation-admin` e o mapeamento fixo de uma empresa são exclusivamente dados sintéticos de laboratório. A conta SQL não pode substituir a autorização do serviço e da empresa por CRM/Portal.
- A tabela não tem rotina de retenção/expurgo de nonce; a decisão aqui é conservadora (não reutilizar), não um dimensionamento aprovado para produção.
- O servidor Python é fixture de CI, não o Laravel CRM. Os dados e a resposta não podem ser apresentados como integração real de clientes.
- Não há n8n instalado no servidor, API de IA, pagamentos, merge, deploy, conexão à Leaseweb, certificado real nem custo novo.

## Próxima etapa
Demonstrar `pg_dump/pg_restore` desta ledger em banco isolado e após restauração confirmar antirreplay, RLS e isolamento de empresa. Em seguida, integrar a candidata à revisão independente de IAM/segredos e aos critérios de homologação da VPS.

**DRAFT / NO-GO / SEM PRODUÇÃO / SEM COBRANÇA / SEM IA PAGA.**
