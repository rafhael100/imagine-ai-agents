# Faísca R06 — trechos comerciais e vínculo verificável da resposta

Data: 18/11/2026 UTC. Candidata filha da R05 Agents `59d0bae5340c23b37ea1e693e3a1f3915de3c075`. **DRAFT / NO-GO operacional.** O recorte aprovado mantém execução determinística, sem cliente real, modelo externo, custo, merge ou deploy.

## Resultado e fronteira

A R06 seleciona trechos UTF-8 da base comercial pública e devolve seu conteúdo exato, origem, digest e classificação `UNTRUSTED_DATA`. O PHP recompõe a resposta editorial completa e exige que seu digest corresponda ao candidato do Comercial. Uma passagem legítima de outro assunto, ou o hash de uma resposta arbitrária, não satisfaz esse vínculo.

As novas classes são puras, sem I/O: [`PublicPassageCatalog`](../src/Integration/Faisca/PublicPassageCatalog.php) resolve o catálogo pinado; [`GroundedConversationBridge`](../src/Integration/Faisca/GroundedConversationBridge.php) aplica o protocolo e compõe a execução R05. Somente o [CLI separado](../tools/faisca_grounded_bridge.php) lê arquivos fixos, stdin e controles de laboratório.

O [contrato R05](FAISCA-R05-PUBLIC-BRIDGE.md), seus mocks, política, limites e CLI permanecem inalterados. `PublicConversationBridge` ainda executa `MockKnowledgeRetriever`/`MockContextEnvelope` e `SupervisedCoordinator`/`MockAiSuggestionProvider`. O campo `execution.evidence.knowledgePassages=1` descreve exclusivamente o registro sintético agregado dessa execução. Os trechos comerciais recuperados estão em `grounding.passages` e têm contagem separada.

Nenhuma passagem vira instrução, autorização, ferramenta ou identidade. O provider R05 continua recebendo somente `ref`, `stage` e `text` sintéticos. A prosa exibida vem da composição editorial pinada; não há geração por LLM. Correspondência exata com uma receita não é uma avaliação geral de veracidade ou de implicação semântica de linguagem natural.

## Catálogo e procedência

[`contracts/faisca/public-passages-v1.json`](../contracts/faisca/public-passages-v1.json) é a cópia byte a byte de `ops/faisca/grounding/catalog.json` do Comercial: 16.233 bytes, 21 passagens e 11 receitas. O digest normativo consta também como constante PHP:

```text
SHA-256: 205754a9b45e723560276e0f7145b05b1a1d3eb6d87c5dd9e006ce84b9aceed8
schema: faisca.grounding.catalog.v1
version: faisca-grounding-1
scope: public_comercial
repository: rafhael100/imagine-comercial
source commit: 84b4c82d1a7ff77b4ee7212bc877d4639ceb809d
```

A raiz contém, nessa ordem, `schema`, `version`, `scope`, `sourceSnapshot`, `passages`, `answers`. `sourceSnapshot` registra repositório, commit e hashes dos três arquivos de origem. Cada passagem preserva `id`, `title`, `sourcePath`, `selector`, `text`, `digest`, `factKey`, `factValue`; `digest` é SHA-256 do texto UTF-8 exato. Cada receita contém `id`, `passageIds`, `prefix`, `suffix`, `actionIds`.

O pipeline do Comercial deve conferir os seletores contra os arquivos desse snapshot; o PHP Agents confere o catálogo normativo que recebeu do checkout revisado e não busca novamente o repositório Comercial. Alterar o corpo e recalcular o digest da passagem ainda viola o pin do catálogo completo. Hashes fornecem integridade dentro dessa cadeia de revisão, sem criar autorização comercial ou validação externa dos fatos.

| Origem editorial | Identificador amplo usado apenas na composição R05 |
| --- | --- |
| `src/data/services.mjs` | `comercial.services@209bd893:faisca-1` |
| `src/data/commercial-faq.mjs` | `comercial.faq@209bd893:faisca-1` |
| `src/data/commercial-process.mjs` | `comercial.process@209bd893:faisca-1` |

Os IDs amplos mantêm a política R05; não substituem a procedência por passagem da R06. As citações públicas R03 são `{id: "faisca.passage.<passageId>@r06-1", version: "faisca-grounding-1"}`.

## Receitas admitidas

Somente a associação exata de `answerId` com a lista ordenada abaixo é aceita. Ações, abertura e encerramento da resposta também vêm da receita. O host não envia esses textos no wire.

| `answerId` | `passageIds`, em ordem |
| --- | --- |
| `goal_presence` | `onepage.description`, `onepage.choice` |
| `goal_campaign` | `landing.intro` |
| `compare_sites` | `onepage.description`, `landing.difference` |
| `channel_google` | `google.approach`, `google.intent`, `faq.midia` |
| `channel_meta` | `meta.approach`, `faq.midia` |
| `channel_compare` | `google.intent`, `meta.approach`, `faq.midia` |
| `pricing` | `faq.precos` |
| `results` | `faq.resultados` |
| `scope` | `faq.revisoes`, `process.proposta` |
| `materials_help` | `faq.materiais`, `process.aprovacao` |
| `process` | `process.objetivo`, `process.proposta`, `process.continuidade` |

