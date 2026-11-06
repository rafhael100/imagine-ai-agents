# AIA-R02/S04 — Segregação, Retenção, Privacidade e Conformidade de Dados | Proposta documental v0.1

> **Estado:** diretrizes **R02-S04-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06**, exclusivamente preliminares; R01-G01 a G11 e todos os gates CORE/SEG/AUT do CRM permanecem abertos.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 com diretrizes funcionais aprovadas; AIA-R01-D01 a D08 e R02-S01/S02/S03-D01 a D10 ratificadas **apenas documentalmente**.  
> **Bloqueios:** R01-G01 a G11 continuam todos abertos, inclusive G05; gates CORE/SEG/AUT e demais decisões próprias do CRM continuam abertos. Nenhum código, ALLOW, SQL, tratamento real, credencial, contrato, teste funcional, alteração em outro repositório ou deploy autorizado.

## 1. Objetivo

Definir os **contratos de responsabilidade, classificação, finalidade, segregação, transferência, retenção e exclusão** para dados processados por Imagine AI Agents em nome de CRM, Portal, Performance AI e Universidade Imagine. O núcleo de IA pode receber dados mínimos ou referências autorizadas, consultar ferramentas, produzir resultados e registrar evidências técnicas; não deve transformar isso em cópia paralela irrestrita dos produtos.

A conformidade efetiva depende da finalidade e do fluxo de tratamento, de contratos e bases legais adequados, da regulamentação aplicável, da implementação técnica e da homologação posterior. **Aprovação desta proposta não é parecer jurídico, aprovação de tratamento real nem encerramento de gate LGPD.**

## 2. Fonte de verdade, responsabilidade e escopos

| Domínio | Titularidade funcional e fonte de verdade | Papel permitido do núcleo |
| --- | --- | --- |
| CRM | Organizações, usuários, leads, atendimentos, histórico, tarefas, planos e créditos | Usar consulta mínima por ferramenta autorizada e devolver sugestão; CRM decide gravação |
| Portal | Identidade local, contratação, cobrança, suporte e serviço elegível | Responder à tarefa autenticada, sem importar o cadastro inteiro |
| Performance AI | Contas de anúncios, campanhas, resultados, aprovações e orçamento de mídia | Recomendar sob contrato; não herdar poder de gasto/conta |
| Universidade | Elegibilidade, conteúdo e progresso dos clientes autorizados | Atender tutor autorizado sem compartilhar histórico entre produtos |
| Imagine AI Agents | Templates, versões, estados de execução, métricas técnicas e evidências próprias | Custodiar somente o mínimo técnico necessário, com controle e prazo |
| Provedores externos | Subprocessamento estritamente contratado e autorizado, quando necessário | Receber somente dados pertinentes ao processamento, com controles e verificação prévia |

A definição de **controlador, operador e suboperadores nos termos da LGPD** deve ser feita por fluxo, contrato e finalidade, não genericamente para todos os produtos. Os dados e permissões do CRM não se tornam públicos para o Portal por haver a mesma empresa no ecossistema.

## 3. Classificação dos dados e tratamento proposto

| Classe | Exemplos | Proteção mínima documental |
| --- | --- | --- |
| C0 — Público autorizado | FAQ publicada e materiais institucionais | Origem, versão, integridade, licença de uso e retirada quando necessário |
| C1 — Operacional interna | Logs de estados, catálogo e metadados não pessoais | Controle de acesso, finalidade, minimização e prazo |
| C2 — Confidencial da empresa | Leads, tarefas, preços internos, campanhas, histórico do cliente | ACL por produto/empresa/recurso, acesso mínimo e proibição de cruzamento |
| C3 — Pessoal | Nome, contato, mensagens, identificadores vinculáveis | Base/finalidade, minimização, segurança, retenção e direitos do titular |
| C4 — Especialmente sensível ou de alto risco | Dados pessoais sensíveis e segredos, prontuários, credenciais, identificadores de pagamento | Negar por padrão para o MVP; exceções somente por avaliação específica, base legal e controles reforçados |

Classes são **propostas para homologação** e podem coexistir em um registro. A C4 não significa que toda informação financeira seja dado pessoal sensível nos termos da LGPD; risco operacional e classificação jurídica exigem análise separada.

Credenciais e segredos **nunca devem entrar como conteúdo de prompt**; seus mecanismos de custódia serão definidos tecnicamente depois.

