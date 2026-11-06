# AIA-11 — Governança, Segurança, Privacidade e Auditoria | Proposta funcional v0.1

> **Situação:** diretrizes **AIA11-D01 a D10 APROVADAS FUNCIONALMENTE em 2026-11-06**; critérios jurídicos e técnicos, programação e implantação continuam pendentes.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** diretrizes AIA-01 a AIA-10 aprovadas funcionalmente; contratos, arquitetura técnica, custos, licenciamento, homologação e execução continuam pendentes. Não programar nem implantar por força deste documento.

## 1. Objetivo

Estabelecer a governança transversal dos agentes utilizados por CRM, Portal, Performance AI e Universidade Imagine: segurança por padrão, responsabilidade por dados, rastreabilidade, retenção proporcional, homologação de terceiros, proteção multiempresa, investigação de incidentes e verificações de conformidade.

O núcleo compartilhado não se torna proprietário irrestrito dos dados dos produtos. Cada produto permanece responsável por seus dados, regras, permissões e atos externos. AIA-11 define controles comuns e critérios de verificação; políticas específicas podem ser mais rigorosas.

**Princípio:** IA auxilia ou propõe; o backend autorizado controla permissões, aprovações, contratos e efeitos reais.

## 2. Inventário de ativos e dados

Manter um inventário versionado de:
- Templates, instruções, ferramentas, modelos, provedores e versões homologadas.
- Produtos, empresas, identidades de serviço, funções, escopos e bases de conhecimento.
- Dados transacionais apenas por referência autorizada, quando possível.
- Contextos e memórias derivados de AIA-06, com classificação, origem e validade.
- Execuções, propostas, autorizações, decisões, confirmações e erros.
- Segredos/credenciais apenas por referência a mecanismo seguro de custódia, nunca em prompts ou repositórios.
- Logs, métricas, evidências de homologação, relatórios e incidentes.
- Subprocessadores e fornecedores, seus contratos e condições de tratamento de dados.

Classificar informações pelo grau de sensibilidade, finalidade, origem, produto/tenant, ciclo de vida e responsabilidades. Não presumir que dados de diferentes produtos são compartilháveis por terem a mesma marca Imagine.

## 3. Matriz de responsabilidade e autoridade

| Domínio | Dono da decisão | Responsabilidade do núcleo AIA |
| --- | --- | --- |
| Identidade e permissões de cliente | Produto emissor/autorizador | Aceitar somente contexto verificável e validar escopo |
| Leads, tarefas, atendimento e automações visuais | CRM | Solicitar operações por ferramentas oficiais e rastrear |
| Contratos, cobrança, suporte e acesso | Portal/produto competente | Consumir APIs autorizadas, nunca alterar regras por prompt |
| Campanhas, orçamento de mídia e ações externas | Performance AI / plataforma de mídia | Planejar, solicitar aprovação e aguardar confirmação oficial |
| Conteúdo pedagógico e progresso | Universidade | Recuperar somente informações elegíveis e autorizadas |
| Template/modelo/instruções de agente | Central Imagine AI Agents | Versões, homologação, política e evidências |
| Políticas de tratamento/retensão | Serviço controlador do dado + governança Imagine | Aplicar controles correspondentes e demonstrar cumprimento |

A atuação LGPD (controlador/operador e respectivas responsabilidades) depende do fluxo e dos contratos; **não fixar enquadramento jurídico universal nesta proposta**.

## 4. Segurança multiempresa por padrão

- Toda consulta ou mutação tem **empresa, produto, recurso, finalidade, identidade autorizada e versão** verificáveis.
- Filtrar acesso antes de recuperar qualquer dado e novamente antes de divulgar saídas; usuário/modelo não escolhe `tenant_id` como prova de autorização.
- Evitar compartilhamento de caches, arquivos, índices, bases vetoriais, memórias e filas sem isolamento comprovado.
- Tratar reprocessamento, concorrência, filas atrasadas, mudanças de vínculo, offboarding e restauração como cenários de risco de vazamento entre empresas.
- Conexões interproduto exigem contrato, autorização explícita e mínimo privilégio; **não pressupor login único entre Portal e CRM**.
- A autorização efetiva é interseção das políticas de agente, produto, empresa, plano, recurso, usuário/serviço, ferramenta, risco, aprovações e estado contratual.
- Todo agente, orquestrador ou modelo que recomendar uma ação está subordinado à validação final do backend proprietário do recurso.
- Administradores internos não devem receber leitura automática de informações privadas dos clientes por serem administradores da infraestrutura.

