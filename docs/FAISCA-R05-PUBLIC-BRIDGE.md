# Faísca R05 — ponte pública sintética entre Node e PHP

18/11/2026, America/Sao_Paulo. Base Agents P67 [#476](https://github.com/rafhael100/imagine-ai-agents/pull/476), SHA `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`. Branch de revisão: `feat/aia-faisca-r05-public-bridge-2026-11-18`.

## Resultado e responsabilidade

Esta candidata acrescenta uma fronteira executável entre o servidor Node do laboratório Faísca e um processo PHP do Imagine AI Agents no mesmo computador. O PHP executa os componentes existentes de conhecimento e coordenação, produz uma sugestão sintética e devolve um recibo correlacionado. O resultado precisa ser validado pelo consumidor antes de liberar o candidato editorial do Comercial.

**O texto final da conversa continua sendo editorial do Comercial.** O PHP recebe somente hashes, identificadores opacos e referências curadas. Não recebe texto livre, candidato em linguagem humana, token da sessão HTTP, identidade de cliente ou pedido de ferramenta. O provedor PHP continua sendo `MockAiSuggestionProvider`; sua saída fixa não representa um modelo de IA operacional.

A aprovação de continuidade R05 autoriza implementação, testes sintéticos e publicação em branches/PRs de revisão. Esta ponte não altera o receiver Central, o CRM, contratos OP-01, grants, migrações, regras de negócio ou gates existentes. Não há merge, deploy, instalação na VPS, dados de clientes, chamada a modelo pago ou efeito operacional.

## Caminho executado

O transporte do browser termina no servidor loopback Node já existente. O Node cria um processo PHP com caminho e configuração confiáveis, escreve um único JSON compacto em stdin e fecha o pipe. O PHP não abre listener HTTP, socket, conexão de banco ou processo filho.

`PublicConversationBridge` executa a seguinte sequência usando as classes originais do Agents:

1. Confere a política fixa e o schema fechado da invocação.
2. Cria um `TenantContext` exclusivamente sintético e uma fonte Faísca em `MockKnowledgeCatalog`.
3. Usa `MockKnowledgeRetriever::select` e `MockContextEnvelope::deliver`, com um `MockRetrievalGrant` explícito, para recuperar uma passagem marcada `UNTRUSTED_DATA`.
4. Usa `SupervisedCoordinator::start`, `step` e `suggest` com `MockAiSuggestionProvider`. O provider recebe apenas os campos sintéticos `ref`, `stage` e `text`, preservando o limite original de 256 bytes por campo.
5. Valida integralmente `kind`, `version`, `run`, `source` e o texto fixo da sugestão.
6. Na entrega, repete `deliver` para revalidar grant, versão, digest e validade. Somente então faz `transition(..., 'CONCLUIDA')` e monta o recibo.

A passagem representa um digest das referências públicas admitidas. É uma fixture Faísca criada explicitamente; não é um documento comercial recuperado nem uma projeção de lead CRM. Existe uma passagem agregada para o conjunto admitido, preservando o máximo de três passagens do retriever original. O código não amplia esse limite para acomodar os quatro IDs possíveis do catálogo público.

O contexto usa produto `SYNTHETIC_FAISCA_COMERCIAL`, tenant `SYNTHETIC_FAISCA_PUBLIC`, principal `SYNTHETIC_FAISCA_LAB_HOST`, finalidade `SYNTHETIC_FAISCA_PUBLIC_GUIDANCE` e recurso `SYNTHETIC_FAISCA_PUBLIC`. Esses valores são fixos do componente. Conversa e mensagem nunca se tornam tenant, usuário ou permissão.

`DenyOnlyEffectGateway` implementa o contrato exigido pelo coordenador e recusa tanto `submit` quanto `status`. Não contém adaptador CRM. O sucesso exige zero tentativas nesse gateway e exatamente os quatro eventos nativos `STARTED`, `STEP_CREATED`, `SUGGESTED`, `RUN_CHANGED`. Os contadores do recibo são derivados dessa execução, incluindo a passagem efetivamente entregue.

## Política fixa

`contracts/faisca/public-context-v1.json` foi copiado integralmente da política pública preparada para o Comercial. O componente exige o SHA-256 dos bytes do arquivo:

`1ae54bb9f1b8113ca4444c27a229d0359d9181290a5c788daa8c6854f12163ec`

O contexto admitido é:

```json
{
  "scope": "public_comercial",
  "knowledgeVersion": "faisca-editorial-2",
  "knowledgeDigest": "fe7daf5ab310acc5a0a600803254344ed02eff7bbd860b731cdf033dcbe4ead7"
}
```

`knowledgeDigest` identifica os bytes integrais de `ops/faisca/knowledge.mjs` do Comercial usados na revisão. A política possui quatro IDs de fontes e 25 IDs de ações permitidos. A requisição pode indicar subconjuntos sem duplicatas: zero a quatro fontes e zero a seis ações. Uma referência curada identifica conteúdo; não concede autoridade operacional. Alterar o arquivo ou seus valores exige revisão e atualização explícita do pin; um valor recebido no pipe não pode atualizar a política.

## Contrato stdin

Uma única mensagem JSON UTF-8 compacta, seguida de EOF, com no máximo 16.384 bytes. Não incluir newline, BOM, whitespace adicional ou JSON concatenado. A comparação com a recodificação canônica recusa nomes duplicados e escapes alternativos; a ordem das propriedades pode seguir o emissor.

Chaves exatas do objeto:

| Campo | Forma |
|---|---|
| `schema` | `faisca.agents.request.v1` |
| `invocationId` | 32 caracteres hexadecimais minúsculos |
| `requestFingerprint` | 64 caracteres hexadecimais minúsculos |
| `candidateDigest` | 64 caracteres hexadecimais minúsculos |
| `conversationId`, `messageId` | 8–96 caracteres ASCII de `A-Z a-z 0-9 _ -` |
| `locale` | `pt-BR` |
| `context` | Exatamente `scope`, `knowledgeVersion`, `knowledgeDigest`, `sourceIds`, `actionIds` |

Os três primeiros valores de `context` devem corresponder à política fixa. As duas listas devem ser subconjuntos das allowlists correspondentes. Campos extras, inclusive texto, credencial, endpoint, provider, cenário, empresa ou usuário, são recusados.

## Contrato stdout

Sucesso: exit code `0`, um JSON compacto e um LF. Chaves exatas: `schema`, as cinco referências de correlação, `locale`, `context`, `suggestion`, `evidence`, `operationalEffects`.

`schema` é `faisca.agents.result.v1`. O objeto `context` contém somente os três valores fixos acima. Os objetos finais têm a forma:

```json
{
  "suggestion": {
    "kind": "SYNTHETIC_SUGGESTION",
    "version": "mock-v1",
    "run": "SYNTHETIC_<INVOCATION_ID_EM_MAIUSCULAS>",
    "source": "SYNTHETIC_FAISCA_PUBLIC",
    "text": "SYNTHETIC summary proposal only"
  },
  "evidence": {
    "runState": "CONCLUIDA",
    "suggestions": 1,
    "effects": 0,
    "knowledgePassages": 1
  },
  "operationalEffects": false
}
```

O consumidor deve comparar integralmente correlação, hashes, contexto, sugestão e evidências antes de usar o recibo. O retorno confirma execução sintética do PHP sobre os metadados vinculados. Não prova que o conteúdo editorial é verdadeiro, que o usuário está autenticado ou que uma ação comercial foi realizada.

Negação controlada: exit code `2`, um JSON compacto e um LF, sem stack, texto da entrada ou exceção do provider:

```json
{"schema":"faisca.agents.error.v1","code":"invalid_request"}
```

| Código | Situação |
|---|---|
| `invalid_request` | JSON, schema raiz, correlação ou argumentos de CLI inválidos |
| `context_denied` | Política, escopo, versão, digest ou revalidação de contexto recusados |
| `source_denied` | Listas de fontes/ações inválidas, excessivas, repetidas ou fora da política |
| `invalid_suggestion` | Callback recusado ou sugestão incompatível com o contrato original |
| `lab_disabled` | Flag explícita ausente ou diferente de `1` |
| `unavailable` | Configuração confiável inválida ou indisponibilidade controlada |

## Ativação e falhas de laboratório

O comando é `php tools/faisca_public_bridge.php`, sem argumentos. A ativação exige `IMAGINE_FAISCA_AGENTS_LAB=1`. A política é lida do caminho fixo versionado. O CLI possui lista literal de 17 imports PHP; junto do CLI e da política, são 19 arquivos necessários ao pin do consumidor.

Os cenários vêm somente do ambiente confiável do processo:

| `IMAGINE_FAISCA_AGENTS_SCENARIO` | Comportamento |
|---|---|
| `success` | Executa o ciclo e entrega o recibo |
| `delay` | Executa a sugestão, espera e revalida antes de entregar |
| `unavailable` | Retorna negação controlada antes da sugestão |
| `malformed` | Executa o ciclo real e produz JSON truncado intencionalmente, exit `0` |
| `exit_after_dispatch` | Executa a sugestão real e encerra sem stdout, exit `3` |
| `revoke_before_delivery` | Revoga o grant mock após a sugestão; a nova entrega é recusada |

`IMAGINE_FAISCA_AGENTS_DELAY_MS` aceita decimal canônico entre `0` e `2000`; atua no cenário `delay`, cujo padrão é `800` ms. A validade do contexto de cada invocação é de 30 segundos e não se renova. Os cenários de saída quebrada são contraprovas explícitas, não respostas válidas do protocolo.

O PHP é um worker de uma invocação e não guarda um ledger entre processos. Deduplicação, resultado incerto e consulta manual continuam no runtime Node. Consulta de um resultado pendente não deve criar outro processo PHP. Encerrar um processo ou perder stdout não demonstra que a sugestão nunca executou. Nenhuma dessas observações prova custo zero de um futuro modelo; nesta candidata não existe conexão a modelo.

## Arquivos e verificação

| Caminho | Responsabilidade |
|---|---|
| `src/Integration/Faisca/PublicConversationBridge.php` | Validação pura, política pinada, conhecimento, sugestão e entrega |
| `src/Integration/Faisca/DenyOnlyEffectGateway.php` | Recusa explícita de efeitos e consulta CRM |
| `tools/faisca_public_bridge.php` | I/O limitado, ativação, imports fixos e cenários confiáveis |
| `tools/check_faisca_public_bridge.php` | Testes diretos e subprocessos PHP reais, sem dependências externas |
| `contracts/faisca/public-context-v1.json` | Política pública imutável desta revisão |

O teste cobre o resultado e contadores reais, correlação, limites de IDs, listas vazias/completas, todas as ações curadas, schema/canonicalização, duplicatas, autoridade extra, versão/hash/fontes, negação antes do provider, tentativa de mutação dentro do callback, saída com recurso/Run/texto indevidos, revogação de grant e fonte, expiração, relógio regressivo, gateway deny-only e os seis cenários do CLI.

```bash
php -l tools/faisca_public_bridge.php
php -l tools/check_faisca_public_bridge.php
php tools/check_faisca_public_bridge.php
php tools/check_synthetic_php_isolation.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=131 EXPECTED_CASES=879 bash tools/run_r01_offline_quality_gate.sh
```

O inventário da candidata contém 131 PHP em `src`, `tests/Unit` e `examples`: 129 da P67 e dois novos. Os testes R05 ficam separados dos 879 casos legados. O gate R01 e o scanner permanecem intactos. A CI deve exigir PHP real e registrar o resultado na SHA exata; ausência do interpretador não conta como teste aprovado. Resultados executados e links da CI pertencem à PR correspondente.

Não é necessário chamar runners PostgreSQL, Central, n8n, composição entre branches ou scripts de migração. A ponte usa apenas CPU e memória locais, sem banco, credenciais, serviços externos ou instalação operacional.
