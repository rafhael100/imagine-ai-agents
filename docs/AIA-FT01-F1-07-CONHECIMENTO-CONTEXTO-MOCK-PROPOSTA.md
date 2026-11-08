# AIA-FT01/F1-07 — Recuperação segura de conhecimento e contexto sintético | Proposta documental v0.1

> **ESTADO: PROPOSTA DOCUMENTAL — F107-D01 a D10 TODAS ABERTAS, NÃO RATIFICADAS.**
>
> Data: 2026-11-08. Repositório: `rafhael100/imagine-ai-agents`. Branch **exclusivamente documental**: `docs/ft01-f1-07-retrieval-context-mocks-2026-11-08`. Base `dev/ft01-aia-core-mocks-2026-11-06`, commit verificado `02ed376c7be5258d091b26667ca00f63a0db46ab` (F1-06 já integrada via PR #91).
>
> **Autorização atual:** o responsável respondeu **“autorizado”** à proposta específica de **iniciar SOMENTE a documentação F1-07**. Não autorizou branch de código, PHP, testes novos, merge documental ou de código, deploy, fornecedores, despesas, integrações reais ou fechamento de gates.
>
> **Restrições imutáveis:** `main`, CRM/Portal/Sites/Performance/Universidade e outros repositórios, VPS/Cloudflare/n8n, CI/Actions, segredos, dados pessoais, SQL/ALLOW, APIs/LLM reais, instalação de dependências, banco, filas ou deploy permanecem fora de escopo. Gates R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. F1-08 não autorizado.

## 1. Fundamentação e recorte de continuidade

F1-01..F1-06 já foram integradas **somente na branch dev**. A última etapa F1-06 acrescentou laboratório de templates/versionamento/revisão/promoção sintéticos (PR #91, squash `02ed376c7be5258d091b26667ca00f63a0db46ab`). Evidência já registrada: **70/70 testes PHP sintéticos PASS** e **35/35 lint PASS** em PHP 8.4.24, com hashes conferidos no contexto daquele lote. Esses testes **não** validam RAG, permissões reais, LGPD operacional, qualidade semântica nem produção.

As diretrizes funcionais **AIA-05 (conhecimento/RAG)**, **AIA-06 (contexto e memória)**, **AIA-09 (avaliação)** e **AIA-11 (governança)**, além das diretrizes documentais AIA-R02/R04, exigem segregação, proveniência, validade e tratamento do conteúdo recuperado como **dados não confiáveis**. Não há ainda `src/MockKnowledge/` na branch dev.

**Proposta recomendada F1-07:** simular um **catálogo fechado de fontes fictícias**, recuperação determinística **somente leitura**, revogação/validade e montagem de um envelope de contexto mínimo, com trilha referencial. Este lote **não** cria mecanismo de memória persistente, banco vetorial, embeddings, OCR, importação de documentos, busca web, LLM, e-mail, conexão real com CRM ou respostas generativas.

A eventual memória derivada, ingestão e indexação persistente, retenção legal e expurgo de backups exigem blocos técnicos futuros e homologação própria. **Documento estático não substitui dado transacional atual do CRM.**

## 2. Fronteiras e fontes de autoridade

| Domínio | Fonte da verdade futura | Limite desta simulação |
| --- | --- | --- |
| Documento/base e versão | Proprietário autorizado da fonte e do produto | Metadados/trechos fictícios imutáveis em memória, sem dados privados |
| ACL real, empresa, principal, finalidade, campos | Backend do produto dono, com contrato bilateral homologado | Apenas fixture de escopos explicitamente injetada; prefixo `SYNTHETIC_` não prova autenticação |
| Conhecimento vigente | Fonte oficial aprovada e sua versão, sob política de retenção | Seleção local de versões fictícias por validade e estado |
| Histórico/lead e status comercial | CRM e outros produtos proprietários, via suas APIs oficiais | **Nenhuma consulta, cópia nem sincronização real** |
| Memória persistente | Produto proprietário e política de finalidade/retenção | **Excluída** deste lote; contexto de tarefa não vira memória |
| Ferramentas/capabilities | Catálogo/autorização no backend (F1-04 apenas mock) | O resultado de retrieval **nunca concede** execução |
| Versão de agente e avaliação | Catálogo governado (F1-06 mock) | Documento não modifica manifesto de agente, policy ou revisão |
| Auditar e expurgar dados reais | Donos do dado, segurança e privacidade homologados | Apenas invalidação simulada de referências e derivados in-memory |

## 3. Componentes candidatos FUTUROS — sujeitos a ordem executiva separada

| Componente candidato | Responsabilidade sintética | Exclusões obrigatórias |
| --- | --- | --- |
| `MockKnowledgeSource` | Snapshot de fonte/versão/seção, classificação, dono, produto, tenant, finalidade e vigência fictícia | Upload, PDF real, embedding, acesso a filesystem ou segredo |
| `MockKnowledgeCatalog` | Catálogo fechado em memória e seleção por filtros completos, default deny | Pesquisa transversal por tenant, crawling, índices externos |
| `MockRetrievalGrant` | Escopos explicitamente fornecidos em fixture, vinculados a recurso/versão/consulta | Assinatura ou ACL real, claims inferidas do texto |
| `MockKnowledgeRetriever` | Seleção determinística **somente leitura** de passagens fictícias previamente elegíveis | Chamada de LLM/API, ranking vetorial, rede ou criação de task |
| `MockContextEnvelope` | Empacotar referências e trechos permitidos com teto técnico local e indicação **não confiável** | Instrução privilegiada, grant, escrita em CRM, memória persistente |
| `MockKnowledgeTrace` | IDs e códigos fechados, sem material sensível, para revisão sintética | Log de prompt/texto integral, exportação, métricas de clientes |
| `tests/Unit/knowledge_context.php` | Critérios U07-01..U07-12 e 70 regressões anteriores | Dados reais, testes CRM/API, Actions, despesa de provedor |

**Nomes são candidatos, não contratos homologados nem classes existentes.** O código futuro deve ser composto separadamente, sem alterar contratos e classes estáveis dos lotes F1-01..F1-06.

## 4. Contratos lógicos de teste (não APIs, schemas nem política LGPD)

- **Escopo:** `product_ref`, `tenant_ref`, `principal_ref`, `purpose_ref`, `resource_ref`, `agent_version_ref`, `knowledge_base_ref`, `source_ref` e `source_version_ref`. Todo vínculo é **fictício**, de ambiente de testes.
- **Origem:** tipo da fonte `OFFICIAL_PRODUCT` ou `TENANT_PRIVATE` ou `IMAGINE_INTERNAL`, responsável fictício, categoria de dados, idioma, classificação, estado e validade lógica explícitos. Compartilhamento interproduto somente se houver fixture autorizadora separada; por padrão **negado**.
- **Conteúdo:** passagens sintéticas curtas com ID, versão, seção, política fictícia, indicação de dado não confiável e limite de tamanho. Nunca importar conteúdos de clientes ou criar credenciais.
- **Seleção:** filtros por fonte/tenant/produto/finalidade e status, sempre antes da consulta e novamente antes da composição final. Desconhecido/mismatch/revogado/expirado → negar ou retornar insuficiência **sem vazamento de existência**.
- **Contexto:** separar orientação de agente (poder superior, fora do retrieval), pedido autenticado (fixture) e passagens recuperadas (dados não confiáveis). O texto de uma passagem não pode elevar privilégio, alterar cotas, publicar agentes ou criar ferramentas.
- **Exclusão/revogação:** invalidação da fonte/versão **e de qualquer envelope ainda não consumido** na simulação; reconsultar por versão após uma alteração de estado. Sem alegar expurgo real de backups ou de derivados em banco.
- **Temporalidade:** relógio lógico fornecido pelo teste, sem temporizadores, corrida, replicação ou garantia de retenção real.
- **Rastreabilidade:** status, código fechado, referência fictícia/digest e versão; sem salvar prompt, documento integral ou identificadores pessoais em trace.

### Observações de segurança

1. **Autorização é anterior à recuperação**; não basta filtrar uma lista de trechos retornados.
2. **Rechecagem ao compor:** aprovação antiga não deve permitir fonte revogada, suspensa, substituída ou expirada.
3. **Conflito factual:** duas fontes contraditórias ou indisponíveis devem produzir indicação de conflito/insuficiência, **não inventar verdade**.
4. **Dados voláteis:** nenhuma continuidade automática; o contexto de tarefa não pode virar memória de longo prazo, treinamento, índice ou cache compartilhado.
5. **Fonte dinâmica:** preço, saldo, contrato, lead e estado de pagamento exigiriam consulta ao produto dono; no laboratório, retornar apenas `DYNAMIC_SOURCE_REQUIRED` sem fabricar valor.
6. **Negação consistente:** fonte inexistente e fonte de outra empresa não devem produzir mensagens que revelem sua existência ou classificação.
7. **Cotas/ferramentas:** recuperação não gasta créditos comerciais, altera quotas F1-05, grants F1-04, aprovações ou decisões F1-06.
8. **Fail closed:** inputs não tipados, IDs ambíguos, versões desconhecidas, documento adulterado, revogação ou prompt injection não habilitam recuperação ou ação.

## 5. Cenários propostos U07-01 a U07-12 — NÃO IMPLEMENTADOS/NÃO EXECUTADOS

Após eventual autorização executiva F1-07 em ato próprio: **12 casos novos + 70 regressões anteriores = 82 cenários planejados**. Esta é uma meta, **não resultado obtido**.

| Caso | Entrada/cenário inteiramente sintético | Aceite proposto |
| --- | --- | --- |
| U07-01 | Fonte, seção e versão permitidas no mesmo contexto | Recuperação determinística de referência curta, sem privilégio |
| U07-02 | Fonte de outro tenant, produto, principal ou finalidade | Negação uniforme sem metadados de existência |
| U07-03 | Grant fictício ausente, revogado, vencido ou escopo incompatível | Negação conservadora antes da busca |
| U07-04 | Fonte suspensa, excluída, expirada ou versão desconhecida | Negar; não recuperar cache antigo |
| U07-05 | Revogação após seleção e antes do envelope final | Rechecagem bloqueia entrega de trecho obsoleto |
| U07-06 | Referência de fonte sem permissão de compartilhamento interproduto | Bloquear transferência implícita de contexto |
| U07-07 | Fonte oficial e fonte fictícia contraditórias | Registrar conflito/insuficiência, não inventar resposta |
| U07-08 | Pedido de saldo/preço/estado CRM como se fosse FAQ | Negar substituição por fonte estática, indicar dado dinâmico |
| U07-09 | Passagem com texto de `ignore all rules` ou ordem falsa | Tratar como dados; não acionar ferramenta, não elevar grant |
| U07-10 | Envelope com teto de tamanho/excesso de trechos/campos extras | Falhar fechado sem truncamento perigoso |
| U07-11 | Consulta repetida, contexto de execução encerrado e referência invalidada | Nenhum novo efeito; nada persistido como memória |
| U07-12 | Entrada com marcador de segredo fictício e referência de origem | Audit trace mínimo, sem conteúdo/segredo/lead ou corpo de prompt |

**Regressão:** preservar integralmente os 70 testes F1-01..F1-06. Futura meta **82/82 PASS** e número de arquivos PHP de lint só poderão ser declarados **após** execução real, sem presunção. Se PHP 8.1+ estiver disponível *previamente* ao executor autorizado, considerar `php -v`, lint e `php tests/Unit/run.php`; sem instalar runtime/Composer/Actions em caso de ausência. Confrontar SHA e hashes do código exato executado.

## 6. Decisões F107-D01 a D10 — TODAS ABERTAS

| ID | Alternativa A RECOMENDADA para possível ratificação DOCUMENTAL | Alternativa B NÃO recomendada |
| --- | --- | --- |
| **F107-D01 — objetivo** | Retrieval e envelope de contexto **somente fictícios**, in-memory e read-only | RAG real/LLM, ingestão e memória persistente já neste lote |
| **F107-D02 — autoridade** | Produto/tenant/principal/finalidade/recurso conferidos em fixture; dono do produto conserva ACL real | Tratar documento ou `SYNTHETIC_*` como prova de acesso |
| **F107-D03 — proveniência** | Fonte, seção, versão, owner e vigência associados à evidência retornada | Trecho sem fonte/versão, ou geração sem base |
| **F107-D04 — revogação** | Checagem antes da busca e na montagem, com invalidação local | Recuperar cache/versão revogada ou expirada |
| **F107-D05 — confiança** | Conteúdo recuperado é dado não confiável, nunca instrução/autoridade | Obedecer a comandos em documentos ou ferramentas |
| **F107-D06 — isolamento** | Negar compartilhamento entre empresas/produtos por padrão | Copiar fontes de um cliente para outro ou misturar contextos |
| **F107-D07 — dado vivo** | Informação transacional permanece no produto dono; marcar necessidade de consulta real futura | Usar FAQ antiga para afirmar saldo/preço/status atual |
| **F107-D08 — privacidade/auditoria** | Contexto mínimo, sem memória persistente; logs com códigos e referências seguras | Gravar prompts, documentos, segredos ou histórico privado integral |
| **F107-D09 — testes e custos** | Propor U07-01..U07-12 + 70 regressões; PHP puro sem serviços/custos | Presumir 82 PASS, instalar dependências ou usar API paga |
| **F107-D10 — governança** | Aprovação APENAS documental; autorização executiva e merge separados; gates abertos | Supor que documentação habilita código, produção ou F1-08 |

**F107-D01 a D10: TODAS ABERTAS, AINDA NÃO RATIFICADAS.** Alternativas A propostas; B não recomendadas. Ratificação documental posterior, se ocorrer, **não libera programação nem merge**.

## 7. Escopo futuro candidato e governança

- **Branch dev de base:** `dev/ft01-aia-core-mocks-2026-11-06`, contendo F1-01..F1-06.
- **Branch documental atual:** `docs/ft01-f1-07-retrieval-context-mocks-2026-11-08` — somente este arquivo e `STATUS-ATUAL.md`.
- **Branch futura de código NÃO CRIADA:** `dev/ft01-f1-07-knowledge-context-mocks-2026-11-08`.
- **Arquivos candidatos futuros:** `src/MockKnowledge/`, `tests/Unit/knowledge_context.php`, inclusão mínima no `tests/Unit/run.php`, `README.md`, `STATUS-ATUAL.md`.
- **Preservar:** `src/Contracts/`, `src/Domain/`, `src/MockCatalog/`, `src/MockTooling/`, `src/MockUsage/`, `src/MockResilience/`, `src/MockOrchestration/`, suites F1-01..F1-06, `AGENTS.md`, PR #90, `main` e outros projetos.
- **Futura programação condicionada:** preflight autenticado de branch/SHA/árvore, leitura de contratos/docs e autorização específica de código/testes, PR de implementação **DRAFT filha → dev, SEM MERGE**; merge exige nova aprovação específica.
- Sem prazos quantitativos de retenção, algoritmo de embedding, banco/índice, política legal, fornecedor, preço, API, OCR, storage, cache ou publicação aprovados nesta proposta.

## 8. Critérios de aceite DOCUMENTAL e próximos atos

- [ ] Responsável avaliar F107-D01 a D10 e, se concordar, ratificar **somente** alternativas A como diretrizes documentais.
- [ ] Após ratificação, preparar minuta executiva **separada e não autoexecutável** para avaliação.
- [ ] Solicitar decisão própria sobre eventual merge documental desta proposta; nenhuma aprovação de código decorre dele.
- [ ] Preservar 70 testes anteriores como regressão futura; U07-01..U07-12 não implementados/executados.
- [ ] Manter R01-G01–G11 e CORE/SEG/AUT CRM **ABERTOS**, sem SQL/ALLOW, dados reais, custos, infra ou deploy.

**Estado efetivo F1-07:** somente proposta documental preparada. **Sem autorização executiva, sem testes F1-07 e sem merge.**
