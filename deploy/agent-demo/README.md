# Agents P3 — distribuição isolada do MVP demonstrável por Docker Compose

**16/11/2026 · preparação e ensaio apenas em GitHub Actions, NÃO IMPLANTADO na VPS.**

A árvore do Imagine Agents contém uma interface comercial navegável P0–P1 e um domínio PHP de laboratório P2. Este pacote serve **somente a interface estática** através de um processo Python sem bibliotecas externas, dentro de contêiner rootless e read-only. O backend PHP e o CRM não são executados nem conectados. O servidor aceita apenas `GET /`, `GET /index.html` e `GET /healthz`; todas as outras rotas e métodos, inclusive `/api/agents/op01/leads`, recebem 404 uniforme. Uma CSP impede rede, formulários, iframes e imagens externas; aplica `no-store`, `nosniff`, `X-Frame-Options: DENY` e demais cabeçalhos.

O Docker Compose `deploy/agent-demo/compose.yaml` usa projeto e rede exclusivos, `internal:true` (sem egress), elimina capacidades, aplica `no-new-privileges`, `read_only: true`, UID sem privilégios, cotas de CPU/RAM/PIDs e **porta host escolhida dinamicamente em 127.0.0.1**. Não ocupa as portas conhecidas do CRM e Sites. A restrição de contexto `Dockerfile.dockerignore` inclui somente HTML e servidor Python; arquivos da aplicação, Git e credenciais não são copiados.

A CI executa Docker real em runner descartável, testa a saúde, bloqueia APIs, valida cabeçalhos e a impossibilidade de escrita na raiz, roda P0/P1/P2 e destrói o ambiente. **A imagem base do runner foi fixada no digest OCI `sha256:9ef1a599f863231c3d91e941389bfdc4a2d6070557243d54df1a4e57015b960e`, observado na CI descartável. Antes de qualquer uso externo continuam obrigatórios a atualização por digest, avaliação de vulnerabilidades e revisão independente**. O serviço não oferece autenticação, TLS público, WAF, privacidade de sessão nem identidade de cliente e não deve ser servido externamente.

## Operação futura: gate de staging privado

**Não iniciar na VPS** até inventário atual de recursos/portas, autorização de instalação específica, revisão de imagem e proteção de origem/HTTPS, avaliação de exposição por proxy (ou túnel autorizado) e estratégia de backup/rollback. A demo deve continuar segregada de Portal, CRM, Sites, n8n e de qualquer dado de cliente. Em staging autorizado, a porta dinâmica seria descoberta com `docker compose port preview 8080` e somente o proxy privado selecionado poderia acessá-la.

As decisões OP-01 e PR CRM #57 seguem NO-GO; rodar esta imagem não executa a integração funcional Agents↔CRM.
