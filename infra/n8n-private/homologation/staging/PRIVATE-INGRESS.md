# Ingresso privado de homologação

A issue Portal #951 comprova acesso administrativo restrito via Tailscale. Sites #38 descreve a necessidade de um domínio técnico de teste e não reserva um hostname para n8n. A configuração R2 do piloto não estabelece Cloudflare Access, DNS privado nem um tenant corporativo. O hostname final deve ser o FQDN do nó aprovado no MagicDNS, se o administrador confirmar MagicDNS e a emissão de HTTPS na tailnet existente. Se essa opção estiver indisponível, usar o hostname designado pelo administrador em zona privada aprovada. Não usar o nome .invalid do laboratório fora dos testes.

## Configuração a apresentar para autorização

- Listener HTTPS somente na interface da tailnet, com ACL/grant limitado ao grupo de operadores. Certificado para o FQDN confirmado. Sem Funnel ou listeners WAN. O n8n permanece somente na rede Compose interna e sem ports. O proxy é o único serviço nessa rede com alcance pelo listener privado.
- Aplicativo OIDC com issuer do tenant aprovado, audience/client ID exclusivo, callback HTTPS /oauth2/callback no FQDN confirmado, conta nominal, grupo imagine-n8n-hml-operators e MFA obrigatório na política do IdP. O proxy verifica grupo e sessão em cada rota. Tokens e cookies recebidos do cliente não podem se transformar em cabeçalhos de identidade upstream. Fixar N8N_PROXY_HOPS=1 somente após confirmar um único proxy confiável.
- Ensaio de aceite: acesso com MFA, requisição sem sessão, sessão expirada, operador removido, acesso direto ao origin e tentativa via interface pública. As quatro recusas de origem/sessão/grupo e o caso positivo devem ser medidos a partir de posições distintas quando aplicável.
- Para Portal → CRM e CRM → Agents, manter o contrato P19. Identidade e concessão por certificado, empresa e operação atribuídas no destino. Aplicar a CRL além da concessão persistente e verificar antes de processar e novamente ao retomar a fila. Testar um certificado revogado, cruzamento de empresa, expiração e reinício entre persistência e ACK. Nenhum certificado de laboratório representa pessoa ou serviço real.

## Retenção do backup

No bucket autorizado, cada captura age é autossuficiente e segue a expiração de 30 dias por objeto. A regra é compatível com esse formato. Não inicializar Restic nesse bucket: uma expiração por idade pode excluir packs, índices ou configurações necessários a snapshots mais recentes. Para um futuro repositório Restic, usar destino com política separada e testar forget em dry-run, check e recuperação antes de autorizar prune. A aplicação da regra e a expiração observada no R2 são evidências diferentes.