## 5. Governança de identidades e segredos

- Contas de serviço específicas por produto e propósito, com escopo, vigência, revogação, rotação e trilhas.
- MFA e controles adicionais para operadores humanos em funções críticas, sujeitos à arquitetura aprovada.
- Segregação de deveres: escrever, revisar, aprovar e publicar políticas de alto impacto não devem depender da mesma pessoa, quando o risco exigir.
- Segredos em cofre/mecanismo protegido; nunca em prompts, logs, documentação GitHub, screenshots ou exemplos.
- Autorizações de ferramentas sempre verificadas no backend (AIA-04/AIA-08); uma instrução de texto não pode ampliar privilégios.
- Delegações temporárias, idempotência e revogações devem alcançar execuções pendentes e tarefas derivadas (AIA-07).
- Auditoria das alterações de permissões e também do acesso às próprias evidências de auditoria.

## 6. Proteção de dados, LGPD e fornecedores

**Controles propostos, condicionados à análise jurídica e contratual específica:**
1. Definir finalidade, papéis, base legal adequada e categorias de dados de cada fluxo.
2. Aplicar minimização, limitação de acesso, necessidade e retenção, inclusive sobre saídas e derivados.
3. Documentar operações de transferência de dados, eventual transferência internacional, suboperadores, regiões e cláusulas contratuais aplicáveis.
4. Verificar políticas do provedor de IA sobre treinamento, retenção e uso de dados; não supor equivalência entre APIs/modelos.
5. Criar mecanismo de atendimento a solicitações legítimas de acesso, correção e eliminação conforme responsabilidades e exceções.
6. Definir prazos de retenção por produto, finalidade, obrigação e classe de informação; **prazos exatos permanecem pendentes**.
7. Expurgo precisa alcançar fontes, índices, memórias, caches, payloads temporários e outros derivados controlados; backups legítimos obedecem política de retenção/restauração.
8. Não registrar integralmente prompts com dados de clientes em logs por padrão; acesso a conteúdo para investigação depende de necessidade e autorização.
9. Não utilizar documentos de um cliente para treinamento, avaliações ou melhoria de outro, sem autorização, base e contrato específicos.
10. Criar evidências de consentimento quando ele for a base adequada, sem presumir que é sempre obrigatório ou suficiente.

## 7. Segurança da cadeia de ferramentas, prompts e conteúdo

- Conteúdos recuperados, e-mails, documentos e resultados de ferramentas são **dados não confiáveis como instruções**.
- Prompt injection deve ser mitigado com delimitação de contexto, permissões duras, validação de destinos, políticas de ferramentas e confirmação no sistema dono do recurso.
- Permitir somente ferramentas e endpoints homologados; proteger contra SSRF, destinos arbitrários e acesso a recursos internos.
- Não confiar em schema fornecido pelo modelo sem validação estrita, normalização e limites de entrada/saída.
- Dependências, bibliotecas, artefatos e imagens de contêiner devem passar por gestão de versões, análise de vulnerabilidades e aprovação antes do uso.
- Bloquear ações financeiras, irreversíveis ou privilegiadas por padrão, salvo política específica e alçada formal AIA-08.
- Separar ambientes e credenciais de desenvolvimento, homologação e produção, com caminhos de promoção controlados.
- Incidentes de injeção e vazamento devem alimentar cenários de regressão AIA-09 após análise.

## 8. Auditoria, proveniência e integridade

