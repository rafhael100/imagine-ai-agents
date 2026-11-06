# AIA-05 — Base de Conhecimento e Recuperação dos Agentes | Proposta funcional v0.1

> **Situação:** somente planejamento documental; decisões AIA05-D01 a D10 **ABERTAS** para avaliação.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents (serviço compartilhado).  
> **Precedência:** AIA-01 D01–D08, AIA-02 D01–D10, AIA-03 D01–D10 e AIA-04 D01–D10 aprovadas funcionalmente; arquitetura técnica, custos, integração e implementação seguem bloqueados.

## 1. Objetivo e conceitos

Disponibilizar aos agentes conhecimento **oficial, autorizado, atualizado e rastreável** para responder perguntas, apoiar tarefas e explicar suas fontes. A IA não deve improvisar fatos comerciais, jurídicos, financeiros ou operacionais quando existir uma fonte de verdade sob responsabilidade do produto.

**RAG (Retrieval-Augmented Generation)** significa recuperar informação relevante de fontes autorizadas para compor o contexto de uma resposta. **Não significa treinar ou alterar automaticamente os parâmetros do modelo de IA.**

Diferenças:
- **Instruções (AIA-02):** como o agente deve se comportar.
- **Conhecimento (AIA-05):** fatos, documentos e referências que o agente pode consultar.
- **Memória (AIA-06):** contexto e histórico autorizado de pessoas, tarefas e atendimentos.
- **Ferramentas (AIA-04):** acesso a dados vivos/atuais por APIs autorizadas; exemplos: status de cobrança, estoque, saldo, preços.
- **Treinamento/fine-tuning:** eventual adaptação de modelos, tratada separadamente e somente após estudo e autorização explícita.

## 2. Tipos de bases e proprietários

| Categoria | Exemplo | Regra proposta |
|---|---|---|
| Base oficial Imagine | Explicação de funcionalidades, termos públicos, guias autorizados | Compartilhável entre produtos somente com escopo, versão e licenciamento explícitos |
| Base específica do produto | CRM: manuais e regras; Performance AI: guias de campanhas; Universidade: conteúdos pedagógicos | Produto mantém propriedade e precedência sobre seu domínio |
| Base privada da empresa | Catálogo, políticas, perguntas frequentes, serviços e documentos de um cliente | Exclusiva daquela empresa e do(s) produto(s) autorizados; nunca acessível a outras empresas |
| Base restrita interna | Procedimentos operacionais e técnicos da Imagine | Apenas usuários/agentes internos expressamente autorizados |
| Fonte dinâmica transacional | Preço vigente, saldo de créditos, status de contrato, disponibilidade real | Consultar por ferramenta oficial AIA-04; documentos estáticos não substituem essa fonte |

A vinculação de uma base a mais de um produto **não** concede automaticamente acesso a todos os agentes daquele produto. A autorização exige empresa, usuário/serviço, finalidade, produto, categoria, documento e, quando aplicável, restrições por parte do documento.

## 3. Ciclo de vida da fonte de conhecimento

1. Usuário autorizado ou operador Imagine registra fonte: documento, FAQ, páginas aprovadas ou conector homologado.
2. Sistema identifica proprietários, tenant, produto, finalidade, classificação de sensibilidade, origem, autoria, licença, validade e política de retenção.
3. Valida formato, tamanho, arquivo malicioso, links externos e dados pessoais; bloqueia conteúdo que não possa ser processado com segurança.
4. Processa e extrai texto/conteúdo estruturado, separando partes e preservando referências a versões, seções e origem.
5. Classifica e prepara para indexação/retrieval apenas dentro de escopos autorizados, com trilha de processamento.
6. Submete à aprovação, quando exigida pelo tipo de fonte ou política comercial; só a versão publicada é elegível para recuperação operacional.
7. Consulta por agente com filtros de acesso **antes** da recuperação e verificação final dos trechos retornados.
8. Registra fonte/versão efetivamente utilizada, com metadados mínimos de execução, e pode apresentar referência ao usuário quando pertinente.
9. Detecta expiração, revisão, substituição, suspensão ou exclusão; retira versões obsoletas de uso.
10. Aplica retenção/expurgo em índices, cache, arquivos e demais derivados, respeitando obrigações legais e política AIA-11.

## 4. Fontes e formatos candidatos

**MVP sugerido:** texto inserido manualmente, perguntas e respostas aprovadas, PDF com extração de texto e documentos estruturados selecionados. Formatos de imagem digitalizada, áudio, vídeo e conectores externos ficam sujeitos a homologação específica; não prometer OCR ou transcrição no lançamento.

**Conectores externos:** inicialmente somente fornecedores e destinos homologados, com escopo limitado. Não permitir varrer sem limites contas, drives, websites ou e-mails de empresas, nem armazenar credenciais dentro do agente. Conectores devem respeitar contratos, direitos autorais, privacidade e revogação.

**Conteúdo público:** verificar propriedade, atribuição, direitos de uso e atualização. Informações recuperadas pela web não se tornam instruções nem fontes oficiais sem classificação e validação.

