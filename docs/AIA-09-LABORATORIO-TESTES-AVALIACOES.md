# AIA-09 — Laboratório, Testes e Avaliação dos Agentes | Proposta funcional v0.1

> **Situação:** **AIA09-D01 a D10 APROVADAS FUNCIONALMENTE em 2026-11-06** pelo responsável. Testes reais, implementação e implantação permanecem bloqueados.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-08 possuem diretrizes funcionais aprovadas. Não existe autorização de programação, testes funcionais reais, contratação, uso de APIs pagas ou deploy.

## 1. Objetivo

Criar um laboratório central para demonstrar, com **evidências reproduzíveis**, se cada versão de agente, instrução, ferramenta, modelo e fluxo é suficientemente útil, segura, confiável e economicamente adequada para a finalidade autorizada. Uma versão não poderá ser liberada apenas porque uma resposta de exemplo pareceu boa.

Os testes deverão cobrir comportamento e consequências da execução, distinguindo:
- **Testes documentais:** cenários, critérios de aceite e dados fictícios em planejamento (fase atual).
- **Simulação técnica isolada:** execução futura com mocks, stubs e dados sintéticos após aprovação específica.
- **Homologação integrada:** APIs e ambientes homologados, após autorização e requisitos de segurança.
- **Produção controlada:** piloto supervisionado com controles de risco, monitoramento e rollback, após gates de implantação.

**Não confundir resultado de simulação com evidência de operação real.**

## 2. Responsabilidades do laboratório

| Componente | Papel |
|---|---|
| AIA-01 | Determina o template do agente e versões elegíveis |
| AIA-02 | Versiona instruções, parâmetros e perfis de personalidade sob teste |
| AIA-03 | Identifica provedores/modelos e custos sob comparação |
| AIA-04 | Define ferramentas homologadas e contratos seguros |
| AIA-05 | Fornece fontes de conhecimento autorizadas e referências verificáveis |
| AIA-06 | Define contexto, memória, retenção e continuidade |
| AIA-07 | Disponibiliza máquina de estados, etapas, filas e idempotência |
| AIA-08 | Define risco, permissões, alçadas, aprovações e bloqueios |
| AIA-09 | Mantém cenários, execuções de avaliação, resultados, revisão e gates de qualidade |
| AIA-10/11 | Observabilidade, custo, privacidade, auditoria e incidentes |
| AIA-12 | Contratos e interfaces técnicas de execução e avaliação |

## 3. Catálogo de cenários

Cada cenário deverá registrar: identificador, objetivo, produto, agente/versão, nível de risco, contexto de entrada **sintético ou autorizado**, pré-condições, ferramentas simuladas, resultado esperado, condições de reprovação, evidências e responsável.

Categorias candidatas:
1. **Funcionalidade:** resposta correta, classificação, resumo, extração estruturada, seguimento de instruções e idioma.
2. **Confiabilidade factual:** evidências, citações, recusa quando não há fonte, contradição e incerteza.
3. **Segurança multiempresa:** consulta de dados de outro tenant, troca de contexto, vazamento por memória/cache e identificadores manipulados.
4. **Permissões e alçadas:** ação proibida, aprovação recusada, expirada, revogada ou falsificada; mudanças após aprovação.
5. **Injeção de instruções:** documentos, sites e saídas de ferramentas que tentam assumir autoridade, extrair segredos ou invocar operações.
6. **Ferramentas:** schema inválido, acesso indevido, mutações duplicadas e erros de autorização no backend.
7. **Execução e filas:** timeout, repetição, concorrência, checkpoints, dependência indisponível, estado indeterminado e reconciliação.
8. **Memória e conhecimento:** dado desatualizado, conteúdo excluído, origem incorreta, retenção e atualização de fontes.
9. **Resiliência/custos:** troca de provedor, limites, excedentes, latência, uso excessivo de tokens e efeito comercial no produto.
10. **Experiência:** clareza, utilidade, idioma, tom, resposta responsável e encaminhamento humano.

## 4. Datasets e privacidade

- Dar preferência a **dados sintéticos e casos ficcionais** versionados, rotulados e auditáveis.
- Não importar leads, prontuários, campanhas, contratos ou conversas privadas de clientes para laboratório por padrão.
- Se futuramente houver dados reais, exigir autorização de acesso, base legal/finalidade, minimização, ambiente segregado, controle de retenção e análise específica de riscos.
- Separar os conjuntos de desenvolvimento, validação e regressão para reduzir adaptação oportunista às perguntas conhecidas.
- Dados de uma empresa nunca poderão ser usados como casos para outra nem compartilhados com provedores sem autorização e contrato compatíveis.
- Conteúdo hostil deve ser marcado como dados não confiáveis; não pode executar ações reais de ferramenta durante os testes.

