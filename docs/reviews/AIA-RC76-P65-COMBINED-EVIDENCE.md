# RC76 — consumidor P65 na candidata combinada

## Objetivo e estado

A suíte P65 valida a projeção mínima retornada pelo CRM. A RC75 reúne as
regressões da Central P30 e das jornadas Agents P62–P64, mas precede o P65.
Uma aprovação isolada do contrato P65 não comprova que seu código original
permanece íntegro e executável na árvore combinada atual.

A RC76 fecha essa lacuna de evidência no recorte já autorizado: leitura mínima
originada em usuário autenticado do CRM, dados fictícios e homologação isolada.
Ela executa o consumidor P65 original junto de toda a matriz RC75, sem alterar
fontes de produto, contratos existentes ou os gates históricos. A execução
completa só pode ser declarada aprovada após o workflow desta candidata terminar
com sucesso no SHA exato; este documento não antecipa um resultado de CI.

## Procedência imutável

| Componente | Origem exigida |
| --- | --- |
| Repositório público | `rafhael100/imagine-ai-agents` |
| Base da PR | `feat/aia-p65-crm-minimal-read-consumer-2026-11-17` |
| Commit P65 e base da PR | `740abafc813dbde70f5a2ad6e138402ed7945276` |
| Branch desta evidência | `test/aia-rc76-op01-combined-evidence-2026-11-17` |
| Central P30, sem atualização do pin | `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8` |
| Base dos invariantes de domínio preservados da RC75 | `6b65a109799df5f34e6431474ba8f3e58cb0fccc` |
| Adaptador histórico de replay preservado | `8b04d0e936168c77db028160b5aeb1758db497c4` |
| Imagem n8n preservada | `ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea` |

O workflow usa `pull_request` para a base acima e faz checkout do `head.sha`
real desse evento. O runner lê `GITHUB_EVENT_PATH`, verifica repositório público,
origem e destino no mesmo repositório, branches e SHA exatos da base, e exige
que `HEAD` corresponda ao `head.sha`. `GITHUB_SHA` continua representando o SHA
de merge fornecido pelo GitHub; não se reescreve essa variável nem o tipo do
evento para acionar um wrapper histórico.

O delta entre o commit P65 e o HEAD da PR deve conter **somente três adições**:

- `tools/run_rc76_p65_virtual_tree.sh`;
- `.github/workflows/aia-rc76-p65-combined.yml`;
- `docs/reviews/AIA-RC76-P65-COMBINED-EVIDENCE.md`.

Qualquer alteração ou remoção de arquivo preexistente, ou outra adição, recusa
o preflight. A evidência atual fica neste registro; `STATUS-ATUAL.md`, a RC75 e
todos os arquivos P64/P65 permanecem literais na base pinada.

O runner calcula a árvore com `git merge-tree`, recusa conflitos e compara os
blobs exigidos pela RC75. Acrescenta comparação entre o commit P65, o HEAD
avaliado e a árvore combinada para os cinco arquivos originais do P65:

- `src/Integration/Crm/MinimalLeadProjection.php`;
- `tools/check_p65_crm_minimal_read.php`;
- `tools/p65-crm-minimal-read-probe.php`;
- `.github/workflows/aia-p65-crm-minimal-read.yml`;
- `docs/reviews/AIA-P65-CRM-MINIMAL-READ-CONSUMER.md`.

Também verifica nessa árvore o runner RC75 e o workflow/documento P64 originais.
O modo `--preflight` termina antes de exportar a árvore ou executar runtimes;
o workflow o executa antes de baixar ou construir imagens. Os mesmos controles
se repetem na execução completa. Não há criação de commit de merge nem avanço
de branch local ou remota; somente objetos Git locais e diretórios temporários.

## Execução na mesma árvore

A árvore é exportada para um diretório descartável do runner GitHub-hosted,
com a mesma limpeza e os mesmos pins da RC75. Os comandos históricos são
mantidos, incluindo:

- verificações de segurança P20/P22/P23/P24 e contrato nativo;
- suítes Agents P48/P49/P50/P53/P60;
- recuperação P25, recuperação de estado P28 incluindo execução nativa P26,
  matriz de recibos P27 preservada pelo gate P29, limites de ingresso P29 e
  autenticação nativa P30;
- revisão assinada P62, detalhe verificável P63 e criação atômica de rascunho
  com autoria P64.

Nesse mesmo diretório, a RC76 acrescenta lint dos três arquivos PHP P65,
`php tools/check_p65_crm_minimal_read.php` e duas chamadas ao probe original.
Uma resposta canônica fictícia com status HTTP 200 é aceita; o mesmo corpo
acompanhado de status HTTP 404 é recusado com código de saída 2. Nenhum código
do consumidor é copiado ou reimplementado pelo wrapper.

O conjunto P65 exercita os cinco estágios permitidos, contrato fechado,
referência esperada e recusas de estruturas, estados, identidade e status
incompatíveis. O probe imprime apenas o resultado de aceitação/recusa. As
chamadas deste job são locais, sem HTTP ao CRM, cookies, credenciais remotas
ou transporte de sessão.

## Evidência produzida e limites

O preflight registra `central`, `p65`, `agents`, `tree` e `event_merge_sha`.
A aprovação completa exige que todas as regressões terminem e apareçam os
marcadores `AIA_RC76_P65_ORIGINAL_PROBE_PASS` e
`AIA_RC76_P65_COMBINED_TREE_PASS`. O resumo da execução identifica os commits
originais e a árvore realmente exportada.

A prova HTTP da autorização no CRM pertence ao harness e ao workflow do CRM,
que fornecem uma resposta da rota privada ao consumidor P65 pinado. Esta RC76
complementa essa prova ao validar a composição Agents/Central; ela não repete
nem substitui a autenticação, associação à empresa, política de leitura,
revogação ou auditoria do CRM. Uma execução verde aqui não comprova uma sessão
HTTP, identidade real do proprietário ou delegação entre serviços.

Permanecem `agents_release=NO_GO`, `central_runtime_dispatch=DISABLED`,
`real_owner_identity=false`, `crm_authority_runtime_verified_in_this_job=false`,
`service_delegation_verified=false`, `external_backup_verified=false` e
`go_for_installation=false`. Não se encerram os gates históricos R01/CORE/SEG/AUT
nem os treze cenários do contrato S2S histórico por causa deste recorte humano.
Não há merge em main/dev, deploy, VPS, produção, clientes reais, chamadas pagas
de IA ou nova contratação.

## Fontes de escopo

- [P65 — consumidor da projeção mínima](AIA-P65-CRM-MINIMAL-READ-CONSUMER.md).
- [P64 — jornada local com autoria](AIA-P64-LOCAL-AUTHORED-DRAFT-JOURNEY.md).
- Runner precedente: `tools/run_rc75_p64_virtual_tree.sh`.
- Workflow precedente: `.github/workflows/aia-p64-local-draft-creation.yml`.
- Novo workflow: `.github/workflows/aia-rc76-p65-combined.yml`.
