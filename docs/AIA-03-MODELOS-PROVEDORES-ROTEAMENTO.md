# AIA-03 — Modelos de IA, Provedores e Roteamento | Proposta funcional v0.1

> **Status:** planejamento documental para revisão. AIA03-D01–D10 **não aprovadas**.  
> **Data:** 2026-11-06. **Escopo:** núcleo compartilhado Imagine AI Agents.  
> **Precedência:** AIA-01 (D01–D08) e AIA-02 (D01–D10) já aprovados funcionalmente; gates técnicos e implementação seguem bloqueados.

## 1. Objetivo
Operar um catálogo central de provedores e modelos de IA para CRM, Portal, Performance AI e Universidade, selecionando o modelo adequado para cada tarefa **sem prender a arquitetura a um único fornecedor**. Controlar privacidade, qualidade, custo, capacidade, versão e disponibilidade, preservando a autonomia de regras e cobrança de cada produto.

AIA-03 **não treina nem oferece um modelo fundacional próprio** no MVP. Fine-tuning e modelos locais poderão ser analisados em fase futura, sob homologação e justificativa econômica/técnica.

## 2. Responsabilidades e limites

| Camada | Responsabilidade |
|---|---|
| Central Imagine AI Agents | Homologar provedores/modelos, credenciais protegidas, políticas de roteamento, custos técnicos, telemetria e contingência |
| Produto consumidor | Autenticar usuário/empresa, validar plano, finalidade, cotas comerciais e direitos de uso, conferir operações da sua API |
| AIA-01 / AIA-02 | Definir templates, instruções, modelos elegíveis, versão e parâmetros de tarefa |
| AIA-04 / AIA-08 | Definir ferramentas executáveis e autorizações; o modelo não recebe acesso irrestrito |
| AIA-05 / AIA-06 | Aplicar políticas de conhecimento, contexto e memória autorizados |
| AIA-09 / AIA-10 / AIA-11 | Qualidade, auditoria, riscos, observabilidade, custo e privacidade |
| AIA-12 | Contratos de integração e identificação de produto/empresa |

## 3. Inventário de capacidades dos modelos

O catálogo deve registrar capacidades **verificadas** por modelo e versão: texto, entendimento de imagem, geração de imagem, áudio, entradas extensas, extração estruturada, chamadas de ferramentas, processamento em lotes, limites de contexto, políticas de conteúdo e preços contratados.

Não assumir que uma capacidade anunciada pelo fornecedor é apropriada para a finalidade sem validação. Recursos e valores variam; validar documentação vigente, contrato e evidências antes de homologar.

Exemplos de classes de tarefa:
- **Econômica:** classificação simples, extração e resumo, se os testes confirmarem qualidade suficiente.
- **Equilibrada:** atendimento consultivo, redação, extração estruturada complexa e suporte.
- **Alta capacidade:** planejamento de múltiplas etapas, diagnóstico de incidentes e análise mais complexa.
- **Especializada:** imagens, visão, transcrição e outras modalidades, quando autorizadas e compatíveis.

Estas classes são rótulos funcionais, **não nomes de produtos comerciais nem compromisso de desempenho**.

## 4. Fluxo proposto para escolha do modelo

1. Receber tarefa autenticada, produto, empresa, versão do agente, finalidade e orçamento permitido.
2. Validar autorização e política de dados/fornecedor **antes de transmitir conteúdo externo**.
3. Filtrar modelos homologados pelo tipo de tarefa, capacidade, contrato, privacidade, região, requisitos de ferramentas e limites.
4. Aplicar a política de roteamento aprovada: padrão explícito por tipo de tarefa; balanceamento somente se validado.
5. Reservar/prevalidar orçamento e limites de uso; negar novas chamadas quando necessário.
6. Executar por adaptador versionado e normalizar resposta, erros, metadados de consumo e identificador da execução.
7. Validar saída antes de qualquer ferramenta de efeito externo; AIA-04/AIA-08 validam a operação.
8. Registrar modelo e versão realmente utilizados, sucesso, custo medido/estimado, evidências mínimas e motivo de fallback.
9. Se falhar, avaliar retry/backoff, alternativa **já homologada e compatível com finalidade, privacidade, autorização e custo**.
10. Encerrar com status explícito; não repetir automaticamente operações com efeitos externos sem idempotência e confirmação.

