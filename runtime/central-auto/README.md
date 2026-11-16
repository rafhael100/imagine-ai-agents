# CENTRAL-AUTO-P19 — ingresso privado candidato

Base #446; árvore consolidada P17 + #435 + Agents P60 é exportada somente no runner pelo procedimento P18 existente, com os quatro arquivos endurecidos P17 preservados. Estes arquivos de runtime são sobrepostos explicitamente para executar a integração na mesma árvore; isso não faz merge de branches.

O endpoint pertence ao produto Agents e usa a inbox, catálogo NO_AI, chave assinada e semântica de duplicidade existentes em AUTO-IA-08/10. Nova entrada autentica mTLS por FastCGI reservado e vínculo persistente empresa/certificado/operação/tenant; os locks de grant e chave permanecem até commit. Payload assinado vincula UUID de empresa, evento e lead. Somente lead.created/lead.ingest; nenhum motor de regras comercial novo.

O schema e referências SYNTHETIC são exclusivamente de CI. Nenhuma fixture representa concessão operacional. SQL nunca deve ser aplicado como migration produtiva. Runtime EXECUTE-only, RLS FORCE na inbox e grants; consulta explícita por tenant autorizado. Receptor não encaminha sessão humana nem faz dispatch, chamada IA ou efeito externo. Confirmação assinada correlaciona empresa/evento/hash e mantém DISABLED.

Teste integrado fixado na candidata CRM #636: duas aplicações Laravel + runtime PHP do produto Agents, três contêineres de aplicação e bancos independentes, ingress privados mTLS, certificado/empresa/operação/revogação, idempotência, auditoria, falha e recuperação. Criação de arquivo não equivale a execução aprovada. Registro dos resultados segue após CI.

Pendente para operação: autoridade de concessões real e revisão externa, política de retenção, custódia/rotação de chaves, migração própria sem tabelas lab, inventário Leaseweb, MFA humano, backup externo e licença. DRAFT/NO-GO; nenhuma VPS ou porta existente alterada.
