# AIA-R02/R04 — Dados, Auditoria, Retenção e Privacidade nos Fluxos e Efeitos | Proposta documental v0.1

> **Situação:** diretrizes **R02-R04-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente preliminares; R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.  
> **Data de referência:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, R02/S01–S05 e R02/R01–R03 ratificados **somente documentalmente**.  
> **Bloqueios:** R01-G01 a G11 permanecem TODOS ABERTOS (especialmente G05/G06/G07/G08/G10); CORE/SEG/AUT e gates próprios do CRM continuam abertos. Este documento não aprova prazos específicos, base legal por fluxo, fornecedor, infraestrutura, integração real, código, SQL, ALLOW, testes funcionais, despesas ou deploy.

## 1. Objetivo e fronteiras de autoridade

Detalhar os **contratos de dados e evidências** necessários para que o núcleo Imagine AI Agents execute tarefas de IA em nome de produtos Imagine, especialmente o piloto CRM, sem se tornar um repositório irrestrito de leads, atendimentos, conversas, credenciais ou conteúdos privados.

- **Produto de origem (CRM no piloto):** autentica e autoriza leitura de recursos, decide quais campos podem sair, controla suas políticas de histórico, direitos, contrato/plano, gravações, retenção e exclusão.
- **Núcleo AIA:** processa apenas finalidade autorizada, acessa ferramentas homologadas, produz sugestões, registra mínimos técnicos e respeita a revogação e a exclusão aplicáveis à sua custódia.
- **Produto executor:** comprova efeitos de negócio; AIA não conclui que houve gravação ou tarefa a partir de texto gerado, aprovação ou callback de transporte.
- **Fornecedores de IA e serviços técnicos:** acesso a dados apenas mediante análise de finalidade, contrato, condições de tratamento, segurança, suboperadores e transferência conforme aplicável.

A definição jurídica de controlador/operador e eventuais suboperadores **será feita por fluxo, contrato e finalidade** mediante revisão responsável. Uma arquitetura ou decisão funcional não equivale, por si, a parecer de conformidade LGPD.

## 2. Inventário de fluxos do piloto CRM

| Fluxo | Dados mínimos pretendidos | Dono da fonte de verdade | Saída e possível derivado | Gate aberto |
| --- | --- | --- | --- | --- |
| Solicitar resumo do lead | Referência autenticada de empresa, lead, finalidade e campos autorizados | CRM | Execução AIA, resumo sugerido e consumo técnico | G01/G02/G05/G06 |
| Consultar interações | Trechos/históricos autorizados por principal e recurso | CRM | Contexto transitório e eventual evidência referencial | G02/G05 |
| Classificação sugerida | Atributos necessários e permitidos, sem replicação integral do banco | CRM | Score/justificativa sugeridos, não status oficial do lead | G02/G05/G07 |
| Registrar resumo | Conteúdo revisado ou elegível e referência do atendimento | CRM | Histórico versionado, evidência de gravação | G03/G04/G05/G08 |
| Criar tarefa | Lead, responsável, descrição, prazo e parâmetros materiais validados | CRM | Tarefa/ID de efeito confirmado; intenção e auditoria referencial | G02/G03/G04/G08 |
| Acompanhar run | Estado, produto/tenant e referência do solicitante | AIA | Status/erros auditáveis sob ACL | G01/G05/G10 |
| Contabilizar consumo | Uso técnico por run/attempt, modelo, quantidade e referência de cobrança autorizada | AIA/CRM, por finalidade | Métrica técnica e evento de conciliação; não cobrança automática | G06/G08 |
| Atender exclusão/correção | Solicitação autenticada, registros e derivados correlacionados | Produto responsável e custodiantes | Expurgo/correção com comprovação mínima | G05/G10 |

Esta tabela **não é um inventário definitivo de dados reais**. Campos, bases legais, categoria, retenção, jurisdição e destinatários necessitam de análise caso a caso.

