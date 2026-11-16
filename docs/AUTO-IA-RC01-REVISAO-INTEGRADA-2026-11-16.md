# AUTO-IA RC01 — Consolidação das automações (diretriz aprovada, produção NO-GO)

**Decisão da direção:** [#339](https://github.com/rafhael100/imagine-ai-agents/issues/339), “automação primeiro, IA por exceção”.

**Este pacote é revisão técnica, não aprovação operacional.** Encadeia alterações somente em novas pastas \`tools/auto_ia\`, documentação e workflows GitHub Actions. Não toca contratos e políticas existentes do CRM, Portal, Sites, Performance, Universidade, SEO ou agente de produção.

## Cadeia auditável

| Entrega | PR histórica | Código/critério |
|---|---|---|
| AUTO-IA-01 | [#340](https://github.com/rafhael100/imagine-ai-agents/pull/340) | 8 produtos, 16 tarefas sem IA, 8 candidatas a avaliação, DENY/NO_AI |
| AUTO-IA-02 | [#341](https://github.com/rafhael100/imagine-ai-agents/pull/341) | pré-validação de cotas e custo **sintéticos**, sem autorização de gasto |
| AUTO-IA-03 | [#342](https://github.com/rafhael100/imagine-ai-agents/pull/342) | ledger SQLite local persistente, idempotência, concorrência e falha segura |
| AUTO-IA-04 | [#343](https://github.com/rafhael100/imagine-ai-agents/pull/343) | custo, tokens e proposta de créditos em trilhas diferentes; zero débito |
| AUTO-IA-05 | [#344](https://github.com/rafhael100/imagine-ai-agents/pull/344) | contrato fictício de evento HMAC interno sem transporte n8n |
| AUTO-IA-06 | [#345](https://github.com/rafhael100/imagine-ai-agents/pull/345) | relatório estritamente NO-GO e mutações negativas contra bypass |

## O que está demonstrado e o que NÃO está
- Os testes offline fazem verificações estáticas e funcionais de laboratório; a prova completa fica nos checks da HEAD exata desta revisão.
- **Não** há chamada de API de IA, chave de fornecedor, débito de créditos, orçamento comercial verdadeiro ou integração n8n.
- O SQLite é laboratório de concorrência, não PostgreSQL com RLS para operação; o uso desconhecido não pode ser considerado igual a zero.
- Assinatura HMAC e SHA de fixture com chave fictícia **não são identidade real ou comprovante oficial de custo**.
- Uma solicitação “AI_REVIEW_REQUIRED” ou “LAB_RESERVED_NO_AUTHORIZATION” nunca significa autorização para cobrar ou fazer chamada externa.
- Os oito sistemas devem continuar próprios dos seus dados, regras, permissões, aprovação humana e pagamentos.
- Não há alteração em \`main\`, VPS Leaseweb, DNS, contêineres, bancos e clientes.

## Gates operacionais ainda fechados
Identidade IAM real por empresa e serviço; ledger transacional PostgreSQL e RLS; anti-replay distribuído; assinatura verificável de orçamento/recibo; conciliação monetária e regra comercial de créditos; revisão AppSec/DBA/LGPD; licenciamento n8n, rede privada e controle administrativo; backups, teste de restauração, rollback e GO expresso.

## Integração e homologação futuras (separadas, nunca automáticas)
1. Revisão independente do diff completo da RC01 e testes da HEAD exata.
2. Resolver as dependências já existentes de CRM/Portal para autorização, RLS e isolamento de tenants, sem permissões implícitas.
3. Projetar o gateway operacional como serviço backend com políticas assinadas, cotas atômicas e segregação de perfis.
4. Inventariar recursos Leaseweb e preparar **plano** de n8n interno em Compose isolado, sem deploy até a homologação.
5. Testar fluxos CRM → evento privado → n8n → callback autorizado em sandbox, sem IA; somente depois avaliar integrações pagas sob nova autorização.

**Regra de release:** NO-GO obrigatório. Nenhum merge, deploy ou ligação de dados reais é consequência desta RC.
