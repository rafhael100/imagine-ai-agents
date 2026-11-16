# CENTRAL-AUTO-P17 — Rotação de certificado do operador sem reiniciar n8n

Continuação de [CENTRAL-AUTO-P15 #434](https://github.com/rafhael100/imagine-ai-agents/pull/434) e [P16 #436](https://github.com/rafhael100/imagine-ai-agents/pull/436). **A prova acontece somente em um nginx descartável de CI, não na Leaseweb.**

## Contrato de revogação testável
1. O proxy nginx começa aceitando um certificado cliente emitido por CA sintética, cujo fingerprint está numa allowlist de **uma entrada**, montada read-only no contêiner. Um segundo certificado, emitido pela **mesma CA e com o mesmo CN**, é inicialmente negado — prova P16.
2. O teste atualiza, no arquivo **de teste do runner** que origina o bind read-only, o único fingerprint permitido. O certificado anterior é removido. O arquivo de configuração não contém a chave privada do operador, só o identificador do certificado.
3. Executa `nginx -t` e `nginx -s reload` somente no contêiner `operator-ingress` desse projeto descartável; **n8n, banco e seus workflows não são reiniciados nem reconfigurados**.
4. Uma nova requisição TLS com o certificado antigo, ainda validamente assinado pela CA, recebe HTTP 403; outra com o novo certificado de **mesmo nome** passa. O teste usa conexões novas para evitar cache de sessão TCP.
5. Confirma novamente que só o nginx publica `127.0.0.1:18744`, enquanto n8n e PostgreSQL17 permanecem sem portas host. Toda a CI volta a verificar isolamento, consumo nativo NO_AI, auditoria oficial, restauro PG17 e mTLS de serviço.

## Limitações
- A troca por arquivo local e reload não é sistema de revogação operacional com emissão, caducidade, CRL/OCSP, proteção de CA, armazenamento de PIN e logs de auditoria. Não é MFA humano nem cadastro real de operador. É apenas um **teste sintético de denegação de acesso à identidade antiga após rotação**.
- Não há validação de conexões HTTP já estabelecidas antes da troca, nem de cluster distribuído, disponibilidade sem interrupção ou mudança em rede externa. Em produção, é necessário encerrar sessões antigas e revogar os acessos também no IdP/Portal.
- A Bridge frontend só existe para o proxy, loopback host, sem publicação DNS. Nenhum token, login real, cliente, cobrança ou API de IA é utilizado.
- Para utilizar um operador real, são exigidos inventário host Leaseweb, IAM/MFA, auditoria AppSec/DBA/LGPD, gestão dos certificados e backup externo, licença n8n e aprovação explícita da implantação.

**DRAFT / NO-GO / NO-VPS / NO-DEPLOY / NO-PAID-API.**
