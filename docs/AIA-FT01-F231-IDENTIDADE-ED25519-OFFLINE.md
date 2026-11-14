# F231 — pré-validação de identidade serviço-serviço com Ed25519

**14/11/2026 · proposta offline · NO-GO.**

## Problema real que esta etapa começa a resolver

A autenticação de **pessoas** no CRM (senha/TOTP/sessão PostgreSQL/Redis, PRs
#597–#599) não autentica o serviço Imagine AI Agents. Para conectar os
serviços precisamos de um mecanismo independente, no qual o CRM não confie
em `X-Tenant-Id`, em IDs escritos pelo agente ou em uma prova HMAC com
chave pública de laboratório.

## Entrega técnica verificável

`src/MockBridge/MockServiceIdentityPreflight.php` implementa uma
**pré-validação criptográfica assimétrica real Ed25519**, mas aceita
**somente atributos sintéticos**. Não há emissão de credenciais, endpoint,
membro CRM, token operacional, conexão com Redis/PostgreSQL ou liberação de
dados.

O validador recebe um conjunto **injetado** de chaves **públicas** de
verificação. Nunca aceita uma chave de dentro da mensagem. Exige
`issuer`, `audience`, `subject`, `tenant_ref`, operação estritamente
somente leitura, `key_id`, SHA-256 dos bytes canônicos do pedido,
`nonce`, validade de no máximo 30 s, assinatura Ed25519 destacada,
ordem/schema de claims fechados e serialização canônica.

`tools/check_f231_ed25519_preflight.php` cria chaves **determinísticas e
deliberadamente públicas de teste**, somente durante o ensaio e nunca
adequadas a produção. Executa **3 tenants** fictícios, **24 negativos**
com assinatura real (inclusive claims inválidas assinadas corretamente),
invalidação da chave de confiança, replay entre tenants, saturação do
ledger local e indisponibilidade. Nenhum teste pode alterar o sistema real.

O gate offline executa a prova PHP e os contratos Python. Baseline
mantido: **879 testes PHP**, lint ampliado de **111 para 112** arquivos
PHP, mais os ensaios de integração. Nenhuma dependência instalada.

## O que a F231 não resolve

O método só informa se a assinatura fictícia é válida: **não concede
autorização nem realiza leitura**. Ainda faltam a fonte operacional
confiável de chaves, rotação e revogação auditáveis, identidade delegada,
chave privada protegida fora do Git, nonce distribuído durável,
autorização do tenant/principal/recurso/campo exclusivamente no backend
CRM, transportes e infraestrutura TLS/mTLS, LGPD e homologação controlada.

**CRM #57 OPEN/DRAFT; 14 gates, 7 decisões e 13 cenários reais
NAO_EXECUTADO. NO-GO.** A PR F231 é empilhada sobre F230 e F229, sem
merge automático. Sem alterações em `main`, VPS, clientes, produção,
segredos, cobranças ou custos.

## Próximo recorte a implementar

Contratar com CRM CORE/AUT/SEG o adaptador de identidade autenticada
(issuer/audience/keyring), política deny-by-default por empresa,
usuário/serviço, lead e campos e storage de nonce compartilhado, após
revisão independente dos gates. O CRM deve continuar sendo o único
autorregulador efetivo da leitura.
