# CENTRAL-AUTO-P23 — Comprovante assinado da origem do backup cifrado

Continua as candidatas [P20 #451](https://github.com/rafhael100/imagine-ai-agents/pull/451), [P21 #452](https://github.com/rafhael100/imagine-ai-agents/pull/452) e [P22 #455](https://github.com/rafhael100/imagine-ai-agents/pull/455). Todas as implementações permanecem em **DRAFT / NO-GO**, sem instalação na Leaseweb.

## Por que P23 é necessária
Criptografia CMS AES-256-GCM e hashes autenticados detectam mudanças do pacote **depois de cifrado**, mas qualquer pessoa que possua o certificado público do destinatário pode tentar produzir outro pacote cifrado com manifesto próprio. Para confirmar **quem assina um backup**, é necessária uma chave de assinatura independente, e a verificação deve comparar a chave pública com um fingerprint confiável fornecido por outro canal.

## Componente desenvolvido
- `signed_receipt_p23.py` assina o hash SHA-256 do **ciphertext CMS**, tamanho, destino, emissor, source sintéticos, timestamp, expiração de até 20 minutos na fixture, nonce e estados explicitamente `false` de custódia real, cópia externa, recuperação em outro host e autorização de instalação.
- A assinatura usa **Ed25519 via OpenSSL**, com arquivo de metadados JSON estritamente canônico e assinatura binária separada. A chave privada precisa ser arquivo normal, não link/hardlink, com modo `0400`/`0600`; recibos existentes não são sobrescritos.
- A verificação exige fingerprint SHA-256 da chave pública Ed25519 **fornecido separadamente**, assinatura válida, digest e tamanho exatos do ciphertext, nonce válido, janela de tempo e schema fechado. Chave diferente, recibo alterado, expiração, campos extras, falso GO, arquivo inválido e links simbólicos são rejeitados.
- A P21 agora gera uma chave de assinatura **nova e fictícia** no GitHub Actions, assina o pacote com dumps PostgreSQL reais e valida a assinatura **sobre a cópia do ciphertext antes de descriptografar**. Um ciphertext adulterado deve falhar na assinatura antes mesmo de iniciar a validação CMS. Depois executa manifesto/TAR P22 e `pg_restore` dos três bancos sintéticos.
- A CI repete os testes P20, P22, as verificações negativas P23 e a restauração criptografada P21, sem publicar arquivos de chaves ou o pacote.

## Limites explícitos
**A chave de assinatura na CI é sintética.** O algoritmo comprova a relação entre o pacote e a chave que assinou, mas não verifica custódia de chave operacional nem a identidade nominal de um emissor real. Não enviar a chave privada para GitHub, chat, logs ou contêiner n8n.

Não há log de proveniência externo, carimbo do tempo confiável, revogação/rotação de assinatura, retenção, transferência entre hosts, restauração de credenciais reais, recuperação a partir de storage externo nem RPO/RTO aprovado. Esses controles continuam obrigatórios para o GO de homologação.

**NO-GO PARA MERGE MAIN, VPS, DEPLOY, CUSTÓDIA REAL, COBRANÇAS, DADOS DE CLIENTES OU IA PAGA.**
