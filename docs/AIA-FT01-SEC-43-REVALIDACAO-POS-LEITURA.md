# FT01/SEC-43 — Revalidação da autorização após leitura OP-01

**Data:** 10/11/2026. **Escopo:** modelo inerte, offline, com leitor e política *inteiramente sintéticos*. **Decisão operacional: NO-GO.**

## Lacuna identificada

A `MockCrmReadBoundary` verificava autorização do owner fictício, prova do serviço e nonce **antes** de chamar o adaptador `CrmReader`. Como a interface aceita adaptadores substituíveis, um leitor malformado pode alterar a permissão no mesmo processo enquanto prepara uma resposta. Sem checagem após o retorno, a borda poderia devolver uma projeção sintética e emitir auditoria `READ_SYNTHETIC_ONLY` apesar de a prova já estar revogada ou a empresa suspensa.

## Correção

- Após `reader->lead()`, **antes de validar a projeção e registrar sucesso**, a borda repete `ownerPolicy->assertAllowed()` e chama `gateway->recheck()`. Isso cobre grant revogado, tenant/membro suspenso, indisponibilidade do owner, envelope/owner revogados e estado de nonce/revogação indisponível ou com digest revogado.
- O novo `MockTrustGateway::recheck()` é **não consumidor**: verifica contexto, período, issuer/audience, scope, owner e saúde/revogação do estado compartilhado **sem consumir de novo o nonce**; `authorize()` reaproveita as mesmas verificações antes de consumir normalmente.
- `MockSharedTrustState::assertLive()` centraliza a consulta read-only de disponibilidade e digest revogado, usada tanto na autorização quanto no recheck. A memória de nonces permanece monotônica, sem evicção ou replay.
- Se o direito for retirado durante o leitor, a auditoria registra apenas uma **negação categorizada**; a resposta pública permanece `DENIED_OR_NOT_FOUND` sem projeção. Uma rechecagem válida não gera segundo nonce e não interfere na ordem `stage/ref`.

## Testes U65-01..10

Dez casos adicionados ao `crm_read_audit.php` simulam: concessão revogada durante leitura, tenant suspenso, membro suspenso, indisponibilidade da política, envelope revogado, prova owner revogada, digest compartilhado revogado, estado compartilhado indisponível, leitura válida com um único nonce e replay bloqueado, e falha pública redigida após revogação durante o leitor.

**Gate esperado: 565 testes PHP, lint de 83 arquivos PHP, validadores Python**, CI no SHA do PR e novamente no commit pós-merge.

## Limites / não-autorização

Esses testes demonstram **reentrância no mesmo processo**, não concorrência real, transação distribuída, banco de dados, ACL do CRM ou revogação atomicamente protegida de mudanças entre a última verificação e a resposta. O CRM precisa controlar a autorização **dentro da transação/consulta real** e executar testes de concorrência em homologação isolada. Os 13 cenários reais OP-01 continuam `NAO_EXECUTADO`; CRM PR #57 segue OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT permanecem ABERTOS/NO-GO.

Nenhuma `main`, VPS, produção, rede, segredos, contas de clientes ou gastos.