O processo é um recorte declarado de três etapas, conforme a receita. Catálogo disponível não significa que toda passagem seja usada por alguma resposta. Perguntas sem cobertura, ambiguidade e controles de navegação permanecem decisões locais do Comercial; não são transformados em respostas factuais supostamente sustentadas pelo PHP.

`factKey`/`factValue` declaram fatos finitos curados. Duas passagens selecionadas com a mesma chave e valores distintos são recusadas. O helper puro `hasDeclaredConflict` tem contraprova separada do catálogo pinado; não afirma detectar contradições livres em português.

## Protocolo de entrada

Uma invocação aceita um objeto JSON compacto UTF-8 seguido de EOF, sem LF ou espaços adicionais. Nomes duplicados, escapes alternativos e campos extras são recusados por comparação com a serialização canônica. A ordem das chaves do pedido pode seguir o emissor; a ordem do candidato e do vínculo é normativa.

```javascript
{
  schema: 'faisca.grounded.request.v1',
  invocationId: '<32 hex minúsculos>',
  requestFingerprint: '<64 hex minúsculos>',
  candidateDigest: '<64 hex minúsculos>',
  conversationId: '<8..96 caracteres ASCII A-Z a-z 0-9 _ ->',
  messageId: '<mesmo formato>',
  locale: 'pt-BR',
  grounding: {
    catalogVersion: 'faisca-grounding-1',
    catalogDigest: '<SHA-256 normativo acima>',
    answerId: '<receita admitida>',
    passageIds: ['<IDs exatos e ordenados da receita>']
  }
}
```

Não existe campo `context` na entrada R06. O PHP deriva o contexto sintético R05 de sua própria política e dos `sourcePath` das passagens selecionadas, com fontes distintas na ordem de seleção. Nenhum texto do visitante, candidato em texto, token, tenant, endpoint, provedor ou cenário é admitido no wire. `requestFingerprint` continua um identificador de correlação opaco; o PHP não dispõe da mensagem original para recalculá-lo.

O candidato é reconstruído independentemente, com estas chaves e ordem:

```javascript
{
  schema: 'faisca.response.v1', conversationId, messageId, locale: 'pt-BR',
  knowledgeVersion: 'faisca-editorial-2', text,
  sources: passageIds.map(id => ({
    id: 'faisca.passage.' + id + '@r06-1', version: 'faisca-grounding-1'
  })),
  actions: recipe.actionIds.map(id => ({id}))
}
```

`text` é `[prefix, ...passages.map(p => p.title + ':\n' + p.text), suffix]`, retirando somente partes vazias e unindo por `\n\n`. SHA-256 da serialização compacta, com Unicode e barras sem escape, deve coincidir com `candidateDigest` antes da execução R05 e novamente antes da entrega. Alterar texto, citações, ações, conversa ou mensagem invalida esse digest.

## Seleção, revalidação e limites

O catálogo exige seu digest normativo completo, schema fechado, passagens únicas, digest de cada texto, origens admitidas, receitas e ações válidas. A seleção armazena a receita e resolve as próprias passagens. A entrega resolve novamente a seleção e verifica revogação, fontes desabilitadas, prazo, integridade, conflitos finitos, limites e igualdade com o que foi selecionado.

| Camada | Limite preservado ou acrescentado |
| --- | --- |
| Pedido R06 | 16.384 bytes; leitura limitada a 16.385 para detectar excesso |
| Arquivo de catálogo | 32.768 bytes; leitura limitada a 32.769 |
| Entradas no catálogo | Até 32 passagens e 32 receitas; o snapshot atual contém 21 e 11 |
| Passagem comercial | Texto UTF-8 válido de até 512 bytes |
| Seleção comercial | 1 a 3 passagens, até 1.200 bytes de texto agregado |
| Texto composto | Até 3.000 bytes, incluindo títulos e moldura editorial |
| Ações da receita | Até 6 IDs da política R05 |
| Prazo lógico | 30 segundos desde a seleção; retorno do relógio e limite final inclusivo negados |
| Mock legado R05 | `SYNTHETIC_` ASCII, até 240 bytes por fonte e 600 por envelope; sem ampliação |

O limite PHP de texto composto mede bytes UTF-8 e é mais estrito que um limite Node de 3.000 caracteres. O catálogo atual fica abaixo de ambos; qualquer evolução exige nova revisão e atualização coordenada do pin.

As concessões e desabilitações R06 são estado lógico da invocação. Não representam autenticação de cliente, autoridade distribuída de fonte, persistência de revogação ou acesso CRM. Os controles trusted de teste só removem disponibilidade. Após uma falha da entrega, a ponte fecha o contexto e revoga a concessão R05 pendente para terminalizar um Run já preparado em `FALHOU`. Isso não desfaz a sugestão sintética já efetuada.

