# CENTRAL-AUTO-P14 — Auditoria oficial de segurança n8n na candidata sem IA

Complementa [CENTRAL-AUTO-P13 #427](https://github.com/rafhael100/imagine-ai-agents/pull/427) e a [RC08 #421](https://github.com/rafhael100/imagine-ai-agents/pull/421), reutilizando o **mesmo n8n oficial 2.47.7 e PG17 descartáveis em GitHub Actions**.

## Implementação
1. O n8n importa e executa o workflow Manual Trigger → Set → Set, sem Code, HTTP, modelo, pagamento, API pública ou webhook. O CLI roda em contêiner separado, pois o serviço de aplicação é parado dentro do ambiente descartável para não competir pela porta Task Broker 5679.
2. Antes de executar a auditoria, valida diretamente no banco `n8n`: **um workflow sintético**, **zero credenciais** e **zero workflows ativos**. Uma base inesperada é recusada.
3. Executa `n8n audit --categories=credentials,database,filesystem,nodes` usando o CLI **oficial do n8n**, conectado somente ao PG17 temporário e sem rede pública. A saída do comando não é enviada a artefatos, e a CI exige a confirmação 'No security issues found'. Riscos, erros ou saída desconhecida bloqueiam.
4. Reexecuta os testes P13 de API desabilitada, SSRF, nós HTTP/Code/etc. excluídos e falta de saída externa para endereços TEST-NET, mais recuperação PG17/volume/chave e mTLS/PG16 da RC08.

## O que não foi comprovado
- A auditoria seleciona **quatro categorias** relacionadas à automação sem IA. A categoria `instance` exige revisão independente sobre atualização de versão, governança de usuários/credenciais e operação real; **não é ignorada para produção**, apenas não é uma condição deterministicamente vazia em CI.
- A execução sem risco em uma fixture de um workflow não prova que a futura instância com apps, contas, credenciais e workflows reais terá riscos zero. Cada publicação precisará de auditoria própria e de governança de mudanças.
- O `n8n audit` mostra riscos conhecidos, mas não é um teste de invasão, escaneamento de rede, avaliação de licença, auditoria LGPD ou prova de faturamento de IA.
- Não há n8n implantado na Leaseweb, IAM real CRM/Portal/AI Agents, ingresso administrativo HTTPS/MFA ou conexão a provedor de modelos. Nenhum gasto, merge ou deploy autorizado.

Referência oficial: [n8n — Security audit](https://docs.n8n.io/hosting/securing/security-audit/).

**DRAFT / NO-GO / NO-SPEND / NO-DEPLOY.**