## 3. Classes de proteção e limites

Proposta de **classificação documental**, a homologar com políticas oficiais:
- **C0 — público expressamente autorizado:** conteúdos abertos e licenciados, com proveniência.
- **C1 — técnico/operacional interno:** versões, IDs não diretamente identificáveis quando possível, saúde e status.
- **C2 — confidencial empresarial:** detalhes comerciais, campanhas, leads e conhecimento privado da organização.
- **C3 — pessoal identificável:** nomes, contatos, conversas, identificadores vinculáveis e histórico de atendimento.
- **C4 — alto risco e/ou legalmente sensível:** credenciais e segredos, dados pessoais sensíveis quando presentes, informações altamente restritas.

Categorias podem se sobrepor; **credenciais não entram em prompts nem em logs**. A classe C4 exige bloqueio por padrão no MVP até avaliação específica e necessidade comprovada. Alto risco operacional não é automaticamente a mesma categoria jurídica de dado pessoal sensível.

A minimização deve ocorrer **antes de consultar o modelo**. Não depender de instrução textual “ignore dados pessoais” para substituir controles de campos, ACL e custódia.

## 4. Matriz de armazenamento e derivados

| Objeto | Onde permanece a referência proprietária | Cópia/derivado possível no AIA | Regra conceitual |
| --- | --- | --- | --- |
| Lead, contato e atendimento | CRM | Somente contexto transitório mínimo autorizado | Não duplicar base inteira |
| Resumo validado no histórico | CRM | Resultado de execução/metadata referencial, quando necessário | CRM controla versão, correção e consulta |
| Sugestão não validada | AIA durante a run, conforme finalidade | Curta duração e escopo de acesso restrito a definir | Não representar como dado oficial do CRM |
| Memória e conhecimento privado | Produto originário + custódia homologada se houver | Derivados sob tenant/propósito, indexação e política própria | AIA-05/06 e direitos de remoção |
| Embeddings, chunks e índices | Custodiante definido em contrato | Representações sujeitas a classificação, isolamento e expurgo | Não tratar vetor como anônimo por pressuposição |
| Checkpoint, fila e payload de falha | AIA quando necessário para execução | Dados minimizados, identidade e estado verificáveis | Expiração e autorização de retomada |
| Eventos de aprovação/tentativa/efeito | Produto executor/aprovador e trilha AIA referencial | Identificadores e evidências mínimas | Origem, integridade, retenção e acesso auditado |
| Logs/observabilidade | Operador técnico autorizado | Sem prompts completos por padrão | Redação, ACL, rastreabilidade de consulta |
| Backup/snapshot | Custodiante da infraestrutura/serviço | Dados sujeitos a políticas e restauração controlada | Não reativar derivados excluídos |

Não escolher ainda localização, banco, índices, tecnologia de criptografia, armazenamento físico, TTL, chave de deduplicação nem modelo de backup.

## 5. Fluxo de dados CRM → AIA → CRM

1. CRM autentica tenant, principal e recurso; aplica suas próprias permissões por organização, lead, atendimento, campo e finalidade.
2. CRM formula pedido de execução AIA com **referências verificáveis**, sem supor vínculo interproduto por e-mail/CNPJ.
3. AIA verifica contrato de confiança, agente, ferramenta, escopo, finalidade, plano, quota e estado da organização.
4. Ferramenta CRM realiza consulta autenticada e CRM **revalida** os direitos no momento da leitura, reduzindo conteúdo permitido.
5. AIA compõe contexto temporário; documentos externos, anexos e mensagens recuperados são **dados não confiáveis**, sem autoridade para redefinir regras.
6. Modelo/fornecedor homologado recebe somente conteúdo mínimo para o caso e finalidade; antes disso, verificar condições contratuais, retenção e eventuais transferências.
7. AIA retorna sugestão de resumo/classificação com versão, incerteza relevante e limites; **não grava no CRM por inferência**.
8. Se houver intenção de gravar resumo/criar tarefa, aplica-se AIA-R02/R03: aprovação quando exigida, ferramenta específica, revalidação no backend CRM, idempotência e efeito confirmado.
9. AIA conserva evidência técnica mínima de pedido, intenção, tentativa, status, efeito e consumo; o CRM conserva histórico comercial/funcional próprio.
10. Revogação, correção, exclusão e desativação devem propagar eventos/solicitações a derivados aplicáveis com trilha e sem exposição cruzada.

