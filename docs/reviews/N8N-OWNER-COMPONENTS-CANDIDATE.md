# Candidata dos providers nativo e de rascunhos owner — 18/11/2026

## Objetivo e base

A branch `integrate/n8n-owner-provider-components-2026-11-18` fecha uma
lacuna de fontes: o arquivo público da #481 fornecia o provider nativo,
mas não os nove arquivos do runtime owner P67 nem sua prova P68. Esta
candidata acrescenta os componentes necessários para construir e ensaiar
os dois providers a partir da mesma fonte candidata, preservando imagens,
processos, perfis e bancos distintos.

O commit inicial deve ser filho único da
[#481](https://github.com/rafhael100/imagine-ai-agents/pull/481),
`64f52f0dcd44a5545d1bfc6c1d685b33c158601f`, árvore
`b7c804ca987c99156e1aa9cc01488a9d6c6226b8`.
A [CI 44247275249](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44247275249),
job `134609148933`, terminou com sucesso no attempt 1. Ela permanece
evidência da fonte original; não aprova antecipadamente os imports e a
admissão desta filha. A candidata mantém CRM #660 em
`96dbf1aed2d1cf3237bc3631c4594540a0168d2a`.

A composição é seletiva. Não incorpora a branch P68 inteira, seus incrementos
P62–P66 ou o contrato de projeção P65. Não afirma uma fonte consolidada de
todos os recursos Agents. O histórico Git da candidata e a proveniência de
arquivos importados são relações distintas.

## Componentes selecionados

| Componente | Origem exata | Relação com a candidata |
| --- | --- | --- |
| Rascunhos owner P67 | [#476](https://github.com/rafhael100/imagine-ai-agents/pull/476), `0c722eee6bd53a7ee46c4ba68ab70200fc191b78` | Nove arquivos de runtime, checker e probe selecionados |
| Recuperação owner P68 | [#477](https://github.com/rafhael100/imagine-ai-agents/pull/477), `85f4c37a6d905b91923d3e31ba9068b2c35d7657` | Checker, probe e runner selecionados |
| Provider nativo e integração CRM | #481, `64f52f0dcd44a5545d1bfc6c1d685b33c158601f` | Ancestral efetivo preservado |

Todos os 14 arquivos importados usam modo Git `100644`. A lista é fechada:

| Caminho | Fonte | Tratamento |
| --- | --- | --- |
| `runtime/central-auto/crm-owner-drafts/Dockerfile` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/OwnerDraftContract.php` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/create-fpm.conf` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/create.php` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/endpoint.php` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/nginx.conf` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/read-fpm.conf` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/read.php` | P67 | Literal |
| `runtime/central-auto/crm-owner-drafts/schema.sql` | P67 | Literal |
| `tools/check_p67_owner_draft_lab.py` | P67 | Extração de API do cenário |
| `tools/p67-owner-draft-probe.php` | P67 | Literal |
| `tools/check_p68_owner_draft_recovery.py` | P68 | Extração de API do cenário |
| `tools/p68-owner-draft-recovery-probe.php` | P68 | Literal |
| `tools/run_p68_owner_draft_recovery.py` | P68 | Literal; dependência do guard histórico |

Os mapas fechados `IMPORTS` no driver integrado e `IMPORTED_COMPONENTS`
no pacote registram modo e blobs original/candidato de cada caminho. Doze
arquivos mantêm os mesmos bytes. As duas adaptações revisadas são:

| Checker | Blob original | Blob candidato |
| --- | --- | --- |
| P67 | `ae872c721c1bd9c07071f9f3e63f82340b38be62` | `5fcaf922e226e8bc82687ca67e9e4b26b9b3d7e2` |
| P68 | `3e094557a83cffec5d5ad3f91335ad97d7ce61ca` | `8b7bc20f0dbef4dd7ac6c14a78a436110ca8d991` |

A API P67 é `exercise(temporary)`; a P68 é
`exercise(temporary, legacy)`. Elas conservam os corpos originais dos
cenários, assertivas e cleanup, retornando o resumo já saneado depois do
PASS. Os wrappers `main` mantêm seus guards históricos e chamam a API.
O novo driver não altera variáveis para aparentar uma execução das branches
originais e não substitui esses guards por aprovações fictícias.

## Dependências preservadas

O driver integrado fixa nove dependências já existentes na #481:

- `infra/n8n-private/check_native_noai_flow.py`;
- `infra/n8n-private/homologation/check_p21_crypto_pg_restore.py`;
- `infra/n8n-private/homologation/check_p24_full_n8n_restore.py`;
- `infra/n8n-private/homologation/check_p27_private_receipt_status.py`;
- `infra/n8n-private/homologation/signed_receipt_p23.py`;
- `infra/n8n-private/homologation/seal_backup.py`;
- `infra/n8n-private/homologation/material_preflight.py`;
- `infra/n8n-private/homologation/p25_agents_recovery.py`;
- `runtime/central-auto/strict-json.php`.

Os blobs dessas dependências devem permanecer iguais aos da base #481.
O JSON estrito usado no Dockerfile owner é o blob
`45867695a8e5268ec19d04ae3193ada11a77c9a9`.

Na comparação com a dependência Central histórica usada pela P67, a diferença
relevante de P27 está limitada a `ReceiptLab.start_http`: o código atual
exige três rotas privadas, enquanto a fonte antiga tratava duas.
`OwnerDraftLab.start_http` sobrescreve integralmente esse método, sem
`super()`; os cenários owner não passam por esse corpo. A revisão conferiu
essa fronteira. Não é necessário importar uma segunda cópia de P27 nem
modificar sua verificação nativa.

## Admissão integrada e imagens

`tools/run_owner_component_candidate.py` fornece os comandos
`preflight`, `p67` e `p68`. Antes de qualquer cenário, exige o evento
push real da branch candidata no repositório público Agents, runner
hospedado, ref e `after` correspondentes à HEAD, checkout limpo e completo,
ancestralidade da #481, árvore real, Docker local e exportação temporária
igual aos objetos Git candidatos.

O workflow mantém um segundo checkout em `_owner_components_pinned`,
fixado no SHA P68. Seus objetos Git permitem conferir os 14 vínculos de
origem e a ancestralidade P67→P68 **nesse checkout de referência**. Isso não
atribui P68 à linhagem da candidata. O driver confere também os blobs
candidatos e as nove dependências, inclusive os módulos carregados.

Há um build PHP owner a partir do Dockerfile P67 literal, com o SHA real da
candidata registrado como label de build. A admissão inspeciona seu ID,
label, Dockerfile e usuário `www-data`; o ID owner deve ser diferente do
build nativo. P67 e P68 recebem esse ID apenas no ambiente de seus
subprocessos. A variável global do build nativo não é substituída.

O manifesto observa `agents_php`, `crm_php` e `owner_php`. Esses IDs
locais não são imagens publicadas nem garantia de builds reproduzíveis bit
a bit. Compartilhar a fonte candidata não exige compartilhar imagem, banco
ou identidade entre os providers.

## Resultados e criação do pacote

Cada subprocesso owner deve emitir exatamente um envelope
`OWNER_PROVIDER_COMPONENT_RESULT_V1`, depois do relatório original e de
seu cleanup. O runner exige:

| Campo | Vínculo exigido |
| --- | --- |
| `component` | P67 ou P68 correspondente ao grupo executado |
| `source_sha` / `source_tree` | HEAD e árvore reais desta CI |
| `run_id` / `run_attempt` | Execução e tentativa atuais |
| `import_source_sha` | Commit fixo da fonte do componente |
| `owner_php_image` | ID local efetivamente inspecionado |
| `result` | Relatório original completo, com campos e valores fechados |
| `go_for_installation` | `false` |
| `separate_from_native_feedback` | `true` |

O pacote valida o envelope antes de aceitar `owner_p67` ou `owner_p68`.
Campos desconhecidos, contagens alteradas e booleanos no lugar de inteiros
são rejeitados; não se descarta silenciosamente um campo antes da validação.
As contagens esperadas são as dos cenários originais preservados, não uma
afirmação de que a CI desta nova candidata já passou.

O runner conserva os 19 grupos da #481 e acrescenta:

1. `owner_provider_admission_contracts`;
2. `owner_draft_contracts`;
3. `owner_draft_recovery`.

Portanto o contrato exige **22 grupos executados**, além de
`source_integrity`, registrado separadamente. `operator_packet_contracts`
já integra os 19 grupos preservados. O artefato só é criado depois de todos
os grupos passarem e de a fonte ser conferida novamente.

Os schemas passam a `N8N_PRIVATE_CANDIDATE_OBSERVATIONS_V2` e
`N8N_PRIVATE_OPERATOR_PACKET_V2`. O artefato
`n8n-owner-components-candidate-${sha}-${attempt}` contém exatamente
`candidate.tar.gz`, `crm.tar.gz`, `adapter.tar.gz`, `manifest.json`
e `SHA256SUMS`. O adapter histórico continua fixado em
`8b04d0e936168c77db028160b5aeb1758db497c4`; ele não é um arquivo da
árvore P68.

`lineage` registra os ancestrais exigidos. `imported_components` registra
os componentes e seus mapas de origem sem alegar ancestralidade. O
verificador offline reconstrói a identidade Git da candidata e confere
cada blob candidato importado. A CI verifica a associação original
commit→caminho→modo→blob. **O pacote não inclui as árvores originais P67/P68
como arquivos independentes e não revalida essa associação offline.**
O campo `import_origins_verified_offline` permanece `false`.

A verificação exige SHA candidato obtido externamente. O argumento opcional
`--expected-manifest-sha256`, quando proveniente do resumo confiável da CI
exata, também fixa os bytes do manifesto. O verificador não consulta o
GitHub nem reexecuta os cenários; checksums isolados não autenticam CI,
imagens ou aprovações. Procedimento completo:
[OPERATOR-PACKET](../../infra/n8n-private/homologation/OPERATOR-PACKET.md).

## Critérios de aceitação e limites

A revisão local deve conferir os 14 pares de blobs, preservação dos corpos e
guards históricos, nove dependências, admissão real, envelopes fechados,
ausência de segredos nos erros e pacote com exatamente três arquivos de
fontes. O teste de pacote inclui a tentativa de mudar um componente e
recalcular árvore, commit, manifesto e checksums: ela continua rejeitada
pelo mapa de componentes revisado. A validação local dos 16 testes de pacote
não substitui os ensaios Docker/PHP/n8n na CI da HEAD publicada.

A execução terminal da CI deve registrar run, job, tentativa, SHA, árvore,
22 resultados e imagens observadas. Deve preservar os relatórios P67/P68 e
a prova nativa, sem somar ensaios sobrepostos. A base #481 passou 19 grupos;
na prova de feedback houve uma execução nativa, seis observações CRM e
cinco modelos do monitor. Outros grupos têm execuções próprias.

O escopo owner permanece criar e consultar rascunhos inertes. Consultas
gravam nonce e auditoria técnica; não modificam o negócio nem autorizam
execução. A P68 restaura seis conjuntos e nove funções do laboratório owner
em dois clusters PG16. Ela usa autoridade atual da fixture de origem ainda
viva, aplica revogações antes do login e falha sem essa autoridade. Seu
limite de durabilidade é o snapshot exportado, sem recuperar commits
posteriores nem comprovar uma fonte de autoridade independente do desastre.

A prova de feedback nativo conserva o perfil CRM de **33 tabelas**. A jornada
humana e a recuperação owner+monitor de **35 tabelas** continuam na
[CI própria do CRM #660](https://github.com/rafhael100/imagine-crm/actions/runs/44246202205).
Esta CI de providers não declara sessão humana CRM, execução owner,
recuperação distribuída, backup externo ou RPO zero. P30 conserva outro
Stack descartável; enforcement global obrigatório de MFA permanece
`false` na evidência disponível.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-GO operacional.** Esta etapa permite
desenvolvimento e revisão em laboratório; não concede acesso a clientes,
operação, instalação em VPS ou contratação.
