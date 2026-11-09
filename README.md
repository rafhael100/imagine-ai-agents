# Imagine AI Agents

## FT01/F1-10 — revisão independente sintética (branch em validação)

O recorte F1-10 acrescenta **encaminhamento humano simulado** após a comparação A/B do F1-09. A evidência de avaliação é recomputada e vinculada por SHA-256 a dataset, rubrica, contexto, baseline e candidata. Dois papéis fictícios, Produto e Segurança, precisam reconhecer o pacote com revisores diferentes do autor; mudança de evidência, expiração, revogação, falha crítica ou caso não executado bloqueia o encaminhamento.

**Nenhum reconhecimento autoriza promoção:** `promotion_allowed=false` e `real_authorization=false` em todos os estados. Não existe autenticação, assinatura ou armazenamento persistente. O hash é apenas vínculo de conteúdo, não certificado de identidade.

Comandos de validação sem fornecedores:
```sh
php tests/Unit/run.php
php examples/f110-review-demo.php
# Em checkout Git íntegro do HEAD aprovado:
EXPECTED_SHA=<SHA_EXATO> bash tools/validate_ft01_offline.sh
```

**Evidência executada:** PHP 8.4.24, **111/111 testes PASS**, **52/52 lint PASS**, **52/52 hashes Git blob coincidentes** com a árvore publicada (fingerprint de pares caminho/hash FNV32 `1e3ddd48`). Exemplo PHP em memória retorna `READY_FOR_HUMAN_HANDOFF` após dois reconhecimentos, com `promotion_allowed=false` e `external_calls=0`. Esta evidência não valida atores ou ferramentas reais.

Especificação em `docs/AIA-FT01-F1-10-REVISAO-INDEPENDENTE-SINTETICA.md`. Código em branch isolada `dev/ft01-f1-10-independent-review-mocks-2026-11-09`, com integração planejada **somente na dev** após preflight de SHAs, testes, diff e mergeabilidade. A `main`, produção, VPS, conectores reais, fornecedores e gates R01/CORE/SEG/AUT estão preservados.



## ESTADO VIGENTE — F1-09 integrado somente à dev (09/11/2026)