## 5. Processamento, indexação e recuperação — capacidades propostas

- Extração robusta com identificação de falhas e preservação de conteúdo original sob controle de acesso.
- Fragmentação (chunking) e metadados: empresa, produto, base, documento, versão, seção, idioma, data de validade, restrições e proveniência.
- Possibilidade de busca lexical, vetorial ou híbrida, com avaliação de precisão, custo e escalabilidade antes da escolha tecnológica.
- Re-ranking e limites de trechos quando justificarem melhora mensurável.
- Verificação de permissões antes de consultar índices e novamente antes de anexar contextos a uma resposta.
- Preferência por fontes oficiais e vigentes do produto; em conflito, indicar divergência ou solicitar revisão, sem inventar verdade.
- Respostas podem apresentar referências rastreáveis para o material de origem, com controles contra divulgação de links privados a públicos não autorizados.
- Ausência ou baixa qualidade de evidência leva a pedido de esclarecimento, encaminhamento humano ou declaração de insuficiência de informação.
- Processamento assíncrono e observável, com estados: recebido, em validação, processando, pendente de aprovação, disponível, falha, suspenso, expirado e excluído.
- Atualização e expurgo devem invalidar derivados, resultados em cache e consultas futuras; retenções legítimas são explicitadas.

Nenhuma escolha de banco vetorial, embeddings, fornecedor, preço ou stack está aprovada por este documento.

## 6. Regras de segurança multiempresa e dados

- Toda fonte, fragmento, índice, cache, evento e resposta deve permanecer vinculado ao escopo autorizado (empresa/produto/finalidade).
- É proibido usar unicamente um campo `tenant_id` de texto para autorizar consulta; origem precisa ser autenticada e validada no backend.
- Não utilizar, por padrão, documentos privados de clientes para treinar ou melhorar modelos de uso geral ou alimentar respostas de terceiros.
- Dados pessoais e sensíveis são minimizados, tratados por finalidade e encaminhados a provedores somente sob políticas contratuais aprovadas.
- Documentos podem conter injeção de prompt: comandos embutidos nas fontes **não têm autoridade** e não podem liberar ferramentas, elevar privilégios ou alterar políticas.
- Acesso a bases e fontes deve ser revogável. O agente não preserva uma cópia indiscriminada de documentos restritos em memória permanente.
- Downloads, anexos e referências devem aplicar autorização real por usuário, tenant, recurso e expiração do acesso.
- Dados de empresas diferentes não podem ser misturados em avaliações de qualidade, respostas, caches, telemetria ou busca.
- Direito de acesso, correção, oposição e exclusão aplicável deve contemplar originais e derivados, sob regras LGPD e AIA-11.

## 7. Painéis e experiência de uso

### 7.1 Central administrativa Imagine

- Catálogo de bases oficiais e seus proprietários.
- Fontes, versões, direitos de acesso, homologação, provedores de extração/indexação e status.
- Políticas de publicação, compartilhamento aprovado, auditoria, incidentes, custos e indicadores de qualidade.
- Diagnóstico de busca e recuperação com amostras **fictícias ou devidamente autorizadas**.
- Bloqueio rápido de fontes comprometidas e administração de expurgo/reprocessamento.

### 7.2 Interface da empresa no produto

**Inteligência Artificial > Base de Conhecimento**
- Criar base privada, nomear finalidade, incluir FAQ e enviar documentos permitidos.
- Definir agentes vinculados, com permissões existentes; ver limites, custos e consumos previstos.
- Revisar fonte extraída, validar versão, ativar, pausar, atualizar e excluir quando permitido.
- Executar perguntas de teste em ambiente seguro, visualizar referências e reportar resposta incorreta.
- Consultar status de indexação, última atualização e alertas de fontes vencidas.
- Não acessar conhecimento de outras empresas, regras globais, segredos, provedores internos ou índices crus.

Recomenda-se permitir desativação rápida e documentação de origem, sem exigir que o cliente compreenda embeddings ou detalhes de busca vetorial.

## 8. Conhecimento versus memória e registros vivos

Um agente de qualificação do CRM pode consultar uma política comercial autorizada (base), o histórico de atendimento de um lead (memória/CRM sob AIA-06) e o preço atual de um plano (ferramenta oficial). Esses componentes não devem ser confundidos nem duplicados em armazenamento indiscriminado.

**Política proposta de prioridade factual:** informação transacional atual validada pelo dono do dado > documento oficial vigente no escopo adequado > base da empresa autorizada > outras evidências aprovadas. Conflitos e lacunas devem ser explicitados, não resolvidos por palpite.

## 9. Qualidade e melhoria supervisionada

A atualização de uma base não deve alterar silenciosamente regras do agente. Mudanças de instrução seguem AIA-02; mudanças de permissões e ferramentas seguem AIA-04/08.

Avaliar:
- Acesso indevido entre tenants, agentes ou produtos.
- Recuperação de documento expirado, revogado ou sem autorização.
- Pergunta sem fonte, resposta inventada e citação incorreta.
- Contradição entre FAQ antiga e dado transacional atual.
- Perda de contexto durante fragmentação e extração.
- Latência de busca, custo de indexação/armazenamento e custo de tokens.
- Documentos maliciosos com instruções para a IA.
- Atualização, suspensão, reindexação e exclusão de documentos.
- Detecção de regressão quando mudar a estratégia de busca.