## Saída e erros

O sucesso retorna JSON compacto e exatamente um LF:

```javascript
{
  schema: 'faisca.grounded.result.v1',
  execution: '<recibo R05 completo e inalterado>',
  grounding: {
    catalogVersion, catalogDigest, answerId, passageIds,
    passages: ['<cada registro completo do catálogo, acrescido de trust: UNTRUSTED_DATA>'],
    text, bindingDigest
  }
}
```

`bindingDigest` é SHA-256 do objeto compacto nesta ordem: `{catalogVersion, catalogDigest, answerId, candidateDigest, passageIds, passageDigests}`. Os digests e IDs seguem a ordem da receita. O consumidor deve revalidar conteúdo, procedência, receita, texto e candidato próprios; comparar somente esse hash ecoado seria insuficiente. Não há `operationalEffects` adicional na raiz; o recibo `execution` já contém `false`.

Erro: `{"schema":"faisca.grounded.error.v1","code":"<código fechado>"}` + LF, exit 2, sem stack, stderr, conteúdo ou identificadores. Códigos: `invalid_request`, `context_denied`, `source_denied`, `invalid_suggestion`, `lab_disabled`, `unavailable`, `grounding_denied`. Framing/identificadores inválidos usam `invalid_request`; falhas de catálogo, receita, digest do candidato, prazo, revogação ou disponibilidade da fonte usam `grounding_denied`.

## CLI e cenários de falha

O CLI não aceita argumentos e exige simultaneamente `IMAGINE_FAISCA_GROUNDED_LAB=1` e `IMAGINE_FAISCA_AGENTS_LAB=1`. Carrega 19 arquivos PHP de origem e dois documentos JSON por caminhos fixos. Com o próprio entrypoint, são 22 arquivos usados pela R06. O pin Comercial conserva ainda o CLI R05 como dependência de compatibilidade: 23 entradas pinadas não significam 23 arquivos executados.

`IMAGINE_FAISCA_AGENTS_SCENARIO` admite `success`, `delay`, `unavailable`, `malformed`, `exit_after_dispatch`, `revoke_before_delivery`, `source_disabled_before_delivery`. `IMAGINE_FAISCA_AGENTS_DELAY_MS` aceita inteiro decimal canônico entre 0 e 2.000; padrão 800 em `delay`, 0 nos demais. O atraso ocorre depois da preparação real. Esses controles pertencem ao ambiente confiável do laboratório, nunca ao visitante.

`unavailable` interrompe antes do preparo. `exit_after_dispatch` sai com código 3 após a sugestão PHP real. `malformed` trunca a saída depois da entrega real. Revogação e fonte desabilitada ocorrem antes da revalidação de entrega. Nenhum desses resultados de transporte permite ao consumidor declarar que a execução anterior foi desfeita, nem o autoriza a repetir automaticamente uma invocação.

## Provas exigidas

[`tools/check_faisca_grounded_bridge.php`](../tools/check_faisca_grounded_bridge.php) exige PHP real e inicia o novo CLI em subprocessos com ambiente mínimo e argv sem shell. Verifica as 11 receitas, resultado exato, 21 passagens do catálogo, origem do registro sintético legado, framing, campos de autoridade proibidos, substituição por passagem legítima de outro assunto, candidato arbitrário com digest recalculado, adulteração de catálogo com digest da passagem recalculado, relógio, revogação, fonte desabilitada, callbacks e falhas reais de transporte.

O vetor fixo `goal_presence`, conversa `synthetic_conversation_01` e mensagem `synthetic_message_01` tem `candidateDigest=b38ca2663b36834914d299c7bf00f9e4e9006ad9fe73e4e14ca9319a78b507a2` e `bindingDigest=be0072c3c34244452888a56db09ce67c03fb2c911a9f98eacd2d653b8c5a66eb`, calculados independentemente para conferência entre linguagens.

```bash
php -l src/Integration/Faisca/PublicPassageCatalog.php
php -l src/Integration/Faisca/GroundedConversationBridge.php
php -l tools/faisca_grounded_bridge.php
php -l tools/check_faisca_grounded_bridge.php
php tools/check_faisca_public_bridge.php
php tools/check_faisca_grounded_bridge.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=133 EXPECTED_CASES=879 bash tools/run_r01_offline_quality_gate.sh
```

O gate legado permanece íntegro. O inventário da candidata sobe de 131 para 133 PHP em `src/tests/Unit/examples`, preservando 879 casos legados. O workflow exige lint, R05, R06 e regressões offline na SHA exata. Implementação e revisão estática não substituem essa execução: PHP está ausente no ambiente local inicial; evidência executada e eventuais correções de CI pertencem à SHA e à PR registradas posteriormente. **Sem skip ou substituto Node para a prova PHP.**