## 5. Políticas comerciais e cotas

- O registro técnico por chamada deve conter produto, empresa, agente/versão, provedor/modelo/versão, tipo de uso, tokens/unidades informados, estimativa/consumo faturável e custo atribuído.
- Separar **custo real do fornecedor**, **créditos de IA comerciais do produto** e **preço cobrado ao cliente**; são grandezas diferentes.
- O produto define o plano, créditos incluídos, adicionais e limites; núcleo registra e devolve telemetria técnica.
- Antes da execução, validar saldos/limites com o domínio dono da cobrança e a política de orçamento; compensar reserva após resultado.
- Definir alertas operacionais e comerciais, bloqueios seguros e mecanismo explícito de excedentes aprovados.
- Usar tabelas de preço versionadas com moeda e vigência; mudanças do fornecedor não podem ser presumidas como preços fixos.
- O custo da IA não se confunde com mídia paga do Performance AI, que segue regras próprias.

## 6. Privacidade, contratos e segurança

- Não inserir automaticamente informações pessoais sensíveis ou dados de outra empresa no prompt.
- Criar contrato de finalidade e categorias permitidas por modelo/provedor, incluindo retenção, transferência internacional, treinamento pelo fornecedor, subprocessadores e garantias necessárias conforme LGPD e políticas.
- Não presumir que "API" significa uso sem retenção nem que todos os fornecedores possuam compromissos equivalentes.
- Credenciais somente em serviço gerenciador de segredos; rotação e revogação. Evitar tokens de provedores em clientes web ou logs.
- Limitar contexto e mascarar dados conforme tarefa, sem comprometer integridade dos registros.
- Fornecedores e ferramentas externas devem passar por homologação AIA-11; rastrear versão do contrato e política aplicada.
- Evitar fallback que transfira dados a um provedor diferente sem base contratual/autorização e necessidade demonstrada.

## 7. Resiliência e contingência

Falhas previstas: indisponibilidade do provedor, rate limit, timeout, problema de formato, custo excedido, modelo depreciado, falha de autorização, limite de contexto, qualidade abaixo do mínimo.

Propor:
- Circuit breaker e filas controladas com retry somente quando seguro.
- Alternativa homologada por tarefa, política e empresa, com logs de justificativa.
- Falha segura quando não existir fallback equivalente ou autorizado.
- Tempo máximo por operação e status de processamento observável no produto.
- Não repetir publicação de campanha, cobrança, envio externo ou operações irreversíveis a partir de retry de inferência sem verificar idempotência.
- Simulação de indisponibilidade em testes homologados antes de ativar fallback de produção.

## 8. Administração e interface interna Imagine

Área **Central Imagine > Provedores e Modelos**:
- Catálogo e status dos fornecedores homologados.
- Modelos com capacidades, preços/versões, regiões/contratos e limites de uso.
- Chaves e ambientes gerenciados sem exposição do segredo em interface.
- Políticas de seleção por classe de tarefa e produto.
- Fallbacks autorizados por cenário, sem permitir acesso cruzado.
- Consumo consolidado e custo detalhado, falhas, latência e alertas.
- Publicação controlada de mudanças no roteamento, auditoria e rollback.

A interface do cliente deve exibir as capacidades de seus agentes, consumo e eventuais preferências **somente se seu produto/plano permitir**, não uma lista de tokens, segredos e provedores técnicos.

## 9. Homologação e avaliação de modelos

Cada configuração modelo-provedor deve ter:
- Contrato e verificação de privacidade/segurança.
- Benchmarks com casos reais anonimizados ou sintéticos e dados não sensíveis.
- Aderência a tarefas, estrutura de saída, chamadas de ferramentas e contexto.
- Testes de falha, quedas, timeout, limites, tentativas de prompt injection e resultados indevidos.
- Latência e custo sob carga esperada, com rastreabilidade.
- Identificação de versão, data de avaliação, aprovador e condições/restrições de uso.
- Nova avaliação quando mudar versão relevante, política do fornecedor ou método de roteamento.