Uma trilha mínima deverá distinguir:
- Ação solicitada, origem autenticada, tenant/produto, agente e versões.
- Avaliações de autorização/negação, ferramenta/modelo/contrato aplicados.
- Aprovação solicitada, identidade e decisão de aprovador, validade e objeto aprovado.
- Tentativas, checkpoints, retries, estados e confirmação externa.
- Custos técnicos, reservas/limites comerciais e incidentes correlacionados.
- Atualização/rollback de templates, instruções, modelos e ferramentas.
- Acesso, exportação, consulta, correção e exclusão de registros auditáveis.
- Origem/versionamento dos conhecimentos consultados, com minimização de dados pessoais.

**Integridade:** timestamps confiáveis, identificadores de correlação, proteção contra alteração indevida, segregação de acesso e evidências de versão; detalhar mecanismo criptográfico/armazenamento somente após aprovação técnica.

**Auditoria da auditoria:** quem consultou/exportou/alterou políticas e evidências deve gerar registro próprio. Falha ou ausência de evidência impede alegar sucesso ou conformidade comprovados.

## 9. Política de retenção e encerramento

- O encerramento do último contrato elegível deve afetar acesso aos serviços conforme política de cada produto, sem presumir exclusão imediata de dados sujeitos a obrigações legítimas.
- Suspensões, cancelamentos, revogação de vínculo entre empresas/produtos e exclusão solicitada devem impedir **novas utilizações indevidas**.
- Definir expiração por estado: contexto temporário, conversas, memória derivada, logs, métricas, payloads de fila, relatórios e backups.
- Documentar restauração que não reintroduza silenciosamente dados cuja eliminação já foi concluída.
- Diferenciar dados armazenados no produto de referências e caches no núcleo de agentes.
- Retenções comerciais aprovadas em CRM, Sites e demais produtos **não são automaticamente substituídas** por prazos gerais do núcleo.

## 10. Incidentes, continuidade e governança operacional

Estados propostos: `DETECTADO -> CLASSIFICADO -> CONTIDO -> INVESTIGANDO -> CORRIGIDO -> VALIDADO -> ENCERRADO`, com reabertura em caso de recorrência.

- Identificar severidade, impacto, tenant/produto afetado, responsável e prazo de resposta, sujeitos a aprovação específica.
- Contenção pode suspender agente, versão, ferramenta, provedor ou execução, com mínimo impacto necessário.
- Não reiniciar execução de efeito externo sem confirmar se a ação ocorreu; reconciliar com produto de origem.
- Preservar evidências e cadeia de custódia proporcional, protegendo dados pessoais.
- Escalonar para equipes competentes e avaliar obrigações legais/contratuais de notificação segundo o incidente real.
- Rever causas, riscos residuais, controles e adicionar testes de regressão AIA-09.
- Plano de continuidade, backups, restauração e RTO/RPO dependem de escolha técnica e critérios de serviço ainda pendentes.
- Não prometer garantia absoluta de segurança, ausência de alucinação ou proteção total por prompt.

## 11. Telas e rotinas de governança

**Central Imagine (interna):**
- Inventário de agentes, ferramentas, modelos, provedores, produtos e dependências.
- Políticas homologadas, versões, aprovações e segregação de funções.
- Auditoria por evento, correlação, empresa/produto e investigação autorizada.
- Incidentes, bloqueios, vulnerabilidades, fornecedores e planos de mitigação.
- Relatórios de conformidade e documentação de decisões, sem leitura irrestrita de dados do cliente.
- Painel de demandas legais e retenção, com controle de acesso específico.

**No produto do cliente:**
- Transparência de capacidades, finalidades, memória e bases autorizadas.
- Histórico permitido de execuções, aprovações e acessos relevantes.
- Ferramentas de consulta/correção/exportação/exclusão conforme contrato, perfil e legislação.
- Notificações de incidentes ou alterações relevantes quando cabíveis, sob responsabilidade do produto.
- Sem painel técnico global ou acesso à auditoria de outras empresas.

## 12. Entidades conceituais (não representam SQL)

