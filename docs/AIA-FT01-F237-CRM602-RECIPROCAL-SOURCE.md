# F237 — vínculo de fontes verificadas com o CRM F247/PR #602

**15/11/2026 | NO-GO | NO-MERGE | NO-DEPLOY | NO-COST**

O Agents usa a PR CRM #602 na revisão imutável 6e4e99879e456aea19124065b105cd989bbf1274. O workflow F237 recupera exclusivamente essa revisão pública, confirma o SHA, compila sintaticamente as classes originais e exige presença de mecanismos de evidência: Ed25519 da fonte original Agents, nonce PostgreSQL com unicidade issuer/audience/nonce, gates de banco descartável, FORCE RLS no escopo e envelope HTTP que responde 404 de forma uniforme.

F237 é um **teste estático de conformidade de fonte**, não executa as dependências Laravel/PostgreSQL da PR CRM #602 e não afirma homologação. A suíte operacional descarregável PG16 foi executada no próprio CRM #602 com CI verde anterior; para cada atualização de pin, revisar e executar novamente a suíte de banco no repo CRM. Nenhum segredo, token, cliente ou VPS entra no teste.

## Handoff crítico

CRM #602 deriva da árvore sintética F0 sem API de negócios. CRM #614 tem aplicação Laravel privada de homologação e rotas legítimas. A barreira F245 da árvore F0 **não pode ser copiada cegamente**, pois rejeitaria rotas que #614 precisa para funcionar. Antes da primeira leitura real em nome de uma empresa: reconciliar as árvores, usar identidade de serviço com chave operacional protegida fora do Git, tenant derivado do CRM, autorização por membro/principal/recurso/campo, revogação, nonce atômico persistente, auditoria sem PII, mTLS e testes de regressão/rollback. Até essas decisões independentes, qualquer resposta pública deve permanecer DENIED_OR_NOT_FOUND.

**Gates CRM #57:** 14 gates, 7 decisões e 13 cenários reais ainda sem aceite operacional. Nenhum merge, deploy, DNS, credencial ou custo adicional.
