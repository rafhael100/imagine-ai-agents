# Providers nativo e owner com fontes CRM #664

Data: 18/11/2026. Estado: candidata para desenvolvimento e revisão,
**DRAFT / NO-MERGE / NO-DEPLOY**.

## Problema e alteração

O pacote seletivo da #482 foi validado com a fonte CRM #660 (`96db`). Desde
então, o CRM ganhou a migração incremental monitor33→owner35 (#662), a
recusa de reaplicação do ledger com RLS/policy alterados (#663) e a migração
base25→monitor33 (#664). A #482 continua uma evidência válida do seu próprio
estado, mas seu arquivo CRM não contém essas fontes novas.

Esta filha direta atualiza o checkout CRM e o pacote de revisão para a
#664. A árvore CRM completa entra no TAR, incluindo os migradores, seus
ensaios, guard, testes, SQL, workflows e contratos. O verificador exige 32
caminhos CRM, preservando os 12 anteriores e acrescentando 20 fontes
necessárias à revisão do conjunto atual. A ausência de uma fonte adicional
é recusada mesmo quando os hashes e a identidade Git do pacote são
internamente coerentes.

A mudança não acrescenta grupos de execução Agents. Os cenários nativos e
owner continuam com seus corpos, guards, isolamento e limites anteriores.
A versão nova exige a própria evidência da HEAD publicada; o PASS da #482
ou do CRM não é reutilizado como resultado da nova execução Agents.

## Identidades fixadas

| Papel | Commit e referência |
| --- | --- |
| Pai Agents | `ce004ad4a8fa5fb59ef38cdd250126d0f0e99b46`, [#482](https://github.com/rafhael100/imagine-ai-agents/pull/482) |
| Árvore do pai | `9a42f4ececea281aad0c0c5ebd690775c584828a` |
| Nova branch | `integrate/n8n-owner-components-crm-664-2026-11-18` |
| CRM atual | `7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8`, [#664](https://github.com/rafhael100/imagine-crm/pull/664) |
| Árvore CRM atual | `1ca57c0fa3197e83a36d9c6024bf13e77f10ad23`, 1.499 arquivos |
| Origem das nove dependências owner | `64f52f0dcd44a5545d1bfc6c1d685b33c158601f`, [#481](https://github.com/rafhael100/imagine-ai-agents/pull/481) |
| Adapter histórico preservado | `8b04d0e936168c77db028160b5aeb1758db497c4` |

O commit e a árvore da nova candidata são observados após a publicação e
registrados pela CI no manifesto. Não são substituídos pelos do pai. A
admissão exige #482 na ancestralidade real; o vínculo das nove dependências
continua sendo #481. Um ramo que descende de #481, mas não de #482, é
recusado antes dos imports de cenário e da criação de recursos Docker.

## Contratos preservados

- 22 grupos executados e o registro separado `source_integrity`.
- 14 componentes owner: 12 imports literais e duas extrações mínimas de API,
  com origens P67/P68 fixadas no mapa existente.
- 81 vínculos nativos históricos, três vínculos próprios do bridge e nove
  dependências owner.
- Schemas `N8N_PRIVATE_CANDIDATE_OBSERVATIONS_V2` e
  `N8N_PRIVATE_OPERATOR_PACKET_V2`, envelopes e limites existentes.
- Três arquivos de fontes (`candidate.tar.gz`, `crm.tar.gz`,
  `adapter.tar.gz`), `manifest.json` e `SHA256SUMS`.
- Builds nativo, CRM e owner separados, com IDs locais observados; bancos e
  processos próprios para cada laboratório.
- Perfil de 33 tabelas na prova de feedback nativo, usando os três módulos
  canônicos de backup do CRM. As tabelas owner do CRM não são instaladas
  por essa prova.

O documento [da composição #482](N8N-OWNER-COMPONENTS-CANDIDATE.md) permanece
como registro histórico, incluindo os 14 componentes e a comparação de P27.
Seu texto sobre a antiga candidata não deve ser interpretado como resultado
da nova HEAD. O [procedimento atual do pacote](../../infra/n8n-private/homologation/OPERATOR-PACKET.md)
explica criação, conferência e limites.

## Evidência própria do CRM, separada da CI Agents

A HEAD CRM #664 concluiu 27 workflows na tentativa 1: 25 com sucesso e os
bloqueios previstos de escopo F251/F252. Os dois gates mantiveram 170
caminhos alterados, 23 conhecidos e 147 bloqueados, com `deploy_approved=false`.
Não foram alterados para liberar esta composição.

| Prova CRM na HEAD #664 | Resultado observado | Fonte |
| --- | --- | --- |
| Base25→monitor33 | Primeira aplicação, reaplicação povoada sem alterações, três recusas e bloqueio real no `CREATE ROLE` literal com `55P03`, rollback e encerramento dos backends; 917 chamadas instrumentadas | [Run 44262660735, job 134659509281](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735/job/134659509281) |
| Monitor33→owner35 | Aplicação e reaplicação, quatro recusas e dois bloqueios reais com `55P03`; 997 chamadas instrumentadas | [Mesmo job, ensaio independente](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735/job/134659509281) |
| Ledger incremental | Três reparos aceitos pela versão histórica, três recusas pela candidata, 28 casos ACL/policy, 13 operações SQL negadas, bootstrap ausente e schema atual | [Run 44262660963](https://github.com/rafhael100/imagine-crm/actions/runs/44262660963) |
| Gates F251/F252 | `BLOCKED`, exit 1, nenhuma aprovação de deploy | [F251](https://github.com/rafhael100/imagine-crm/actions/runs/44262660972) e [F252](https://github.com/rafhael100/imagine-crm/actions/runs/44262660935) |

As contagens incluem preparação e polling; não equivalem a cenários
independentes e não são somadas aos 22 grupos Agents. O catálogo33 coincidiu
entre as duas referências independentes, com digest
`576d52ae27cde8b1525b6621afeec3b752dac6a16ea2dfbcba5b6c22c9148093`.
Isso não constitui uma execução sequencial única 25→35.

A origem base25 é o `schema.sql` de `96db` com o script de papéis e já possui
o ledger. Não se presume que a migration001 aplicada ao schema histórico
`cc173` produza essa origem exata. Os campos `head` dos dois relatórios owner
foram mascarados como `***` pelo runner e permanecem preservados assim na
evidência; a identidade exata é vinculada pelos metadados do run e pelos
objetos Git publicados.

A evidência consolidada CRM tem SHA-256
`ab8955d7ef49013c9fb601745a43fabd233b2d067635dc1a6024bab6c251270d`;
a revisão independente do job owner tem SHA-256
`1c0a7817678acff67c19a1a10beb04d5cbf283c1fc796b3d38a4aaec1c500807`.
Os links de CI acima permitem consultar os registros de origem. A candidata
não inclui esses relatórios no pacote de fontes nem os registra como novos
checks ou observações Agents.

A #663 preserva seu histórico próprio: o primeiro build do P22 falhou ao
baixar o arquivo PECL redis; a repetição do job passou sem alterar fontes.
Isso não ocorreu na #664, cujos workflows aplicáveis passaram na primeira
tentativa. O histórico não é apagado nem transferido à evidência nova.

## Validação desta atualização

Nove testes locais de admissão passaram, mantendo os oito anteriores e
acrescentando um contraexemplo com commits Git reais para a ancestralidade
#482. A ref antiga, mesmo coerente entre evento e ambiente, é recusada.

Dezessete testes locais do pacote passaram, preservando os 16 anteriores.
O novo método percorre as 20 fontes adicionais ausentes, tanto na criação
quanto na inspeção offline, com objetos Git coerentes, exige
`BUILD_SOURCE_INCOMPLETE` e confirma a ausência de saída parcial. A suite
mantém as negativas de pin antigo, linhagem e componentes adulterados.

A revisão cruzada conferiu diffs, blobs, modos, preimages e reconstrução dos
patches. O runner conserva modo `100755`. Sintaxe Bash, AST Python, os dois
heredocs Python e a equivalência YAML fora da branch e do pin CRM foram
conferidos. Esses testes locais não executam Docker, PHP, n8n ou PostgreSQL.

Após a publicação, a aceitação exige:

1. CI da HEAD exata, com 22 grupos e integridade aprovados, seus relatórios
   P67/P68 e a prova nativa separados.
2. Manifesto e artefato próprios desse run/attempt, com os três IDs de build
   observados e as identidades de fontes correspondentes.
3. Download independente e conferência offline usando verificador obtido
   da candidata publicada, SHA da HEAD confirmado externamente e digest do
   manifesto extraído da CI exata antes de confiar no download.
4. Registro dos resultados terminais e digests na PR, preservando qualquer
   falha ou repetição observada.

A conferência offline reconstrói árvores Git e valida os blobs candidatos
importados. A associação original commit→caminho→blob P67/P68 continua
verificada pela CI, pois suas árvores não são arquivos separados do pacote:
`import_origins_verified_offline=false`. Um digest externo fixa os bytes do
manifesto, mas o verificador não consulta GitHub, não reexecuta cenários e
não autentica por si só as alegações de CI ou de imagem.

## Limites de uso

O pacote é material de build e revisão dos providers selecionados. Não é
instalador, backup, Compose operacional completo nem consolidação de todos
os incrementos Agents. Os migradores CRM continuam ferramentas para o
runner sintético autorizado, com contratos próprios; não são roteiro de
aplicação no host real.

P67 cria e consulta rascunhos inertes. P68 recupera seu laboratório a partir
do snapshot e de autoridade atual da fixture ainda viva. A prova nativa
mantém seu perfil separado; P30 usa outro Stack e não demonstrou enforcement
global obrigatório de MFA. Não há prova de restauração distribuída, RPO
zero, custódia externa, sessão humana CRM ou aprovação operacional nova.

A próxima etapa operacional depende de inventário real atualizado, materiais
e autoridades legítimos, revisão nominal e plano exato. Nenhuma aprovação
é fabricada a partir dos resultados de desenvolvimento.
