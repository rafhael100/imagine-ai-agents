# CENTRAL-AUTO-P13 — Rede privada e superfície NO_AI do n8n

Continua a [RC08 #421](https://github.com/rafhael100/imagine-ai-agents/pull/421), [HOM-01 issue #414](https://github.com/rafhael100/imagine-ai-agents/issues/414) e mantém o workflow nativo [P9 #409](https://github.com/rafhael100/imagine-ai-agents/pull/409) sem alterar a lógica 120 visitas → 12 leads → 2 clientes.

## Escopo aprovado: sem consumo de API IA e sem egress autorizado
- Adiciona `N8N_PUBLIC_API_DISABLED=true` e `N8N_PUBLIC_API_SWAGGERUI_DISABLED=true`: protege a superfície programática, **não substitui autenticação e MFA de administrador**.
- Adiciona `N8N_SSRF_PROTECTION_ENABLED=true`. Proteção SSRF é defesa em profundidade; **as redes internas Docker continuam a barreira principal**. Não inclui allowlist que poderia liberar acesso a rede local/cloud metadata.
- Amplia `NODES_EXCLUDE` para proibir os nós `code`, `executeCommand`, `ssh`, `readWriteFile` e `httpRequest` nesta fase. **HTTP Request deve continuar bloqueado até existir gateway autorizado com contrato S2S e egress controlado.** Não se trata de configuração genérica para produção com integrações externas.
- A política fechada em `check_noai_egress_policy.py` é acionada tanto diretamente na CI quanto pelo `check_compose_static.py` já utilizado por RC08, para evitar aprovação com um verificador antigo.
- Testes mutacionais recusam abertura de API/Swagger, desligamento SSRF, allowlist de IP ou hostname, chave de modelo em ambiente, alteração das redes para externas, porta host pública, desbloqueio de nós arriscados, DNS manual e perda de capacidades reduzidas.
- Teste em contêiner oficial n8n/PG17 verifica flags e tenta conexão TCP somente com os endereços **RFC 5737 TEST-NET** `192.0.2.1` e `198.51.100.2`; a conexão não pode ser estabelecida. **Não há chamada a fornecedor ou endereço real de cliente**. Isso não prova isolamento de todos os protocolos nem garante o firewall de uma VPS futura.
- Executa novamente o workflow nativo, restauração do banco PG17, recuperação do volume/chave em segundo Compose de CI, mTLS/PG16, inventário de host fictício e bloqueios NO-GO da RC08 na mesma HEAD.

## Limitações importantes
- A ausência de credenciais/egress durante os testes significa que nenhuma API paga é chamada **nesse laboratório**. Os campos `ai_api_calls=0` e `credits_debited=0` continuam sendo campos emitidos pelo próprio workflow, não comprovantes financeiros independentes.
- A regra de ativação da IA pertence ao **CRM/AI Agents com permissão, orçamento e aprovação por empresa**, não ao n8n. Não conceder chaves de provedor diretamente ao n8n e não permitir que um editor contorne o motor de créditos.
- Esta configuração exclui acesso HTTP por desenho. Para permitir comunicação n8n→CRM/Portal no futuro, criar proxy/receiver privado específico com identidade mTLS, allowlist de ações e domínio, expiração, replay durável, revogação e auditoria; não remover proteção global sem revisão independente.
- Nenhum n8n foi instalado na Leaseweb. Ainda faltam inventário real autorizado, licença aplicável ao serviço interno, ingress HTTPS/MFA, IAM operacional e backup externo de dados e chave. **Nenhum acesso de cliente ao editor n8n**.
- A CI pode demonstrar que sockets a endereços TEST-NET não conectaram; não demonstra que todos os caminhos até provedores estão bloqueados nem constitui medição de cobrança real. Avaliar egress no ambiente da VPS sob autorização.

## Fontes oficiais
- [n8n — bloquear nós](https://docs.n8n.io/hosting/securing/blocking-nodes/)
- [n8n — desativar API pública](https://docs.n8n.io/hosting/securing/disable-public-api/)
- [n8n — proteção SSRF](https://docs.n8n.io/hosting/securing/ssrf-protection/)

**DRAFT / NO-GO / SEM MERGE, DEPLOY, VPS, IA PAGA OU DADOS REAIS.**
