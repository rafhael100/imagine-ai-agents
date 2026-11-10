# FT01/SEC-46 — Imunidade ABA de autorização e recibo local de leitura OP-01

**Data:** 10/11/2026. **Escopo:** laboratório PHP totalmente sintético e sem rede. **Situação operacional: NO-GO.**

## Problemas encontrados

1. Após SEC-45, uma revogação de permissão seguida por uma nova concessão **durante o mesmo leitor ou provedor** poderia deixar a política aparentemente autorizada no último `recheck`. Isso é o problema *ABA*: o estado final coincide com o inicial, mas uma autorização anterior não pode automaticamente recuperar validade.
2. `MockCrmReadPublicFacade::recheck` respondia se a política/prova estava válida, **sem exigir leitura anterior efetivamente aprovada**. A intenção era revalidar uma leitura já realizada, não oferecer um oráculo independente de autorização.

## Correção implementada

- `MockCrmOwnerPolicy` mantém **revisão monotônica local**; concessão, revogação, suspensão, reativação e mudança de disponibilidade avançam a revisão. Overflow nega operações; até mutações idempotentes invalidam o comprovante anterior de modo conservador.
- `MockCrmReadBoundary` captura a revisão imediatamente após checar a política e exige que seja a mesma **depois** do adaptador fictício retornar e da revalidação de trust, antes de registrar sucesso de leitura.
- `MockCrmReadPublicFacade` mantém **um único recibo interno da última leitura bem-sucedida**, sem token ou material sensível em resposta. O recibo vincula exatamente os mesmos objetos envelope/owner, o contexto, o hash da requisição e a revisão original da política. Uma nova tentativa de leitura, inclusive negada, substitui/invalida o recibo.
- `recheck` nega quando não existe recibo, a requisição/prova diverge, a política mudou mesmo que tenha sido restaurada, ou a revogação de trust permanece. Não há nova leitura, consumo de nonce, auditoria ou chamada externa; o controle se mantém deliberadamente local ao processo.

## Regressões U68-01..12

- **U68-01..07:** recheck sem leitura, leitura negada, replay que invalida recibo, requisição/prova trocadas, revogação+reconcessão, suspensão+reativação e falha+restauração do owner.
- **U68-08..09:** leitor reentrante que revoga+reconcede grant ou suspende+reativa tenant não gera auditoria de sucesso.
- **U68-10..12:** provedor que revoga+reconcede grant, suspende+reativa membro ou torna owner indisponível e recupera durante a sugestão não pode finalizar Run com sucesso.

**Gate previsto:** **597 testes PHP, lint de 83 arquivos e verificadores Python**, CI verde no SHA do PR e novamente após squash na dev.

## Limitações e bloqueios

A revisão e o comprovante são **memória volátil de um processo**; apenas uma leitura pode permanecer elegível a recheck por fachada. Leituras intercaladas invalidam a anterior, conservadoramente. Não há autenticação de serviço, assinatura, relógio confiável, transação/isolamento de banco, API real, ACL/RLS persistente nem consistência entre workers. A autorização final deve ser implementada no próprio CRM e homologada com testes de concorrência e revisão independente.

CRM PR #57 continua OPEN/DRAFT; os **13 cenários reais OP-01 seguem NAO_EXECUTADO**, decisões OP01-R01..R07 e gates R01-G01..G11/CORE/SEG/AUT ABERTOS/NO-GO. Nenhuma alteração na `main`, VPS, produção, clientes, segredos ou gastos.