## 4. Inventário de fluxos e registros necessários

Para cada fluxo futuro, exigir ao menos:
- Produto controlador/origem, responsável, consumidor autorizado e finalidade concreta.
- Categoria de dados, proveniência, necessidade de cada campo, base legal aplicável e eventual interesse de terceiros.
- Empresas, identidades, recursos, permissões e contratos de compartilhamento, inclusive delegação.
- Provedor/modelo, localidade de processamento/transferência, eventual suboperador e política de uso de dados.
- Saídas geradas, possíveis derivados (resumo, embeddings, vetor, cache, logs), fonte de verdade e estado.
- Regras e eventos de retenção, suspensão, correção, exclusão e descarte.
- Alertas, auditoria e evidências mínimas necessárias de conformidade, sem copiar payloads pessoais desnecessários.

Não usar a autorização comercial genérica do produto como substituto do inventário e dos direitos sobre cada categoria de dados.

## 5. Segregação multiempresa e entre produtos

Isolamento deve ser validado em **todas** as camadas e no ciclo de vida inteiro:
1. Emissor/identidade autenticados; `tenant` verificado e vinculado, nunca declarado por prompt.
2. Consultas por recurso e campos com reautorização no backend proprietário (R02/S01/S03).
3. Filas, workers, checkpoints e reprocessamentos sem contexto residual de uma empresa para outra.
4. Bases de conhecimento, índices vetoriais, embeddings e caches com escopo real por empresa/produto/finalidade (AIA-05).
5. Memória derivada, sessões, históricos, resumos e acesso/correção/exclusão com dono identificado (AIA-06).
6. Telemetria, relatórios, custo, logs, auditoria e alertas sem revelar dados de outros clientes (AIA-10/11).
7. Payloads de callback, webhooks, notificações e exports com destino/ACL homologados (AIA-12).
8. Backups, restauração, migração, incidente e expurgo sem reintrodução indevida de dados removidos.

**Uma identidade técnica compartilhada não concede privilégio sobre os tenants.** A escolha física entre instâncias, schemas, banco, filas ou armazenamento é gate técnico posterior, mediante testes negativos.

## 6. Ciclo de vida dos dados e derivados

### 6.1 Recepção mínima

Solicitação AIA recebe **referência e contexto autenticados** preferencialmente a um payload completo. Quando precisar de conteúdo, a ferramenta oficial aplica filtros de campos, classe e finalidade e registra correlação.

### 6.2 Processamento transitório

O núcleo compõe contexto de execução com somente material pertinente. Não gravar indiscriminadamente mensagens, anexos, URLs privadas, segredos ou todo o contexto nos logs. Conteúdo de terceiros e documentos recuperados são dados não confiáveis, não instruções superiores.

### 6.3 Resultados

A IA entrega **proposta de resumo/classificação**. O CRM, por suas permissões e contratos, decide exibir, validar e persistir no histórico com versionamento e acesso autorizado. Agentes não podem converter hipóteses em fatos oficiais, alterar situação contratual nem confirmar entrega por texto gerado.

### 6.4 Derivados

Derivados incluem, quando futuramente habilitados: trechos de texto em cache, resumos, embeddings, índices, representações de busca, checkpoints, artefatos de avaliação e relatórios. Devem conservar vínculo à origem, versão, empresa, produto, finalidade, classificação e política de eliminação.

### 6.5 Suspensão, atualização e eliminação

Revogação ou suspensão deve interromper **novas consultas e usos não autorizados** conforme estado atual. Correção/eliminação requer localizar originais e derivados sob custódia do serviço, atualizar índices/caches elegíveis, registrar comprovação mínima e considerar retenções legais/contratuais de backups sem prometer apagamento físico instantâneo universal.

### 6.6 Restaurar backups sem reintroduzir dados indevidos

Projetar, em homologação posterior, conciliação de solicitações de exclusão/suspensão durante restauração para evitar que registros previamente eliminados reapareçam como acessíveis. A restauração não deve reativar acesso só porque os bytes ainda existem em backup legítimo.

## 7. Retenção e prazos: não substituir regras dos produtos

**Não definir prazo global único para todo o ecossistema nesta fase.**

Cada classe deve ter política específica conforme finalidade, risco, contrato, obrigação legal, status do produto, origem, necessidade de auditoria e direito dos titulares.

