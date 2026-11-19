# Google Workspace e callback do ingresso privado

Google Workspace corporativo é o candidato confirmado. Discovery pelo issuer https://accounts.google.com. O hostname MagicDNS já foi confirmado pelo custodiante. Manter em configuração privada. A opção Serve usa HTTPS 8443 e proxy 127.0.0.1:18443, sujeitos à leitura do inventário. O n8n permanece na rede interna sem publicação de 5678.

## Callback e alternativa

ts.net consta na Public Suffix List. Isso não comprova ownership do domínio registrável do callback nem o aceite no Google Auth Platform. A URI exata inclui esquema HTTPS, porta 8443 e caminho /oauth2/callback. Tratar MagicDNS como opção condicional à aceitação do domínio autorizado no projeto Google. Nenhum OAuth client foi criado.

Alternativa preferencial se houver rejeição ou falta de comprovação de domínio: usar um subdomínio de zona corporativa já verificada pela Imagine, com resolução privada na tailnet e TLS terminado no gateway para esse mesmo nome. Editor e callback devem usar a mesma origem corporativa, evitando cookies entre domínios e redirects abertos. Fazer bind apenas no IP da tailnet. Certificado de CA interna requer distribuição de confiança aos clientes. Certificado público via DNS-01 requer autorização da emissão, do desafio DNS e da divulgação em CT. Não publicar porta WAN nem preservar um callback público em separado. Confirmar 8443, 18443 e inventário antes de aplicar a topologia escolhida.

## Autenticação e verificação

- OAuth aplicativo Internal, se elegível no projeto Workspace aprovado, e Authorization Code com state, nonce e PKCE. Validar assinatura/JWKS, iss, aud, exp, nonce, sub e hd exato. Parâmetro hd no pedido é só dica de login. Um endereço com sufixo corporativo não decide acesso.
- Usar lista nominal aprovada e mapear as contas aos sub verificados na inscrição. Não inferir Google Groups no ID token. Aplicar 2-Step Verification obrigatória à OU/grupo, confirmar inscrição e usar evidência auditável da política. Login OIDC não atesta MFA por sessão. Se houver requisito de comprovação por sessão, adicionar fator verificável pelo gateway antes do aceite.
- Sessão server-side com expiração absoluta de 30 min como proposta. Revogar sessão e retirar autorização nominal. Não encaminhar headers de identidade vindos do cliente. Revalidar WebSocket ativo e fechá-lo dentro da janela de revogação acordada.
- Aceite com aplicação sintética: recusar token sem hd, hd divergente mesmo com e-mail corporativo, sub fora da lista, MFA ausente, sessão vencida/revogada, API pública, webhook, WebSocket não autenticado e acesso direto ao origin. Testar encerramento de WebSocket já aberto ao revogar. HTTPS, TLS, portas e rota permanecem sujeitos ao ensaio no ambiente designado.

Administração precisa confirmar apenas o domínio exato hd, projeto/owner OAuth, lista/grupo e gestão da política 2SV, domínio corporativo verificado disponível para a alternativa, inventário/ACL do nó e escolha de TLS. Sem ativação ou alterações de infraestrutura.
