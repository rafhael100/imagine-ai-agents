# Licença n8n — análise de arquitetura P20

Consulta às fontes oficiais em 16/11/2026. Análise técnica do implementador, não parecer jurídico independente ou liberação comercial. Não houve contato externo, contratação ou despesa.

A [orientação oficial de licenciamento](https://support.n8n.io/article/can-i-use-your-license-for-my-use-case) distingue uso pela própria equipe, inclusive para entregar resultados a clientes, de acesso de terceiros à construção/configuração de workflows. O segundo caso pode exigir licença comercial, mesmo com interface intermediária.

A [FAQ oficial](https://docs.n8n.io/n8n-community-license/community-license/license-faq.md) admite n8n como backend técnico de produto para disparar fluxos predefinidos e conectar contas. Não basta esconder o editor: permitir ao usuário determinar lógica de workflows por outra interface também entra na análise de licenciamento. A [Sustainable Use License](https://docs.n8n.io/n8n-community-license/community-license.md) limita o uso concedido e não libera funcionalidades de outra edição.

## Recorte candidato Imagine e conclusão condicional

Inferência arquitetural: manter regras comerciais executadas nativamente no CRM, políticas globais no Portal e regras de produto no respectivo produto. n8n será operado apenas por equipe Imagine e receberá operações técnicas fixas, predefinidas e versionadas. A regra CRM não deve gerar, modificar ou configurar grafo/workflow n8n. Cliente não terá credenciais/editor/API n8n; não aceitar workflow JSON, código, nós arbitrários, URLs livres ou templates de lógica definidos pelo cliente.

Esse recorte permite preparar uma homologação de uso técnico interno compatível em princípio com as orientações consultadas. **Não é aprovação irrestrita do SaaS Imagine.** A revisão da integração final precisa demonstrar que configuração permitida no CRM continua sendo regra nativa, sem converter-se indiretamente em construtor de workflows n8n. Mudança desse limite reabre o gate e exige análise específica; não comprar licença automaticamente.

Administração nominal/MFA deve ser comprovada na imagem/edição pinada. MFA do Portal e mTLS do ingresso não comprovam MFA do editor n8n. As [instruções oficiais de 2FA](https://github.com/n8n-io/n8n-docs/blob/main/docs/administer/manage-users-and-access/verify-user-identity/require-two-factor-auth.md) descrevem inscrição pessoal e recuperação. Disponibilidade de MFA não significa sua obrigatoriedade. Não introduzimos flags não verificadas nem declaramos recursos comerciais liberados. Aprovação exige demonstração com operador fictício nominal na imagem exata, política de sessão/revogação e evidência sanitizada de MFA obrigatório para todos os operadores.

### Evidência técnica P30 — 17/11/2026

O [ensaio isolado 44197654228](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44197654228)
verificou as rotas nativas na imagem fixada, versão `2.47.7`: conta nominal
sintética, TOTP, recovery code de uso único, logout, troca de senha e
invalidação de sessões anteriores. O procedimento completo está em
[MFA-P30](MFA-P30.md), incluindo o compromisso de executar também a matriz
consolidada da candidata.

A tentativa de enforcement retornou HTTP 200, mas a configuração efetiva e
persistida continuou `false`, com entitlement ausente. Portanto o aceite
de obrigatoriedade para todos os operadores permanece aberto. A fonte da
versão fixada e a [documentação de políticas](https://docs.n8n.io/deploy/host-n8n/configure-n8n/security/manage-security-policies)
distinguem esse recurso do cadastro individual. Esta evidência não ativa
licença ou altera a conclusão comercial condicional acima.

Nenhuma IA paga é necessária para gatilhos, follow-up, notificações, agendas e validação determinística. Manter dispatch e APIs pagas desligados; solicitação excepcional passa pelo AI Gateway, nunca diretamente do n8n ao fornecedor.
