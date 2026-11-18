# Pacote público da candidata privada n8n — 18/11/2026

**Candidata DRAFT.** Conferir a evidência desta HEAD e seu artefato no workflow
`n8n-private-candidate.yml`, da branch
`integrate/n8n-owner-components-crm-664-2026-11-18`. Esta filha da #482
atualiza as fontes CRM do pacote para a #664. Conserva os dois providers,
os 14 imports owner e os 22 grupos de provas Agents da base; não acrescenta
execução de migrations CRM a esses grupos. A evidência anterior não aprova
antecipadamente a nova HEAD. O pacote oferece fontes para build e revisão
dos providers selecionados; não declara consolidado todo o produto Agents.
Não é instalador, Compose operacional completo, backup ou autorização de acesso.

## Componentes owner e linhagem

A base imediata é a [#482](https://github.com/rafhael100/imagine-ai-agents/pull/482)
em `ce004ad4a8fa5fb59ef38cdd250126d0f0e99b46`, cuja
[CI 44251661738](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44251661738)
passou em attempt 1 com 22 grupos e verificação do pacote. A composição dessa
base preserva #481 e #479 e os componentes selecionados P67/P68. Os nove
arquivos do runtime owner, checker/probe P67 e checker/probe/runner P68
continuam imports seletivos. Doze permanecem literais; nos dois checkers
somente a API de cenário foi extraída, conservando os entrypoints e guards
históricos. A admissão integrada usa a identidade real da nova candidata.

`lineage` contém os ancestrais exigidos, incluindo #482, #481 e #479.
`imported_components` registra as origens P67/P68 por commit, caminho,
modo, blob original, blob candidato e adaptação. Essa segunda relação
**não é uma declaração de ancestralidade nem de merge da branch P68**.
O pacote não incorpora os incrementos P62–P66. O
[contrato histórico dos componentes](../../../docs/reviews/N8N-OWNER-COMPONENTS-CANDIDATE.md)
permanece preservado e enumera os arquivos e a diferença P27 já sobrescrita
pelo laboratório owner. A admissão continua vinculando suas nove dependências
à #481, além de exigir a ancestralidade real da #482 nesta branch.

A criação conserva os 19 grupos anteriores à #482 e os três grupos owner:
admissão integrada, P67 e P68. São **22 grupos executados mais o registro
inicial de integridade**. P67/P68 compartilham uma imagem PHP owner observada,
em subprocessos próprios; o provider nativo e o CRM conservam seus builds e
perfis distintos. Não se exige imagem ou banco comum entre a prova owner e
a prova de feedback nativo. O pacote registra os três IDs locais sem atribuir
a eles publicação em registry.

## Fontes CRM #664 e provas próprias do CRM

O pin atual é [CRM #664](https://github.com/rafhael100/imagine-crm/pull/664),
commit `7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8`,
árvore `1ca57c0fa3197e83a36d9c6024bf13e77f10ad23`. O TAR do CRM
contém essa árvore completa. A lista obrigatória do verificador passa de
12 para 32 caminhos: acrescenta as fontes da migration001 corrigida, os dois
migradores incrementais, seus ensaios, guard e testes, workflows, inputs
pinados e documentos correspondentes. A ausência de qualquer fonte adicional
é recusada na criação e na inspeção offline, mesmo com hashes e identidade
Git da fixture coerentes.

O perfil executado pelo provider nativo permanece com **33 tabelas** e usa os
três módulos canônicos de backup da mesma fonte, com snapshot exportado para
segurança, catálogo, dados e dump. As rotas e tabelas owner do CRM não são
instaladas por esse ensaio. Os 81 vínculos históricos e os três bindings
próprios do checker/compatibilidade permanecem; a atualização do pin não
amplia o contrato executado nos Agents.

A [CI própria do CRM #664, run 44262660735, attempt 1](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735)
concluiu com sucesso. No
[job owner 134659509281](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735/job/134659509281),
o ensaio base25→monitor33 registrou 917 chamadas de verificação, com primeira
aplicação, reaplicação povoada, três recusas e bloqueio real de criação de
papel seguido de `55P03` e rollback. O ensaio separado monitor33→owner35
registrou 997 chamadas de verificação, incluindo seus próprios cenários e
limpeza. Essas contagens incluem preparação e polling; não são números de
cenários independentes e não devem ser somadas aos 22 grupos dos Agents.

A base25 desse ensaio é `schema.sql` de `96db` mais o script de papéis e já
contém o ledger. Não se afirma que a migration001 sobre o schema histórico
`cc173` produz a mesma origem, nem que as duas etapas incrementais foram
uma execução única 25→35. A prova de ledger e as provas de migrations pertencem
à CI do CRM. Sua presença como fontes no pacote não cria novos resultados
PASS ou novas observações de execução no manifesto Agents.

Os contratos específicos estão nos documentos do CRM:
[migration ledger e drift](https://github.com/rafhael100/imagine-crm/blob/7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8/docs/automation/CRM-AUTOMATION-MIGRATION-DRIFT.md),
[monitor33→owner35](https://github.com/rafhael100/imagine-crm/blob/7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8/docs/desenvolvimento/CRM-OWNER-MONITOR-INCREMENTAL-MIGRATION-2026-11-18.md)
e [base25→monitor33](https://github.com/rafhael100/imagine-crm/blob/7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8/docs/desenvolvimento/CRM-BASE-MONITOR-INCREMENTAL-MIGRATION-2026-11-18.md).
A [compatibilidade histórica CRM #660](CRM-660-CANDIDATE.md) permanece como
registro da base #481; não substitui a evidência da nova HEAD com CRM #664.

## Lacuna concreta e composição histórica

A Central #475 foi validada numa árvore temporária combinada com o domínio
#468. Seu Dockerfile copia
`src/MockOrchestration/CapturedLeadTaskContract.php`, arquivo ausente no
checkout isolado da #475. Esta candidata reúne fisicamente essas fontes e a
P30, permitindo construir a imagem a partir de um checkout único. Mantém os
blobs originais de runtime, domínio e driver MFA; a seção histórica de status
da #475 foi preservada na resolução do conflito documental.

| Fonte | Commit fixo | Evidência anterior |
| --- | --- | --- |
| [Central #475](https://github.com/rafhael100/imagine-ai-agents/pull/475) | `dfa4685cd5df4a9499c3420f580980e769a1981d` | [CI 44225455499 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44225455499) |
| [Domínio #468](https://github.com/rafhael100/imagine-ai-agents/pull/468) | `05e4ea7942e530055f4e455544d82f5d3edbd56d` | [CI 44208519874 — success na composição com Central #469](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44208519874) |
| [MFA P30 #464](https://github.com/rafhael100/imagine-ai-agents/pull/464) | `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8` | [CI 44203383716 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203383716) |
| [Candidata #479](https://github.com/rafhael100/imagine-ai-agents/pull/479) | `3d30b2f7977b37d3bb86d6ff11bc63728145f5fd` | [CI 44236540149](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44236540149), com o pin histórico CRM #654 |
| [Candidata #481](https://github.com/rafhael100/imagine-ai-agents/pull/481) | `64f52f0dcd44a5545d1bfc6c1d685b33c158601f` | [CI 44247275249 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44247275249), 19 grupos executados com CRM #660 |
| [Candidata #482](https://github.com/rafhael100/imagine-ai-agents/pull/482) | `ce004ad4a8fa5fb59ef38cdd250126d0f0e99b46` | [CI 44251661738 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44251661738), 22 grupos, três arquivos de fontes e manifesto V2 com CRM #660 |
| [P67 #476](https://github.com/rafhael100/imagine-ai-agents/pull/476) | `0c722eee6bd53a7ee46c4ba68ab70200fc191b78` | Origem de 11 arquivos selecionados; o mapa de blobs distingue import de ancestralidade |
| [P68 #477](https://github.com/rafhael100/imagine-ai-agents/pull/477) | `85f4c37a6d905b91923d3e31ba9068b2c35d7657` | Origem de três arquivos selecionados e checkout fixo para validar as origens na CI |
| [CRM #660](https://github.com/rafhael100/imagine-crm/pull/660) | `96dbf1aed2d1cf3237bc3631c4594540a0168d2a` | [CI owner 44246202205 — success](https://github.com/rafhael100/imagine-crm/actions/runs/44246202205), própria do CRM; não atribuída ao n8n |
| [CRM #664](https://github.com/rafhael100/imagine-crm/pull/664) | `7e1b00a0c173bcfcac3a9051ef6cad3aededcdb8` | [CI owner 44262660735 — success](https://github.com/rafhael100/imagine-crm/actions/runs/44262660735), migrations em ensaios separados; não atribuídas aos 22 grupos Agents |
| [Adapter histórico](https://github.com/rafhael100/imagine-ai-agents/commit/8b04d0e936168c77db028160b5aeb1758db497c4) | `8b04d0e936168c77db028160b5aeb1758db497c4` | Dependência pública pinada do replay existente; não é fonte de autoridade atual |

O commit e a árvore **reais desta candidata** são obtidos do checkout da CI
e registrados no manifesto, sem reutilizar a identidade de uma árvore virtual
anterior. A consolidação possui sua própria história Git; o CRM e o adapter
continuam dependências públicas separadas, com seus commits e árvores próprios.

## Verificação e conteúdo público

O workflow `.github/workflows/n8n-private-candidate.yml` e o runner
`tools/run_n8n_private_candidate.sh` destinam-se exclusivamente ao runner
descartável autorizado. Verificam a preservação das fontes e exigem os checks
da #475 no checkout consolidado, a compatibilidade de backup com a fonte CRM #664,
o driver P30 original e os três grupos owner antes do pacote. A base #481
preservou 81 dos 82 vínculos de fontes anteriores; o checker de feedback e
seus dois módulos de compatibilidade são fixados por blobs próprios revisados.
Os builds usam arquivos de fontes da HEAD completa e do CRM pinado. O build
owner usa uma exportação temporária separada da mesma HEAD. O adapter
histórico é acrescentado somente ao ambiente dos testes nativos depois do build.

A criação é restrita à CI hospedada e recebe observações produzidas pelo
runner, sem aceitar uma aprovação operacional no manifesto:

```text
operator_packet.py create --candidate-repo PATH --crm-repo PATH --observations JSON --output-dir NOVO
operator_packet.py verify DIRETORIO --expected-candidate-sha SHA40 [--expected-manifest-sha256 SHA256]
```

O diretório de saída deve ser novo. O runner usa
`$RUNNER_TEMP/n8n-private-candidate-packet` e verifica o pacote antes do upload.
O artefato `n8n-owner-components-candidate-${{ github.sha }}-${{ github.run_attempt }}` contém
somente `candidate.tar.gz`, `crm.tar.gz`, `adapter.tar.gz`, `manifest.json` e
`SHA256SUMS`, com retenção de sete dias no GitHub. As fontes podem ser
regeneradas dos mesmos objetos Git públicos após esse prazo. Essa retenção
de artefato de revisão não constitui backup nem altera sua política operacional.

Para verificar uma cópia baixada, usar o script de um checkout confiável na
SHA da candidata, sem extrair ou executar código do download ainda não verificado.
`PACKET_DIR` aponta para os cinco arquivos, sem extrair os TARs; definir
`EXPECTED_CANDIDATE_SHA` a partir do commit confirmado na PR/CI no GitHub,
nunca a partir do próprio manifesto recebido. Quando disponível, fornecer
`EXPECTED_MANIFEST_SHA256` a partir do resumo da CI exata, obtido por canal
confiável; esse argumento é opcional no CLI e fixa os bytes do manifesto:

```bash
python3 infra/n8n-private/homologation/operator_packet.py verify \
  "$PACKET_DIR" --expected-candidate-sha "$EXPECTED_CANDIDATE_SHA" \
  --expected-manifest-sha256 "$EXPECTED_MANIFEST_SHA256"
```

O verificador é offline, sem Docker, SQL ou rede. Confere hashes, bytes, tipos e
caminhos das entradas e suas árvores Git contra os commits públicos incluídos.
O `create` confere também os ancestrais exigidos, incluindo #482, #481 e #479, no
checkout da CI; a verificação offline não reivindica revalidar o histórico
Git completo. O manifesto e as observações usam os schemas V2.

Para os imports owner, a CI consulta os objetos Git originais no checkout
`_owner_components_pinned`, fixado na P68, e confere commit→caminho→modo→blob.
O arquivo candidato contém os 14 componentes finais, cujos modos e blobs são
verificados offline contra o mapa fechado. Os três arquivos públicos de
fontes continuam sendo candidata, CRM e adapter histórico: **não incluem uma
árvore histórica P68 separada**. Portanto a associação original às fontes
P67/P68 é verificada na CI e não revalidada offline
(`import_origins_verified_offline=false`).

O artefato contém arquivos de fontes públicas reproduzíveis e um manifesto
SHA-256 com commits/árvores, vínculo ao run e imagens observadas. Não inclui
estado dos bancos, dumps, volumes ou material privado de execução — chaves,
certificados, senhas, cookies e sessões — nem relatórios brutos do ambiente.
Não inclui imagens Docker.

A reprodutibilidade refere-se aos bytes dos arquivos de fontes para os mesmos
objetos Git. **Os builds de contêiner não são reprodutíveis bit a bit:** os
Dockerfiles originais preservam bases como `php:8.4-fpm-bookworm` e instalações
de pacotes mutáveis. O manifesto distingue referências de registry por digest
de n8n/PG17/PG16/nginx dos IDs locais `agents_php`, `crm_php` e `owner_php`
observados nos builds PHP. Um ID local não é digest de imagem distribuída
nem publicação em registry. Hashes não
constituem assinatura do operador, análise de vulnerabilidades ou aprovação.

Manifesto e `SHA256SUMS` podem ser substituídos juntos. A conferência contra a
HEAD obtida por fonte confiável valida a identidade completa das fontes Git;
não autentica os status de CI ou imagens de registry declarados no relatório
nem substitui a aprovação do operador. Um digest externo confiável do manifesto
permite conferir que seus bytes correspondem ao artefato indicado pela CI;
o verificador não consulta o GitHub, não reexecuta os cenários nem autentica
por si mesmo a procedência desse digest.

## O que a CI desta candidata deve distinguir

- A cadeia de eventos e retornos usa os harnesses existentes: captura mTLS,
  tarefa autorizada, fila P6, execução nativa, ledger P26, ACK e histórico CRM.
  `PREPARED` representa preparo técnico; não altera comercialmente o lead.
- As recuperações existentes comparam estado e revogações em recursos
  descartáveis. O pacote público de fontes não contém esses backups nem
  transforma restore dentro do runner em recuperação externa operacional.
- P30 usa **outro Stack descartável**, com banco/conta novos. A prova de MFA
  não é executada sobre o n8n restaurado e não prova recuperação de identidade
  humana. O driver permanece separado dos ensaios de workflow/restore.
- MFA individual e revogação de sessões passaram na P30 histórica. Na imagem
  sem entitlement, o enforcement global efetivo e persistido foi `false`,
  mesmo após resposta HTTP 200 ao pedido de ativação. O controle obrigatório
  para todos os operadores permanece pendente; ver [MFA-P30](MFA-P30.md).
- P67 permite criar e consultar rascunhos inertes. A consulta não altera dados
  de negócio, mas grava nonce e auditoria técnica. Não autoriza execução owner
  nem verifica uma sessão humana no CRM.
- P68 recupera seis conjuntos de dados e nove funções do laboratório owner
  em outro cluster PG16. A autoridade atual provém da fixture de origem ainda
  viva e é aplicada antes do login. O limite é o snapshot exportado; não há
  prova de RPO zero, desastre independente, backup externo ou restore
  distribuído entre owner, CRM e n8n.

Os resultados da nova HEAD devem ser registrados a partir do run terminal.
Não somar ensaios sobrepostos nem atribuir todos os workflows executados pelas
regressões à contagem de uma única prova de retorno.

## Handoff operacional: evidência atual e próxima ação

| Tema | Evidência disponível e próxima ação |
| --- | --- |
| Host | [Inventário real por console em 16/11](https://github.com/rafhael100/imagine-ai-agents/issues/414#issuecomment-6925460120): oito CPUs, oito Compose e 18 contêineres naquele instante. É histórico; o handoff da #482 registrou o portal Leaseweb exigindo login. Retomar por autenticação legítima e atualizar a coleta somente leitura. |
| Coexistência | Preservar todos os projetos e listeners, inclusive CRM 18081/18086 e Sites 18090. A porta 17990 foi observada livre em 16/11, **não reservada**. Confirmar recursos, portas, redes, volumes e egress atuais antes de propor instalação. |
| Acesso e autoridade | Definir contas nominais, ingresso privado com MFA obrigatório, revogação e ausência de bypass; conferir grants por empresa/operação, origem de autoridade e custódia de PKI/chaves reais. Nenhuma fixture fornece essas concessões. |
| Licença | Manter integrações técnicas fixas da Imagine e regras comerciais no CRM. Rever o recorte real em [LICENSE-P20](LICENSE-P20.md); nenhum entitlement ou gasto novo é aprovado por este pacote. |
| Backup externo | Há registro de piloto histórico Restic/R2 e da baseline INF-DEV-03; não declarar R2 inexistente. Identificar o destino/prefixo já aprovado, custódia, quota e recibo externo próprios deste pacote. Restore operacional desde essa cópia ainda precisa ser demonstrado. |
| Instalação | Consolidar fontes/imagens/configuração, migrations próprias, janela, monitoramento, rollback e aceite gerencial do risco residual. Não executar bootstrap/cleanup de CI na VPS. Plano exato e autorização de instalação permanecem pendentes. |

Reutilizar [PLAN-P20](PLAN-P20.md), [RECOVERY-P20](RECOVERY-P20.md),
[RECOVERY-P28](RECOVERY-P28.md), `material_preflight.py`,
`../readonly_inventory.py` e `../assess_readonly_host.py`. São instrumentos
candidatos, não instaladores. O inventário não lê env/segredos; salvar sua
saída somente no canal privado aprovado. A avaliação continua NO-GO e não
autentica o host. O preflight de materiais só deve ler material autorizado em
ambiente protegido e não substitui custódia ou revogação efetiva. Não copiar
checklists OP-01 antigos como se fossem o estado atual desta integração.

A [baseline INF-DEV-03 preservada na candidata Portal #927](https://github.com/rafhael100/imagine-portal/blob/86c9f74e890d5d2ae254c63b65dee36af3a58e7f/apps/portal/deploy/phase6/RELEASE-GATES.md)
é Restic/R2 Standard UE, backup diário com retenção inicial de 30 dias,
verificação semanal, restauração mensal e simulação trimestral de desastre.
Não substituí-la por 7 cópias diárias/4 semanais. O piloto histórico não
comprova cópia externa deste pacote, custódia atual, RPO/RTO medidos ou autorização para
agendamento/prune. Os procedimentos P20 devem ser lidos com essa decisão
posterior e com o escopo atual de recuperação.

## Revisão, decisão e escopo preservado

Conduzir revisão interna cruzada com apoio de IA e decisão final da Direção,
conforme [Portal #58](https://github.com/rafhael100/imagine-portal/issues/58)
e [registro da Direção no CRM](https://github.com/rafhael100/imagine-crm/issues/27#issuecomment-6731873469).
Não criar requisito de contratar consultoria externa, nem apresentar revisão
por agentes como auditoria externa. Registrar achados, evidências e riscos
aceitos; a aprovação para continuar o desenvolvimento não fornece evidência
de host, backup ou autoridade ainda não verificada. Gates executáveis de
outros produtos não são modificados por este handoff.

Os componentes selecionados de [P67 #476](https://github.com/rafhael100/imagine-ai-agents/pull/476)
e [P68 #477](https://github.com/rafhael100/imagine-ai-agents/pull/477), incorporados
na #482, permanecem nas fontes da candidata. Suas provas continuam separadas
da jornada de sessão do [CRM #664](https://github.com/rafhael100/imagine-crm/pull/664).
A prova nativa usa o perfil de 33 tabelas; a prova owner+monitor de 35 tabelas
e as migrations incrementais pertencem à CI do CRM. Atualizar as fontes do
pacote não equivale a executar essa jornada de sessão nos Agents ou a recuperar
os três serviços em um snapshot distribuído.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-GO operacional.** Fontes públicas e
evidência de CI não concedem acesso a clientes, instalação, operação de
serviços, contratação, IA paga ou alteração dos gates da [issue 414](https://github.com/rafhael100/imagine-ai-agents/issues/414).
