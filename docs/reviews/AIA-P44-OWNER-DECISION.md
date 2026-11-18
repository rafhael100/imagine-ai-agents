# AIA-P44 — Decisão do produto proprietário separada da autenticação mTLS

16/11/2026. Incremento empilhado sobre P43 (#416).

**Falha conceitual importante:** o endpoint original do CENTRAL-AUTO-P7 é um contrato privado **de leitura administrativa** CRM/Portal. Ser admitido por HTTPS/mTLS e aparecer na ledger de nonces não comprova que o CRM autorizou uma operação de automação, nem representa consentimento, permissão de escrita ou decisão do produto proprietário.

**Novo fluxo opt-in de laboratório:** antes da P42 consumir o nonce mTLS e gravar as linhas P16 e P6, a P44 exige uma segunda prova independente: **decisão explícita assinada Ed25519 de um owner de políticas CRM fictício**, com operação fechada `run.status.project`, audiência AI Agents, company UUID, tenant, event, payload SHA-256, referência de decisão, versão da chave do proprietário, janela de validade e `ALLOW_NO_AI_LAB`. A chave pública é cadastrada por empresa em PostgreSQL16 de CI, sem armazenar chave privada.

O método `PgOwnerDecisionLab::verifyAndConsumeInsideTransaction` confere a assinatura e bloqueia a linha da chave `FOR SHARE` até a transação **única** do stage P42 terminar. O grant é consumido com unicidade, e só junto com a prova P7, o ledger antirrepetição P16 e a fila P6. Uma falha de operação, assinatura, identidade, hash, expiração ou revogação reverte tudo. Uma decisão não pode alimentar dois eventos. `PgOwnerDecisionBoundStageLab` não aceita omissão do grant.

**Teste:** mTLS válido isolado não autoriza stage; operação diferente é negada mesmo com assinatura válida; hash incorreto, falta de assinatura, replay de grant e chave revogada geram DENY sem efeitos; uma nova chave explicitamente registrada em laboratório pode gerar outra decisão para evento distinto.

**Limite:** os recursos do CRM real **não são chamados** para a decisão; ambos os pares de chave são sintéticos e gerados na CI. As APIs históricas P42/P17 ainda existem para regressões e não são bloqueios de produção. Para uma implantação são necessários autenticação do serviço proprietário, emissão real de autorização, privilégios de operação, consentimento LGPD, rotação/custódia segura de chaves, controle de despesas, revisão independente, n8n real e integração autorizada. Ninguém autorizou deploy, merge ou cobrança nesta PR.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