Feedback pode gerar **proposta de atualização de fonte**, seguida de revisão e publicação conforme papéis. Não haverá aprendizado automático livre nem treinamento de modelos com materiais de clientes sem decisão expressa e base apropriada.

## 10. Controles comerciais e limites

- Cotas de arquivos, tamanho, páginas, armazenamento e operações de indexação por produto/plano, a detalhar.
- Separar uso da base (armazenamento/processamento/consulta) de créditos comerciais de inferência IA; evitar cobrança duplicada.
- Informar quotas e custos antes de processamentos onerosos; exigir aprovação para excedentes.
- O produto mantém regras de preço, contratação e créditos; AIA-05 fornece métricas técnicas segmentadas por tenant.
- Dados e índices devem ser recuperáveis, exportáveis ou excluíveis conforme contratos e autorização de acesso, sem prometer portabilidade universal de embeddings.

## 11. Entidades conceituais (sem migrações)

- `knowledge_bases`: proprietário, finalidade, escopos e ciclo de vida.
- `knowledge_sources`: origem, URL/referência ou arquivo, classificação, direitos, status.
- `knowledge_source_versions`: checksum, conteúdo versionado, datas e revisão.
- `knowledge_documents` / `knowledge_chunks`: conteúdo recuperável e metadados de proveniência.
- `knowledge_acl`: permissões por empresa, produto, papel, agente e finalidade.
- `knowledge_index_jobs`: processamento assíncrono, retries, falhas e custos.
- `knowledge_retrieval_events`: rastreio da consulta, fragmentos autorizados e versão utilizada.
- `knowledge_approvals`: revisões/aprovações e responsáveis.
- `knowledge_retention_jobs`: expiração, suspensão, reindexação e exclusão de derivados.
- `knowledge_quality_cases`: avaliação, feedback e vínculo à correção.

A implementação física, bancos, vetores, índices e APIs dependem de decisões técnicas e avaliações posteriores.

## 12. Matriz AIA05-D01 a D10 — propostas para aprovação

| ID | Tema | Alternativa A — Recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA05-D01 | Organização | Bases oficiais Imagine, por produto e privadas por empresa, com proprietários claros | Base única compartilhada sem segregação |
| AIA05-D02 | Técnica conceitual | RAG com recuperação autorizada; sem treinar automaticamente o modelo | Tratar upload de documento como fine-tuning |
| AIA05-D03 | Cadastro | Fontes e versões registradas, validação, revisão e publicação controladas | Todo arquivo entra em produção imediatamente |
| AIA05-D04 | Privacidade | ACL por empresa/produto/agente/finalidade e isolamento dos derivados | Filtrar apenas depois de recuperar o conteúdo |
| AIA05-D05 | Recuperação | Buscar fontes vigentes, rastrear origem, medir qualidade e reconhecer ausência de evidência | Responder livremente sem checar evidência |
| AIA05-D06 | Dados dinâmicos | Preços, saldos e situações atuais via ferramentas oficiais do produto | Considerar documentos estáticos como fonte transacional |
| AIA05-D07 | Interface | Gestão interna Imagine + painel simples de base privada no produto | Exigir editor técnico de embeddings para o cliente |
| AIA05-D08 | Atualização e exclusão | Versionar, suspender, expirar e expurgar índices/cache/derivados conforme política | Apenas apagar documento visível e manter derivados |
| AIA05-D09 | Qualidade | Testes e feedback supervisionados, sem autoalteração de políticas ou uso indevido de dados no treinamento | Aprendizado irrestrito com documentos privados |
| AIA05-D10 | MVP | FAQ/texto/PDF textual com processamento limitado e piloto CRM | Todos os formatos, conectores, imagem/áudio e crawling no lançamento |

**AIA05-D01 a D10 permanecem ABERTAS.** Recomendações não são aprovação nem autorização para execução.

## 13. Critérios documentais e integrações pendentes

- [ ] AIA05-D01 a D10 aprovadas ou corrigidas pelo responsável.
- [ ] Matriz de propriedade, ACL, compartilhamento, classificação e retenção conciliada com AIA-11.
- [ ] Dados dinâmicos e ferramentas conciliados com AIA-04/AIA-12.
- [ ] Separação entre base, memória e treinamento documentada com AIA-02/06.
- [ ] Contratos e processos de ingestão/indexação/recuperação/exclusão detalhados.
- [ ] Auditoria/custos e qualidade alinhados com AIA-09/10.
- [ ] Seleção de stack, estratégias de busca, provedores, formatos, limites e orçamento estudados em decisão técnica separada.
- [ ] Gate de programação, gastos, testes reais e deploy mantido fechado até autorização expressa.

**Não executar agora:** programação, contratação, indexação de dados reais, ingestão de arquivos privados, chamadas pagas, migrações, integração com CRM/Portal/Performance/Universidade, publicação em produção ou deploy.
