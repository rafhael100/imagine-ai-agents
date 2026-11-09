# FT01/F1-25 — Reconhecimento de envio não é prova de efeito

**Data:** 09/11/2026. **Escopo:** apenas laboratório PHP sintético; **NO-GO** operacional.

## Problema
`Effect` permite o estado `SOLICITADO`, mas o revisor de trilha deliberadamente não o aceita como evidência de efeito. O coordenador já convertia `NAO_SOLICITADO` em `INDETERMINADO`, mas podia registrar `SOLICITADO` após o primeiro envio e produzir trilha incompatível com a revisão de auditoria.

## Correção
O coordenador normaliza `NAO_SOLICITADO` e `SOLICITADO` para `INDETERMINADO` no registro de efeito não-terminal. O reconhecimento técnico do envio **não** equivale à execução confirmada. A reserva de idempotência persiste e a execução repetida só consulta `status`, sem segundo `submit`. Quando já existe uma prova terminal `CONFIRMADO`, uma consulta que retorna apenas `SOLICITADO` é rejeitada e deixa a evidência anterior intacta, com evento `STATUS_REJECTED`.

## Testes novos
- U25-01: `SOLICITADO` no retorno de `submit` vira `INDETERMINADO` com trilha completa.
- U25-02: o mesmo envio pode tornar-se `CONFIRMADO` apenas por consulta posterior de status, sem reenvio.
- U25-03: status `SOLICITADO` não rebaixa prova terminal confirmada.

**Gate esperado:** 326 testes PHP e 79 arquivos PHP com lint. Estas metas só se tornam evidência após CI verde no SHA exato.

## Limitações
Nada disso implementa IAM, ACL, banco, autenticação, durabilidade, recuperação entre processos ou integração com o CRM. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue sem aceite bilateral, R01-G01..G11 e CRM CORE/SEG/AUT **abertos**. Não alterar `main`, VPS, credenciais, dados reais, fornecedores ou produção.
