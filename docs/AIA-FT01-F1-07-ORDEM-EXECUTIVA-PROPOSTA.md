# AIA-FT01/F1-07 — Minuta de ordem executiva para conhecimento e contexto mockados | v0.1

> **ESTADO: MINUTA APENAS DOCUMENTAL, NÃO APROVADA PARA EXECUÇÃO.**
>
> **Data:** 2026-11-08. Repositório `rafhael100/imagine-ai-agents`. Branch somente documental `docs/ft01-f1-07-ordem-executiva-proposta-2026-11-08`, derivada da dev `dev/ft01-aia-core-mocks-2026-11-06`, SHA conferido `02ed376c7be5258d091b26667ca00f63a0db46ab`.
>
> **Precedência:** o responsável aprovou **exclusivamente documentalmente** as alternativas A de F107-D01 a D10 no PR #93. O PR #93 permanece **OPEN/DRAFT, não integrado**. Este PR de minuta é **independente** e não integra ou altera PR #93. A aprovação das diretrizes NÃO autoriza sua execução.
>
> **Pendências paralelas:** PR #92 (correções de consistência F1-06) e PR #90 (minuta histórica F1-06) permanecem abertos e fora do escopo; não foram revisados, mesclados ou alterados aqui. Antes de eventual programação F1-07, reavaliar a base após possível alteração de F1-06 pelo PR #92.
>
> **Limites permanentes:** `main`, outros projetos (CRM/Portal/Sites/Performance/Universidade), VPS/Cloudflare/n8n, CI/Actions, bancos/SQL/ALLOW, APIs de CRM/LLM, dados reais e pessoais, segredos, fornecedores, custos e deploy intocados. R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. F1-08 não autorizado.

## 1. Objetivo executivo FUTURO — condicionado à autorização explícita

Somente se houver autorização executiva **específica e posterior** à integração documental expressamente autorizada do PR #93, implementar laboratório em **PHP puro com dados inteiramente sintéticos e memória volátil** para:

1. **Fonte fictícia versionada**: metadados imutáveis de propriedade, tenant, produto, principal, finalidade, recurso, classificação, seção, versão, vigência e conteúdo de fixture estritamente limitado.
2. **Grant de recuperação em fixture**: escopos explícitos e validade lógica, revogação verificável; não equivale a autenticação, identidade, ACL ou grant de serviço real.
3. **Recuperação local somente leitura**: filtrar por escopo e versão antes de retornar trechos; ausência de fonte e fonte não autorizada com mesmo código de negação, sem revelar existência.
4. **Composição de contexto mínimo**: delimitar referências, citações/versões e passagens fictícias como `UNTRUSTED_DATA`; revalidar elegibilidade de cada fonte antes da entrega final.
5. **Invalidação conservadora**: suspender, expirar, substituir ou revogar fonte/grant bloqueia envelopes ainda não consumidos; não presumir exclusão em armazenamento persistente ou backup.
6. **Auditoria referencial mínima**: código fechado + hashes de referências sintéticas, sem registrar corpo de documento, prompt, histórico, token ou lead.

**Exclusões:** não usar documentos, arquivos, e-mails, PDFs ou dados de cliente; não criar RAG real, embeddings, banco vetorial, OCR, memória persistente, indexação, consulta web, chamada de modelo, resposta generativa, API, filas, cache distribuído ou integração com produtos Imagine.

## 2. Arquivos e branches CANDIDATOS — execução ainda BLOQUEADA

| Item | Diretriz para futura autorização |
| --- | --- |
| Base de implementação | `dev/ft01-aia-core-mocks-2026-11-06`, em SHA a conferir no momento da futura ordem, após revisão do PR #92 e merge documental específico do PR #93 |
| Branch de código candidata | `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08` — **NÃO CRIADA/NÃO AUTORIZADA** |
| Classes novas candidatas | `src/MockKnowledge/`: `MockKnowledgeSource`, `MockKnowledgeCatalog`, `MockRetrievalGrant`, `MockKnowledgeRetriever`, `MockContextEnvelope`, `MockKnowledgeTrace` |
| Testes novos candidatos | `tests/Unit/knowledge_context.php` — 12 casos U07-01..12 |
| Runner | Inclusão mínima da suíte após os 70 testes anteriores em `tests/Unit/run.php` |
| Documentação de execução | `README.md`, `STATUS-ATUAL.md`, com SHA, ambiente, logs, limites, testes realmente executados e gates |
| Destino eventual PR código | **DRAFT filha → dev, SEM MERGE** |
| Merge de código | Somente em aprovação distinta, após verificação de diffs/SHAs/testes |

**Preservar sem modificação:** `AGENTS.md`, `src/Contracts/`, `src/Domain/`, `src/MockCatalog/`, `src/MockTooling/`, `src/MockUsage/`, `src/MockResilience/`, `src/MockOrchestration/`, `src/Mocks/`, todas as suítes F1-01..F1-06, documentos ratificados, pipelines e outros repositórios. Alterações fora do recorte exigem **parada e nova decisão**.

