# FT01/SEC-23 — Limites de intenção, idempotência e identidade de aprovação

**Data:** 10/11/2026. **Escopo:** modelo PHP sintético e totalmente offline. **CRM real / piloto / produção: NO-GO**.

## Lacuna e correção

Os objetos `Intent` e `Approval` verificavam apenas o prefixo `SYNTHETIC_` para referências, chaves e material. Era possível criar objetos com identificadores ou material arbitrariamente extensos. A assinatura `Intent::signature()` replica o material e a chave em um JSON; entradas excessivas podem provocar consumo desnecessário de memória e CPU antes de qualquer operação.

A SEC-23 aplica limites no **construtor**, antes de assinatura ou criação de aprovação:

- `Intent.id` e `Intent.idempotencyKey`: **máximo de 128 bytes** cada, com sufixo após `SYNTHETIC_` obrigatório.
- `Intent.material`: **máximo de 4096 bytes**, com prefixo e sufixo válidos. A interface de ferramentas mantém seu limite próprio **mais restritivo de 256 bytes** para material.
- `Approval.id` e `Approval.approver`: **máximo de 128 bytes** cada, com sufixo obrigatório.
- Entradas não conformes continuam retornando `INVALID_SYNTHETIC_REF`; aprovação inválida não vira autorização nem gera `submit`.

## Regressões U45-01..07

Teste de ID de intenção com 129 bytes, chave de idempotência com 129 bytes, material com 4097 bytes, sufixos vazios, ID/revisor de aprovação excessivos ou vazios, aceitação dos limites exatos (128/4096 bytes), assinatura determinística e preservação do fluxo válido após uma tentativa rejeitada.

**Gate exigido:** 421 testes PHP, lint de 82 arquivos PHP e verificadores Python existentes. A integração à `dev` depende de CI no SHA revisado.

## Limites e decisão operacional

O controle reduz abuso de tamanho de dados no laboratório, mas **não substitui** autenticação serviço-serviço, identidade de pessoa, rate limiting, armazenamento durável, ACL do CRM ou assinatura criptográfica de aprovações.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT. O [pacote bilateral EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) mantém 7 decisões, 14 gates e 13 casos sem aceite. R01-G01..G11 e CORE/SEG/AUT permanecem ABERTOS/NO-GO. Sem `main`, VPS, produção, segredos, dados reais, fornecedores pagos ou custos.
