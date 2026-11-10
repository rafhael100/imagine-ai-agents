# FT01/SEC-31 — Limites do catálogo de conhecimento e da trilha sintética

**Data:** 10/11/2026. **Escopo:** PHP puro, fontes fictícias, execução offline, sem rede ou serviços pagos. **Decisão operacional: NO-GO.**

## Problema

O `MockKnowledgeCatalog` armazenava entradas de fontes sem limite. O `MockKnowledgeTrace` também podia crescer indefinidamente em eventos de seleção e entrega. Como a trilha não é persistente nem criptograficamente verificável, o objetivo é apenas impedir consumo irrestrito de memória e nunca apresentar uma leitura não auditada como sucesso.

## Correções implementadas

- O catálogo aceita **até 256 fontes** por instância; a 257ª recebe `KNOWLEDGE_CAPACITY_DENIED`, antes de qualquer gravação.
- Versões anteriores não são expulsas. Repetir uma fonte exatamente igual continua idempotente; alterar o digest de uma identidade existente continua gerando `KNOWLEDGE_CONFLICT`, mesmo com catálogo cheio.
- Revogar/desabilitar uma fonte existente continua permitido no limite; a fonte desabilitada permanece inelegível e não libera a vaga para outro ID.
- A trilha aceita **até 1.024 eventos**. Seleções, entregas, conflitos e encerramentos adicionais falham com `KNOWLEDGE_CAPACITY_DENIED` após validação de esquema, antes de anexar um evento novo.
- Nenhum histórico é descartado. O registro expõe apenas códigos fechados e hashes das referências, sem trechos de documentos ou conteúdo de prompts.
- Se a trilha estiver cheia ao entregar uma seleção já aberta, a entrega falha sem retornar passagens. O encerramento mantém o objeto fechado, mas comunica a falha de auditoria explicitamente; isso **não** é registro durável de encerramento.

## Testes de regressão U53-01..07

O pacote cobre 256 fontes e recusa da 257ª; repetição idempotente; conflito por digest; desabilitação segura no limite; entrada inválida sem consumir capacidade; recusa do 1.025º evento com histórico íntegro; e falha fechada na entrega com trilha exaurida.

**Gate exigido na revisão:** 472 testes PHP, lint de 82 arquivos PHP e validadores Python de contrato, prontidão e isolamento. Verificar CI no SHA exato do PR e novamente após merge em `dev`.

## Dependências e restrições

Este laboratório não implementa armazenamento persistente, indexação, embeddings, LLM real, IAM nem autorização por empresa no backend CRM. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT e R01-G01..G11 / CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO. Sem `main`, VPS, produção, dados reais de clientes, segredos, despesas ou chamadas externas.
