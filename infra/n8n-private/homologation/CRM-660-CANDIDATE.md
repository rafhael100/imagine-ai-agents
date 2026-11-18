# Candidata n8n com o CRM #660 — 18/11/2026

## Problema e recorte

A [#479](https://github.com/rafhael100/imagine-ai-agents/pull/479) validou o
fluxo nativo completo com o CRM #654. A [#660](https://github.com/rafhael100/imagine-crm/pull/660)
integrou a jornada privada, os rascunhos owner, o monitor e a fidelidade do
catálogo PostgreSQL. Sua API de restauração exige `source_catalog`; importá-la
isoladamente também exige carregar o módulo irmão do catálogo. A chamada
histórica #479 não deve ser usada como prova de compatibilidade com essa API.

Esta candidata fecha esse recorte técnico em CI sintética. Não instala as
tabelas owner no laboratório nativo: captura, execução de lead, retorno
assinado, monitor SQL/PHP e recuperação usam o perfil fechado de **33 tabelas**.
A jornada humana e o perfil owner+monitor de 35 tabelas são provas separadas
da #660. Os arquivos estão no pacote completo de fontes do CRM, sem atribuir
sua execução a este ensaio.

## Identidades e admissão

| Item | Identidade |
| --- | --- |
| Fonte Agents #479 | `3d30b2f7977b37d3bb86d6ff11bc63728145f5fd` |
| Árvore Agents de origem | `774964d9a8b9d94f62be5bbfb38673be0e1592b5` |
| CRM #660 fixo | `96dbf1aed2d1cf3237bc3631c4594540a0168d2a` |
| Árvore CRM | `d76f2cc02ce483242b3cd5ccf4b043b0f25aa8e6` |
| Branch nova | `integrate/n8n-candidate-crm-660-2026-11-18` |
| Repositório de CI real | `rafhael100/imagine-ai-agents` |

O workflow e o runner admitem somente `push` dessa branch, repositório público,
runner GitHub hospedado e Docker local. O checkout deve ser limpo, completo e
igual à SHA do evento. #479 e as fontes anteriores devem ser ancestrais; o CRM
é checado no commit fixo com ancestralidade #654. Nenhuma identidade de CI é
substituída para executar o harness em outro repositório.

Dos 82 vínculos de blobs históricos, 81 continuam iguais. A exceção explícita
é `check_crm_execution_feedback.py`, cuja preimagem é
`98292744b96437decf2e307b7284a2063e7550fd`. O runner exige o novo blob do checker
e os blobs próprios de `crm_feedback_backup.py` e `test_crm_feedback_backup.py`.
Não há curinga ou remoção geral da conferência de fontes. O runtime PHP,
domínio, ingress, driver P30 e demais contratos originais permanecem iguais.

## Captura e restauração

O carregador resolve os três módulos canônicos `private_crm_backup_catalog`,
`private_crm_backup_contract` e `private_crm_backup_snapshot` dentro do mesmo
arquivo CRM. Recusa links simbólicos e módulos homônimos já carregados de
outra origem. Se o carregamento falhar, remove somente os módulos introduzidos
por essa tentativa; o módulo snapshot usa o mesmo contrato já carregado.

O helper CRM exporta um snapshot READ ONLY/REPEATABLE READ do PG16 da fixture.
Perfil, manifesto de segurança, catálogo e hashes das 33 tabelas são lidos por
`snapshot.query`; `snapshot.dump` importa esse mesmo ID. Todas as conexões
herdam `PGPORT=5433`, a porta interna existente. O ID Docker é aquele que a
fixture criou, inspecionou e registrou como recurso próprio. O exporter é
fechado antes da revogação e da restauração, inclusive em falhas.

A leitura do monitor antecede a captura; o ensaio exige que os hashes antes,
dentro e depois do snapshot sejam idênticos. Isso vincula seus modelos ao
mesmo estado persistido sem dizer que a sessão SQL do monitor importou o
snapshot. O dump fica em memória, fora do pacote público.

A API atual recebe obrigatoriamente o catálogo da origem. São mantidos os
vínculos catálogo/segurança, todas as definições CHECK/FK/unique, colunas,
collation, owners, ACLs, RLS e funções. A única equivalência é a lista fechada
PG16 já implementada no CRM. O relatório guarda hashes dos catálogos, hashes
de segurança restaurada, igualdades brutas e a regra/quantidade de equivalências
observadas. Roles ficam NOLOGIN e sem memberships; revogações posteriores ao
snapshot continuam aplicadas antes dos probes. O restore não executa n8n.

## Evidência e limites

As sete regressões novas executam imports dos módulos CRM reais, verificam a
API obrigatória e comprovam que `CHECK (true)` é rejeitado embora a projeção
antiga sem definição aceite a mesma alteração. Também cobrem origem dos
módulos, erros parciais, identidade do contêiner e ciclo do snapshot. O caso de
captura usa um dublê local apenas para testar o encaminhamento e cleanup;
isso não é prova PostgreSQL. A recuperação real é exigida pelo workflow.

As 11 regressões do pacote incluem negação do pin CRM antigo, linhagem #479
omitida, regressão bridge sem registro e alegação indevida de 35 tabelas.
**Local: 18 testes aprovados. CI da nova HEAD: pendente.** PHP, PostgreSQL,
Docker, n8n e os 19 grupos completos ainda precisam do run terminal desta
candidata. Há ainda um registro inicial de integridade; ele não é um vigésimo
grupo executado. Os resultados #479/#654 históricos não substituem o novo run.

O novo artefato `n8n-crm660-candidate-SHA-ATTEMPT` contém fontes completas e
manifesto próprio. Exige o CRM atual, ancestralidade #479 e o novo grupo de
regressão, mantendo os limites de [OPERATOR-PACKET](OPERATOR-PACKET.md).
Não inclui dumps, chaves, sessões, logs brutos, imagens ou estado dos bancos.
IDs locais de builds PHP não são imagens distribuídas nem prova de builds
reproduzíveis bit a bit. A verificação offline autentica hashes de fontes
contra o pin fornecido; não autentica por si só observações da CI.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-GO operacional.** A referência original
#479, main, DEV, VPS, política MFA, licença, custódia, infraestrutura e gates
de instalação não são alterados por esta candidata.