**Critério importante:** preço menor isoladamente não basta para homologar um modelo.

## 10. Modelo conceitual de dados (sem migrações)

- `ai_providers`: fornecedor, contrato, regiões e status de homologação.
- `ai_models`: identidade de modelo, capacidades, limites e status.
- `ai_model_versions`: versão efetiva e evidências de compatibilidade.
- `ai_model_prices`: preços versionados e condições de faturamento.
- `ai_provider_credentials`: apenas referências a segredos protegidos, sem chaves em texto.
- `ai_routing_policies`: regras versionadas e elegibilidade por agente/tarefa/produto.
- `ai_fallback_policies`: equivalência e restrições de contingência.
- `ai_usage_records`: consumo medido e custo estimado/conciliado por tenant/produto.
- `ai_model_evaluations`: avaliações, métricas e versões do teste.
- `ai_provider_incidents`: incidentes, impactos e ações de mitigação.

Nomes, stack, schema e contratos são sugestões; nenhuma implementação aprovada.

## 11. Decisões AIA03-D01 a D10 — propostas para aprovação

| ID | Tema | Opção A — Recomendada | Alternativa B |
|---|---|---|---|
| AIA03-D01 | Estratégia de provedores | Abstração multi-provedor, sem fornecedor obrigatório único | Um fornecedor e APIs diretas em cada produto |
| AIA03-D02 | Catálogo | Homologação central de fornecedor, modelo, versão e capacidade | Cada agente escolhe qualquer modelo livremente |
| AIA03-D03 | Roteamento | Classe de tarefa + política, custo e qualidade validada | Sempre o modelo mais caro/mais poderoso |
| AIA03-D04 | Fallback | Somente para alternativa homologada e compatível com privacidade, permissões e finalidade | Troca automática de fornecedor sem validação |
| AIA03-D05 | Custo | Medição técnica central e créditos comerciais sob domínio de cada produto | Um único saldo indiscriminado entre produtos |
| AIA03-D06 | Segurança | Segredos protegidos, contratos e controle de dados antes de cada chamada | Credenciais comuns ou repasse irrestrito de contexto |
| AIA03-D07 | Alterações | Versionamento, testes e publicação controlada de modelos/roteamento | Atualizações automáticas sem avaliação |
| AIA03-D08 | Interface | Administração técnica Imagine; cliente vê apenas capacidades/consumo autorizados | Cliente acessa livremente configuração dos provedores |
| AIA03-D09 | Qualidade | Homologação com testes de tarefa, custo, segurança e resiliência | Seleção por preço ou marketing do fornecedor |
| AIA03-D10 | Primeira entrega | MVP só com texto, extração estruturada, sumarização e tool calling necessários ao piloto CRM | Incluir todas as modalidades e fine-tuning desde o MVP |

**Todas as decisões AIA03-D01 a D10 permanecem abertas.** Nenhum fornecedor, modelo comercial, preço, contrato, região ou stack técnica foi escolhido/contratado.

## 12. Casos de exceção e critérios documentais

Casos: dois tenants com políticas diferentes, provedor não homologado, finalidade incompatível, API de provedor indisponível, saldo insuficiente, mudança de preço, modelo descontinuado, contagem divergente, retries e resposta estruturada inválida, operação externa pendente, fallback com risco de transferência internacional.

Critérios a revisar:
- [ ] AIA03-D01 a D10 aprovadas ou corrigidas pelo responsável.
- [ ] Matriz de elegibilidade por produto, tarefa, dados e política definida.
- [ ] Contratos de privacidade, segurança, limites e versionamento especificados.
- [ ] Estratégia de medição/contingência alinhada a AIA-08/10/11/12.
- [ ] MVP e casos de teste documentais acordados.
- [ ] Escolha de fornecedores e preços atualizados avaliados em decisão separada.
- [ ] Aprovação adicional de arquitetura, orçamento e implementação em etapas.

**Gates mantidos:** sem código de produto, testes reais, criação de chaves, consumo pago, gastos, deploy, migrações ou alterações em outros repositórios.