## 6. Auditoria minimizada versus comprovação de efeito

Para garantir rastreabilidade **sem copiar todos os dados privados**, uma trilha referencial pode conter:
- `event_ref`, `correlation_ref`, `run_ref`, `step_ref`, `intent_ref`, `attempt_ref`, `effect_ref` e `approval_ref` se aplicáveis;
- referências verificadas e protegidas a produto, tenant, principal, delegação, recurso e finalidade;
- identidade e versão de política, ferramenta, agente, contrato, regra de retenção e provedor;
- decisão de autorização/negação, horário de evento, tipo de confirmação e resultado de conciliação;
- classificação de dado, identificador de acesso à evidência material autorizada e histórico de consulta da própria auditoria;
- consumo técnico e evento de conciliação, distinto de cobrança comercial.

**Tensão documental:** S05/R03 exige vincular parâmetros materiais à aprovação; S04 exige minimizar dados. A solução futura deverá preservar prova de **identidade/versão/integridade da proposta** e acesso controlado ao conteúdo necessário sob retenção legítima, sem presumir que hash, pseudônimo ou token sejam anonimização definitiva. Para evidências de alto risco, definir contrato específico de custódia e consulta.

Não expor stack traces, tokens, prompts integrais, telefones ou conversas em logs comuns. Acesso do operador Imagine ao painel técnico **não** concede leitura indiscriminada do CRM.

## 7. Retenção, exclusão, correção e backups

**Não definir prazo único** para contexto, RAG, memórias, filas, logs ou backups do núcleo, nem substituir prazos já aprovados dos produtos.

Definir em etapa de homologação, por `produto + finalidade + classe + categoria + custodiante`:
- início/fim do período, evento de expiração, razão legítima de conservação, estado comercial e obrigações aplicáveis;
- propriedade e acesso à versão oficial, tratamento de dados corrigidos e memórias derivadas antigas;
- prazo de contexto transitório, fila de falha, deduplicação, índices, checkpoints e registros de incidentes;
- expurgo de cópias autorizadas, embeddings, snippets, caches e reproduções em datasets, quando aplicável;
- backups sob retenção legítima, acesso restrito e **reconciliação de exclusões na restauração**, sem prometer apagamento físico instantâneo universal;
- confirmação mínima de tratamento e possível exceção legal/contratual, com revisão por responsáveis.

O período aprovado para reenvio de leads do **Imagine Sites** (72h de entrega inicial + 360h de fila de falhas, total 432h) **não** vira TTL universal das execuções AIA; é política de um fluxo próprio. Políticas de retenção e cancelamento já decididas no CRM também **não** são alteradas por esta documentação.

**Falhas seguras:** um job expirado ou de tenant desvinculado não deve recuperar dados eliminados de backups ou memória para completar execução antiga. O status deve indicar impedimento e, quando apropriado, necessidade de reconciliação.

## 8. Solicitações de titulares, compartilhamento e terceiros

