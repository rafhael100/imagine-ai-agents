# Imagine AI Agents

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
