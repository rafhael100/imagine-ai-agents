# CENTRAL-AUTO-P16 — Vínculo da identidade do operador ao certificado exato

Complementa [P15 #434](https://github.com/rafhael100/imagine-ai-agents/pull/434) e [HOM-01 #414](https://github.com/rafhael100/imagine-ai-agents/issues/414). **Não é IAM operacional nem MFA.** A CI permanece com certificados descartáveis e sem acesso ao servidor Leaseweb.

## Ameaça controlada
Uma CA, mesmo confiável, pode assinar mais de um certificado cujo `Subject CN` seja `imagine-operator-ci`. A verificação somente do CN não distingue duas chaves diferentes com o mesmo nome. P16 mantém validação TLS/mTLS por CA e DN, mas acrescenta a autorização do **fingerprint do certificado exato**.

## Implementação
- O nginx oficial usa `map $ssl_client_fingerprint $operator_cert_allowed` com uma lista de fingerprints limitada a um certificado de laboratório. O mapa fica em arquivo separado montado `:ro`, não em variável de ambiente nem credencial de aplicativo.
- O script de GitHub Actions gera um certificado autorizado, outro de CN diferente na mesma CA, um **segundo certificado com CN idêntico na mesma CA** e um certificado da CA errada. Calcula o fingerprint SHA-1 do certificado previamente verificado (SHA-1 aqui é apenas identificador para *pinning*, não uma assinatura de CA) e monta a lista com um único identificador de 40 caracteres hexadecimais.
- Um teste real abre a interface n8n pelo proxy apenas com o certificado pinado. Sem certificado, CA errada, CN diferente e **mesmo CN com outra chave/certificado** são bloqueados.
- O validador do Compose exige os dois arquivos de configuração com caminhos e destinos específicos, ambos montados somente leitura, em um proxy `127.0.0.1:18744` isolado de PostgreSQL. Testes negativos proíbem remover ou tornar gravável o arquivo de pinning.
- Os demais jobs P15 continuam executando n8n oficial/PG17, workflow nativo sem IA, auditoria oficial do n8n, backup/restore, mTLS de serviços e bloqueios NO-GO.

## Limitações e próximos gates
- O arquivo de fingerprint no CI **não é um repositório de identidades real**. Em produção, a emissão e revogação de certificado devem ficar sob autoridade aprovada, com armazenamento seguro, validade curta, observabilidade e política de rotação. Fixar certificado no proxy não equivale a um segundo fator de login humano.
- O método é de defesa em profundidade após autenticação da CA. Futuros requisitos devem considerar IDs persistentes de operador, IdP, SSO/MFA, segurança de sessão do n8n, controle de acesso ao editor e segregação de clientes.
- Como em P15, o proxy tem uma bridge frontal sem exposição pública e a instância n8n continua nas redes internas. **Não executar overlay na Leaseweb** ou abrir DNS/firewall até revisão independente e inventário real.
- Somente dados fictícios; sem merge, deploy, credenciais reais, dados pessoais, APIs pagas ou cobrança.

Referência: [ngx_http_ssl_module — ssl_client_fingerprint](https://nginx.org/en/docs/http/ngx_http_ssl_module.html).

**PR DRAFT / NO-GO / NO-VPS / NO-MFA OPERACIONAL.**