- **Dados de origem:** permanecem sob política do CRM, Portal, Performance AI ou Universidade.
- **Contexto transitório:** reter pelo mínimo necessário para a execução e recuperação autorizada; TTL técnico a aprovar.
- **Fila de execução e falhas:** retenção limitada e rastreável; não transferir automaticamente prazos do sistema Imagine Sites para todas as filas AIA.
- **Memória derivada e RAG:** vigência explícita, revogação e eliminação de índices/caches elegíveis.
- **Logs e auditoria:** metadados mínimos com retenção proporcional; restrição de acesso e registro de consulta.
- **Backups:** retenção legítima, acesso restrito e política de restauração/expurgo posterior.
- **Cancelamento e suspensão:** cessação de novos usos conforme produto, sem presumir eliminação imediata de informação sujeita à guarda legítima.

**Preservação de decisões existentes:** por exemplo, o prazo funcional específico da fila de reenvio de leads dos Sites Imagine (72h + 360h = 432h) e as políticas comerciais/retentivas do CRM não são alterados, nem automaticamente estendidos, por este documento. Harmonização de prazos entre módulos requer contrato explícito entre os donos dos dados.

## 8. Provedores de IA e transferência de dados

Antes do uso real de modelo ou ferramenta terceirizada, avaliar:
- Cláusulas de processamento, finalidades autorizadas, subprocessadores, localização/região e eventual transferência internacional.
- Retenção, reutilização, treinamento e compartilhamento de prompts/outputs por plano, provedor e API específica.
- Controles de segurança, exclusão, incidente, confidencialidade, auditoria e possibilidade de saída/portabilidade.
- Minimização, anonimização quando viável e validade técnica de desidentificação; pseudonimização **não** remove automaticamente a aplicação da LGPD.
- Aprovação técnica/comercial/jurídica do fluxo e custos, incluindo versões de termos.
- Proibição documental de usar dados privados de cliente para melhorar modelos de terceiros ou servir outros tenants sem base e autorização apropriadas.

Nenhum modelo/provedor, cláusula, região, API, cotação ou licenciamento é aprovado por esta proposta. Consentimento não é base legal universal; avaliar a base correta e exceções por finalidade.

## 9. Exercício de direitos e interface de governança

**Dentro do produto proprietário:** fluxo de solicitação de acesso, confirmação de tratamento, correção, bloqueio ou eliminação quando cabível, revisão de resumo e exportação sob permissões e legislação aplicáveis. O produto deve coordenar a propagação de solicitações pertinentes ao núcleo AIA e provedores integrados.

**Dentro da Central Imagine:** inventário de fontes, operações, classificações, bases e contratos, registros de solicitação e execução de expurgo, responsáveis, auditoria de acesso, incidentes e exceções — sem visibilidade irrestrita do conteúdo privado dos clientes.

**Distinguir responsabilidades:** titular de dados pessoais, usuário da empresa cliente e administrador do tenant não são necessariamente a mesma pessoa. A autenticação e o atendimento de solicitações precisam respeitar a LGPD, contratos e autoridade de cada um.

## 10. Auditoria, incidentes e conformidade

- Correlacionar pedido, produto, identidade, empresa, ferramenta, finalidade, recurso, versões e decisão com mínimo conteúdo pessoal (AIA-10/11).
- Registrar acessos autorizados e negados, mudança de vínculo, exportação, reclassificação, retenção, exclusão e falhas de expurgo.
- Integridade, rastreabilidade e consulta de auditoria devem ter controles de acesso e auditoria da própria auditoria.
- Falha ou suspeita de vazamento deve gerar incidente com classificação, contenção e responsáveis; avaliação de comunicações legais depende do evento real.
- Não repetir ação externa após timeout sem reconciliação (AIA-07).
- Falha de expurgo de dados deve bloquear novas utilizações ilegítimas e gerar alerta, não marcar conclusão falsamente.
- Obrigações técnicas e jurídicas serão homologadas por responsável competente e não por decisão autônoma do agente.

## 11. Matriz de cenários negativos documentais