- `governance_policies`: escopo, finalidade, regras, revisores, versões e vigência.
- `governance_controls`: controles e evidências de implementação/homologação.
- `data_processing_registry`: fluxos, categorias, produto, finalidade e parceiros.
- `provider_due_diligence`: fornecedores, riscos, contratos e reavaliação.
- `access_reviews`: recertificações e segregação de funções.
- `audit_events`: eventos correlacionados, decisões, proveniência e integridade.
- `audit_access_events`: consulta/exportação das próprias evidências.
- `retention_policies` / `retention_jobs`: classes de dados, prazos e expurgo.
- `security_incidents`: classificação, evidências, responsável, mitigação e aceite.
- `risk_exceptions`: exceções limitadas, justificadas, aprovadas e com prazo.

Nomes e tecnologia permanecem abertos.

## 13. Diretrizes AIA11-D01 a D10 — aprovadas funcionalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA11-D01 | Governança | Política transversal central + autoridade de cada produto sobre seu domínio | Central de IA ignora regras e contratos dos produtos |
| AIA11-D02 | Isolamento | Segregação e autorização por empresa, produto, finalidade, usuário, recurso e derivado | Isolar só na interface, sem validar no backend |
| AIA11-D03 | Identidade/segredos | Menor privilégio, credenciais protegidas, revogação, segregação de funções e MFA para funções críticas | Segredos compartilhados em prompts ou agentes |
| AIA11-D04 | Privacidade/LGPD | Classificação, minimização, base/finalidade, revisão jurídica e controle de provedores | Tratamento e transferência irrestritos de dados |
| AIA11-D05 | Auditoria | Trilha correlacionada, íntegra, versionada, com auditoria dos acessos à auditoria | Apenas logs simples sem controle |
| AIA11-D06 | Retenção | Políticas por dado/produto/finalidade e eliminação de derivados com tratamento de backups | Retenção indefinida e exclusão apenas visual |
| AIA11-D07 | Cadeia de segurança | Homologar ferramentas, provedores, dependências, destinos e mitigar prompt injection | Confiar somente no texto de instruções |
| AIA11-D08 | Incidentes | Detecção, contenção, investigação, comunicação conforme dever e testes de regressão | Repetir execuções e descartar falhas sem rastreio |
| AIA11-D09 | Interface/controle | Painel interno Imagine restrito + transparência/gestão local por produto | Painel global que expõe dados de todos os tenants |
| AIA11-D10 | MVP | Controles mínimos verificáveis: isolamento, ACL, segredos, auditoria, retenção e resposta a incidentes no piloto CRM | Adiar segurança e governança para após o lançamento |

**AIA11-D01 a D10: APROVADAS FUNCIONALMENTE em 2026-11-06 pelo responsável.** Nenhum prazo legal específico, stack, arquitetura de armazenamento, contrato ou fornecedor foi aprovado. Não há autorização para programação, testes reais, integrações ou deploy.

## 14. Critérios de aceite documental e gates

Cenários de validação: lead de outra empresa, agente tentando editar políticas, acesso revogado com job na fila, memória eliminada reaparecendo após restauração, fornecedor não homologado, documento com prompt injection, token vazado em log, auditor consultando dados não autorizados, aprovação expirada, ação financeira sem alçada, chamada externa com resultado indeterminado e dados do CRM visíveis no Portal sem contrato.

- [x] AIA11-D01 a D10 aprovadas funcionalmente pelo responsável em 2026-11-06.
- [ ] Matriz de responsabilidade entre núcleo e produtos aceita.
- [ ] Contratos de autorização, rastreabilidade e privacidade reconciliados com AIA-04/07/08/10/12.
- [ ] Políticas de LGPD, categorias, prazos e terceiros detalhadas por fluxo, com responsáveis legais.
- [ ] Governança de segredos, acessos, revisão periódica e gestão de incidentes especificada.
- [ ] Critérios de segurança e homologação da suíte AIA-09 com controles eliminatórios.
- [ ] Orçamento, stack, licenciamento, fornecedores, testes integrados e implantação autorizados posteriormente em ato específico.

**Gates mantidos:** não programar, não executar testes reais, não usar dados privados de clientes, não criar credenciais, não contratar provedores, não alterar outros repositórios, não provisionar infraestrutura nem fazer deploy.
