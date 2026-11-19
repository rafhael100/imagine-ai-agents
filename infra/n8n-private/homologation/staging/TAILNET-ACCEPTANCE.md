# Aceite do ingresso pelo nó Tailscale existente

Configurar N8N_PRIVATE_HOST com o FQDN confirmado pelo custodiante em canal administrativo. Manter o valor fora do repositório. Verificar MagicDNS e HTTPS Certificates na página DNS do console Tailscale em leitura. Resolver o FQDN a partir de um cliente autorizado. A resolução do nome e o estado do listener são evidências distintas. Não executar tailscale cert ou serve para sondar estado.

## Proposta de configuração

| Componente | Valor |
|---|---|
| Ingresso | Tailscale Serve HTTPS privado na porta TCP 8443, sem Funnel. Antes de aplicar, comparar `tailscale serve status` e `ss -ltn` com o inventário atual. Se houver conflito, interromper e revisar a proposta. |
| Destino do Serve | Gateway OIDC em 127.0.0.1:18443, porta candidata a conferir, sem bind WAN. Nenhuma rota direta ao n8n. |
| n8n | https://N8N_PRIVATE_HOST:8443/ como editor base URL. Serviço n8n:5678 somente na rede Compose interna, sem ports. Dois proxies, Serve e gateway: validar N8N_PROXY_HOPS=2 e a cadeia de headers antes do aceite. |
| OIDC | Callback https://N8N_PRIVATE_HOST:8443/oauth2/callback, Code + PKCE quando suportado, issuer e client ID do aplicativo privado, grupo de operadores e MFA obrigatório na política do IdP. Validar sessão no gateway antes de cada requisição administrativa. |
| Origin | Gateway aceita o host aprovado, ignora identidade e forwarding vindos do cliente e gera cabeçalhos confiáveis a partir da cadeia aprovada. n8n sem listener no host. Bloquear encaminhamento de API pública e webhooks no gateway e manter N8N_PUBLIC_API_DISABLED=true. |
| Sessão e WS | Cookie Secure, HttpOnly, SameSite=Lax, sessão server-side com validade absoluta proposta de 30 min. Autenticar antes do upgrade. Encerrar sockets ao expirar ou revogar a sessão. Selecionar gateway com essa capacidade ou comprovar intervalo máximo de revalidação acordado. |

## Certificados

Certificado gerenciado pelo Serve, com chave mantida no nó e renovação automática. Verificar SAN, validade e cadeia de um cliente da tailnet e alertar se restarem menos de 21 dias. A emissão ACME de certificado público inscreve o FQDN em Certificate Transparency e a remoção/revogação do certificado não apaga esse registro. Se a divulgação for recusada, propor CA privada e distribuição de confiança aos clientes, com novo desenho para terminar TLS no proxy. Em incidente, desabilitar ingresso e retirar acesso do nó pela autoridade Tailscale quando autorizado, revogar o certificado junto à autoridade emissora e substituir o material antes de reabrir. Para mTLS S2S, usar CA separada e grants do P19, aplicar CRL e revogação da concessão.

## Matriz de testes a executar com identidades sintéticas

| Caso | Aceite |
|---|---|
| Operador no grupo com MFA | Login e editor acessíveis, REST interno do editor sujeito também à autorização nativa |
| Sem MFA ou fora do grupo | IdP exige MFA ou gateway nega; sem sessão utilizável, upstream não recebe pedido |
| Sessão expirada | Nova requisição exige autenticação, socket anterior encerra dentro da janela aprovada |
| Sessão ou acesso revogado | Gateway bloqueia novas requisições e encerra conexão aberta conforme política |
| WebSocket de push | Upgrade 101 com sessão válida, recusa sem sessão e sem bypass por spoof de headers |
| API pública e webhooks | Recusa mesmo com sessão do operador |
| Acesso direto ao origin | Portas 5678 e 18443 inacessíveis a partir da tailnet e WAN; editor acessível somente em 8443 pela ACL aprovada |

Pendências administrativas: evidência de MagicDNS e HTTPS Certificates, autorização da exposição do FQDN em CT, confirmação do issuer/tenant e grupo com MFA, revisão de portas e da ACL específica para o nó. Nenhum comando de configuração foi aplicado.
