# AUTO-IA-13 — Proibição padrão de IA nos workflows internos n8n

A diretriz [DEC-AUTO-IA-001 #339](https://github.com/rafhael100/imagine-ai-agents/issues/339) estabelece que **automação convencional é a primeira opção** e chamadas pagas de IA exigem autorização e controle financeiro independentes.

## Escopo validado agora
O catálogo em `tools/auto_ia/policy/approved_no_ai_workflows.json` é **fechado e negado por padrão**. Ele declara somente o workflow manual e inativo do funil [AUTO-IA-12 #357](https://github.com/rafhael100/imagine-ai-agents/pull/357), com seu SHA Git Blob exato para impedir alteração silenciosa do JavaScript.

O verificador percorre todos os arquivos `*.workflow.json` em `tools/auto_ia/demo/`. Qualquer arquivo novo sem entrada correspondente, nó não previsto, workflow ativo, credenciais, URL, webhook, LangChain/AI Agent, HTTP Request, pin data, JavaScript modificado ou formato de conexão inesperado causa falha. Inclui oito testes negativos com mutações adversariais e rejeição de chaves JSON duplicadas.

**Isso é uma defesa de integridade estática, não uma garantia universal de ausência de consumo.** Código obfuscado, nós externos, integrações adicionadas fora deste diretório ou configuração do n8n na VPS só poderão ser controlados por validações de instalação, segredo, rede/egress, credenciais e autorizações no Gateway central. O verificador exige revisão exata de conteúdo para qualquer Code node, em vez de confiar só em busca de palavras.

## Para crescer sem desperdício
- Workflows sem IA devem ser adicionados ao catálogo após revisão de diff, origem empresarial e funções necessárias, ficando desativados por padrão.
- Os que exigirem IA **não** devem ser liberados por essa lista: precisam de política assinada, cota e autorização central; nunca chaves de provedores diretamente no n8n.
- Os caminhos reais de homologação/produção devem ter um validador equivalente no processo de importação e permissão de editor controlada; esta implementação só cobre workflows versionados em `tools/auto_ia/demo/`.
- Não há integração do verificador à interface n8n operacional, nem instalação na VPS, merge, deploy, pagamento, créditos ou API paga.

**DRAFT / NO-GO / NO-AI-SPEND / NO-DEPLOY.**
