# FT01/QUEUE — fila de execuções sintéticas de leitura supervisionada

**Escopo:** PHP puro, processo local, empresa de teste única, sem serviços externos. **CRM real e produção: NO-GO.**

## Entrega

`MockReadOnlyRunQueue` oferece a primeira estrutura FIFO de preparação de execuções OP-01, separada do coordenador e sem executar o trabalho:
- `enqueue(context, runRef, requestRef, now)`: vincula somente referências sintéticas com idempotência por run; nega colisão de request ref.
- `claim(context, now, leaseTicks)`: reserva um único item pendente pela ordem de entrada, com prazo de lease explícito e identificador sintético de comparação, **não uma credencial**.
- `finish(context, runRef, claimRef, result, now)`: apenas confirma `CONCLUIDA`/`FALHOU` sob claim e prazo válidos, sem efeito externo. Replay exato é idempotente.
- `cancel(context, runRef, now)`: cancela apenas pendentes, nunca uma reserva em andamento.
- `status`, `counts`, `events`: demonstram estados e trilhas minimizadas, com hashes dos IDs de empresa e run.

**Tratamento de incerteza:** um lease expirado vira `INDETERMINADO` com evento de auditoria antes de qualquer outra reserva; **nunca há requeue ou retry automático**. Não se infere ausência de efeito a partir da falta de resposta. A fila também falha fechada com relógio regredido, claim incorreto, estado inválido ou capacidade esgotada.

Limites fixos do laboratório: 64 itens, até 256 registros de trilha e leases de 1–300 ticks sintéticos. Uma observação de tempo maior não pode ser revertida. Antes de varrer expirações + reservar um próximo item, a fila reserva capacidade para todos os eventos correspondentes.

## Não confundir com infraestrutura operacional

A fila não cria Run nem Step no `SupervisedCoordinator`, não dispara o `MockReadOnlyPilot` automaticamente e não executa leitura. É uma simulação do **controle de admissão e recuperação segura**, não uma fila Redis, worker, armazenamento persistente, lock distribuído, TTL transacional, orquestrador ou operação autenticada. A simples criação/claim de um item não prova acesso à empresa nem autorização do CRM.

A entrega mantém OP-01 exclusivamente read-only; **nenhuma fila de mutação** ou chamada automática de ferramenta de escrita foi adicionada. Implementação produtiva exigirá autenticação do worker, isolamento multitenant, lease persistente e atomicidade distribuída, reconciliação, métricas, retenção e revisão independente.

## Regressões U78-01..15

Vazio, idempotência e colisão; FIFO; isolamento pelo contexto; IDs adversariais; confirmação e falha idempotentes; credencial fictícia inválida; prazo expirado e estado indeterminado; expiração + próxima reserva; cancelamento antes/depois da reserva; relógio monotônico e limites; 64 vagas sem evicção; eventos sem referências de recurso/cliente.

**Gate planejado:** 687 testes PHP, lint de 86 arquivos PHP e validadores Python offline, CI do PR e pós-merge no SHA correto. A documentação de aceite/NO-GO permanece vigente.

**Bloqueio permanente:** [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) OPEN/DRAFT; 13 cenários reais `NAO_EXECUTADO`; R01-G01..G11 / CORE/SEG/AUT NO-GO. Sem DB, API, dados reais, autenticação, VPS, clientes, produção, `main` ou gastos.
