# AUTO-IA-09 — Identidade de serviço simulada: envelope HMAC fechado

Complementa [AUTO-IA-08 #351](https://github.com/rafhael100/imagine-ai-agents/pull/351), sem confundir assinatura HMAC com IAM de produção.

## Entrega
- Contrato versionado de **14 campos estritos** (issuer, audience, operation, tenant_ref, event_ref, nonce_ref, request_ref, digest, janela de tempo, key_id e HMAC).
- O emissor de laboratório corresponde ao produto. O destino fixo é `imagine-ai-agents`; somente as **16 operações NO_AI** são aceitas.
- Janela de até 300 s, clock do processo verificador passado por backend, verificação constante de MAC e suporte à retirada de um key ID para representar rotação.
- Testes com 24 operações do catálogo, alteração de empresa/nonce/evento/hash, troca de chave, expiração, dados extras e exploração de quebra de delimitação.
- O serviço de verificação não tem chave privada hardcoded, cliente HTTP nem método para executar comandos. Chaves sintéticas somente no script de teste.

## Limites e NO-GO
**LAB_VERIFIED_NO_AUTHORIZATION** não autentica empresa real nem autoriza inserção no inbox PostgreSQL. Sem vínculo criptográfico comprovado entre o verificador PHP e a função SQL de AUTO-IA-08; isso exige um contrato S2S operacional com identidade de serviço, credenciais protegidas e prova de admissão confiável. O inbox RLS e o antirreplay ainda devem ser testados em uma fronteira de confiança única.

Exige-se revisão independente de segurança, tenant source-of-truth no CRM/Portal, revogação, rotação e persistência de nonce e de políticas. Sem rede, credenciais reais, VPS, n8n instalado, API paga ou custos.
