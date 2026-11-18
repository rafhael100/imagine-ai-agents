# AIA-P63 — Detalhe verificável da proposta

18/11/2026. Continuação da fila P62, em PostgreSQL16 e HTTP privados de CI.

Base: [P62 / PR #465](https://github.com/rafhael100/imagine-ai-agents/pull/465),
SHA `bb1a0e5a348ec68fde6a249f4e081750bc73aeca`, com
[RC73 aprovada](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203592593).
Essa evidência verifica a base; os resultados P63 dependem da nova CI.

## Comportamento para revisão

Com `AIA_P63_PROPOSAL_DETAIL_LAB=true`, a fila oferece **Ver condição e
insumos**. O detalhe mostra a comparação completa, o valor observado, o
limiar, as entradas da simulação e a revisão do ruleset. Por exemplo, três
propostas com valor e limiar iguais a 12 distinguem `12 ≥ 12`, `12 ≤ 12` e
`12 = 12`. O operador vem da regra original reavaliada; os números sozinhos
não permitem deduzi-lo.

A autoria sintética e os pareceres continuam sendo verificados por
P59/P61/P62. Pareceres revogados ou expirados permanecem indisponíveis como
estado atual, mesmo quando a condição da proposta pode ser reproduzida.
Nenhum parecer se transforma em aprovação, tarefa CRM ou execução n8n.

Lotes antigos exibem **Entradas originais indisponíveis**. Não há
reconstrução por suposição, preenchimento com uma fixture nova ou reparo na
leitura. Seleções de outro tenant, propostas ausentes e dados inconsistentes
recebem a mesma recusa genérica, sem uma página parcial.

## Persistência compatível

`PgDeterministicDraftRegistryLab::createTables()` acrescenta três colunas a
`aia_p51_lab.preview_batches`: `command_canonical`, `ruleset_canonical`
(TEXT) e `inputs_version`. O upgrade mantém registros existentes na versão
0, com ambos os textos nulos. Novos lotes recebem versão 1 e os dois textos
no mesmo INSERT e na mesma transação que os rascunhos, inclusive quando não
há regras correspondentes.

Os textos preservam os bytes, a ordem das chaves e a ordem das regras usados
pelos contratos P48/P49. O envelope canônico completo é limitado a 8192
bytes; uma constraint também exige o par completo e limita seu tamanho no
banco. O marcador distingue legado de perda de insumos capturados. Replay
idêntico não escreve nem preenche registros antigos. Replay com insumos
capturados alterados é recusado.

Não mudam `proposal_ref`, fingerprint P52, hashes anteriores, assinaturas,
contratos fechados P53/P60 ou os métodos de pareceres. Esta é a migração da
fixture descartável de CI, não uma migração operacional do CRM.

## Leitura no mesmo snapshot

`PgSignedReviewQueueBridgeLab::read($tenant, $proposal = null)` mantém o
retorno P62 original quando não há seleção. Ao selecionar uma proposta,
chama `PgVerifiableProposalDetailLab::readInsideReadOnlySnapshot()` antes
de confirmar a transação `REPEATABLE READ, READ ONLY` já existente. A
conexão continua sendo o leitor de tenant com SELECT apenas e FORCE RLS
nas mesmas cinco tabelas.

O leitor de detalhe verifica os hashes dos textos, exige JSON canônico,
reavalia o `DeterministicRuleEngine` original e confere escopo, revisão,
hash do resultado e contagem. Compara o conjunto completo e ordenado de
rascunhos do lote, incluindo os fingerprints P52, antes de projetar a regra
selecionada. Alterar outro rascunho do mesmo lote também impede confirmar
o detalhe como reproduzido.

O campo adicional `detail` tem schema
`AIA_P63_VERIFIABLE_PROPOSAL_DETAIL_V1`, estado `VERIFIED` ou
`ORIGINAL_INPUTS_UNAVAILABLE`, identidade da proposta e explicação quando
disponível. `can_approve`, `can_execute` e `operational_authorization`
permanecem falsos. Autores, chaves, assinaturas e JSON canônico bruto não
são renderizados na página.

A rota continua `examples/p56-live-pg-review-local.php`, GET com
`proposal=SYNTHETIC_PROPOSAL_...` e filtro `kind` opcional. A seleção nunca
aceita empresa enviada pelo navegador. Sem o opt-in P63, o comportamento
de entrada e a fila existentes são preservados.

## Prova funcional focada

`tools/check_p63_verifiable_proposal_detail.py` reutiliza `ReviewLab` da
P62: cluster PG16 descartável, containers isolados, página PHP somente
leitura e cliente HTTP sem credencial de banco. O probe próprio prepara:

- Upgrade do formato P51 anterior, com cinco lotes preservados como legado
  e replay sem preenchimento retroativo.
- Um lote com três condições `gte/lte/eq` sobre os mesmos valores, lidas
  por novas conexões e exibidas de forma distinta por HTTP.
- Lote sem propostas com insumos preservados e falha na segunda inserção
  de rascunho que reverte também o lote e seus textos.
- Alteração dos insumos e alteração de outro rascunho, ambas recusadas sem
  reparo; os limites de tamanho e presença do par são aplicados no banco.
- Alteração concorrente entre a leitura da fila e a do detalhe: a consulta
  em curso mantém o snapshot anterior completo e a seguinte recusa o dado
  alterado. Não há callback de teste no código da aplicação.
- Isolamento de tenant, legado explícito, parecer de recusa visível e
  estado atual indisponível após revogação, preservando as linhas nas leituras.

Não há novos workflows n8n, APIs pagas, credenciais humanas, publicação de
portas, escrita CRM ou identidade operacional. Os insumos e hashes locais
continuam sendo evidência do laboratório; não constituem trilha externa
imutável nem consentimento de uma pessoa real. O runtime PHP/PG/HTTP deve
ser confirmado pela CI da candidata antes de registrar o resultado como
verificado.
