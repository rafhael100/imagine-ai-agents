# AIA-P59 — Segregação entre autor e revisor de propostas

16/11/2026. Evolução sobre P58 (#442), mantendo ambiente de laboratório e registros fictícios.

## Lacuna resolvida

O registro P57/P58 já exigia dois papéis de revisão independentes, mas a proposta P51 não possuía informação de autoria. Portanto, o mesmo sujeito sintético que criou uma proposta poderia apresentar um voto PRODUCT e alegar independência.

A P59 acrescenta um **vínculo sintético de autoria** por tenant/proposal com o fingerprint P52, por meio do \`PgDraftAuthorProvenanceLab\`. A autoria é selada uma única vez numa transação PostgreSQL; replay idêntico é idempotente, enquanto tentativa de substituir o autor ou o hash é negada. A tabela usa FK para a proposta P51.

A P57 passa a oferecer parâmetros opt-in \`enforceAuthor=true\` na submissão e leitura de pareceres. A verificação do vínculo e da desigualdade autor/revisor ocorre **na própria transação**, usando locks compatíveis com P58: bloqueio da proposta para serializar pareceres, consulta do autor e validação de chave do revisor. Em consultas de status, o vínculo também é checado no mesmo snapshot \`REPEATABLE READ, READ ONLY\`, sem bloquear para escrita.

A facade \`PgAuthorBoundSignedReviewLab\` sempre ativa essas verificações. As interfaces antigas da P57 continuam em laboratório para compatibilidade com seus testes; não há API pública nem autorização operacional.

## Testes

A CI verifica: autoria selada e replay exato; tentativa de mudar autor ou tenant; mesmo autor tentando votar como PRODUCT (DENY); votos Ed25519 de dois revisores diferentes do autor, ainda SEM autorização de execução; proposta sem origem selada rejeitada; inclusão de origem que habilita apenas os pareceres da fixture; alteração do autor para um dos revisores bloqueando consulta; adulteração do fingerprint da autoria detectada.

## Limites inegociáveis

O autor foi definido por uma fixture de CI, não por autenticação real do CRM. A P59 não resolve de forma operacional identidade, poder de representação, times de revisão, MFA, consentimento, nem aprovação de mudanças de negócio. O hash de autoria não é assinatura de um usuário real. Mesmo com origem verificada no laboratório e dois votos, \`can_approve=false\`, \`can_execute=false\` e \`NO-GO\` continuam prevalecendo. Não houve merge, deploy, dados pessoais ou despesas.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
