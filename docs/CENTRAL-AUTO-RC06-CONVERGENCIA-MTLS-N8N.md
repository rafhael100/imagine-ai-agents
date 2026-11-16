# CENTRAL-AUTO-RC06 — Candidata consolidada: comunicação mTLS, ledger e n8n privado

**Diretriz aprovada:** [issue #339](https://github.com/rafhael100/imagine-ai-agents/issues/339). **Estado de produção:** DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-CHARGE.

## Bases e evidências de laboratórios já comprovadas
- [CENTRAL-AUTO-RC05 #392](https://github.com/rafhael100/imagine-ai-agents/pull/392): classes CRM #634 e Portal #904 pinadas e revogação concorrente de chave HMAC da Agents #384 em PostgreSQL; [CI #43877133935](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43877133935) aprovado.
- [CENTRAL-AUTO-P5 #397](https://github.com/rafhael100/imagine-ai-agents/pull/397): real HTTP/TLS mutuamente autenticado entre dois processos no loopback CI, com classe Portal original e snapshot produzido pelas classes originais CRM; [CI #43879104035](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43879104035).
- [CENTRAL-AUTO-P6 #399](https://github.com/rafhael100/imagine-ai-agents/pull/399): PostgreSQL16 com role restrita, antirreplay atômico e sobrevivência a restart do emissor/receiver simulado; [CI #43880367509](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43880367509).
- [CENTRAL-AUTO-P7 #401](https://github.com/rafhael100/imagine-ai-agents/pull/401): backup/restore em base descartável independente preservando replay, RLS e registros; [CI #43880890506](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43880890506).
- [CENTRAL-AUTO-P8 #403](https://github.com/rafhael100/imagine-ai-agents/pull/403): falha por perda de EXECUTE na função SQL é bloqueada com HTTP 503 sem dado; após recuperar grant um único evento é aceito (200) e repetido é negado (409); [CI #43882289427](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43882289427).
- [CENTRAL-AUTO-P4 #391](https://github.com/rafhael100/imagine-ai-agents/pull/391): Compose mais seguro, runtime PostgreSQL sem superusuário, segredos montados por arquivos e redes internas sem portas publicadas; erro inicial de acesso host foi corrigido e [CI de containers oficiais #43881792134](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43881792134) aprovada. O SHA exato dos três arquivos críticos da P4 é checado novamente nesta revisão.

## Nesta RC06
Os oito arquivos da configuração P4 foram importados byte a byte da HEAD `efc6786209742d3a1f6300523fdadd2f85a9da16`, sem reimplementar os controles, para a linha P8 que já inclui os contratos cruzados. O verificador legado `check_rc02_release.py` foi atualizado para rejeitar host ports e admitir somente a exposição interna :5678, sem afrouxar a exigência de redes Docker internal=true.

O workflow `central-auto-rc06-convergence.yml` avalia na mesma HEAD: (a) CRM/Portal mTLS por TLS real e PostgreSQL, falha/revitalização de armazenamento, nonce persistente, concorrência e restauração; (b) configurações P4 imutáveis, secret files e rede privada por análise estática. O workflow da P4 também poderá executar contêineres oficiais efêmeros no PR, em outro runner, sem instalar na VPS.

## O que não foi entregue, intencionalmente
- **Ainda não existe conexão real CRM→Portal→Agents entre apps implantados.** A fonte CRM é fixture Python de dados sintéticos gerada por classes reais, não endpoint Laravel operando no servidor.
- **Não há IAM S2S operacional**, secrets reais, certificado real, cadastro de empresa/serviço confiável, grants atuais da aplicação nem autorização comercial para ações.
- **n8n não está instalado na Leaseweb.** Com redes 100% internas não existe interface no host 17990; acesso administrativo deve ser concebido separadamente com autenticação nominal/TLS/MFA, regras de egress e revisão. Nenhuma porta foi aberta para satisfazer um teste.
- Faltam backup/restore de host real, rollback, monitoramento, licença e revisão independente AppSec/DBA/LGPD. Os testes de CI são indícios técnicos, não autorização de deploy.
- Zero chamada paga de IA, zero cobrança, nenhum merge, DNS, firewall, credencial de produção, dados pessoais ou VPS modificada.

## Bloqueios de próxima fase
1. Revisão independente dos contratos CRM #634, Portal #904, Agents #384/#387, desta RC06 e conflitos de versões/branches.
2. Plano de identidade real empresa+serviço+permissão com revogação confiável e credencial de origem rotacionável; sem usar o nome do certificado fictício como grant.
3. Segurança e inventário da VPS Leaseweb (somente leitura) para capacidade, redes/portas, backup externo, runbooks, licença, TLS e acesso administrativo privado.
4. Somente depois, homologação limitada com Apps efetivamente executando (sem dados reais e sem IA paga) e aprovação do operador antes de instalar ou publicar.

**DECISÃO: NO-GO PARA MERGE/PRODUÇÃO, N8N REAL, PAGAMENTOS, API PAGA E DADOS DE CLIENTES.**