- **PR de código [#100](https://github.com/rafhael100/imagine-ai-agents/pull/100): MERGED na `dev/ft01-aia-core-mocks-2026-11-06`**, squash `da1710b4cc2cabe7eddf87da0605a1ba69a08724`. A `main` foi conferida em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a` e permaneceu inalterada.
- **Entrega:** `src/MockEvaluation/` (suíte sintética, snapshot de versão, comparador com gates críticos e revisão humana), 12 testes U09, exemplo PHP `examples/f109-evaluation-demo.php`, laboratório visual `examples/f109-comparador-versoes-offline.html`, smoke Node `tests/Browser/f109_smoke.cjs` e QA responsiva opcional `tests/Browser/f109_responsive_qa.py`.
- **Provas executadas:** `php tests/Unit/run.php` → **97 cases, 0 failures, exit 0**; **48/48 PHP lint PASS** e **48/48 hashes de blobs PHP idênticos** ao PR. `node tests/Browser/f109_smoke.cjs` em Node.js 22.16 → `F109_SMOKE_PASS`. `python tests/Browser/f109_responsive_qa.py` em Chromium real com Playwright já instalado → **5/5 viewports PASS** (1440×900, 1024×768, 768×1024, 390×844, 320×680), três decisões corretas, navegação por teclado, tabela com rolagem interna acessível, zero erros JS ou requisições externas. Correção mobile feita antes do merge.
- **Integridade pós-merge:** todos os **53 arquivos de código, testes e demos** do commit integrado possuem blobs Git idênticos ao head do PR #100 verificado. HTML F1-09 SHA-1 Git `ccca71572725ba6e6900a5d189d24c5fadf1334b`, smoke Node `f8f3ca01e8256adb99faff79cef5a6b6a1f126e0`, QA Chromium `6d2c59545d1166cafa988a68c20731083a819885`.
- **Limites:** execução **sintética e offline**, sem agentes/LLM, autenticação, CRM real, memória persistente, dados pessoais, custo de fornecedor, VPS, SQL, API, Cloudflare, publicação ou deploy. Browser QA executado via `page.set_content` sobre bytes idênticos; **Firefox e abertura `file://` ainda não foram testados**. A aprovação de testes não homologa uso real.
- **Gates ainda abertos:** R01-G01 a G11, CORE/SEG/AUT CRM, integração e segurança operacional. Autorização ampla vale apenas para desenvolvimento, testes locais e integração controlada na `dev`; não habilita merge na `main` ou produção.

**Precedência:** qualquer texto histórico abaixo que descreva PR #100 como DRAFT, Node/Chromium como pendentes ou F1-09 apenas em branch filha foi escrito antes do merge; consulte o registro vigente acima.

---


## HISTÓRICO FT01/F1-09 — avaliação A/B sintética — desenvolvimento anterior ao merge

O próximo recorte, em branch isolada, acrescenta um **comparador determinístico em PHP** e uma **prévia visual offline**. A suíte de seis casos fictícios fixa dataset, rubrica, escopo e baseline para comparar versões, bloqueando regressões críticas e classificando casos não executados como inconclusivos. **Mesmo um resultado 6/6 exige revisão humana; jamais publica um agente.**

Comandos locais sem serviços ou dependências:
```sh
php tests/Unit/run.php                     # regressão sintética completa — 97 testes
php examples/f109-evaluation-demo.php      # três relatórios A/B fictícios
node tests/Browser/f109_smoke.cjs          # comparador visual offline
python tests/Browser/f109_responsive_qa.py # Chromium + Playwright se já instalados
```

Para navegar: abrir `examples/f109-comparador-versoes-offline.html` em navegador. A especificação está em `docs/AIA-FT01-F1-09-AVALIACAO-COMPARACAO-OFFLINE.md`. Código PHP: **97/97 testes, 48/48 lint, 48/48 hashes de blobs conferidos contra o GitHub**. Smoke executado em **Node.js 22.16 real**, e o comparador HTML foi verificado em **Chromium real, cinco resoluções, sem erros JS, chamadas de rede ou rolagem horizontal externa**, com acesso por teclado e tabela móvel corrigida. O HTML do GitHub foi conferido por hash Git; **Firefox não testado**. Os gates técnicos de laboratório estão cumpridos; nenhum gate de produção, autenticação ou integração foi encerrado. Sem LLM, APIs, dados reais, custos, deployment, main, nem fechamento de R01/CORE/SEG/AUT.


## ESTADO VIGENTE — FT01/F1-08 integrado à dev (08/11/2026)

- **Código e protótipo:** PR #98 **MERGED exclusivamente em `dev/ft01-aia-core-mocks-2026-11-06`**, squash `d37cd9a603981c3d963823307119c402b67edf89`. `main` inalterada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Nenhum deploy, VPS, integração, gasto ou fornecedor acionado.
- **Entrega:** `examples/f108-laboratorio-navegavel.html` (quatro telas e seis decisões offline), `tests/Browser/f108_smoke.cjs`, especificação F1-08 e melhorias mobile/acessibilidade. HTML no commit da dev: blob SHA-1 `a6ff332f520a55089efa6178aeda98cb4a9008eb`; smoke Node: `38360656fa421275075be054d71680a84078e0d4`, iguais aos testados.
- **QA realmente executado:** Node.js 22.16.0 em processo real `F108_SMOKE_PASS`; Chromium headless real com Playwright `page.set_content` em cinco viewports (`1440×900`, `1024×768`, `768×1024`, `390×844`, `320×680`), **5/5 PASS**, seis casos e 14 eventos por viewport, sem erros de JS/console, requisições externas ou overflow horizontal. Capturas desktop/mobile inspecionadas; `aria-pressed` e foco por teclado conferidos. **Firefox não testado**; navegação `file://` bloqueada pelo sandbox, por isso renderização foi por `set_content`.
- **Regressão anterior preservada:** `php tests/Unit/run.php` na base sintética reconstruída → `85 cases, 0 failures`; os **43 arquivos PHP da dev pós-merge #98 têm blobs idênticos aos da base validada**. Estes números não significam homologação de segurança, ACL, LGPD, RAG, APIs ou produção real.
- **Autorização vigente:** responsável concedeu autorização ampla para desenvolver/provar mocks sintéticos e fazer merges **somente na dev** após validações, com solicitações apenas para decisões críticas. `AGENTS.md` foi atualizado documentalmente para refletir esse processo.
- **Bloqueios preservados:** R01-G01..G11 e CORE/SEG/AUT CRM abertos; `main`, produção, infraestrutura, serviços reais, dados de clientes, credenciais, migrações, SQL/ALLOW e despesas exigem novas decisões específicas.

**Precedência:** trechos históricos abaixo que descrevem F1-08 como PR DRAFT, ou o QA visual como pendente, são registros do desenvolvimento anterior à integração #98 e não definem o estado atual.

---


## FT01/F1-08 — Laboratório visual navegável (branch de demonstração)

Uma prévia offline em português já está disponível em [examples/f108-laboratorio-navegavel.html](examples/f108-laboratorio-navegavel.html). Baixe o HTML e abra-o no navegador, sem servidor ou dependências. Ele contém quatro telas, três agentes **fictícios** e seis cenários determinísticos de segurança. Todos os dados da UI são sintéticos; o JavaScript não invoca as classes PHP, não utiliza IA real, não envia informações a servidores e não guarda dados em disco.

Para verificar as interações com Node 18+ (somente módulos nativos):
```sh
node tests/Browser/f108_smoke.cjs
```

A especificação do recorte está em [docs/AIA-FT01-F1-08-LABORATORIO-VISUAL-OFFLINE.md](docs/AIA-FT01-F1-08-LABORATORIO-VISUAL-OFFLINE.md). O smoke em Node.js real passou; a revisão visual em Chromium real foi concluída em cinco resoluções (desktop, tablet e celular), sem falhas de navegação, scroll horizontal, erro JS ou requisições externas. Corrigidos menu mobile, textos e estado acessível dos cenários. Firefox e qualquer integração real não foram homologados. Este protótipo não habilita login, autorizações reais, integrações entre produtos, credenciais, APIs, produção ou gastos. `main` e os gates de segurança permanecem preservados.


## ESTADO VIGENTE — 08/11/2026, pós-merge FT01 (prevalece sobre históricos abaixo)

- **Branch de desenvolvimento:** `dev/ft01-aia-core-mocks-2026-11-06`, SHA `550aa742b10754ffa0cc1502e22bbfb72d966a81` (squash **PR #96** F1-06 corretivo + F1-07 sintético). **PR #96 MERGED somente na dev.**
- **Validado de fato com PHP CLI 8.4.24:** `php tests/Unit/run.php` **85 cases, 0 failures, exit 0**; lint PHP em **43/43 arquivos PASS**; **43/43 SHA-1 de blob Git iguais** aos arquivos do HEAD `91919913ddbe346f0cdda64e41df7c73a38d7dee` testado. Todos os 43 arquivos PHP da dev pós-squash foram comparados com esse HEAD e os hashes coincidem.
- **Modo da verificação:** em razão da falta de DNS para `git clone` no sandbox, os 43 arquivos exatos foram reconstruídos a partir dos blobs GitHub e comparados por hash; a cópia reconstruída não tinha diretório `.git`. A evidência comprova conteúdo executado, não simula um checkout Git nem CI.
- **PRs #92 e #95:** encerrados **sem merge individual**, porque integralmente substituídos pelo PR #96; comentários e commits preservados. **PR #94:** minuta histórica de F1-07, ainda documental e sem merge.
- **O que foi implementado:** laboratório em memória, dados exclusivamente sintéticos, sem agentes reais conectados, CRM/Portal APIs, LLM, banco, segredo, produção, pagamentos ou custos. O exemplo offline permanece em `examples/knowledge_context_demo.php`. `main` permanece `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`, sem alterações.
- **Gates NÃO encerrados:** R01-G01..G11, CORE/SEG/AUT CRM e avaliação de integração/segurança real continuam abertos. A cobertura de testes de um mock não equivale à homologação de produção.
- **Próxima fase:** análise de F1-08 em documentação isolada; nenhuma dependência externa, deploy ou serviço real está automaticamente autorizado.

**Precedência:** qualquer trecho antigo abaixo afirmando que PRs #92/#95 estão abertos, que F1-07 não foi implementado ou que os 85 testes não foram executados refere-se a uma fotografia histórica, **não ao estado vigente**. Mantenha esses registros por rastreabilidade, não para orientar novo merge.

---


**Status:** projeto criado no GitHub; fundação documental inicial (05/11/2026).  
**Repositório:** `rafhael100/imagine-ai-agents` (privado).  
**Execução:** sem código de produto, sem infraestrutura provisionada e sem deploy.

## Propósito

Construir a camada compartilhada de **configuração, execução supervisionada e governança de agentes de IA** do ecossistema Imagine.

**Produtos consumidores previstos:** Imagine CRM, Portal Imagine, Imagine Performance AI e Universidade Imagine.

## Princípios

1. **Núcleo comum, domínios independentes:** cada produto mantém suas APIs, identidades, dados e regras de negócio. Não há acesso direto e irrestrito aos bancos dos produtos.
2. **Isolamento multiempresa:** contexto, memória, documentos, ferramentas e custos segregados por empresa e produto, com autorização obrigatória no backend.
3. **A IA propõe, o sistema valida:** regras críticas (acesso, finanças, exclusão, publicações, cobranças) não dependem somente de prompts.
4. **Autonomia graduada:** informar, sugerir, solicitar aprovação e executar operações explicitamente permitidas conforme risco.
5. **Observabilidade:** versões, execuções, erros, custos, testes, avaliações e trilhas de auditoria.
6. **Melhoria controlada:** feedback e testes antes de promover versões.
7. **Integração por contratos técnicos:** APIs autenticadas, eventos, filas e ferramentas versionadas.
8. **Não duplicação:** o CRM mantém o motor nativo de automações; n8n pode atender fluxos internos controlados pela Imagine, mediante validação do licenciamento comercial.

## Documentos

- [AGENTS.md](AGENTS.md) — diretrizes para Codex e agentes de desenvolvimento.
- [STATUS-ATUAL.md](STATUS-ATUAL.md) — fatos, pendências e bloqueios.
- [docs/ARQUITETURA-INICIAL.md](docs/ARQUITETURA-INICIAL.md) — limites e componentes.
- [docs/ESCOPO-FUNCIONAL.md](docs/ESCOPO-FUNCIONAL.md) — 12 módulos candidatos.
- [docs/ROADMAP.md](docs/ROADMAP.md) — etapas e critérios para avançar.

## Próximas decisões

Revisar/aprovar limites do serviço, contratos entre produtos, proteção de dados, estratégias de modelos/provedores, custos, licenciamento, MVP e critérios de aceite.

**Regra:** a publicação documental neste repositório não autoriza programação, alterações nos demais repositórios, despesas, infraestrutura ou deploy.

## FT01-F1-01 — domínio mockado (2026-11-06)

Primeiro lote autorizado: oito modelos em `src/Domain`, contratos locais em
`src/Contracts`, três mocks determinísticos em `src/Mocks` e dez casos sintéticos
em `tests/Unit/run.php`. Nenhum contrato é homologado com CRM. Todos os dados de
exemplo são marcados `SYNTHETIC`; o prefixo é convenção de teste, não sanitização
nem prova de identidade. Não fornecer dados reais.

Requisito de sintaxe: PHP 8.1+ já disponível (não há Composer nem bibliotecas).
No diretório do repositório, executar localmente:

```sh
php tests/Unit/run.php
```

O runner usa autoload local e verificações explícitas, com código de saída 1 em
falha. Não usa rede, banco ou serviços. **Neste ambiente PHP não está instalado:
nenhum teste PHP ou lint PHP foi executado; compatibilidade ainda não verificada.**
Nenhum runtime ou dependência foi instalado para contornar a restrição.

O contexto contém produto, tenant, principal, finalidade, recurso e `mock-v1`.
Aprovação usa relógio inteiro fornecido pelo chamador e vincula intenção material
imutável. Deduplicação é volátil, por produto/tenant/chave, com igualdade exata de
material e contexto; não oferece atomicidade ou recuperação. O estado global de
run não confirma efeito. `CONFIRMADO` exige prova **sintética** do fake, sem valor
para o CRM real. Sugestões ignoram instruções contidas no texto de entrada e não
dispõem de capacidade de escrita. Uso técnico não implementa crédito/faturamento.

Vocabulário mínimo de run: `RECEBIDA → EXECUTANDO ↔ AGUARDANDO`, com saídas
terminais `CONCLUIDA`, `FALHOU`, `CANCELADA`. É um recorte local dos documentos,
não a máquina definitiva de R02/R03. Não há compensação, fila, persistência,
callback, expiração de run ou reconciliação oficial. Status indeterminado permanece
indeterminado; nenhum retry produz novo efeito nesse fake.

Pendências: executar lint e os dez casos em ambiente autorizado com PHP já
existente; revisar os contratos simulados e homologar contratos reais separadamente.
R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos. F1-02, merge e deploy exigem
nova autorização.

## FT01-F1-02 — coordenação supervisionada local (2026-11-08)

`SupervisedCoordinator` recebe contexto, gateway fake e provedor fake por injeção
manual. Permite iniciar run, criar etapas, sugerir, executar intenção com aprovação
externamente fornecida, consultar efeito e transitar a run explicitamente. Não
emite aprovação: usa o objeto F1-01, inclusive revogação e janela `[issuedAt,
expiresAt)` com relógio passado como parâmetro. Não há relógio global ou agendamento.

`execute` reserva a intenção antes do único `submit`. Repetição da mesma intenção
consulta `status`; divergência material falha com `IDEMPOTENCY_CONFLICT`. Falha de
envio vira `INDETERMINADO`, nunca retry automático. `afterTimeout` exige tentativa
TIMEOUT vinculada à intenção e faz somente consulta. `reconcile` também funciona
para run terminal, preservando a distinção entre estado da run e efeito. Novas
operações exigem run ativa. Confirmado/negado não podem regredir; ausência de
registro após dispatch é tratada como indeterminação. Erro na consulta propaga a
falha sem liberar novo envio ou apagar a última evidência.

`MockSupervisedGateway` compõe o fake F1-01 sem modificar contratos. Expõe contadores
para observar submissões/consultas e permite simular perda da resposta depois do
efeito. `recordSyntheticEvidence` é controle explícito de fixture, não API real;
atualiza resultado pendente/indeterminado para confirmação, negativa ou continuação
da espera. Prova CONFIRMADO continua exclusivamente `SYNTHETIC_MOCK_CRM`.

Rastros são arrays em memória com sequência, referências de produto/tenant/run/
step/intent e códigos fechados. Referências recebem hash determinístico para evitar
copiar conteúdo arbitrário mesmo com prefixo SYNTHETIC. Não armazenam material,
prompt, sugestão, erro bruto ou stack. Hash não é autenticação nem anonimização de
produção. Cada coordenador serve um contexto sintético; não é ACL CRM.

Os dez casos originais são preservados; `tests/Unit/supervised.php` acrescenta os
12 casos U02-01–U02-12 da ordem executiva, carregados pelo mesmo runner. Em ambiente
autorizado com PHP 8.1+ **previamente disponível**, executar:

```sh
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```

Nesta execução Codex, PHP está ausente: **lint e os 22 testes NÃO EXECUTADOS**.
Nenhuma instalação ou GitHub Actions utilizada. A validação independente anterior
F1-01 registrada no STATUS não comprova o código novo F1-02.

Limitações: estado volátil por instância, nenhuma atomicidade, concorrência,
recuperação durável ou prova de serviço real. Run concluída não indica efeito
confirmado; cancelamento não apaga efeitos existentes. Novos objetos/coordenadores
não representam recuperação de processo. Sem merge, F1-03 ou deploy. R01-G01 a G11
e CORE/SEG/AUT CRM continuam abertos.

## FT01-F1-03 — laboratório sintético de resiliência (2026-11-08)

Escopo executado com autorização específica do responsável **somente** na branch filha de desenvolvimento `dev/ft01-f1-03-resilience-mocks-2026-11-08`. Nenhuma execução real, merge ou deploy autorizado.

- `MockFaultGateway` envolve o gateway fake existente e permite perda determinística da **resposta** de submit após entrega ao fake, mais falhas de consulta status. Não possui retry, rede, relógio ou persistência.
- `ReadOnlyTraceReview` interpreta cópias de eventos estritamente sintéticos e nega confirmação sem `Effect` fake compatível; resiste a truncamento, conflito, contexto divergente, eventos desconhecidos e clocks explícitos regressivos. `afterSimulatedRestart` SEMPRE sinaliza ausência de estado durável, sem invocar efeitos.
- `tests/Unit/resilience.php` adiciona **12** cenários U03-01 a U03-12 ao runner, preservando as 22 regressões F1-01/F1-02. **34** é a meta de execução do runner oficial, não um resultado presumido.
- Nova verificação local auxiliar: PHP 8.4.24, sintaxe dos 3 arquivos novos aprovada, 12/12 cenários sintéticos aprovados contra reprodução local dos contratos F1-01/F1-02. As três cópias de arquivos novos exibiram hashes Git blob SHA-1 idênticos aos do GitHub. **Não é evidência de execução dos 34 testes contra o checkout integral do PR**; essa pendência permanece.
- Em ambiente autorizado que tenha PHP 8.1+ **já disponível**, executar `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` e `php tests/Unit/run.php`, registrar os códigos de saída, versão e os 34 resultados efetivos. Não instalar PHP/Composer por iniciativa própria nem habilitar GitHub Actions.

**Limitações:** estado e provas apenas fictícios e voláteis; nenhum journal durável, autenticação/ACL real, integração CRM/IA, garantias de concorrência, idempotência entre processos, conformidade operacional ou recuperação após queda de produção. Não armazenar dados pessoais nesses testes. Os gates R01-G01–G11 e CORE/SEG/AUT CRM permanecem abertos.

Revisão Codex F1-03 em 2026-11-08: o trabalho já existente na filha e no PR #80
foi preservado. A leitura do trace agora exige início explícito, rejeita nova
submissão na mesma sequência e regressão de efeito terminal, compara a prova
com o último efeito observado e rejeita relógio/assinatura opcionais nulos.
Os casos U03-04/U03-05/U03-12 incluem essas entradas adversariais, mantendo os 12
casos F1-03 e as 22 regressões anteriores. Neste ambiente, `php -v` retorna 127
(`php: command not found`): lint e os **34 casos NÃO EXECUTADOS pelo Codex**.
A evidência auxiliar histórica acima não valida estas correções nem a suíte
completa no SHA atual. Aceite permanece pendente de execução posterior.

## FT01-F1-04 — ferramentas e capabilities simuladas (2026-11-08)

**Autorização executiva específica:** o responsável autorizou **somente programação e testes unitários sintéticos F1-04**. Branch filha `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08`; PR de código apenas **draft** contra `dev/ft01-aia-core-mocks-2026-11-06`, sem merge ou deploy.

- `src/MockTooling/MockToolCatalog.php`: catálogo fechado e versionado de três ferramentas fictícias (leitura, sugestão e mutação fake), com validação de parâmetros, whitelist e limites.
- `src/MockTooling/MockCapabilityGrant.php`: concessão puramente fictícia injetada fora de textos de IA; exige produto, empresa, principal, finalidade, recurso e versão idênticos; permite revogação e prazo explícito.
- `src/MockTooling/MockToolDispatcher.php`: despacho somente local, leitura pelo reader fake, sugestão pelo coordenador anterior e mutação fake **apenas via SupervisedCoordinator** já autorizado. Nunca concede aprovação, envia HTTP ou retenta gravação cega. Rastro limitado a códigos fechados e referências sintéticas hash.
- `tests/Unit/tool_dispatch.php`: 12 cenários novos U04-01 a U04-12. O runner carrega as 34 regressões anteriores sem reescrevê-las.

**Evidência independente do código publicado:** PHP CLI 8.4.24; **26/26 arquivos PHP lint PASS**; **46/46 testes sintéticos PASS, zero falhas**; **exit code 0**. Os hashes Git blob SHA-1 de todos os 26 arquivos PHP locais testados coincidem com os blobs correspondentes da branch GitHub. Uma fixture de teste U04-08 foi ajustada para distinguir concessão ainda vigente de aprovação expirada; todas as regras de autorização foram mantidas.

**Não é produção:** grants sintéticos **não são identidade/ACL**, resultados fake não são confirmações reais do CRM, ausência de persistência, filas, concorrência, APIs, modelo LLM, dados reais e validação bilateral. Gates R01-G01–G11 e CORE/SEG/AUT CRM continuam **ABERTOS**; `main`, infra e demais projetos intocados. Merge e F1-05 **não autorizados**.

## FT01-F1-05 — laboratório de consumo técnico e cotas fictícias (2026-11-08)

**Autorização executiva distinta e expressa:** o responsável respondeu `sim` à pergunta específica sobre executar programação e testes sintéticos F1-05, após ratificação documental de F105-D01 a D10 e incorporação do PR #85. A autorização **não permite merge de código, deploy, integração real, F1-06 ou alteração de `main`**.

- `src/MockUsage/MockUsagePolicy.php`: política técnica *fictícia*, em memória, vinculada a produto, empresa, principal, finalidade, recurso, operação `mock.*`, versão do contexto e intervalo lógico de validade; revogação e cota máxima.
- `src/MockUsage/MockUsageLedger.php`: registro volátil de tentativas/estados ESTIMADO/OBSERVADO/AJUSTADO, deduplicação e conflito material, consumo conservador por tentativa sem somar estimativa e observação, quota `SYNTHETIC_WITHIN_QUOTA` **não autoriza ferramentas**, conciliação incerta, e alertas locais deduplicados.
- `src/MockUsage/MockUsageProof.php`: prova de uso fictícia, isolada, proveniente apenas de `SYNTHETIC_MOCK_PROVIDER`; **não constitui comprovante de despesa, transação ou efeito do CRM**.
- `tests/Unit/usage_quota.php`: 12 casos novos U05-01 a U05-12. `tests/Unit/run.php` apenas inclui esta suíte após as 46 regressões F1-01 a F1-04, sem alterar os testes anteriores.

**Execução real independente (código exato verificado):** PHP CLI **8.4.24**, sandbox local; `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **30/30 PASS**, exit 0; `php tests/Unit/run.php` → **58/58 PASS, zero falhas**, exit 0. Conteúdo dos **30/30 arquivos PHP** publicados na branch F1-05 conferido por **Git blob SHA-1** contra os arquivos locais testados, sem divergências.

**Limitações:** somente **processo único, memória volátil e dados inventados**. Sem reserva de quota atômica, durabilidade, validação real de identidade ou permissão, custos efetivos, preço, cobrança, saldo de créditos, provedor, faturamento, conciliação financeira, LGPD operacional, alertas externos ou CRM real. Não há qualquer fonte de verdade além das fixtures do laboratório.

**Segurança/governança:** `src/Contracts/`, domínio anterior, coordenador F1-02, resiliência F1-03, despacho F1-04, `AGENTS.md` e documentos ratificados permanecem intactos. Código somente na branch filha, PR futuro **draft e sem merge**, `main` e infraestrutura intocadas, R01-G01–G11 e CORE/SEG/AUT CRM **ABERTOS**, F1-06 não autorizado.

## FT01-F1-06 — catálogo e revisão de versões SOMENTE sintéticos (2026-11-08)

**Autorização executiva delimitada:** após integrar documentalmente a F1-06 (PR #89), o responsável respondeu **"sim"** à pergunta específica de executar programação e testes **somente sintéticos** em branch filha isolada, **sem merge, deploy ou integrações reais**.

- `src/MockCatalog/MockAgentVersionSnapshot.php`: manifesto de referências `SYNTHETIC_*`, tipado, com versão e digest material determinístico; snapshot imutável, `safeView` limitado.
- `src/MockCatalog/MockAgentTemplateCatalog.php`: catálogo fechado por contexto de produto/empresa/principal/finalidade/recurso, sem consultas externas; versões conflitantes e suspensas negadas.
- `src/MockCatalog/MockVersionReview.php`: revisão fictícia vinculada ao SHA de manifesto, dataset, regra, revisor e intervalo lógico; revogação explícita; **não é assinatura nem homologação**.
- `src/MockCatalog/MockPromotionGate.php`: promoção, suspensão e rollback **apenas locais**; estado fail-closed, elegibilidade vigente, idempotência e trilha mínima (hashes/códigos), sem chamadas de ferramentas, concessão de capacidades ou publicação.
- `tests/Unit/catalog_release.php`: 12 casos U06-01 a U06-12; runner passou a carregar os 58 casos anteriores intactos + os 12 novos.

**Validação realizada sobre código idêntico à branch GitHub:** PHP CLI **8.4.24**, lint `find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → **35/35 PASS, exit 0**; `php tests/Unit/run.php` → **70/70 PASS, zero falhas, exit 0**. Os **35 hashes Git blob SHA-1** dos arquivos PHP da branch foram confrontados com os 35 arquivos locais testados, sem divergências. Inclui verificação adversarial de arrays de ferramentas malformados, sem suprimir avisos.

**Limites obrigatórios:** processo único e estado volátil, sem agentes/identidades/permissões reais, modelo LLM, assinatura criptográfica, publicação operacional, rede, CRM, autenticação, persistência, concorrência, custo ou rollback de infraestrutura. Catálogo fictício não altera cota, approval, ferramentas ou crédito comercial. O PR de código permanece **DRAFT e SEM MERGE**; `main` e VPS intocadas. Gates R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**, F1-07 bloqueado.

## FT01-F1-07 — recuperação de conhecimento e contexto SOMENTE sintéticos (2026-11-08)

**Autorização executiva atualizada:** o responsável concedeu autorização ampla para desenvolver o Imagine AI Agents, sem necessidade de consultas rotineiras, mas mantendo isolamento de branch, ausência de despesas e preservação de `main`/produção. Esta autorização mais recente substitui o bloqueio executivo histórico de código F1-07, **sem homologar nenhuma integração real**. F107-D01..D10 alternativas A já estavam ratificadas documentalmente (PR #93, merge exclusivamente documental na dev). O PR #92 de correções F1-06 permanece DRAFT pendente de regressão integral e NÃO é alterado pela F1-07.

**Código novo nesta branch filha da dev:**
- `src/MockKnowledge/MockKnowledgeSource.php` — metadados sintéticos tipados, versão, fonte, dono, classificação, validade lógica e digest determinístico.
- `MockKnowledgeCatalog.php` — catálogo fechado e segregado por **contexto integral + referência + versão**; fonte desconhecida, expirada, desativada ou de outro contexto recebe negativa uniforme.
- `MockRetrievalGrant.php` — grant inteiramente fictício, explícito por fonte/base/versão/contexto, com janela lógica e revogação.
- `MockKnowledgeRetriever.php` — seleção local, determinística e somente leitura, limitada a três fontes previamente autorizadas; pedidos dinâmicos recebem `DYNAMIC_SOURCE_REQUIRED`.
- `MockContextEnvelope.php` — rechecagem de grant e de todas as fontes ao entregar, proteção contra material adulterado, teto de conteúdo, sinalização `UNTRUSTED_DATA`, conflito factual sem inventar resposta e encerramento explícito.
- `MockKnowledgeTrace.php` — somente códigos fechados, sequência e hash de referências fictícias, sem corpo de passagem ou prompt.
- `tests/Unit/knowledge_context.php` — U07-01..U07-12; `tests/Unit/run.php` inclui a suíte após os **70 testes** históricos. Meta atual desta branch F1-07: **82** casos. Quando o PR #92 de três testes adicionais entrar na dev, a integração conjunta poderá requerer **85** casos, não presumir resultado.

**Evidência executada nesta revisão:** PHP CLI 8.4.24 já disponível, sem instalação nem fornecedor. Lint **6/6 PASS** nas seis classes novas localmente reconstruídas; hashes Git blob SHA-1 **6/6 coincidentes** com os arquivos publicados na branch. Runner auxiliar local exercitando U07-01..U07-12 e isolamento adicional de referência igual entre tenants: **13/13 PASS, 0 falhas**. Essa reprodução **não equivale** à execução do arquivo oficial `tests/Unit/knowledge_context.php` no checkout completo, nem à regressão oficial `php tests/Unit/run.php`; **82/82 NÃO foi executado/comprovado**. O checkout integral segue inacessível por DNS no sandbox; não houve Actions, Composer ou custos.

**Restrições:** laboratório de processo único e memória volátil; nenhum RAG, LLM, OCR, embedding, crawler, banco/SQL, autenticação/ACL real, documentos de cliente, APIs CRM, importação, persistência, retenção legal, cobrança, infraestrutura, publicação ou efeito externo. `main`, contratos/implementações F1-01..F1-06 e outros produtos preservados. PR de código DRAFT sem merge automático; R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**.

### Demonstração offline executável F1-07 (2026-11-08)

O arquivo `examples/knowledge_context_demo.php` é um exemplo sem infraestrutura, Composer, banco, API ou LLM. Execute no checkout da branch com PHP 8.1+ já instalado:

```sh
php examples/knowledge_context_demo.php
```

O exemplo materializa uma única passagem fictícia (`READY` / `UNTRUSTED_DATA`), simula revogação antes da entrega (`DENIED`) e tenta reutilizar o grant de outra empresa (`DENIED`). A saída não contém dados de clientes nem autoriza ferramentas.

**Validação reproduzida:** PHP 8.4.24 já presente; `php -l` do exemplo PASS; execução exit 0. Hash Git blob do exemplo `0d9fe667bb3e09e34228618b6b9234b19d1ef9fc`, idêntico ao arquivo publicado. Um pacote portátil offline de demonstração foi criado no ambiente de revisão (não implantado). Os **82 testes oficiais da branch F1-07 não foram executados**, nem os **85 futuros combinados** com a correção #92. PR de código continua DRAFT sem merge.

### Validador offline de regressão (sem rede e sem Actions)

O script `tools/validate_ft01_offline.sh` exige um **checkout Git completo** com PHP 8.1+ já instalado e verifica o SHA, integridade mínima de diretórios, lint de todos os PHP e execução real do runner oficial. Retorna **exit 2 / BLOCKED** se o checkout está incompleto e **exit 1 / FAIL** se lint/testes falham; só devolve `VALIDATION_PASS` em caso de regressão efetiva sem falhas.

```sh
# Após checkout completo do PR #95 (F1-07 sem #92):
bash tools/validate_ft01_offline.sh

# Após conciliar também o PR #92 (3 casos F1-06 a mais):
EXPECTED_CASES=85 bash tools/validate_ft01_offline.sh
```

A base atual do PR #95 exige **43 arquivos PHP** (incluindo o exemplo) e **82 testes**. Com F1-06 corretivo, permanece 43 PHP e passam a ser exigidos 85 testes. Para fixar uma revisão específica, defina `EXPECTED_SHA` com o SHA completo previamente verificado no GitHub. O validador não instala dependências, não chama rede e não cria CI.

**Evidência desta revisão:** o script exato foi conferido por hash Git blob SHA-1 `2359d514cf2983b2101d87b08393388df04a1970`. Na cópia PARCIAL do sandbox, retornou corretamente `BLOCKED` / exit 2, sem falsificar validação completa. **82/82 e 85/85 não executados**. Os PRs #92 e #95 seguem DRAFT sem merge.