## 5. Tipos de avaliação

| Método | Finalidade | Limitação |
|---|---|---|
| Asserção determinística | Verificar schema, estado, autorização, escopo, idempotência, regras rígidas | Não mede sozinho a qualidade semântica |
| Rubrica humana | Avaliar utilidade, linguagem, completude e evidências | Requer calibragem, amostragem e revisão |
| Avaliador automatizado | Auxiliar classificação de erros e qualidade de conteúdo | Pode errar, variar e herdar vieses; não substitui gates críticos |
| Comparação A/B offline | Comparar versões com o mesmo dataset e orçamento | Amostra e distribuição podem não representar produção |
| Teste adversarial | Buscar falhas de segurança, vazamento e abuso de ferramenta | Cobertura finita: aprovação não prova risco zero |
| Observação de piloto | Medir comportamento em ambiente supervisionado | Requer aprovação técnica e operacional posterior |

**Regras duras de segurança e autorização deverão ser verificadas por código e critérios determinísticos**, não por opinião do modelo.

## 6. Métricas candidatas

- **Qualidade:** exatidão factual, completude, utilidade, groundedness, adequação do idioma/tom.
- **Segurança:** acessos indevidos, tentativa de execução não autorizada, injeção bem-sucedida, exposição de dados, bypass de aprovação.
- **Confiabilidade:** taxa de conclusão confirmada, erros por ferramenta, reexecuções indevidas, recuperações e cancelamentos consistentes.
- **Operação:** p50/p95 de latência, tempos de espera por fila, custo por tarefa concluída, consumo de tokens/créditos, taxa de escalonamento.
- **Produto:** satisfação/avaliação de operadores (quando disponível), taxa de correções humanas e relevância do resultado no caso de uso.

Evitar um único "score global" que esconda falhas críticas. Segurança e privacidade devem ser **gates eliminatórios** quando aplicável, independentemente da qualidade média.

## 7. Etapas da validação e promoção

1. Cadastrar versão candidata e escopo da alteração (instruções, modelo, tool, conhecimento, memória, fluxo).
2. Aplicar checks documentais de risco, permissões e compatibilidade.
3. Rodar suíte isolada de casos sintéticos e registrar evidências, quando futuramente autorizado.
4. Revisar falhas determinísticas, segurança, comportamento e custos.
5. Comparar com baseline da versão homologada: regressão crítica reprova.
6. Pedir parecer a responsáveis adequados por produto, segurança, dados e operação conforme risco.
7. Aprovar ou recusar a versão com justificativa e critérios reproduzíveis.
8. Publicar de forma gradual com monitoramento e possibilidade de suspender/voltar para versão anterior.
9. Investigar incidentes e adicionar casos de regressão.
10. Reavaliar após alteração material do modelo, fornecedor, fonte de conhecimento, ferramenta, esquema ou regra.

**A IA poderá sugerir ajustes**, mas a promoção de instruções oficiais, autonomia, ferramentas e modelos dependerá de revisão e aprovação autorizadas.

## 8. Estados da avaliação

Estados candidatos: `RASCUNHO -> PREPARADA -> EM_EXECUCAO -> EM_REVISAO -> APROVADA / REPROVADA / CANCELADA / EXPIRADA`.

Resultados de cenários: `PASSOU`, `FALHOU`, `INCONCLUSIVO`, `NAO_EXECUTADO`.

- Resultado inconclusivo **não** é considerado aprovado.
- Falha de infraestrutura deve ser separada de erro de comportamento.
- Mudança de dataset, critérios ou versão durante uma rodada invalida a comparação, exigindo nova rodada identificada.
- Avaliações devem registrar versão exata de modelo, prompt, ferramentas e ambiente quando efetivamente executadas.
- A aprovação do laboratório não substitui a aprovação de implantação nem contratos do produto.

## 9. Interfaces e papéis

**Central Imagine — Laboratório**
- Suítes, casos, datasets, rubricas, baselines e evidências.
- Comparação de versões, falhas por gravidade, custo/latência e análise de regressão.
- Revisão técnica, segurança, privacidade, homologação e publicação com alçadas.
- Histórico de aprovações, recusas, exceções, revisões e incidentes.

**Dentro dos produtos**
- Teste controlado de configuração permitida ao cliente, sem efeitos externos.
- Prévia do comportamento e recomendações de ajuste dentro do plano.
- Feedback sobre respostas e correções propostas.
- Nenhuma exposição de datasets privados da Imagine, segredos técnicos, regras globais ou resultados de outras empresas.