Se branch candidata já existir, não recriá-la, sobrescrevê-la ou usar force-push; comparar histórico/arquivos e reportar conflitos.

## 3. Preflight da eventual implementação

1. Conferir conexão GitHub, branch/commit autenticados, árvore limpa, permissões, SHA da `main` e `dev` e diffs de PRs abertos. Não fazer merges automaticamente.
2. Conferir que F107-D01 a D10 estão ratificadas documentalmente e PR #93 foi integrado documentalmente **mediante autorização específica**, sem presumir que a ratificação equivale a merge.
3. Inspecionar explicitamente PR #92 e quaisquer alterações de consistência F1-06 que possam afetar estado/contratos do catálogo. **Se houver dependência não reconciliada, interromper.**
4. Ler `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, AIA-05, AIA-06, AIA-09, AIA-11, AIA-R02/R04, documentos F1-01..F1-07, contratos e componentes de código F1-01..F1-06.
5. Verificar PHP CLI 8.1+ **já disponível**. Se não houver, não instalar nem usar GitHub Actions; registrar `lint/testes NÃO EXECUTADOS`.
6. Registrar critérios de parada, SHA base, arquivos permitidos, fixtures inventadas, limitações de simulação e proibição de custos/integração real.

## 4. Invariantes de segurança propostas

- **Autorização fora do texto:** um documento ou resumo contendo instruções não modifica grant, política, quota, approval, catálogo de ferramentas nem ordena execução.
- **Negação uniforme:** fonte desconhecida, de outra empresa, produto, finalidade, principal ou recurso, ou grant ausente, recebem falha conservadora sem revelar existência.
- **Escopo completo:** validação antes da seleção e imediatamente antes da montagem do envelope. Compartilhamento interproduto negado por padrão.
- **Versionamento exato:** trecho somente se proprietário, fonte, seção, versão, validade e classificação corresponderem ao grant e à consulta. Nunca trocar silenciosamente para versão antiga ou documento não aprovado.
- **Revogação antes da entrega:** qualquer suspensão, expiração, invalidação, exclusão ou revogação ocorrida após a seleção impede que contexto não consumido seja entregue. Não prometer expurgo real.
- **Proveniência rastreável:** envelope contém apenas referências fictícias com origem/versão e passagens sintéticas limitadas, sempre rotuladas como dados não confiáveis.
- **Incerteza:** dados contraditórios ou sem evidência levam a conflito/insuficiência, não a verdade inventada.
- **Dados transacionais:** solicitações de preço, saldo ou estado vivo CRM devem retornar `DYNAMIC_SOURCE_REQUIRED`, sem inferir valor de FAQ estática.
- **Limites e sanitização:** tipos estritos, máximo de trechos/tamanho, negação de campos extras/marcadores inválidos. Log não inclui texto integral, segredos ou dados privados.
- **Estado efêmero:** nenhum armazenamento ou memória automática; teste in-memory single-process não comprova ACL de produção, isolamento entre processos, retenção legal, segurança de backup, buscas vetoriais nem confiabilidade semântica de IA.

Se qualquer invariante exigir mudar contratos, classes anteriores, `main`, dados reais ou infraestrutura, **parar e solicitar autorização adicional**.

## 5. Matriz de aceitação FUTURA — 12 U07 + 70 regressões = 82 testes

| Caso | Comprovação sintética proposta |
| --- | --- |
| U07-01 | Consulta da mesma fonte/seção/versão em contexto autorizado retorna referência válida, sem privilégios |
| U07-02 | Outro tenant, produto, principal, finalidade ou recurso recebe negativa uniforme |
| U07-03 | Grant ausente, revogado, vencido ou versão discordante bloqueia antes da consulta |
| U07-04 | Fonte desconhecida, desativada, excluída, expirada ou versão não elegível bloqueia |
| U07-05 | Revogação entre recuperar e compor invalida envelope pendente |
| U07-06 | Compartilhamento entre produtos sem autorização explícita é negado |
| U07-07 | Conteúdo fictício contraditório resulta em conflito/insuficiência, sem síntese inventada |
| U07-08 | Preço/saldo/estado dinâmico CRM não é substituído por FAQ |
| U07-09 | Prompt injection em passagem não executa ferramenta, nem altera grants |
| U07-10 | Excesso de tamanho/trechos, schema inválido ou campo inesperado falha fechado |
| U07-11 | Repetição, encerramento ou invalidação não cria estado/memória persistente |
| U07-12 | Trace exclui corpo de documento, prompt e marcadores de segredo fictício |

**ESTADO:** U07-01 a U07-12 **NÃO IMPLEMENTADOS/NÃO EXECUTADOS**. Os **70 testes anteriores** F1-01..F1-06 já validados pertencem ao lote anterior; após eventual implementação F1-07, devem ser novamente executados como regressão. **82 é somente META futura, não PASS atual.**

Com autorização executiva separada e PHP CLI preexistente:
```sh
php -v
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```
Registrar saída real, exit codes, quantidade de arquivos testados, SHA do checkout, hashes dos arquivos do GitHub e falhas, **sem declarar PASS não executado**.

## 6. Governança e etapas independentes

- [x] F107-D01 a D10 alternativas A **ratificadas somente documentalmente** no PR #93, em 2026-11-08.
- [ ] Aprovação expressa, exclusiva, para eventual **merge documental** do PR #93 à dev.
- [ ] Avaliação do PR #92 de correções F1-06 e seus impactos sobre a base — não interferir.
- [ ] Avaliação desta minuta executiva F1-07 — **não é ordem concedida**, mesmo se integrada documentalmente.
- [ ] Decisão independente autorizando **programação PHP pura + testes sintéticos F1-07** sob recorte restrito.
- [ ] Depois: publicar PR de código **DRAFT**, sem merge; nova decisão específica sobre merge.
- [ ] `main`, produtos, infra, fornecedores, dados reais e deploy continuam bloqueados. Gates R01-G01–G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**.

## 7. Texto de autorização executiva sugerida — NÃO CONCEDIDA

> **Autorizo expressamente o desenvolvimento e os testes SINTÉTICOS da FT01-F1-07**, exclusivamente no repositório `rafhael100/imagine-ai-agents`, após confirmar os SHAs, a integração DOCUMENTAL específica do PR #93 na branch `dev/ft01-aia-core-mocks-2026-11-06` e a resolução/revisão de dependências do PR #92. Autorizo criar apenas a branch de código isolada `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08` e adicionar PHP puro in-memory em `src/MockKnowledge/`, `tests/Unit/knowledge_context.php`, inclusão mínima no runner e documentação `README.md`/`STATUS-ATUAL.md`. Autorizo até 12 novos casos U07-01..U07-12 e regressão dos 70 anteriores, sem instalação de PHP/Composer nem uso de Actions. Abrir PR DRAFT filha → dev, **SEM MERGE**. Não autorizo tocar `main`, outros produtos, contratos/classes estáveis, CRM real, APIs/LLM, dados privados, SQL/ALLOW, credenciais, servidores, gastos, deploy, F1-08 ou fechamento dos gates R01-G01–G11 e CORE/SEG/AUT CRM. Havendo divergências, parar e registrar o impedimento.

Este parágrafo é **somente uma sugestão para futura autorização**, não um comando vigente.

**Conclusão:** F1-07 tem diretrizes documentais ratificadas, mas o desenvolvimento e os testes novos permanecem BLOQUEADOS até ordem executiva específica. Nenhum merge da presente minuta é autorizado por este ato.

## Atualização de precedência — decisão posterior de 2026-11-08

**Este adendo prevalece sobre as referências históricas a bloqueios executivos anteriores**, sem alterar o texto original da minuta.

- **PR #93:** diretrizes F107-D01..D10 alternativas A ratificadas DOCUMENTALMENTE e integradas exclusivamente à branch `dev/ft01-aia-core-mocks-2026-11-06` no squash `40a71cb2797d8c954f392e94ef4b02b4cd04e55d`. As afirmações anteriores de que o PR #93 está aberto/sem merge são históricas e não mais atuais.
- **Autorização posterior do responsável:** autorizou amplamente o desenvolvimento do Imagine AI Agents e pediu autonomia para decisões não críticas. Esse ato autoriza o recorte sintético de código e testes F1-07 antes previsto como futuro, **mas não homologação operacional, despesas, infraestrutura, dados reais ou produção**.
- **PR de código #95:** [F1-07 conhecimento/contexto sintéticos](https://github.com/rafhael100/imagine-ai-agents/pull/95), branch `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08`, criada e publicada, ainda **OPEN/DRAFT/SEM MERGE**. Contém seis classes PHP novas, U07-01..U07-12, runner e documentação. PHP CLI 8.4.24 local: lint das seis classes e hashes 6/6 verificados, 13/13 casos em runner auxiliar PASS; **82 testes oficiais ainda NÃO foram executados**, apenas registrados estaticamente.
- **PR corretivo #92:** permanece OPEN/DRAFT/SEM MERGE, independente, com três testes F1-06 adicionais e regressão integral pendente. Quando #92 e #95 estiverem integrados à mesma dev, a meta conjunta será 85 testes, sem presumir o resultado.
- **PR #94:** permanece como **minuta histórica DOCUMENTAL em DRAFT / SEM MERGE**. Não substitui a ordem posterior nem deve ser utilizada para negar a existência do código F1-07. Não integra o PR #95 nem altera `main` ou código.
- **Gates:** R01-G01..G11 e CORE/SEG/AUT CRM ABERTOS; sem credenciais, dados privados, API/LLM real, SQL/ALLOW, CI pago, fornecedores, implantação ou alterações de infraestrutura.
