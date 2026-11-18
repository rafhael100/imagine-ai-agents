# P66 / RC77 — identidade Imagine na criação, fila e detalhe

## Necessidade e autorização

A [issue #289](https://github.com/rafhael100/imagine-ai-agents/issues/289) aprovou a aplicação do Design System Imagine e do Experience System às interfaces humanas. A migração anterior cobria F108; P64 e P56/P63 ainda usavam marca tipográfica e cores distintas, e sua prova HTTP não executava um motor de navegador.

Esta candidata aplica a identidade aprovada à criação de rascunhos P64, à fila P56/P62 e ao detalhe P63. Usa as funções existentes: preencher indicadores fictícios, ver a prévia, guardar rascunhos, filtrar a fila e consultar o detalhe verificado. Não acrescenta aprovação, execução, privilégio, integração ou regra de negócio.

Base: [RC76, PR #473](https://github.com/rafhael100/imagine-ai-agents/pull/473), `bd50ab49113e775490cb55d76b088731ec108cc9`. A candidata preserva o consumidor P65 e a composição com a Central P30 `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8`.

A base passou na [CI 44217619259](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44217619259), job `134511282870`, executando a árvore combinada `cd4b6fba353a20f02b00facec4614be4f052a18f`. O consumidor original manteve 6 casos positivos/85 negativos junto de toda a matriz histórica.

## Fonte visual

| Referência | Identificação imutável |
|---|---|
| Portal, proposta canônica [#589](https://github.com/rafhael100/imagine-portal/pull/589) | `28ab9f66dff640af7bf8ddae91eaa939135e2988` |
| `design-system/tokens.css` | Git blob `b1fc205e272b5fb79b774741c38e7928aadc0446` |
| `design-system/experience-system.md` | Git blob `15e9c58c14dad6ad2388df60cea1ae0af6b44d17` |
| PNG original já presente em Agents | Git blob `678adeceb3a8ecdaeb6b576b9ee18d1bf0396f77` |
| Bytes do PNG 200 × 55 | SHA256 `009e7096173ec354acc5e702f5f0b2609a8b2e34a860d31b252fbb3dc6d0db58` |

O logo é embutido sem transformação. Os aliases locais usam laranja `#F9941D`, grafite `#171B19`, branco e fundo `#F7F7F8`, além dos tokens auxiliares aprovados. O helper de apresentação é local e não copia backend de outro projeto.

O cabeçalho identifica o produto e o ambiente de teste. A navegação destaca a página atual e aponta apenas para funções implementadas. P56 utilizado sem a composição P64 não oferece um link de criação indisponível. Skip link, foco visível, rótulos e áreas de toque permitem navegar por teclado e em telas estreitas.

## Fronteiras preservadas

As duas páginas mantêm suas condições de admissão, tratamento de entrada, contexto sintético, transações, CSRF, idempotência, autoria e leitura. O escritor continua em processo e role separados do leitor. Nenhum serviço de domínio, motor, schema SQL ou autenticação é modificado.

A única ampliação da CSP é `img-src data:` para o PNG embutido. Scripts, frames, fontes remotas e recursos externos continuam proibidos. Os scanners conferem exatamente o logo original e os destinos de navegação esperados; não passam a aceitar imagens ou links arbitrários. A matriz P62 comprova a composição sem criação, enquanto P64 e P66 comprovam a navegação com ela habilitada.

## Prova do navegador

O ensaio importa `CreationLab` e prepara um PostgreSQL descartável com zero lotes, rascunhos, autorias e votos. O Chromium usa a imagem oficial Playwright 1.56.1 e o pacote da mesma versão, ambos preparados antes do laboratório. A imagem efetiva é resolvida por ID/digest e conferida contra o tag esperado.

O cliente compartilha somente o namespace de rede do cluster isolado, sem portas publicadas ou egress. Não recebe arquivos de senha, DSN, segredos ou acesso ao socket Docker. Fonte e dependências são montadas somente para leitura; apenas o diretório de capturas e os temporários do navegador podem ser gravados.

A jornada real percorre formulário, prévia, gravação, fila, filtro e detalhe. Checkpoints do navegador permitem ao orquestrador verificar o banco antes de prosseguir: prévia sem alteração; gravação de um lote com dois rascunhos e duas autorias; navegação de consulta sem nova mutação. A página de detalhe precisa corresponder aos insumos e à condição originais verificados pelo domínio.

O ensaio cobre desktop, tablet, celular, teclado, foco, logo carregado, ausência de overflow e reflow equivalente a 200%. A ampliação emulada é identificada como tal; não se afirma execução de zoom nativo se o navegador não o fizer.

## Comparação visual reproduzível

As capturas `after` mostram as três telas atuais em desktop e celular. Para a referência `before`, o teste encerra somente os dois servidores próprios, preserva o PostgreSQL e suas linhas e inicia os mesmos processos/roles com overlays somente leitura dos dois arquivos PHP da base RC76, verificados por blob. Um segundo navegador limitado a GET captura as três telas originais sobre os mesmos rascunhos. Estatísticas e fingerprint do banco precisam permanecer idênticos.

Assim, `before` e `after` identificam a versão do código; a captura da versão anterior ocorre depois da jornada no relógio. O teste não recria dados, não fabrica HTML e não repete a gravação para produzir a comparação. São previstas nove capturas: três de referência desktop e seis atuais desktop/celular. Tablet e reflow são verificados sem multiplicar imagens.

## Composição RC77 e resultados

O novo runner valida o evento pull request real, repositório público, base RC76, branch, HEAD, conjunto exato dos treze arquivos alterados e árvore virtual. Preserva `GITHUB_SHA` e `GITHUB_EVENT_NAME`. Todos os comandos RC76 e pins de Central, replay e n8n continuam presentes; P65 permanece literal e executa na mesma árvore. Os novos arquivos de UI/prova também precisam sobreviver à composição byte a byte.

O preflight termina antes de preparar imagens. A execução completa roda a matriz preservada, lint do helper e a prova Chromium. O workflow mantém apenas PNGs sem credenciais por sete dias; resultados, revisão executada, árvore combinada e capturas inspecionadas ficam registrados na PR da SHA efetivamente validada. Este documento não antecipa sucesso de runtime nem aceite humano.

Como evidência independente do recorte OP-01 humano, [CRM #649](https://github.com/rafhael100/imagine-crm/pull/649) passou na SHA `810f6815f4d8dc35ba46dbe7accf78c6b63b7dc1`, e o [resumo CRM #651](https://github.com/rafhael100/imagine-crm/pull/651) passou na SHA `d3f06599cddfc244f6089d3dac01f29e5a45b8c0`: 68 leituras HTTP, 247 verificações Chromium, três idiomas e regressão privada completa. Isso não converte a UI P66 em sessão CRM nem em delegação de serviço.

## Limites

A entrega atende às três telas nomeadas; não declara migração de todas as interfaces do projeto. Permanecem o ambiente sintético, o NO-GO operacional, a revisão humana pendente e os requisitos de identidade/delegação, MFA administrativo obrigatório, custódia de backup externo e implantação. Não há merge em main/DEV, VPS, clientes, chamadas pagas ou contratação.
