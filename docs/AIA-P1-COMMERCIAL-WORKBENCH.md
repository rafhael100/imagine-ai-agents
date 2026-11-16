# Imagine Agentes — P1 bancada funcional do Assistente Comercial

**16/11/2026 — evolução da demonstração P0; somente navegador, dados sintéticos e NO-GO.**

A [PR P0 #335](https://github.com/rafhael100/imagine-ai-agents/pull/335) entregou uma interface navegável sem comunicação externa. P1 acrescenta interações efetivas à própria página `examples/p0-assistente-comercial-demo.html`, mantendo a mesma superfície e identidade:

1. **Criar rascunho** na aba *Meus agentes*: nome de 3–40 caracteres com caracteres permitidos, 2 modelos fechados (Assistente Comercial e Análise de leads), normalização NFC, recusa de nomes duplicados e até **5 rascunhos**.
2. **Selecionar o agente** na aba *Executar simulação*: o preset inicial funciona mesmo sem rascunho; as novas escolhas são criadas e removidas apenas pelo ciclo de vida da aba, nunca salvas em banco ou browser storage.
3. **Executar** até **8 recomendações** por página/sessão usando o algoritmo determinístico testado em P0. O modelo Análise de leads só produz resumo: não cria aprovação nem ação externa.
4. **Revisar sugestões**: cada recomendação de próxima ação cria item pendente na Central de aprovações. Aprovar/rejeitar altera unicamente a tela local; nenhuma mensagem, tarefa de CRM, chamada IA ou n8n é enviada.
5. **Auditar localmente**: histórico limitado a 10 linhas, sem identificadores pessoais, armazenado exclusivamente na memória.

A página continua sob CSP `connect-src 'none'`, sem XHR/fetch, cookies, localStorage, scripts externos, endpoints, roteamento, cobrança ou chaves. O limite de execuções é **apenas demonstração visual e não um controle seguro de consumo**. Configuração de agente, verdadeira autenticação, ACL, cotas server-side, aprovação multifator, telemetria operacional, motor real de IA e n8n ainda não existem nesse P1.

## Validação

CI testa `python3 tools/check_p0_sales_demo.py` (regressão P0: CSP + cenário de funil + Node JS real) e `node tools/check_p1_sales_workbench.js` (DOM local artificial: criação válida, HTML hostil, duplicidade, cinco rascunhos, dois modelos, oito execuções, criação e aprovação de tarefa local, ausência de ações externas). Ambos sem rede nem dependências de terceiro.

**Próxima fase**: P2 — testes de compatibilidade entre a prévia do Portal e a demo do Agents e início de um contrato formal de configuração versionada e aprovação sujeito a revisão, sempre sem endpoint operacional até IAM, CORE, SEG, DBA e LGPD.
