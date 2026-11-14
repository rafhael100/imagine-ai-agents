# F221 — Troca JSON ponta a ponta do laboratório Agents ↔ CRM

**14/11/2026 · laboratório offline, NO-GO.**

Até a F220, o parse do pedido e a serialização da resposta eram comprovados em testes separados. A F221 introduz `MockCrmReadEdgeBoundary::exchangeCanonicalJson`, uma operação **somente em memória** que aceita bytes JSON do contrato sintético, executa a fachada já existente e entrega apenas bytes JSON canônicos da resposta fechada. Resposta inválida ou bytes malformados resultam em uma única negativa pública, sem detalhes de empresa, recurso ou causa, com invalidação de qualquer recibo anterior.

A rotina **não** implementa protocolo HTTP, endpoint Laravel, autenticação de serviço, ACL real nem transporte para outro processo. O objetivo é uma prova de integração do contrato de transporte ainda inerte, não criar uma permissão implícita.

O teste PHP `tools/check_f221_canonical_exchange.php` executa **6 cenários**: sucesso mínimo de dois campos ordenados, replay negado, JSON com chave duplicada, falta de permissão do proprietário, identidade ausente e contador esgotado. Também demonstra recuperação com prova fictícia nova, recheck e auditoria sem leitura adicional. A CI executa o código com regressões Python e, após checkout da versão SHA-fixada do Imagine CRM, o inspetor PHP real do CRM confere o ciclo completo. O retorno do inspetor **nunca concede direitos**.

Baseline **879 testes PHP / lint 110**, mais ensaios F216–F221. **Sem HTTP, IAM real, API, token, CRM real, dados pessoais, VPS, deploy, produção ou cobrança.** [CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**, com **13 cenários reais NAO_EXECUTADO** e **14 gates NO-GO**. `main` permanece intacta.
