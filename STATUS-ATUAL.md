# Imagine AI Agents — estado atual

## 2026-11-18 — pacote dos providers com as fontes CRM #664

A branch `integrate/n8n-owner-components-crm-664-2026-11-18` é filha direta
da [#482](https://github.com/rafhael100/imagine-ai-agents/pull/482), fixada em
`ce004ad4a8fa5fb59ef38cdd250126d0f0e99b46`. Atualiza o pin CRM de `96db` para
`7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8`, da
[#664](https://github.com/rafhael100/imagine-crm/pull/664). O pacote passa a
incluir a migration001 corrigida e os dois migradores incrementais já
validados na CI própria do CRM. O verificador exige 32 caminhos CRM e recusa
a falta das fontes adicionais tanto na criação quanto na inspeção offline.

A admissão exige a ancestralidade real da #482 e mantém a #481 como origem
das nove dependências owner. Foram preservados os 14 imports P67/P68,
os 81 vínculos históricos e os três vínculos do bridge, os 22 grupos de
execução Agents e o registro inicial de integridade. As migrações CRM não
são executadas por esses grupos. A prova nativa continua no perfil de 33
tabelas; o laboratório owner mantém seus processos e bancos separados.

A [CI CRM #664](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735)
passou na tentativa 1: base25→monitor33 registrou 917 chamadas instrumentadas
e monitor33→owner35 registrou 997, em ensaios separados. A
[CI do ledger na mesma HEAD](https://github.com/rafhael100/imagine-crm/actions/runs/44262660963)
também passou. São 25 workflows CRM aprovados e os dois gates de escopo
F251/F252 bloqueados, preservando `deploy_approved=false`. Esses resultados
não aprovam antecipadamente a nova candidata Agents.

A validação local da candidata passou nove testes de admissão e 17 do
pacote, com revisão cruzada dos diffs. A aceitação desta HEAD requer sua
própria CI, com os 22 grupos, e a conferência independente do artefato
contra o SHA candidato e digest do manifesto obtidos da CI exata.
O V2 continua com três TARs de fontes, manifesto e `SHA256SUMS`;
a associação original dos imports P67/P68 é verificada na CI e não
revalidada offline. Não é instalador nem recuperação distribuída.

Contrato desta atualização:
[N8N-OWNER-COMPONENTS-CRM-664-CANDIDATE](docs/reviews/N8N-OWNER-COMPONENTS-CRM-664-CANDIDATE.md).
O contrato e o registro histórico da #482 permanecem abaixo e não substituem
os resultados da nova HEAD. **DRAFT / NO-MERGE / NO-DEPLOY.**

## 2026-11-18 — pacote seletivo dos providers nativo e de rascunhos owner

A branch `integrate/n8n-owner-provider-components-2026-11-18` é uma filha
da [#481](https://github.com/rafhael100/imagine-ai-agents/pull/481), fixada em
`64f52f0dcd44a5545d1bfc6c1d685b33c158601f`. A #481 permanece como evidência
estável: [CI 44247275249](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44247275249),
attempt 1, passou os 19 grupos executados e o registro inicial de integridade.
A contagem da prova de feedback foi uma execução nativa e seis observações
CRM; outros grupos possuem execuções próprias e não são somados a ela.

Esta filha acrescenta **14 arquivos selecionados**: runtime owner P67 e suas
provas P67/P68, com 12 imports literais e duas extrações de API de cenário.
As origens P67/P68 são registradas por commit/caminho/blob em
`imported_components`, separadamente da linhagem Git. A branch P68 inteira
e os incrementos P62–P66 não são incorporados. O pacote oferece fontes dos
dois providers; não declara consolidado todo o produto Agents.

A admissão integrada verifica o push real, checkout, árvore, origens fixas,
dependências e a imagem owner observada antes dos cenários. Os guards dos
entrypoints históricos continuam preservados. O build owner é separado dos
builds nativo/CRM; P67/P68 usam processos e bancos descartáveis próprios.
O runner mantém os 19 grupos anteriores e adiciona três grupos obrigatórios:
contratos da admissão, rascunhos e recuperação owner — **22 grupos executados
e um registro inicial de integridade**. A CI desta nova HEAD ainda precisa
ser concluída; a evidência da #481 não a aprova antecipadamente.

O pacote V2 mantém três arquivos de fontes (`candidate`, `crm`, `adapter`).
A CI verifica a associação commit→caminho→blob das origens num checkout P68
fixo. O verificador offline confere a árvore candidata e seus 14 blobs
selecionados; não revalida a associação original P67/P68, pois essas árvores
históricas não são incluídas como arquivos independentes.

**Validação local:** 16 testes do pacote passaram, incluindo alterações com
identidade Git recalculada, vínculos de run/imagem e limites de autoridade.
Não houve execução Docker/PHP/n8n local desta candidata.
[Escopo, fontes e critérios](docs/reviews/N8N-OWNER-COMPONENTS-CANDIDATE.md).
CRM nativo permanece em 33 tabelas; owner+monitor de 35 tabelas, sessão humana
CRM, execução owner e recuperação distribuída não são provas desta CI.
**DRAFT / NO-MERGE / NO-DEPLOY / NO-GO operacional.**

## 2026-11-18 — candidata n8n com o CRM #660 e catálogo de recuperação completo

A branch `integrate/n8n-candidate-crm-660-2026-11-18` deriva da #479 em
`3d30b2f7977b37d3bb86d6ff11bc63728145f5fd` e fixa o CRM #660 em
`96dbf1aed2d1cf3237bc3631c4594540a0168d2a`. A branch #479 original conserva seu
pin #654 e sua evidência. O novo workflow admite somente push desta branch
no repositório público Agents e runner hospedado, com checkout e ancestralidade
conferidos antes de criar recursos de laboratório.

O checker de feedback passa a carregar contract/catalog/snapshot da mesma
fonte CRM, e a capturar perfil, segurança, catálogo, linhas e pg_dump sob o
mesmo snapshot exportado. A restauração exige `source_catalog`, conserva a
comparação fechada do catálogo e registra seus hashes/igualdades e a regra
PG16 efetivamente usada. O pacote público exige o pin novo e a linhagem #479;
rejeita a substituição pelo pin antigo, omissão da nova regressão e atribuição
indevida da prova owner de 35 tabelas ao ensaio nativo de 33 tabelas.

**Validação local:** 7 regressões de compatibilidade + 11 do pacote passaram;
Bash, Python e YAML foram inspecionados. Não houve execução Docker/PHP/n8n
local. CI completa da candidata ainda pendente: a previsão é 19 grupos de
comandos e o registro inicial de integridade, sem somar suítes sobrepostas.
[Escopo e critérios](infra/n8n-private/homologation/CRM-660-CANDIDATE.md).
**DRAFT / NO-MERGE / NO-DEPLOY**; nenhuma aprovação operacional é derivada do pacote.

## 2026-11-18 — contexto completo de fontes e pacote de revisão do n8n privado

A candidata `feat/n8n-private-candidate-bundle-2026-11-18` reúne a Central
[#475](https://github.com/rafhael100/imagine-ai-agents/pull/475), o domínio
[#468](https://github.com/rafhael100/imagine-ai-agents/pull/468) e a prova MFA
[#464](https://github.com/rafhael100/imagine-ai-agents/pull/464) em um checkout.
A #475 isolada dependia de uma árvore virtual para fornecer o contrato PHP
copiado pelo Dockerfile; a consolidação fornece esse arquivo sem modificar
os blobs originais de runtime, domínio ou driver P30. O histórico abaixo
permanece preservado.

O [handoff do operador](infra/n8n-private/homologation/OPERATOR-PACKET.md)
fixa as fontes e critérios para CI direta, arquivos públicos reproduzíveis
e manifesto com HEAD/árvore e imagens observadas. Builds PHP conservam bases
mutáveis: IDs locais não são imagens publicadas nem builds reproduzíveis bit
a bit. O pacote não é backup, instalador ou Compose operacional completo.
P30 usa Stack separado, sem afirmar MFA sobre o n8n restaurado.

**Candidata DRAFT:** conferir evidência e artefato da HEAD no workflow
`n8n-private-candidate.yml`; as CIs históricas verdes das fontes não aprovam
antecipadamente esta consolidação. O inventário Leaseweb de 16/11 é
histórico, 17990 não está reservada e a sessão desta rodada requer login legítimo.
MFA individual foi provado; política global permaneceu false. Ingresso
obrigatório, autoridade/custódia reais, licença, coexistência atual, restore
externo deste pacote e plano exato de instalação continuam pendentes.
Preservar o piloto histórico R2 e INF-DEV-03 (diário/30 dias, verificação
semanal, restore mensal, simulação trimestral); revisão interna cruzada com
IA e aceite final da Direção, sem exigir contratação externa. P67/P68 e os
rascunhos do owner seguem em linha própria. **DRAFT / NO-MERGE / NO-DEPLOY.**

## Continuidade — histórico real no leitor da tela CRM

A candidata filha da [PR #472](https://github.com/rafhael100/imagine-ai-agents/pull/472)
fixa o CRM [#654](https://github.com/rafhael100/imagine-crm/pull/654) em
`e1b3b7f118eff0350a00789aecc7bb2bb2331464` para verificar a função SQL e o
modelo PHP de apresentação com as observações reais do ensaio n8n/mTLS.
A leitura usa a credencial app separada, transação READ ONLY e recuperação
com roles NOLOGIN. A prova humana de sessão/MFA/HTTP/navegador permanece na CI
do CRM; o harness não relaxa o perfil de identidade para a porta do laboratório.

[Escopo e critérios](infra/n8n-private/homologation/CRM-EXECUTION-MONITOR-EVIDENCE.md).
O resultado executado deve ser conferido na CI da HEAD publicada. Não há
aprovação antecipada nem nova autoridade operacional: DRAFT / NO-MERGE /
NO-DEPLOY, sem clientes, VPS, backup externo verificado ou custos.

## Continuidade — status de execução assinado e retorno ao CRM

Após a execução nativa de leads [#469](https://github.com/rafhael100/imagine-ai-agents/pull/469), este recorte acrescenta uma API privada separada de acompanhamento e sua integração com uma projeção e histórico no CRM. O recibo P27 de ingresso conserva o contrato original. A leitura de execução exige grant próprio, mTLS e assinatura, valida origem/tarefa/ledger/evidência em snapshot consistente e não executa ou reconcilia a fila.

O [contrato e critérios de prova](infra/n8n-private/homologation/CRM-EXECUTION-FEEDBACK.md) descrevem os estados, o hash estável das observações e o significado limitado de `PREPARED`. O workflow fixa domínio Agents e CRM por commit e exige a troca real sintética via HTTPS, n8n nativo e persistência CRM, além das regressões anteriores. Os resultados efetivos ficam na PR da HEAD testada; não se antecipa aprovação. DRAFT / NO-MERGE / NO-DEPLOY; identidade operacional, VPS e backup externo não verificados.

## 2026-11-17 — candidata CRM → fila → execução n8n de lead

A branch `feat/n8n-crm-lead-execution-2026-11-17` acrescenta o fluxo nativo
`lead.ingest` à captura #467. A Central consome o domínio #468 no SHA
`05e4ea7942e530055f4e455544d82f5d3edbd56d`, composto somente em árvore
temporária de CI. Reutiliza a fila, o ledger e a evidência assinada existentes.

O resultado do workflow é um recibo `PREPARED` com identificadores e hashes
da origem; `crm_mutated=false`. Não representa atualização comercial no CRM.
Receber o evento e autorizar sua execução continuam sendo decisões distintas.

A matriz nova parte do POST mTLS, cobre staging concorrente, execução real do
n8n, retomada de resultado sem executar novamente, revogação e restauração
conjunta. A aprovação depende da CI do SHA exato; o desenho e testes locais
não substituem a execução dessa matriz. Documentação:
[CRM-LEAD-EXECUTION](infra/n8n-private/homologation/CRM-LEAD-EXECUTION.md).

**NO-GO operacional:** identidade e chaves sintéticas, sem instalação na VPS,
backup externo verificado ou autorização operacional de clientes.

## Candidata de captura durável CRM — 17/11/2026

Branch isolada `feat/n8n-crm-lead-capture-2026-11-17`, derivada da P29 #463
em `1838e7e13667c2f2c33d19b5b3eb469637a7c431`. A P29 foi aprovada na
[CI 44203009721](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203009721).

O receptor passa a oferecer captura explícita e imutável dos bytes mínimos de
`CRM_LEAD_EVENT_V1`, junto da admissão da inbox, em uma transação. O objetivo
é fornecer origem durável para a futura operação `lead.ingest`. Repetição,
falha na gravação e expiração preservam a atomicidade; a captura tem prova
própria de restauração e não altera o significado do ACK de recebimento.

Procedimento e limites:
[CRM-LEAD-CAPTURE](infra/n8n-private/homologation/CRM-LEAD-CAPTURE.md).
Validação integrada deve ser conferida na HEAD desta candidata; a CI da base
não aprova estes novos cenários. **DRAFT / NO-GO operacional.**

## Candidata P29 / RC71 — 17/11/2026

Continuação da [P28 #462](https://github.com/rafhael100/imagine-ai-agents/pull/462),
SHA `c0c46f6f1d3d4ff007469388d3f53fa4719eb71e`, aprovada na
[CI 44201828792](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44201828792).
Branch: `feat/central-auto-p29-ingress-boundaries-2026-11-17`.

O ingresso assinado passa a recusar nomes JSON repetidos, inclusive escapes
equivalentes, e referências UUID malformadas antes de consultar o banco.
A validade é reavaliada depois das esperas reais por locks; uma tentativa
vencida desfaz sua própria inbox e auditoria sem apagar um escritor vencedor.
ACK, consulta de recibo e dispatch `DISABLED` conservam seus contratos.

O ensaio isolado passou na
[CI 44198509977](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44198509977):
24 casos do parser, 25 negativos HTTP assinados, quatro corridas PostgreSQL
observadas e controles válidos posteriores, junto com HTTPS/mTLS P27.
A RC71 verifica novamente esse conjunto com a recuperação completa P25/P28,
a execução P26 e as suítes Agents na mesma árvore temporária. Conferir o
resultado da HEAD concreta antes de considerar a matriz consolidada aprovada.

Contrato e limites: [INGRESS-P29](infra/n8n-private/homologation/INGRESS-P29.md).
**DRAFT / NO-GO operacional.** Dados, certificados e concessões sintéticos;
integração do cliente CRM e autoridade real continuam pendentes.

## Candidata P28 / RC70 — 17/11/2026

Continuação da [P27 #461](https://github.com/rafhael100/imagine-ai-agents/pull/461),
SHA `8041c022776bbc1f998224a880726a8a3e69db37`, aprovada na
[CI 44192247253](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44192247253).
Branch: `feat/central-auto-p28-complete-candidate-recovery-2026-11-17`.

O backup agora captura o estado produzido pelo fluxo P26, incluindo resultado,
recibo e evidência de ACK, junto com inbox e grants P27. A restauração compara
17 tabelas e seus objetos em outro PG16, reconcilia revogações atuais mantidas
fora do dump e confirma que ACK histórico, RESULT e UNKNOWN não geram novo
trabalho. Roles permanecem NOLOGIN, sem reativação de concessões.

O mesmo pacote recupera PG17, volume, chave e seis workflows n8n inativos em
outro Stack. Os grafos são conferidos por exportação, sem executar novamente.
O wrapper roda P26 uma vez com o hook P28, além de P25 e do transporte P27.

Procedimento e limites: [RECOVERY-P28](infra/n8n-private/homologation/RECOVERY-P28.md).
Conferir a execução na CI da HEAD concreta. **DRAFT / NO-GO operacional.**

## Candidata P27 / RC69 — 17/11/2026

Continuação da [P26 #460](https://github.com/rafhael100/imagine-ai-agents/pull/460),
SHA `8cea78524c0ec9cc6aafe440c7b6b17160177bf9`, cuja
[CI 44190424888](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44190424888)
aprovou o fluxo real e a conciliação. Branch:
`feat/central-auto-p27-private-receipt-status-2026-11-17`.

A nova consulta privada permite recuperar o recibo de um evento já recebido
sem reenviá-lo. Exige mTLS, assinatura e concessão específica de leitura,
além do vínculo empresa/tenant, grant de ingresso e chave atuais. Retorna
somente referências e `RECEIVED / DISABLED / NOT_DISPATCHED`, com assinatura
vinculada à consulta. Outra empresa, revogação, evento inexistente e hash
incorreto não recebem informações sobre o evento.

O receiver P19 e seu ACK permanecem intactos. A consulta não apresenta a
inbox como execução n8n nem liga `lead.ingest` à operação P26. O ensaio usa
HTTPS/mTLS real com certificados sintéticos e componentes isolados, além das
verificações P25/P26 anteriores na mesma árvore temporária.

Contrato e limites: [RECEIPTS-P27](infra/n8n-private/homologation/RECEIPTS-P27.md).
Verificar o resultado na CI da HEAD desta candidata.
**DRAFT / NO-GO operacional; integração do cliente CRM ainda não realizada.**

## Candidata P26 / RC68 — 17/11/2026

Continuação da [P25 #459](https://github.com/rafhael100/imagine-ai-agents/pull/459),
SHA `63695d777dff4088717c262b13f98576ee02f96f`, cuja
[CI 44188838729](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44188838729)
aprovou a recuperação integrada. Branch:
`feat/central-auto-p26-native-outbox-execution-2026-11-17`.

O novo executor CI liga a fila P45 a uma projeção técnica **real no n8n**,
conserva o resultado observado e utiliza o ACK/evidência transacional original.
Um ledger persistido antes da chamada impede repetição automática quando o
processo termina sem resultado conhecido. A conciliação após resultado durável
reutiliza o comprovante, revalida a autorização e não executa o workflow outra
vez. As referências, conteúdo, tentativa e grafo ficam vinculados.

O evento é `mock.run.completed`, operação `run.status.project`. P19
`lead.ingest` conserva seu contrato e dispatch desligado. As identidades e
concessões permanecem sintéticas; execução em CI não constitui IAM real.
Procedimento: [EXECUTION-P26](infra/n8n-private/homologation/EXECUTION-P26.md).
O resultado do conjunto deve ser conferido no check desta HEAD.
**DRAFT / NO-GO operacional.**

## Candidata P25 / RC67 — 17/11/2026

Continuação da [P24 #458](https://github.com/rafhael100/imagine-ai-agents/pull/458),
SHA `62474b6a8d7b6f9119b31ae9c5ed3ca49f842689`, na branch
`feat/central-auto-p25-agents-state-recovery-2026-11-17`.

O ensaio passa a recuperar o **estado efetivo da candidata Agents** em outro
servidor PostgreSQL 16 descartável: inbox, grants, roles, ACLs, RLS, replay,
outbox e controles de reserva. O dump atravessa o mesmo pacote assinado e
cifrado que o n8n completo. O componente CRM continua um marcador sintético.

Inclui a distinção entre revogações já presentes no backup e revogações
posteriores: o estado antigo permanece em quarentena até reconciliar uma
referência atual sintética, mantida fora do dump. Isso não comprova IAM real.
As classes originais da RC65 permanecem pinadas e são exercitadas na árvore
temporária RC67, sem merge entre as linhas de desenvolvimento.

Procedimento, critérios de CI e limites:
[RECOVERY-P25](infra/n8n-private/homologation/RECOVERY-P25.md).
Resultado de execução deve ser conferido no check da HEAD desta candidata.
**DRAFT / NO-GO operacional; dispatch desligado.**

## Candidata P24 / RC66 — 17/11/2026

Branch isolada `feat/central-auto-p24-complete-n8n-recovery-2026-11-17`, derivada da
[Central P23 #456](https://github.com/rafhael100/imagine-ai-agents/pull/456), SHA
`995201f9300590f86b6ce5bf5d46168cee5ef903`. A linha Agents permanece fixada na
[RC65 #457](https://github.com/rafhael100/imagine-ai-agents/pull/457), SHA
`3fb86c5fb011bc9df1f12a4a30a7c094370b6872`. Não houve merge entre essas branches.

- **Correções reproduzidas:** a recusa de gravação do recibo preserva destinos de
  outro produtor; o assinante exige par Ed25519 correspondente; flags exigem
  booleano falso; o manifesto recusa chaves JSON duplicadas, tipos incorretos,
  dumps fora do formato custom, chave fora dos limites e permissões inseguras.
- **Novo ensaio funcional:** recuperação do schema real do n8n em PostgreSQL 17,
  volume e chave, desde pacote CMS assinado, com nova stack descartável. Verifica
  workflow nativo antes/depois e credencial exclusivamente sintética criptografada.
  Os componentes CRM/Agents desse pacote continuam sendo bancos marcadores
  sintéticos; não representam restauração dos schemas desses produtos.
- **Compatibilidade:** `tools/run_rc66_p24_virtual_tree.sh` combina os fontes em
  árvore Git temporária, confere os blobs das duas origens e executa segurança,
  domínio Agents e recuperação n8n nessa mesma árvore, sem criar merge commit.
- **Evidências:** regressões locais em Python/OpenSSL e revisão independente do
  código. O ensaio Docker/PHP é exclusivo de GitHub Actions; resultado e SHA
  executados devem ser conferidos no check da PR. Não usar um check da P23/RC65
  antiga como evidência da nova candidata.

Procedimento e limites: [RECOVERY-P24](infra/n8n-private/homologation/RECOVERY-P24.md).
**DRAFT / NO-GO operacional.** Backup externo, restauração em outro host, custódia
real, identidade operacional e homologação da VPS continuam dependentes de
evidências próprias. Esta candidata não altera a `main`, a VPS, o R2, os serviços
reais, a cobrança ou as permissões de clientes; dispatch permanece desligado.

## Histórico da linha dev — 14/11/2026

**14/11/2026.** GitHub. **Produção: NO-GO.**

## Estado executivo

**Fase:** offline.

**Branch:** `dev/ft01-aia-core-mocks-2026-11-06`; `main` intacta. **879 testes PHP / lint 110 + Python/DOM**; EVID-43,EVID-44,EVID-45,EVID-46,EVID-47,EVID-48,EVID-49,EVID-50,EVID-51,EVID-52; #293/#294 (43605185307,43605619299), #296/#297 (43606939495,43607272003), #299/#300/#301 (43609511492,43610007078,43610242488); 43038243942,43088648397. **NO-GO**.

## Últimas entregas verificadas

**SEC-60/F187:** #261 CI #43019868566 (cross-Run); #262 CI #43020983307 (JSON), **810 testes PHP / lint 100**. [EVID-42](docs/AIA-R01-EVID-42-SEC60-F187-CI-POSTMERGE.md). **NO-GO**.

**F184–SEC-59:** #255–259, CI #43013609438, **800 testes PHP**; [EVID-41](docs/AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md). CRM real **NO-GO**.

**SEC-57–F183:** #250 CI #42980115062; #251 CI #42980489349; #252 CI #42981149687; #253 CI #42981975813 (**776 testes PHP**). [EVID-40](docs/AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md). **NO-GO**.

**OP-01:** #239 CI #42949459592; #240 CI #42949959755; #241 CI #42950862393; #242 CI #42951498940 (**699 testes PHP**). [EVID-39](docs/AIA-R01-EVID-39-OP01-QUEUE-PILOT-POSTMERGE.md). **NO-GO**.

**SEC-53 integrada à dev (PR #237):** baseline **648 testes PHP**, lint de **83 arquivos PHP**, [CI #42945854088](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42945854088); bloqueia alteração de Run, U75-01..08, [SEC-53](docs/AIA-FT01-SEC-53-RUN-LIFECYCLE-READONLY-CALLBACK.md), [EVID-38](docs/AIA-R01-EVID-38-SEC53-CI-POSTMERGE.md). CRM `NAO_EXECUTADO` / **NO-GO**.

**SEC-52 integrada à dev (PR #235):** baseline **640 testes PHP**; [CI #42943113650](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42943113650), guarda compartilhada; sete U74-01..07, [SEC-52](docs/AIA-FT01-SEC-52-GUARDA-COORDENADOR-CALLBACK.md), [EVID-37](docs/AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md). CRM `NAO_EXECUTADO` / **NO-GO**.

**SEC-51 integrada à dev (PR #233):** **633 testes PHP**, [CI #42940604581](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42940604581); reentrada negada, U73-01..04, [SEC-51](docs/AIA-FT01-SEC-51-REENTRADA-FERRAMENTAS-CALLBACK.md), [EVID-36](docs/AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md). **NO-GO**.

**SEC-50 integrada à dev (PR #230):** baseline **629 testes PHP**, U72-01..08, [CI #42938703131](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42938703131), [SEC-50](docs/AIA-FT01-SEC-50-CAPABILITY-RECHECK-POS-FERRAMENTA.md), [EVID-35](docs/AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md). Revogação pós-tool; CRM **NO-GO**.

**SEC-49 integrada à dev (PR #228):** epoch do trust compartilhado; oito U71-01..08, [SEC-49](docs/AIA-FT01-SEC-49-REVISAO-TRUST-COMPARTILHADO.md), [EVID-34](docs/AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md).

**SEC-48 integrada à dev (PR #226):** nega READ aninhada antes de auditoria positiva; U70-01..08. [SEC-48](docs/AIA-FT01-SEC-48-REENTRADA-LEITURA-OP01.md), [EVID-33](docs/AIA-R01-EVID-33-SEC48-CI-POSTMERGE.md), [CI #42924834951](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42924834951). CRM **NO-GO**.

**SEC-47 integrada à dev (PR #224):** recheck monotônico por recibo; U69-01..08. [SEC-47](docs/AIA-FT01-SEC-47-RECHECK-TEMPO-MONOTONICO.md), [EVID-32](docs/AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md), [CI #42923158871](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42923158871). CRM **NO-GO**.

**SEC-46 integrada à dev (PR #222):** revisão monotônica e recibo contra ABA; U68-01..12. [SEC-46](docs/AIA-FT01-SEC-46-RECIBO-REVISAO-OWNER-ABA.md), [EVID-31](docs/AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md), [CI #42918687251](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42918687251). CRM **NO-GO**.

**SEC-45 integrada à dev (PR #220):** revalida autorização sintética após a sugestão e antes de concluir o Run. Revogação no provedor termina como `FALHOU`, sem resumo, nova leitura ou segundo nonce; dez regressões U67-01..10. [SEC-45](docs/AIA-FT01-SEC-45-REVALIDACAO-POS-SUGESTAO.md); [EVID-30](docs/AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md), [CI #42913437800](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42913437800). Homologação CRM: `NAO_EXECUTADO` / **NO-GO**.

**SEC-44 integrada à dev (PR #218; [CI #42906719897](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42906719897)):** a ferramenta OP-01 também usa a mesma invariante de projeção das outras três camadas; 10 regressões U66-01..10 validam ferramenta → edge → fachada → leitor, revogações durante a leitura, replay e auditoria. [SEC-44](docs/AIA-FT01-SEC-44-FERRAMENTA-PROJECAO-COMUM.md), [EVID-29](docs/AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md). CRM real: `NAO_EXECUTADO` / **NO-GO**.

**SEC-43 integrada à dev (PR #216):** revalida autorização sintética do proprietário, credenciais e revogação compartilhada **após** a leitura e antes da auditoria de sucesso, sem consumir segundo nonce. Dez regressões U65-01..10. [SEC-43](docs/AIA-FT01-SEC-43-REVALIDACAO-POS-LEITURA.md), [EVID-28](docs/AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md). Homologação real: `NAO_EXECUTADO` / **NO-GO**.

**SEC-42 integrada à dev (PR #214):** matriz adversarial determinística de mais de 200 entradas para o parser OP-01, comparada à execução PHP em lotes limitados; contrato estrito e sem rede. [SEC-42](docs/AIA-FT01-SEC-42-PARIDADE-MUTACOES-OP01.md). Os 13 cenários reais permanecem `NAO_EXECUTADO` / **NO-GO**.

**SEC-41 integrada à dev (PR #213):** a projeção OP-01 usa o mesmo contrato de validação na borda, resposta pública e telemetria. Leitores fictícios inconsistentes (inclusive valores de 1 MiB) não podem gerar auditoria de sucesso; nove novos testes U64-01..09. [SEC-41](docs/AIA-FT01-SEC-41-PROJECAO-OP01-UNIFICADA.md). Homologação CRM ainda `NAO_EXECUTADO` / **NO-GO**.

**F163 / EVID-25 integrado à dev (PR #211):** 13 cenários OP-01 ensaiados offline (U63-OP01-N01..N11/P01..P02), [EVID-25](docs/AIA-R01-EVID-25-ENSAIOS-OP01-13-CENARIOS.md), [EVID-26](docs/AIA-R01-EVID-26-F163-CI-POSTMERGE.md). Homologação real `NAO_EXECUTADO` / **NO-GO**.

[EVID-24](docs/AIA-R01-EVID-24-CONSOLIDACAO-SEC39-40.md): SEC-39/40; [CI #42889307984](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42889307984) aprovada, 533 testes PHP. Não autoriza CRM.

**SEC-40 integrada à dev (PR #209):** limites de manifestos/referências; U62-01..07, [SEC-40](docs/AIA-FT01-SEC-40-MANIFESTO-LIMITADO.md). Sem registro real de agentes.

**SEC-39 integrada à dev:** limita projeções sintéticas antes de chamar provedor, U61-01..08. [SEC-39](docs/AIA-FT01-SEC-39-LIMITES-SUGESTOES.md). Sem IA real.

**SEC-38 integrada à dev:** concessões de ferramenta sintética validam identificadores `id` e `toolId` com no máximo 90 bytes e formato fechado já na criação. Seis regressões U60-01..06 para 1 MiB, fronteira 90/91 bytes, padrões inválidos e revogação. [SEC-38](docs/AIA-FT01-SEC-38-CONCESSOES-LIMITADAS.md). Não implementa IAM real.

**SEC-37 integrada à dev:** parser OP-01 valida primeiro o total de 8 chaves e recusa referências com mais de 90 bytes antes do regex; não consome nonce ou auditoria de leitura para entradas inválidas. Sete regressões U59-01..07 com cargas grandes. [SEC-37](docs/AIA-FT01-SEC-37-PARSER-OP01-LIMITADO.md). Só dados sintéticos, sem API real.

**SEC-36 integrada à dev:** IDs longos de ferramenta são negados antes de consulta ao catálogo ou recursos e sua auditoria usa hash de marcador constante. Cinco regressões U58-01..05 (incluindo 1 MiB de entrada, borda 128/129 bytes, preservação de nonce e ferramenta válida posterior). [SEC-36](docs/AIA-FT01-SEC-36-IDS-FERRAMENTA-LIMITADOS.md). Sem gateway de rede.

**SEC-35 integrada à dev:** o coordenador também impede regressão `PENDENTE → INDETERMINADO` em respostas de adaptadores inconsistentes, sem substituir objetos de evidência já conhecidos. Oito regressões U57-01..08; normaliza respostas sem prova e mantém status-only após timeout. [SEC-35](docs/AIA-FT01-SEC-35-COORDENADOR-MONOTONICO.md). Sem CRM real.

**SEC-34 integrada à dev:** a versão sintética selecionada deixa de ser apresentada como ativa quando a revisão associada é revogada; consultas com relógio explícito também verificam validade temporal. Sete regressões U56-01..07. [SEC-34](docs/AIA-FT01-SEC-34-REVALIDACAO-REVISAO-ATIVA.md). Não aprova versões reais.

**SEC-33 integrada à dev:** impede regressão de evidências sintéticas de `PENDENTE` para `INDETERMINADO`, preserva replays não terminais sem alocar novo efeito e mantém resultados terminais imutáveis. Seis regressões U55-01..06. [SEC-33](docs/AIA-FT01-SEC-33-EVIDENCIAS-MONOTONICAS.md). Sem CRM ou IAM real.

**SEC-32:** limita a 256 efeitos sintéticos; [documentação](docs/AIA-FT01-SEC-32-EFEITOS-LIMITADOS.md). Sem CRM real.

**SEC-31:** limites locais de 256 fontes e 1.024 eventos; [documentação](docs/AIA-FT01-SEC-31-LIMITES-CONHECIMENTO.md). Sem RAG real.

**SEC-30 — catálogo limitado:** limites de modelos/snapshots fictícios sem apagar histórico. [Detalhes](docs/AIA-FT01-SEC-30-CATALOGO-LIMITADO.md). Sem agentes publicados.

**SEC-29 integrada à dev (PR #198):** limite de decisões e eventos do controle de versões simulado, com reserva de espaço para suspensão e seis regressões. [Evidência técnica SEC-29](docs/AIA-FT01-SEC-29-RESERVA-AUDITORIA-VERSOES.md). A execução segue offline e o CRM real continua **NO-GO**.

**SEC-28:** o quadro de revisão local limita registros de votação a 128 pacotes sem descartar decisões anteriores. O segundo parecer de pacotes já iniciados continua possível. [Detalhes e regressões](docs/AIA-FT01-SEC-28-REVISOES-BOUNDED.md). **Isto não é homologação real.**

**SEC-26 / PR #194** vinculou a identidade do consumo ao estado do transporte ([CI pós-merge #42863662530](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863662530) success). **SEC-27 / PR #195** limitou IDs e unidades no domínio de uso ([CI pós-merge #42864322239](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864322239) success). [EVID-23](docs/AIA-R01-EVID-23-IDENTIDADE-USO-446.md) registra evidência e limites. Os marcos anteriores abaixo são históricos.

A [SEC-25 / PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192) limitou o medidor de uso em memória e garantiu falha segura sem perda de idempotência; [CI pós-merge #42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) success. Ver [EVID-22](docs/AIA-R01-EVID-22-MEDIDOR-USO-433.md). Entregas anteriores abaixo são históricas.

[SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) aplicou limites a Intent/Approval ([CI pós-merge #42847828963](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42847828963) success). [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) estendeu os limites aos objetos básicos Run/Step ([CI pós-merge #42848621884](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42848621884) success). Ver [EVID-21](docs/AIA-R01-EVID-21-LIMITES-DOMINIO-427.md). Marcos anteriores abaixo são históricos.

As SEC-21 e SEC-22 foram integradas somente à `dev`: [PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) exige Run/Step canônicos na interface de ferramentas ([CI pós-merge #42836105212](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836105212) success); [PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) limita o tamanho dos IDs ([CI pós-merge #42836672922](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836672922) success). [EVID-20](docs/AIA-R01-EVID-20-RUN-STEP-CANONICO-414.md) apresenta o estado técnico vigente. Os marcos anteriores abaixo são históricos.

A [SEC-20 / PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) corrigiu a auditoria de projeções inconsistentes antes de registrar sucesso; [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) aprovada. Consulte [EVID-18](docs/AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md). O [README](README.md) atual foi consolidado e a versão completa anterior permanece arquivada.

**Marcos históricos:** F1-30 / SEC-16 a SEC-19 (PRs #173, #174, #175, #177, #178) e EVID-16 (PR #179) estão documentados no [arquivo integral](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md); CIs e decisões históricas permanecem preservadas.

**Reproduzir em checkout revisado:**

```bash
php examples/f127-read-only-pilot-demo.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=117 EXPECTED_CASES=879 bash tools/run_r01_offline_quality_gate.sh
```

O comando acima exige PHP e Python já instalados, não emite credenciais e não acessa CRM real. O sucesso não abre gates operacionais.

## Pacote formal de revisão entregue, sem aceite (10/11/2026)

O [PR #184](https://github.com/rafhael100/imagine-ai-agents/pull/184) integrou um [pacote JSON de aceite bilateral](docs/evidencias/op01-pacote-aceite-bilateral.json) que correlaciona **7 decisões OP01-R01..R07**, **14 gates** e **13 cenários não executados**, com propostas de responsáveis e evidências pendentes. A [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) passou. Consulte [EVID-19](docs/AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md).

O registro foi comunicado no CRM PR #57 **sem aprovação ou merge**. Os dados deste pacote não provam a execução de testes reais e permanecem **NO-GO**.

## Bloqueadores de integração CRM ↔ Agents

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT, não integrado e não homologado** em 10/11/2026. A versão documental revisada foi reconciliada com o Agents em [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md), usando HEAD CRM `97bc1e8d6ce4cf4b52378472c31cb49de6a66d51` e blob `cefb390ec41a76d486b137279d92627f097d1d47`; isso **não** aprova nenhum gate. A proposta é documental; nenhuma rota, API real ou permissão foi autorizada. As decisões precisam de aceite independente por área:

- **R01-G01/G03 e CRM AUT/SEG:** identidade serviço-serviço, issuer/audience, vínculo principal/tenant, expiração, revogação e anti-replay distribuído;
- **R01-G02 e CRM CORE/SEG:** autorização final pelo backend CRM por tenant, membership, recurso, finalidade e campo, incluindo revogação e isolamento multiempresa;
- **R01-G05/G08:** classificação dos campos reais, base legal, minimização, retenção, esquema/versionamento de mensagens, erros não enumeráveis e responsabilidade de cada componente;
- **R01-G07/G10:** ambiente isolado, segredos sob controle, revisão independente de segurança, testes de integração positivos/negativos, recuperação e rollback;
- **R01-G11:** responsáveis, limites financeiros e autorização específica do piloto. Produção exige aceite adicional.

**Todos os gates R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO.** Nenhum teste sintético, CI verde ou comentário em PR substitui a aprovação e a evidência de runtime requeridas.

## Referências atuais e histórico preservado

- [Dossiê técnico EVID-16](docs/AIA-R01-EVID-16-SEGURANCA-TOOL-397.md) — SEC-18/19, evidências e limites.
- [Handoff EVID-15](docs/AIA-R01-EVID-15-HANDOFF-READ-TOOL-389.md) e [EVID-14](docs/AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md) — entregas anteriores.
- [Roadmap atual](docs/ROADMAP.md) — sequência de decisões e critérios de saída.
- [Status histórico integral, preservado sem perda](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) — decisões, PRs e registros datados; **não** interpretar métricas antigas como status atual.
- [Roadmap histórico integral](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) — evolução e ideias anteriores.

**Restrições mantidas:** nenhuma modificação em `main`, VPS ou produção, sem dados de cliente, segredos, chamadas pagas ou novos contratos. A continuidade permitida é revisão e desenvolvimento controlado em `dev`, não autorização de ativação comercial.
