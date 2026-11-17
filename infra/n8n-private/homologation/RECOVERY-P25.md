# CENTRAL-AUTO-P25 — recuperação do estado efetivo da candidata Agents

## Avanço sobre P24

P24 recupera o n8n completo, mas os componentes CRM e Agents do pacote são
bancos com uma tabela marcadora. P25 substitui **somente o componente Agents**
pelos schemas de ingresso, autorização e fila utilizados pelas candidatas
existentes. O componente CRM continua explicitamente sendo um marcador.

A entrada `check_p25_agents_state_restore.py` reutiliza o ensaio P24. O dump
Agents passa pelo mesmo manifesto, pacote CMS AES-256-GCM, recibo Ed25519,
verificação de procedência e decriptação autenticada que o banco, volume e
chave do n8n. Não existe um caminho alternativo de restauração sem assinatura.

**Candidata de CI sintética, não migration ou configuração de produção.**
Um resultado válido exige sucesso do workflow da HEAD concreta da PR.

## Banco e permissões recuperados

Origem e destino Agents são dois servidores PostgreSQL 16 descartáveis,
com volumes distintos e o mesmo nome de banco exigido pelas classes existentes:
`imagine_agents_ci`. O destino não herda as roles globais da origem.

As roles de laboratório são reconstruídas por uma lista fechada, sem login,
sem `SUPERUSER`, sem `BYPASSRLS` e sem permissão de criar bancos ou roles.
O `pg_restore` preserva ownership e ACL. Isso é diferente do procedimento
n8n, que explicitamente utiliza `--no-owner --no-acl --role=n8n_runtime`.
O procedimento n8n permanece adequado ao seu próprio schema e não é copiado
para a autorização do Agents.

Essa separação segue a documentação do PostgreSQL:
[`pg_dump`](https://www.postgresql.org/docs/16/app-pgdump.html) não inclui
objetos globais como roles, e as opções de
[`pg_restore`](https://www.postgresql.org/docs/16/app-pgrestore.html) controlam
a aplicação de owners e permissões no destino.

Fontes reutilizadas, sem uma segunda implementação do domínio:

- `tools/auto_ia/pg/001_auto_ia_pg_lab.sql`, `002_no_ai_inbox.sql` e
  `003_signed_ingress_lab.sql`;
- `runtime/central-auto/receiver-grants.sql`;
- outbox, replay, lease, ACK e autoridade da Agents RC65
  `3fb86c5fb011bc9df1f12a4a30a7c094370b6872`;
- adaptador histórico pinado em `8b04d0e936168c77db028160b5aeb1758db497c4`,
  requerido pelo laboratório de replay.

O wrapper RC67 combina as fontes em árvore Git temporária, verifica os blobs
das origens e exporta o adaptador separado. Não cria merge commit. As fontes
públicas exportadas ficam legíveis pelo contêiner PHP e montadas somente para
leitura; os arquivos de segredo e os dumps ficam fora dessa árvore.

## Revogação depois do snapshot

Um backup íntegro pode conter uma concessão que já foi revogada após a sua
criação. A assinatura do backup não comprova que aquela concessão continua
válida. Por isso, o ensaio separa três fatos:

1. Revogações que já existiam no snapshot devem permanecer negadas.
2. Uma revisão atual fornecida fora do dump deve negar o estado antigo
   restaurado. A revisão confiável não pode ser lida do próprio snapshot.
3. O ingresso do destino fica em quarentena, com roles `NOLOGIN`, até a
   aplicação explícita das revogações da autoridade atual sintética. A fixture
   não emite novas permissões e não representa IAM operacional.

O teste demonstra o risco de snapshot antigo e o comportamento de bloqueio
esperado. Não demonstra integração com um serviço real de identidade,
custódia de certificados ou disponibilidade dessa autoridade numa recuperação.

## Critérios de aprovação

O probe exercita as funções e classes existentes depois de restauradas, em
vez de conferir apenas contagens de tabelas. O workflow deve exigir:

- ownership das funções `SECURITY DEFINER`, ACLs e `FORCE RLS` preservados;
- roles limitadas sem acesso direto a chaves, grants ou escrita na inbox;
- isolamento das linhas de cada tenant e ingresso assinado permitido apenas
  no vínculo de empresa e certificado autorizado;
- evento duplicado reconhecido sem nova linha, e conflito de conteúdo negado;
- estados terminais `ACKED` e `DEAD` preservados, sem retomada automática;
- ledger de replay persistido e tentativa/worker antigos sem poder concluir
  outra reserva;
- revogação anterior ao dump e revisão atual posterior ao dump bloqueando
  retomada indevida;
- o ensaio completo n8n P24, inclusive workflow nativo e credencial fictícia,
  continuando aprovado no mesmo pacote.

A outbox P6 é uma tabela de laboratório operada pelas classes sob `postgres`.
O ensaio não a apresenta como outbox de produção com isolamento RLS. RLS e
role restrita são verificados onde já existem, especialmente na inbox e nos
grants do receiver.

## Isolamento e limites

Os servidores Agents usam rede Docker `none`. O PHP conecta ao loopback no
namespace do respectivo PostgreSQL, sem publicar portas no host. O n8n usa
as redes internas já verificadas pelo P24. O driver recusa recursos
preexistentes e limpa somente recursos criados pelo próprio job.

O relatório conserva `go_for_installation=false`, dispatch operacional
desligado, ausência de dados de clientes e zero chamadas pagas. A restauração
é feita no mesmo runner: backup externo, outro host, R2, identidade real,
retenção, RPO/RTO e implantação continuam exigindo evidências próprias.
O arquivo descreve o ensaio; o check da HEAD e seu log são a evidência de
execução, nunca uma CI antiga da candidata base.
