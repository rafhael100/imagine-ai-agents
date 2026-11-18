# AIA-P48 — Núcleo funcional de automações determinísticas e projeção do funil

16/11/2026. Continuação de P47 (#423), com uma implementação funcional pura em PHP 8.4, sem depender de Docker ou de uma API paga.

**Entrega:** src/Automation/DeterministicAutomationService.php contém um projetor matemático de funil que recebe visitas semanais estimadas, taxa de conversão de visitas em leads e taxa de conversão de leads em clientes, usando inteiros em basis points (10.000 pontos = 100%). Arredonda as quantidades estimadas em cada etapa com half-up, sem erros de ponto flutuante. Uma camada de serviço aceita somente o comando estrito AIA_DETERMINISTIC_COMMAND_V1, a operação funnel.calculate e referências de empresa e requisição sintéticas, e retorna AIA_DETERMINISTIC_RESULT_V1 com contagens estimadas, decisão NO_AI, zero tokens, zero chamadas e zero débito de créditos. A resposta **não é um evento de faturamento** nem comprova visitas ou clientes reais.

**Paridade com a RC07:** com 120 visitas estimadas, 10% de taxa de leads e 20% de conversão em clientes, o cálculo puro retorna exatamente **12 leads e 2 clientes projetados**. O workflow de nós nativos do n8n RC07 é executado na outra tarefa de CI e deve produzir o mesmo resultado. A comparação é de resultado e semântica, não passagem real de eventos entre processos.

**Segurança:** validações de schema fechado e entradas tipadas, volume até 100 milhões de visitas por semana, taxas de 0 a 100% e arredondamento sem overflow dentro desses limites. Rejeita números negativos, strings disfarçadas de inteiros, floats, campos extras, operação de escrita publicitária e identidades não sintéticas. A saída é somente visualização/previsão técnica. Testes unitários adversariais e da bateria P13–P47 são executados em CI.

Ainda não há API autenticada do CRM/Portal chamando esse núcleo, persistência de projeções, consentimento, regras de custos ou fluxo dinâmico de dados ao n8n privado. O cálculo determinístico, por definição, não justifica chamadas de IA.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
