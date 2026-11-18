# AIA-P64 — prévia, autoria e rascunhos na mesma gravação

18/11/2026. Continuação sintética da fila P62 e do detalhe verificável P63.

## Entrega

A página local `examples/p64-create-drafts-local.php` recebe visitas e duas
taxas percentuais, apresenta a prévia determinística e permite guardar o
lote inteiro para consulta na fila existente. As taxas aceitam vírgula ou
ponto decimal, com até duas casas; a conversão usa dígitos e inteiros, sem
ponto flutuante ou interpretação de separadores de milhar.

As duas regras são a fixture já existente do P50: pelo menos 12 leads
projetados gera uma proposta para revisão humana; pelo menos dois clientes
projetados gera um rascunho de tarefa CRM. O cálculo e a avaliação continuam
nos serviços P48/P49. Não há editor de código, regras arbitrárias, provedor
de IA, chamada n8n ou criação de tarefa real.

A prévia não grava PostgreSQL. A confirmação guarda lote, rascunhos,
entradas originais P63 e autoria sintética P59 com um único commit. A fila
P62 e seu detalhe P63 permanecem no processo separado de leitura.

## Composição e compatibilidade

`PgAuthoredDraftStagingLab` possui a transação READ COMMITTED READ WRITE.
Ela revalida o perfil de escrita, serializa os writers P64 do mesmo tenant
por advisory lock transacional, reutiliza o registry P51 e sela o autor
de cada proposta. Um erro em qualquer etapa reverte todas as linhas da
tentativa. Os limites são 128 lotes e 128 rascunhos por tenant, compatíveis
com a leitura fechada P55/P62; não há truncamento ou descarte de histórico.

`PgDeterministicDraftRegistryLab::stageInsideTransaction()` e
`PgDraftAuthorProvenanceLab::sealInsideTransaction()` usam a transação do
chamador sem fazer begin, commit ou rollback. Uma resposta DENY exige que
o chamador reverta sua transação; a composição P64 faz isso sempre.

As APIs anteriores `stage()` e `seal()` continuam donas de suas transações
e recusam transação já aberta. Seus resultados anteriores, inclusive os
motivos de falha sob uma conexão configurada como read-only, são
preservados. O construtor P51 de um argumento continua exigindo o gate
P16 original, com identidade administrativa exclusivamente na fixture de
CI. O código do `PgSyntheticReplayGate` não foi modificado.

O novo caminho `forScopedWriter()` recebe um `PgScopedDraftWriterLab`
verificado no próprio PostgreSQL e vinculado à mesma instância PDO. O
writer não pode usar o `stage()` legado para confirmar um lote isolado
da composição, nem criar schemas por esse registry.

## Writer fechado e segregação

O perfil novo aceita somente os dois logins fictícios de CI A/B, com
tenant e autor fixos. Verifica current_user e session_user, base, driver,
ausência de herança, memberships, ownership, superuser e bypass RLS.

Cada writer tem SELECT e INSERT somente em:

- `aia_p51_lab.preview_batches`;
- `aia_p51_lab.review_drafts`;
- `aia_p59_lab.draft_authors`.

PostgreSQL exige UPDATE em pelo menos uma coluna para SELECT FOR SHARE ou
FOR UPDATE. Por isso, cada tabela concede UPDATE apenas em `tenant_ref`.
A policy UPDATE usa o tenant fixo em USING e `WITH CHECK(false)`: permite
os row locks necessários, mas nenhuma atualização efetiva de linha.
INSERT de autoria também exige o autor fixo. O perfil verifica exatamente
as policies de SELECT, INSERT e UPDATE aplicáveis, sem normalizar seus
literais, e nega grant options inclusive em colunas.

