# FT01/REV-01 — livro de revisão dupla para resultados indeterminados

**11/11/2026. Apenas PHP sintético offline. Nenhuma identidade/revisão operacional é validada. NO-GO.**

## Produto e regras simuladas

A fila OP-01 registra `INDETERMINADO` quando um lease expira sem prova de conclusão. Esse estado não pode ser resolvido por um novo envio: não há certeza de que a leitura original não ocorreu. Uma revisão independente também **não deve inventar** o resultado de uma operação. O componente `MockUncertaintyReviewBoard` cria um registro auxiliar, separado da fila e sem controle de jobs.

- Apenas jobs já `INDETERMINADO` podem receber observações.
- Cada observação exige um `reviewerRef` estritamente fictício e um dos códigos fechados `SYNTHETIC_TIMEOUT_OBSERVED` ou `SYNTHETIC_RESULT_UNKNOWN`.
- A primeira observação resulta em `AWAITING_SECOND_REVIEW`; segunda de **outro** revisor e mesmo motivo resulta em `REVIEWED_UNRESOLVED`. Motivos divergentes produzem `REVIEW_CONFLICT`. Estado inicial: `AWAITING_REVIEW`.
- Uma reapresentação idêntica pelo mesmo revisor é idempotente; alterar motivo nega `REVIEWER_CONFLICT`; terceiro voto nega `REVIEW_CAPACITY_DENIED`.
- No máximo **64 casos / 128 eventos**. Os eventos contêm somente hashes das referências, número sequencial e marcador técnico, nunca empresa, pessoa, referência de Run, motivo detalhado ou prova.
- A resposta pública tem somente `state`, `review_count` e `real_authorization=false`.
- Se a fila estiver vinculada ao piloto, o quadro também aplica a guarda do coordenador antes de registrar uma observação, bloqueando callbacks recursivos de IA.

**Crucial:** `REVIEWED_UNRESOLVED` significa que **a incerteza foi observada**, não que a operação foi concluída, validada, liberada ou rejeitada. A fila continua `INDETERMINADO` e nunca reexecuta. O nome do revisor não é identidade autenticada: qualquer código sintético pode montar fixtures, então isso **não substitui** revisão humana independente real, IAM, autenticação, auditoria assinada ou homologação.

## Regressões U86-01..13

Negação de job pendente/ausente; estado inicial; primeira observação; concordância; divergência; replay idempotente; alteração de voto rejeitada; terceiro revisor recusado; outra empresa; entradas inválidas/longas; privacidade da trilha; tentativa de registrar revisão dentro do callback; e limite de 64 casos / 128 eventos.

**Gate de implementação:** 766 casos PHP, lint de 95 arquivos PHP, verificação de isolamento e testes Python offline. CI no SHA exato do PR e pós-merge; todas as etapas de homologação operacional seguem `NAO_EXECUTADO`.

## Dependências de produção

CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT; 13 cenários reais OP-01 `NAO_EXECUTADO`; gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Ainda faltam identidade/autenticação de revisores, segregação de funções, evidência externa de resultado, API CRM com escopo de empresa, retenção LGPD, storage transacional, worker, observabilidade, revisão independente e gates de piloto. Sem `main`, VPS, deploy, clientes, credenciais, custos ou dados reais.