- O **produto responsável** recebe/coordena solicitação autenticada de acesso, confirmação, correção, oposição, bloqueio ou eliminação, conforme requisitos aplicáveis, e propaga instruções aos custodiantes AIA e provedores envolvidos.
- Titular dos dados, funcionário do cliente e administrador do tenant são sujeitos potencialmente distintos; nem todo pedido do administrador permite revelar dados de terceiros.
- Compartilhamento entre produtos exige contrato **específico**, finalidade, vínculo comprovado, direitos/autorizações e base jurídica adequada; conta única do Portal não substitui ACL do CRM.
- Antes de enviar dados a modelos e serviços externos, revisar termos efetivos do plano/API, uso para treinamento, retenção, subprocessamento, incidentes, localização e transferências internacionais. Definir responsáveis por avaliação jurídica e contratual.
- Se a finalidade for incompatível, o provedor não estiver homologado ou houver falha de permissão, **não transmitir dados reais**; aplicar alternativa segura somente se formalmente aprovada.
- Não criar “aprendizado global” de agentes com dados de clientes por padrão. Melhorias supervisionadas com datasets sintéticos ou material legalmente autorizado seguem AIA-09/11.

## 9. Incidentes e cruzamento indevido de dados

Exemplos de sinais que exigem contenção e avaliação responsável:
- lead, chunk RAG, embedding, contexto ou memória de tenant A retornado ao tenant B;
- payload, prompt, credencial ou telefone em telemetria aberta ou callback incorreto;
- modelo ou terceiro recebe dado antes de homologação ou fora da finalidade autorizada;
- retorno de resumo com informação pessoal extraída de fonte não autorizada;
- backup restaura derivado eliminado e o torna acessível;
- alteração não autorizada em provas de aprovação/efeito ou acesso ao histórico de auditoria;
- retenção expirada com novas tentativas que continuam reprocessando o payload;
- log/replay com parâmetros materiais reproduzidos em outra empresa;
- ausência de prova de eliminação ou de efeito confirmado usada para marcar processo como concluído.

As respostas futuras deverão prever **detecção, bloqueio, preservação de evidências mínimas, responsável, classificação, investigação, comunicação quando exigida e ações corretivas**, sem automatizar comunicação legal ou eliminar provas indiscriminadamente. Essas rotas ainda requerem política técnica e jurídica específica.

## 10. Casos sintéticos obrigatórios para homologação futura

| Caso | Resultado documental exigido |
| --- | --- |
| Contexto de lead de empresa A aparece em fila do tenant B | Negar acesso e registrar falha de isolamento |
| Embedding de documento excluído ainda retorna em busca | Bloquear uso e conciliar expurgo de índice e caches |
| Resumo oficial corrigido, derivado antigo persistente | Invalidar/corrigir derivado e impedir uso de memória obsoleta |
| Dados pessoais completos são gravados em log de erro | Minimizar, controlar acesso e tratar incidente |
| Consulta de auditoria por operador não autorizado | Negar, registrar tentativa sem expor conteúdo |
| Provedor com cláusula de retenção incompatível | Não enviar dados reais sem homologação |
| Callback com referência de execução de outro tenant | Rejeitar, auditar e não alterar estado |
| Operação de exclusão bloqueada por retenção legal legítima | Registrar fundamento, responsável e acesso restrito; não mentir sobre exclusão |
| Restauração de backup reintroduz usuário/índice excluído | Impedir disponibilidade indevida e reconciliar exclusões |
| Usuário solicita resumo mas só pode ver campos restritos | Filtrar ou negar antes da inferência |
| Aprovação material precisa ser provada sem logar payload integral | Usar evidência referencial/integridade sob controle e política aprovada |
| Cancelamento da run após tarefa já confirmada | Não apagar tarefa do CRM automaticamente nem apagar evidência |
| Retry de inferência consome tokens mas não cria efeito CRM adicional | Registrar uso técnico real e evitar crédito comercial duplicado |
| Dado pessoal do cliente entra em dataset de melhoria global | Bloquear sem fundamento, autorização e contrato específicos |
| Exigência de expurgo conflita com estado indeterminado de efeito | Preservar apenas evidência mínima legitimamente necessária e escalar conciliação |

São **cenários propostos**, não testes executados. Critérios eliminatórios de AIA-09 ainda aguardam homologação.

