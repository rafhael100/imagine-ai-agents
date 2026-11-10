# FT01/SEC-47 — Relógio monotônico por recibo de leitura sintético

**Data:** 10/11/2026. **Escopo:** laboratório PHP offline, sem acesso ao CRM real. **Gate operacional: NO-GO.**

## Problema

A SEC-46 vinculou o recheck a um recibo único de leitura e a uma revisão monotônica da política. Contudo, o horário `$now` ainda era aceito independentemente em cada `recheck()`. Uma prova sintética válida no instante 15 e expirada no instante 40 poderia falhar no instante 40 e ser aceita novamente com `$now=15`, sem nova leitura. O mesmo risco aparece ao retroceder o horário de uma prova de escopo com duração mais curta.

## Correção

- O único recibo privado de `MockCrmReadPublicFacade` registra também `observed_at`, inicialmente o horário da leitura aprovada.
- Cada recheck para a **mesma requisição e prova** exige `now >= observed_at`. Uma observação avançada é gravada **antes** de validar a prova. Se o prazo expirou, rollback posterior não resgata o recibo.
- Horário menor, prova inválida/revogada, falha de política, recheck malformado ou exceção tornam o recibo inutilizável. Um novo pedido de leitura pode gerar um **novo** recibo com **nova** prova e nonce.
- A comparação de hash e o parser fechado da SEC-46 seguem precedendo a atualização do relógio; uma requisição diferente não avança o relógio da leitura válida.
- Nenhum segundo consumo de nonce, chamada ao leitor, auditoria ou contador é feito pelo recheck.

## Regressões U69-01..08

Retrocesso de horário, sequência monotônica válida, expiração da credencial no limite exato, expiração antecipada da prova owner, horário negativo, requisição divergente, payload malformado e estado de nonce compartilhado com expiração. Todos os horários são inteiros fictícios.

**Gate esperado:** **605 testes PHP / lint de 83 arquivos**, verificadores Python, CI no SHA exato e nova CI pós-merge na `dev`.

## Limites importantes

Essa verificação é monotônica **apenas dentro do recibo de uma fachada PHP em memória**. Ela não implementa relógio real confiável, impede rollback entre processos, persiste memória, nem valida identidade, token, autenticação serviço-serviço, autorização transacional ou concorrência distribuída. Um novo recibo usa o `$now` fornecido pelo test harness, sem garantia externa de tempo: o CRM precisa fornecer tempo/expiração autoritativos.

O [PR #57 do Imagine CRM](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT e os 13 cenários reais seguem `NAO_EXECUTADO`. As decisões OP01-R01..R07 e gates R01-G01..G11 / CRM CORE/SEG/AUT continuam ABERTOS/NO-GO. Sem `main`, VPS, deploy, cliente, segredo ou nova despesa.
