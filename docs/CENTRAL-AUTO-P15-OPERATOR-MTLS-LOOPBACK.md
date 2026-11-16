# CENTRAL-AUTO-P15 — Ingress administrativo opcional, loopback e certificado mTLS

Continuação da [Central de Automações HOM-01 #414](https://github.com/rafhael100/imagine-ai-agents/issues/414) e [P14 #430](https://github.com/rafhael100/imagine-ai-agents/pull/430). **A opção é exclusivamente de laboratório CI e NÃO é uma instalação na Leaseweb nem acesso administrativo utilizável pelo cliente.**

## Arquitetura e separação das redes
- O Compose original P14 **não é alterado**: n8n e PostgreSQL17 continuam em Docker `internal: true`, sem host ports, API pública desabilitada, SSRF protegido e sem Code/HTTP/SSH/arquivo/IA.
- `docker-compose.operator-loopback.yml` é um *overlay opcional* para um quinto job de CI. O serviço nginx oficial recebe uma rede de frontend bridge separada e a rede interna `n8n_apps_private`, **mas não participa da rede de armazenamento `n8n_storage`**. n8n e PostgreSQL não participam da rede frontend.
- O ingress publica **somente** `127.0.0.1:18744` para TLS interno; nenhum bind `0.0.0.0`, nenhuma porta de n8n 5678 ou PostgreSQL 5432 para host. As políticas originais são preservadas em job independente e a política da candidata compara o Compose completo com a base fechada.
- Nginx faz proxy apenas para `http://n8n:5678`, exige TLS 1.2+ e `ssl_verify_client on` com CA de cliente exclusiva de CI. Além disso, só aceita o CN sintético `imagine-operator-ci`; certificado válido mas CN diferente recebe 403. O processo não carrega segredos de IA nem dos bancos.
- A CA de cliente e o certificado público do servidor têm leitura permitida dentro do contêiner, mas a **chave privada de laboratório** é montada somente para o usuário root, modo `0400`, sem `DAC_OVERRIDE`. O master nginx tem apenas `SETUID`, `SETGID` e `CHOWN` para reduzir a identidade dos workers e ajustar a propriedade do diretório temporário isolado; `DAC_OVERRIDE`, `NET_ADMIN` e demais privilégios continuam bloqueados. Isso não dispensa auditoria operacional de privilégios.
- O n8n no overlay configura `N8N_PROXY_HOPS=1` e editor URL HTTPS loopback. Webhooks externos permanecem sem URL e o `N8N_PUBLIC_API_DISABLED=true` permanece inalterado.

## Teste real (nenhum fornecedor pago)
1. GitHub Actions usa n8n oficial fixado por digest, PostgreSQL17 pinado no início do job e nginx oficial pinado por digest obtido da imagem publicada. Gera CA e certificados descartáveis apenas no runner, separados entre CA de servidor e CA de cliente, e depois elimina tudo.
2. Com o overlay, executa `docker compose config` e validadores de esquema, rede, permissões, imagem por digest e porta única loopback. Uma suíte de mutações altera porta, bind, serviço, rede, credencial e profile e exige recusa.
3. Inicia três contêineres somente neste job descartável. Inspeciona *apenas* a rede e o bind host deste projeto CI. Testa que o certificado do operador abre a página n8n via HTTPS; sem certificado, com certificado da CA errada, e com CN não autorizado o acesso é bloqueado.
4. Comprova que n8n e banco seguem sem host port, e executa `down --volumes` **somente do projeto CI `imagine-n8n-p15-ci`**, sem afetar qualquer host ou projeto real.

## Limites e pré-condições
- **mTLS não é MFA**. O projeto não possui ingress operacional com login humano e segundo fator verificado, custódia de certificado/CA real, autorização nominal, revogação OCSP/CRL, SSO nem vínculo com contas no Portal. Esses recursos são gates externos e não devem ser anunciados como concluídos.
- A rede frontend do proxy é *não interna* para permitir o bind loopback no host. Isso não dá conectividade externa ao n8n, mas o ingress tem uma superfície de rede adicional, razão pela qual a candidata é apenas CI e exige revisão independente AppSec.
- Não executar o overlay na Leaseweb. Em CI o CN e os certificados são deliberadamente sintéticos; qualquer publicação antes de autorização formal para acesso privado constituiria violação do NO-GO.
- Para um operador remoto, um futuro túnel SSH seguro, acesso Zero Trust/IdP MFA ou VPN com autenticação formal deve ser homologado separadamente. Não fornecer URL pública nem realizar mudança de DNS/firewall sem autorização.
- Depois do acesso administrativo, continuam pendentes licença aplicável ao modelo Imagine, IAM S2S e isolamento entre empresas, gateway de integração, backup externo e recuperação sob credenciais reais, revisão AppSec/DBA/LGPD e aceite humano.

Referências: [n8n reverse-proxy](https://github.com/n8n-io/n8n-docs/blob/main/docs/deploy/host-n8n/configure-n8n/basic-configuration/configuration-examples/configure-webhook-urls-with-reverse-proxy.md) e [Docker Compose networks](https://docs.docker.com/compose/how-tos/networking/).

**DRAFT / NO-GO / NO-MERGE / NO-VPS / NO-MFA-OPERATIONAL / NO-PAID-AI.**