## 11. Diretrizes R02-R04-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R04-D01 | Autoridade dos dados | Produto é fonte de verdade; AIA recebe referências e conteúdo mínimo autorizado | Núcleo replica integralmente os bancos dos produtos |
| R02-R04-D02 | Classificação/inventário | Inventário por finalidade, classe, origem, produto, tenant, destinatário e responsável | Dados tratados como intercambiáveis e sem classificação |
| R02-R04-D03 | Isolamento de derivados | Segregar RAG, embeddings, memória, fila, cache, logs, eventos, resultados e backups | Segregação apenas na interface |
| R02-R04-D04 | Minimização | Filtrar campos e conteúdo antes de modelo, ferramenta e log; negar segredos em prompts | Transmitir e armazenar todos os dados disponíveis |
| R02-R04-D05 | Auditoria vs privacidade | Prova referencial de autorização/efeito com integridade, controle de acesso e retenção proporcional | Logs irrestritos ou nenhuma prova de ação |
| R02-R04-D06 | Retenção e expurgo | Política por produto/classe/finalidade, derivados rastreáveis e reconciliação de backups; preservar prazos preexistentes | Prazo único ou exclusão apenas visual |
| R02-R04-D07 | Direitos dos titulares | Solicitações coordenadas pelo produto e propagadas a custodiantes/terceiros quando aplicável | Agente decide isoladamente ou ignora solicitações |
| R02-R04-D08 | Provedores e transferências | Homologação jurídica/técnica de finalidade, treinamento, subprocessadores, retenção e local de processamento | Enviar dados privados ao provedor sem verificar termos |
| R02-R04-D09 | Incidentes e auditoria | Resposta a vazamentos, retenção indevida, log impróprio e falhas de expurgo com evidência mínima | Repetir execução e descartar incidentes |
| R02-R04-D10 | Primeiro recorte | Planejar dados mínimos de resumo/classificação/tarefa no CRM, sem dados reais nem fornecedores nesta etapa | Ingestão global de produtos e memória permanente desde o MVP |

**R02-R04-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação exclusivamente documental preliminar, sem escolher prazos de retenção, provedores, bases legais específicas, infraestrutura, modelos ou políticas contratuais reais. Os gates permanecem abertos; sem programação, SQL, ALLOW, testes reais ou deploy.

## 12. Pendências e homologação futura

- [x] R02-R04-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Inventário final de dados e riscos por fluxo com classificação, responsáveis e bases jurídicas revisados.
- [ ] Mapa de origem/derivados, ACL, política de correção/eliminação, retenção, backups e restauração validado com produtos.
- [ ] Contrato de evidência da aprovação e de confirmação de efeito reconciliado com AIA-R02/R03 e AIA-08/10/11.
- [ ] Credenciamento de provedores, condições de treino/retenção, transferências, suboperadores e incidentes aprovado quando necessário.
- [ ] Política de resposta a incidentes e auditoria da auditoria especificada com alçadas.
- [ ] Critérios sintéticos AIA-09 estabelecidos e testes reais mantidos bloqueados até autorização.
- [ ] Contratos oficiais do CRM de leitura, histórico, resumos e tarefas reconciliados com CORE/SEG/AUT ainda abertos.
- [ ] Gates R01-G01 a G11 **todos abertos**, em especial G05/G06/G07/G08/G10.
- [ ] Programação, SQL, ALLOW, credenciais, APIs, gastos, infraestrutura, integração e deploy exigem decisão expressa e separada.

**Próximo bloco documental recomendado:** `AIA-R02/R05 — Homologação de Segurança, Casos de Teste Sintéticos e Evidências de Aceite`, sem executar testes reais nem fechar gates por aprovação genérica.

**Preservar:** `main`, PRs e bloqueios de CRM, Portal, Performance AI e Universidade; VPS/Cloudflare/n8n; fornecedores, contratos externos, bancos e pipelines.