Referências do PostgreSQL 16: [privilégios de SELECT e row locks](https://www.postgresql.org/docs/16/sql-select.html)
e [policies por comando, inclusive SELECT FOR UPDATE/SHARE](https://www.postgresql.org/docs/16/sql-createpolicy.html).

Os locks ACCESS SHARE são mantidos desde antes da revalidação das policies
até o fim da transação. Uma alteração DDL não pode trocar a policy
validada antes de as consultas de negócio consumirem esse perfil.

O writer não recebe privilégios sobre chaves ou observações assinadas P57.
Os readers P62 conservam os privilégios e policies anteriores. O servidor
do formulário recebe apenas a senha sintética do writer A; o servidor da
fila recebe apenas a senha sintética do reader A. Nenhum servidor HTTP
recebe a senha administrativa.

## Confirmação e replay

O servidor gera o request_ref e conserva entradas, revisão das regras,
hash da prévia e token em uma sessão privada. O POST de confirmação aceita
somente ação, CSRF e token da prévia. Tenant, autor, request, proposta,
hash e estado não são campos escolhidos pelo navegador.

O token e o request são preservados até dez minutos contados da criação
da prévia, inclusive após o commit; guardar não renova esse prazo. Uma
resposta perdida pode ser seguida da mesma confirmação: o registry exige
entradas, regras e conjunto de propostas idênticos, e a composição exige
autoria já existente com o mesmo fingerprint em todas elas. Não há novo
lote, alteração de autoria ou reparo silencioso de um vínculo ausente.

Lotes legados mantêm a semântica P63: um replay não preenche entradas
originais que não tenham sido capturadas. Novas gravações usam o mesmo
INSERT P51 que conserva os textos canônicos e inputs_version=1.

A confirmação idêntica continua permitida no limite de capacidade.
Confirmação com dados divergentes, autoria ausente ou perfil de banco
alterado falha fechada. Uma prévia sem propostas não cria lote P64 vazio.

## Interface local e fronteira HTTP

O formulário só atende no processo PHP de CI em 127.0.0.1:8787. A fila e o
detalhe atendem no reader separado, em 127.0.0.1:8786. As URLs de ação e
redirecionamento são constantes no código. Após guardar, a resposta 303
abre a fila; se houver uma proposta, abre seu detalhe diretamente.

POST exige Host e Origin exatos e token CSRF de sessão. O parser de
application/x-www-form-urlencoded lê o corpo bruto limitado, rejeita
campos repetidos depois de decodificar nomes, arrays, campos extras,
codificação inválida e entradas numéricas ambíguas. Nenhuma mutação usa
GET. A sessão tem cookie HttpOnly/SameSite=Strict, modo estrito e diretório
privado em tmpfs. Esse cookie não representa login humano nem TLS.

A CSP permite form-action somente para self e a URL fixa do reader. Essa
exceção é necessária para o redirecionamento entre portas após o POST;
não há wildcard ou destino escolhido pela requisição. A interface não
carrega scripts, fontes, imagens ou estilos de rede. Até cinco prévias
recentes ficam na sessão, sem expulsar uma anterior para criar outra.

A verificação de referência para o redirecionamento é o
[teste WPT de form-action com destino bloqueado](https://github.com/web-platform-tests/wpt/blob/master/content-security-policy/form-action/form-action-src-redirect-blocked.sub.html).

## Verificação desta entrega

`tools/check_p64_authored_draft_journey.py` reutiliza o laboratório P62/P63
e o cluster descartável P25. Usa PostgreSQL 16 e PHP reais, com imagens
fixadas pelo workflow, network none compartilhada apenas entre os
contêineres do job, sem portas no host. Só limpa recursos próprios, com
identificação e labels conferidos pelo helper existente.

O ensaio acrescenta as verificações ligadas à nova jornada:

- prévia sem escrita, taxas pt-BR, gravação pelo HTTP e detalhe P63 das
  propostas e de suas entradas originais;
- perda da resposta sem leitura pelo cliente, seguida de confirmação
  idêntica sem novas linhas;
- campos extras, duplicatas, tipos inadequados, CSRF, Origin/Host e token
  de outra sessão negados sem mutação; o limite de cinco prévias conserva
  a confirmação anterior para retry, sem evicção;
- row locks permitidos, UPDATE efetivo negado, separação entre tenants e
  ausência de acesso a chaves/votos;
- drift do perfil, inclusive grant option de coluna, negado antes da
  escrita, com controle válido após restabelecer a fixture;
- falha no segundo autor, comprovada por sequência da fixture, sem lote,
  rascunho ou autor residual da tentativa;
- replay com autoria removida ou entrada alterada negado sem reparo;
- duas sessões observadas esperando o lock: a mesma request produz uma
  gravação e um replay; duas requests disputando a última vaga produzem
  uma gravação e uma negação, sem exceder 128;
- limite de lotes isolado do limite de rascunhos, replay no limite e
  preservação das guardas e dos motivos das APIs legadas.

A navegação é exercitada por HTTP real e o driver exige a CSP exata. Não
se declara execução de Chromium ou outro motor de navegador neste
ensaio. PHP, PostgreSQL e Docker não estão disponíveis no ambiente local;
até a publicação, somente verificações estáticas locais e revisão do
código podem ser registradas. A aprovação de runtime depende da CI RC75
na árvore combinada correspondente.

## Limite de autoridade

A saída interna é AIA_P64_AUTHORED_DRAFT_BATCH_V1, sempre com
can_approve=false, can_execute=false e operational_authorization=false.
O autor é uma fixture de CI, não uma pessoa autenticada, consentimento,
representante da empresa ou revisor. Nenhum contrato do CRM foi alterado,
nenhuma credencial real foi lida e nenhum fluxo de execução foi ativado.
NO-GO operacional permanece independente do resultado técnico da CI.