| Cenário | Comportamento exigido |
| --- | --- |
| Agente do tenant A tenta recuperar lead ou embedding do tenant B | Negar e registrar tentativa sem divulgar B |
| Empresa muda vínculo CRM/Portal | Não reaproveitar caches ou credenciais de vínculo antigo |
| Documento excluído ainda aparece em índice de busca | Bloquear acesso e reconciliar eliminação do derivado |
| Resumo corrigido no CRM e memória antiga persiste | Invalidar/suspender memória derivada inadequada |
| Contexto privado enviado para log de erro | Minimizar e conter incidente; não reproduzir payload indiscriminadamente |
| Provedor tem uso de dados para treinamento incompatível com contrato | Não transmitir dados reais até avaliação e homologação |
| Backup restaura memória eliminada | Impedir reativação e aplicar reconciliação de exclusões |
| Usuário pede exclusão de informação sujeita a guarda legal | Avaliar fundamento e tratar direitos/limites de forma documentada |
| Operador interno consulta dados de cliente sem atribuição | Negar e auditar |
| Chamadas falham após retenção de payload expirar | Encerrar adequadamente e não recriar dados por inferência |
| Callback contém dados de outro tenant | Recusar entrega/associação e registrar incidente |
| Dados de um cliente entram em dataset de treinamento global | Bloquear na ausência de base/autorização/contrato específicos |

Estes são casos de aceitação **documentais**, não resultados de teste.

## 12. Diretrizes R02-S04-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-S04-D01 | Propriedade do dado | Produto mantém a fonte de verdade; AIA recebe somente referências/conteúdo estritamente autorizado | Banco central copia integralmente dados de produtos |
| R02-S04-D02 | Classificação/finalidade | Inventário por classe, base legal, finalidade, origem, produto, tenant e responsável | Tratar todo dado como público e intercambiável |
| R02-S04-D03 | Segregação | Isolar dados e derivados em API, RAG, memória, fila, cache, logs, callback e backup | Separação apenas visual na interface |
| R02-S04-D04 | Minimização | Processar o mínimo de campos e tempo necessário; não armazenar prompts íntegros por padrão | Copiar e conservar todas as conversas e anexos |
| R02-S04-D05 | Retenção | Política por produto/classe/finalidade e conformidade; não substituir prazos já aprovados | Prazo único ou retenção indefinida no núcleo |
| R02-S04-D06 | Exclusão/derivados | Suspender novos usos indevidos e conciliar expurgo de índices, memórias, caches e backups legítimos | Excluir apenas da interface, deixando derivados acessíveis |
| R02-S04-D07 | Terceiros | Homologar dados enviados a provedores, retenção, treinamento, transferências e suboperadores antes de uso | Enviar dados reais sem contrato e análise |
| R02-S04-D08 | Direitos e solicitações | Atendimento coordenado pelo produto, propagação documentada ao núcleo e provedores, com auditoria | Pedidos ignorados ou geridos unicamente pelo agente |
| R02-S04-D09 | Incidentes/auditoria | Registrar eventos mínimos, acessos, negativas, incidentes e falhas de expurgo com privacidade | Logs irrestritos sem rastreabilidade de acesso |
| R02-S04-D10 | MVP | Matriz de dados do piloto CRM com leitura/resumo e derivados mínimos; sem dados reais nesta fase documental | Ingestão multicanal e memória global desde a primeira etapa |

**R02-S04-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Nenhum prazo de retenção, contrato de provedor, arquitetura de armazenamento, base legal de fluxo ou tecnologia está homologado; sem autorização de programação, testes reais, ALLOW, SQL, dados privados ou deploy.

## 13. Dependências e próximos detalhamentos

- [x] R02-S04-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Inventário por produto/fluxo, base legal e responsabilidades revisto com responsáveis internos/jurídicos.
- [ ] Mapeamento de dados e derivados à matriz de acesso R02/S01–S03 e AIA-05/06/11.
- [ ] Políticas concretas de retenção, expurgo, backups e restauração por classe/produto.
- [ ] Contratos, privacidade, regiões e condições de modelos/provedores homologados.
- [ ] Matriz de incidentes e proteção de logs/telemetria.
- [ ] Critérios e casos sintéticos de segurança no laboratório AIA-09.
- [ ] Preservar integralmente R01-G01 a G11 e CORE/SEG/AUT do CRM; nenhum fechamento implícito.
- [ ] Autorização separada para código, infraestrutura, testes reais, custos, credenciais e deploy.

**Próximo bloco documental candidato:** R02/S05 — Estados, Aprovações, Idempotência e Reconciliação entre núcleo e produtos.

**Proteções inalteradas:** `main`, PRs e gates de CRM, Portal, Performance AI e Universidade; VPS/Cloudflare/n8n; credenciais, SQL, ALLOW, integrações reais, gastos e deploy.
