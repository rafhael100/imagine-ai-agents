# CENTRAL-AUTO-P8 — Falha fechada durante perda de permissão SQL, com recuperação

Continua [P5 #397](https://github.com/rafhael100/imagine-ai-agents/pull/397), [P6 #399](https://github.com/rafhael100/imagine-ai-agents/pull/399) e [P7 #401](https://github.com/rafhael100/imagine-ai-agents/pull/401). Corrige a última condição não exercitada no contrato de observação CRM/Portal em HTTPS/mTLS: a possibilidade de indisponibilidade da ledger durável depois que o certificado do cliente já foi autenticado.

## Ensaio de falha e recuperação
- Executa servidor **sintético** de estado CRM e classe original Portal em processos separados, via mTLS em loopback do runner GitHub Actions, com certificados descartáveis e banco PostgreSQL16 independente.
- Após testar estado e replay persistente, revoga **somente no banco descartável** o direito `EXECUTE` da conta restrita `ca_mtls_lab_service` sobre a função `central_auto_lab.admit_nonce`.
- Repete uma requisição válida e assinada. A função SQL não pode gravar o nonce; o servidor retorna **503 sem estado do cliente**. Nenhum efeito é considerado concluído.
- Restaura apenas o grant de laboratório. A **mesma referência de evento e nonce** passa uma vez (HTTP 200) e é negada no reenvio seguinte (HTTP 409). Persistem seis referências fictícias no banco, conferidas em dump/restauração para segundo banco descartável.
- Repete as verificações de concorrência, RLS forçada, permissões e hash de origem/restauração. A implementação real não acessa os bancos do CRM nem do Portal.

## Segurança e limites
- Simular revogação de grant não prova recuperação de perda de rede, HA, quorum ou backup operacional. Para isso será necessário cenário de staging privado com interrupções reais e revisão DBA/SRE.
- A conta e o segredo do laboratório são sintéticos; não constituem IAM operacional por serviço/empresa. O servidor de teste não é endpoint CRM Laravel publicado.
- O teste é apenas CI; **sem VPS Leaseweb, n8n operacional, APIs pagas de IA, dados reais, merge ou deploy**.
- A publicação continua bloqueada até inventário e autenticação operacional, custódia de chaves, mTLS de produção, revisão AppSec/DBA/LGPD e plano de reversão.

**DRAFT / NO-GO / SEM DESPESAS.**
