# F216 — Recibo da borda sintética negado após qualquer nova tentativa

**14/11/2026 — correção executável de segurança in-process.**

A borda `MockCrmReadEdgeBoundary` podia negar uma nova tentativa *antes* de chamar a fachada (por exemplo schema ou limite do contador), preservando o recibo de leitura fictícia anterior da `MockCrmReadPublicFacade`. Além disso, se a telemetria falhasse após uma avaliação de sucesso da fachada, a borda retornava negação sem invalidar o recibo recém-emitido. Isso não representava permissão real, mas permitia recheck positivo de uma leitura que a borda já tinha negado.

**Correção:** `discardReadReceipt()` na fachada invalida o único recibo local; se chamada durante `handle()`, também marca tentativa aninhada para negar o resultado externo. A borda aplica essa invalidação **antes** de reservar o contador e de validar o schema, e repete no catch de falhas internas/telemetria. Não muda o recheck legítimo da própria tentativa, que continua não consumindo outro nonce.

**Provas PHP executadas no gate:** (1) sucesso fictício → novo payload inválido → recibo inválido → prova nova funciona; (2) sucesso → capacidade esgotada → recibo invalidado; (3) adaptador fecha contador indevidamente durante a leitura → borda nega, sem recibo utilizável; (4) tentativa aninhada rejeitada pelo parser contamina a leitura externa, que também falha. Testes Python adicionais conferem implementação e proteção do quality gate.

**Restrições mantidas:** laboratório offline, **879 testes PHP / 110 arquivos lint + PHP F216 e Python**, zero API ou cliente real, 13 cenários OP-01 `NAO_EXECUTADO`, CRM #57 OPEN/DRAFT, 14 gates abertos, NO-GO, sem produção, VPS, credenciais, custos ou mudança de `main`. Not an operational CRM IAM permission.
