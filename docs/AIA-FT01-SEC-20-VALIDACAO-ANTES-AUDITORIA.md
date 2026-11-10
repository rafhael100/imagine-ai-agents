# FT01/SEC-20 — Validar projeção antes do evento de sucesso na auditoria

**Data:** 10/11/2026 · **Escopo:** laboratório sintético PHP, sem CRM real · **Decisão operacional: NO-GO**

## Defeito corrigido

A borda interna `MockCrmReadBoundary` aceitava uma projeção com os campos solicitados mesmo quando um leitor alternativo devolvia um valor incorreto ou de outro recurso. O `MockCrmReadAuditedBoundary` registrava `READ_SYNTHETIC_ONLY` antes de a fachada pública verificar o conteúdo. A resposta pública já podia recusar valores impróprios, mas a **trilha interna teria um falso evento de sucesso**.

## Correção

- `MockCrmReadBoundary` valida **antes de retornar ao auditor**: todos os campos esperados, sem extras, valores como strings `SYNTHETIC_...`, `ref` igual ao recurso do contexto autorizado e `stage` exatamente `SYNTHETIC_NEW` no conjunto de dados de laboratório.
- O contrato local de leitor agora usa a interface `CrmReader` já existente, permitindo simular retornos malformados nos testes, sem criar adaptadores de rede, SQL ou endpoints.
- A trilha registra `DENIED/PROJECTION` para retorno incorreto, sem os dados brutos do leitor. Somente projeção fictícia válida recebe `READ_SYNTHETIC_ONLY/NONE`.
- `MockCrmReadPublicFacade` continua exigindo política do CRM dono, prova de confiança e auditoria, não oferecendo caminho de leitura direta.

## Regressões U41-01..06

Lead fictício de outro recurso; dado com aparência de informação privada; estágio sintético não permitido; campo extra; ausência ou tipo incorreto; caso positivo com ordenação dos campos e evento único de sucesso. Os testes usam leitor falso, **sem cliente, CRM, rede ou provedor externo**.

**Gate esperado:** 403 testes PHP, lint de 82 arquivos PHP e verificadores de contrato Python. Requer CI verde no SHA do PR, além de revisão de diff e integração somente na `dev`.

**Limite:** o conjunto de estágios e IDs é fictício; validações reais precisam ser definidas e executadas **no backend CRM** com autenticação, ACL multiempresa, privacidade, transações e parecer independente. O [PR #57 do CRM](https://github.com/rafhael100/imagine-crm/pull/57) permanece DRAFT, e R01-G01..G11 e CORE/SEG/AUT estão ABERTOS/NO-GO. Nenhuma mudança na `main`, VPS, produção ou custos.
