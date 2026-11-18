# CENTRAL-AUTO-P19 — ingresso privado candidato

## Captura imutável para processamento posterior

O modo de servidor `AGENTS_PRIVATE_LEAD_CAPTURE=immutable-v1` conserva os
bytes autenticados de `CRM_LEAD_EVENT_V1` na mesma transação da inbox.
A configuração padrão mantém o caminho anterior; modos desconhecidos falham
fechados. O ACK continua confirmando apenas recebimento com `DISABLED`.
Contrato, duplicidade, rollback, restauração e limites em
[CRM-LEAD-CAPTURE](../../infra/n8n-private/homologation/CRM-LEAD-CAPTURE.md).

Base #446; árvore consolidada P17 + #435 + Agents P60 é exportada somente no runner pelo procedimento P18 existente, com os quatro arquivos endurecidos P17 preservados. Estes arquivos de runtime são sobrepostos explicitamente para executar a integração na mesma árvore; isso não faz merge de branches.

O endpoint pertence ao produto Agents e usa a inbox, catálogo NO_AI, chave assinada e semântica de duplicidade existentes em AUTO-IA-08/10. Nova entrada autentica mTLS por FastCGI reservado e vínculo persistente empresa/certificado/operação/tenant; os locks de grant e chave permanecem até commit. Payload assinado vincula UUID de empresa, evento e lead. Somente lead.created/lead.ingest; nenhum motor de regras comercial novo.

O schema e referências SYNTHETIC são exclusivamente de CI. Nenhuma fixture representa concessão operacional. SQL nunca deve ser aplicado como migration produtiva. Runtime EXECUTE-only, RLS FORCE na inbox e grants; consulta explícita por tenant autorizado. Receptor não encaminha sessão humana nem faz dispatch, chamada IA ou efeito externo. Confirmação assinada correlaciona empresa/evento/hash e mantém DISABLED.

Teste integrado fixado na candidata CRM #636: duas aplicações Laravel + runtime PHP do produto Agents, três contêineres de aplicação e bancos independentes, ingress privados mTLS, certificado/empresa/operação/revogação, idempotência, auditoria, falha e recuperação. Criação de arquivo não equivale a execução aprovada. Registro dos resultados segue após CI.

Pendente para operação: autoridade de concessões real e revisão externa, política de retenção, custódia/rotação de chaves, migração própria sem tabelas lab, inventário Leaseweb, MFA humano, backup externo e licença. DRAFT/NO-GO; nenhuma VPS ou porta existente alterada.


## Consulta privada de recibo — P27

A rota candidata `POST /private/events/receipt` recupera o recibo de um evento
P19 já admitido, sem reenviá-lo. Exige mTLS, query assinada e concessão
independente `event.receipt.read`, além do vínculo de empresa, grant de ingresso
e chave atuais. Retorna somente referências e `RECEIVED`, `DISABLED`,
`NOT_DISPATCHED`; não afirma execução pela fila P26.

`receipt-status.sql` instala a função e o papel NOLOGIN/EXECUTE-only de leitura.
O login separado e os grants usados pelo ensaio são criados exclusivamente na
CI. `index.php` e o ACK original permanecem intactos. O Dockerfile passa a
incluir o novo handler e o nginx acrescenta apenas a rota exata de consulta.

Contrato, assinatura, permissões, ensaio HTTPS/mTLS e limites:
[RECEIPTS-P27](../../infra/n8n-private/homologation/RECEIPTS-P27.md).
O estado operacional continua **NO-GO**, sem instalação ou integração do cliente
CRM presumidas por estes testes.

## Validação do ingresso — P29

O receiver passa a recusar nomes JSON duplicados, inclusive escapes
equivalentes, e exige referências UUID textuais para empresa, evento e lead.
Mensagens JSON válidas conservam liberdade de formatação e ordem dos campos.
O ACK válido mantém seu formato, assinatura e dispatch `DISABLED`.

A função de admissão confere novamente os prazos depois das esperas no banco.
Uma tentativa que expire durante a escrita desfaz suas alterações de inbox e
auditoria, preservando o registro de outra transação vencedora. O ensaio
cobre requisições HTTP malformadas com HMAC válido e quatro corridas reais no
PostgreSQL, seguidas por controles válidos.

Contrato e verificação: [INGRESS-P29](../../infra/n8n-private/homologation/INGRESS-P29.md).