**Papéis candidatos:** autor de caso, revisor de produto, segurança, responsável por privacidade, aprovador de versão, auditor e operador. Para riscos elevados, limitar conflitos entre quem cria critérios, avalia e aprova publicação.

## 10. Falhas graves e política de bloqueio

Exemplos eliminatórios:
- Dados de outro tenant apresentados ou utilizados indevidamente.
- Execução de ferramenta sem permissão efetiva ou aprovação exigida.
- Exposição de credenciais/segredos.
- Ação financeira, publicação ou exclusão sem controles do backend.
- Reexecução duplicada de operação com efeito externo após timeout.
- Ignorar suspensão/revogação de agente, ferramenta ou autorização.

Falhas de qualidade e performance devem ter limiares definidos por caso de uso **antes** da execução. Não declarar percentuais de aprovação, disponibilidade, benchmark ou custo sem medições reais.

## 11. Entidades conceituais (não são migrações)

- `evaluation_suites`: coleção versionada de casos, finalidade e produto.
- `evaluation_scenarios`: casos, requisitos, severidade e resultados esperados.
- `evaluation_datasets`: origem, classificação, autorização, versão e retenção.
- `evaluation_rubrics`: critérios explícitos e dimensões avaliadas.
- `evaluation_runs`: rodada, versões do agente/modelo/ferramentas, ambiente e estado.
- `evaluation_results`: status, pontuações, falhas e evidências mínimas.
- `evaluation_reviews`: parecer, aprovador, justificativa e escopo.
- `evaluation_baselines`: versão homologada de referência.
- `evaluation_release_gates`: critérios de liberação e bloqueio por risco.
- `evaluation_regressions`: incidentes e casos recorrentes.

A stack, a armazenagem e os identificadores físicos não foram definidos.

## 12. Diretrizes AIA09-D01 a D10 — aprovadas funcionalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA09-D01 | Laboratório | Laboratório central versionado, com avaliação específica por produto/tenant | Avaliações informais sem registro |
| AIA09-D02 | Dados de teste | Dados sintéticos por padrão e uso excepcional de dados reais sob aprovação | Copiar dados privados para testes automaticamente |
| AIA09-D03 | Tipos de teste | Combinar asserções determinísticas, rubricas humanas, adversarial e avaliação automatizada auxiliar | Somente um modelo IA julgando outro |
| AIA09-D04 | Segurança | Falhas graves de privacidade/autorização como gates eliminatórios | Aprovar por média alta mesmo com vazamento |
| AIA09-D05 | Métricas | Medir qualidade, segurança, custo, latência e conclusão separadamente | Um índice opaco único |
| AIA09-D06 | Versionamento | Fixar agente, prompts, tools, modelo, dataset, rubrica e baseline por rodada | Comparar versões mutáveis sem evidências |
| AIA09-D07 | Publicação | Avaliação + revisão + aprovação antes de promover versões, com rollback | Publicar automaticamente após testes superficiais |
| AIA09-D08 | Melhoria | Feedback gera proposta supervisionada e nova avaliação | Agentes reescrevem regras e se aprovam sozinhos |
| AIA09-D09 | Interface | Central Imagine + simulação limitada nos produtos, sem efeitos externos | Editor de testes e ferramentas irrestrito ao cliente |
| AIA09-D10 | MVP | Suíte de regressão do agente CRM: respostas, dados, permissões, tool calling, erros e custo | Benchmarks multimodais completos desde a primeira entrega |

**AIA09-D01 a D10: APROVADAS FUNCIONALMENTE em 2026-11-06.** Os gates de homologação técnica, limites, implementação, testes reais, consumo e deploy permanecem abertos.

## 13. Critérios documentais e gates pendentes

- [x] AIA09-D01 a D10 aprovadas funcionalmente pelo responsável em 2026-11-06.
- [ ] Matriz de severidade, limiares por cenário/produto e responsáveis aprovada.
- [ ] Política de dados, LGPD, segregação e retenção alinhada a AIA-11.
- [ ] Contratos de execução e evidências alinhados a AIA-07/08/10/12.
- [ ] Suíte mínima do piloto CRM e critérios de não regressão documentados.
- [ ] Especificar limites/contratos de fornecedores e custo antes de executar testes.
- [ ] Decidir stack técnica, orçamento e recursos somente em gate posterior.
- [ ] Autorização expressa de implementação/testes reais/deploy separada da aprovação funcional.

**Gates mantidos:** sem código, testes funcionais reais, chamadas pagas, credenciais, dados privados de clientes, alterações em outros repositórios, migrações, infraestrutura ou deploy.
