# OP-01 — construtor de requisições ligado ao contexto confiável sintético

**Data:** 10/11/2026. Escopo 100% PHP offline, fictício e sem rede. **Aprovação de piloto e produção: NO-GO.**

## Entrega de desenvolvimento

`MockCrmReadRequestBuilder::fromContext(TenantContext $trustedContext, string $requestRef, array $fields)` compõe a requisição OP-01 com as oito chaves do contrato sintético a partir do **contexto de teste já vinculado**. O chamador pode escolher somente a referência da requisição e a lista mínima de campos `ref`/`stage`; não informa livremente `tenant_ref`, `principal_ref`, `resource_ref`, `purpose_ref`, `operation` ou `contract`.

O método valida sua própria saída usando `MockCrmReadRequest` e `assertMatches`, negando refs longas (incluindo 1 MiB), lista não ordenada/associativa, duplicatas, campos extras e tipos inválidos. A criação da requisição é determinística, sem geração de nonce, grant ou efeitos.

O exemplo [f127](../examples/f127-read-only-pilot-demo.php) agora demonstra a construção do pedido através do builder, mantendo todos os testes de replay, empresa alheia, campo privado e política do dono. O builder **não** deve ser aplicado para "corrigir" corpos enviados por clientes não confiáveis: o receptor `MockCrmReadRequest` continua verificando o corpo e a política do proprietário.

## Regressões U77-01..12

1. Oito chaves exatas e vínculo ao tenant/principal/recurso/finalidade.
2. Ordem explícita de dois campos e leitura de teste.
3. Contextos sintéticos distintos não se confundem.
4. Referências vazias/ilegais negadas.
5. Limites de 90/91 bytes e ref de 1 MiB.
6. Lista vazia e listas grandes negadas.
7. Campo privado e duplicata negados.
8. Estrutura associativa e valores não string negados.
9. Claims ou flags arbitrários nunca incluídos; corpo adulterado rejeitado.
10. Saída determinística sem reserva de nonce ou estado.
11. Requisição bem formada **não** supre ausência de autorização CRM-owner.
12. Piloto fictício consome requisição construída sem registrar mutação.

**Gate planejado:** 672 casos PHP, lint de 85 arquivos PHP e todos validadores Python offline; CI sobre o SHA exato do PR e pós-merge. Não executar CRM real.

## Caminho crítico mantido

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua somente documental, **OPEN/DRAFT**, com OP01-R01..R07 e R01-G01..G11 / CORE/SEG/AUT em **NO-GO** e 13 cenários operacionais `NAO_EXECUTADO`. O contexto de `TenantContext` **não** é identidade autenticada, não contém segredo e não prova escopo de empresa, ACL/RLS, autorização ou auditoria distribuída.

Sem Laravel, SQL, controller, endpoint, credential, VPS, clientes, dados reais, deploy, `main` ou gastos.
