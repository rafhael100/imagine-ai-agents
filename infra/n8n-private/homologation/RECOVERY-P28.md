# CENTRAL-AUTO-P28 — recuperação do resultado, confirmação e recibos

## Lacuna encerrada pelo ensaio

P25 recupera a estrutura de ingresso e fila que sua fixture cria. P26 acrescenta
resultado nativo, recibo e confirmação duráveis em outro cluster, e P27
introduz grants próprios de leitura. Esses estados novos também precisam
atravessar o backup e continuar seguros após a restauração.

P28 captura o **mesmo banco que acabou de executar os cenários P26**, instala
nele os schemas originais P19/P27 e admite dois eventos sintéticos pelo SQL
existente. Não substitui o estado produzido por fixtures de ACK. O hook é
opcional e ocorre depois dos asserts de P26, dentro do trecho que garante a
limpeza. O entrypoint P28 executa P26 uma única vez.

O wrapper RC70 também executa P25 e o transporte HTTPS/mTLS P27, na mesma
árvore temporária com a RC65 fixada. Não cria merge commit nem move outras
branches.

## Conteúdo recuperado

O snapshot compara contagens e hashes de 17 tabelas, além de owners/RLS,
definições de funções e constraints. Os grupos incluem:

| Estado | O que deve sobreviver |
| --- | --- |
| Outbox e replay | Evento canônico, hash, estado, worker, tentativa e ledger de replay |
| Ledger nativo P26 | Contexto, hashes do grafo e conteúdo, resultado, recibo, assinatura e estado |
| Signers e ACK | Chaves públicas registradas e evidência atômica da confirmação |
| Empresa e proprietário | Vínculos, admissão consumida, chave do dono e decisão assinada consumida |
| Ingresso e leitura | Inbox, audit P19, chaves sintéticas, grants de ingresso e consulta P27 |

O pacote permanece no formato de cinco componentes P20: dump Agents,
dump n8n, volume, chave e marcador CRM. O dump Agents preserva OWNER/ACL;
o n8n utiliza a restauração própria sob `n8n_runtime`. CRM continua
explicitamente um banco marcador, sem alegação de schema real do produto.

A cópia é cifrada e assinada. O consumidor verifica Ed25519 antes da
descriptografia autenticada, valida o manifesto e materializa os componentes
pelos helpers existentes. Não há um segundo protocolo de backup ou um caminho
de recuperação sem assinatura.

## Quarentena e referência atual

Depois do dump, a origem revoga o grant de leitura A e a chave de proprietário
do caso MAIN. A referência dessas revogações fica fora do backup e vinculada
ao hash do dump. Ela é uma fixture atual sintética, não uma autoridade IAM.

O destino PG16 é um cluster novo. Recebe somente sete roles conhecidas,
todas NOLOGIN e sem memberships; logins e senhas de aplicação não são
restaurados. Após comparar o snapshot, o ensaio suspende o EXECUTE da consulta
de recibo e exige que uma tentativa ainda seja recusada.

A reconciliação aceita somente as revogações previstas, revisão 1 para 2,
preservando escopo e vencimentos. O controle B é conferido contra a mesma
referência atual. Só depois é restabelecida a ACL técnica de consulta, com as
roles ainda sem login. Nenhuma concessão de negócio é reativada, nenhuma
validade é prolongada e nenhum listener de aplicação é iniciado para Agents.

## Resultado durável sem repetição

No banco restaurado:

- MAIN confirma o ACK histórico e a evidência única, mesmo depois da revogação
  posterior do proprietário; isso confirma um fato histórico, sem novo efeito.
- O RESULT sem confirmação continua preservado e recebe negativa de ACK.
- Os dois UNKNOWN continuam exigindo conciliação, sem retry automático.
- Consultas autorizadas de recibo retornam RECEIVED; o grant A revogado não
  volta a permitir leitura por estar ativo no snapshot antigo.

O probe compara integralmente o ledger antes/depois da conciliação, excluindo
apenas `updated_at`. Também compara outbox, replay, decisões consumidas e
evidência. Resultado, assinatura, tentativa e autoridade não podem mudar para
fazer a recuperação aparentar sucesso.

## n8n restaurado sem executar novamente

O destino n8n recebe PG17, volume e os mesmos bytes da chave. O volume é
normalizado pelos helpers P24 e comparado membro a membro, incluindo conteúdo,
uid/gid e permissões. O destino contém os seis workflows inativos de P26.

Antes de inicializar a aplicação restaurada, o driver recusa execuções
pendentes que poderiam ser retomadas. Durante o hook, o helper de execução
CLI é bloqueado. Depois do boot, usa somente exportação para conferir os seis
grafos e seus vínculos originais, sem chamar `execute`.

O relatório conserva duas execuções nativas observadas na fase P26 e **zero
chamadas de execução durante a recuperação**. Como o Compose não retém dados
de execuções bem-sucedidas, não se apresenta um contador durável do banco n8n
como evidência independente. O perfil P28 não contém credenciais n8n; a prova
de decriptação da credencial fictícia continua pertencendo ao P25, executado
no mesmo workflow.

## Evidência e limites

O check da HEAD concreta precisa comprovar o conjunto: P25, P26 com hook P28
e o transporte P27. Revisão estática ou uma CI da base não substitui essa
execução. O cleanup remove somente os recursos descartáveis do próprio job.

A captura e a restauração acontecem no mesmo runner. Não comprovam outro
host, armazenamento externo, R2, custódia real, autoridade atual de produção,
schema CRM real ou autorização para implantar. Os fingerprints da fixture de
recuperação são sintéticos; o mTLS real com material sintético é exercitado
separadamente por P27.

**DRAFT / NO-GO operacional.** Recuperar um dado histórico não recria a
permissão de usá-lo nem autoriza repetir um trabalho.
