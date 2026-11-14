# F219 — Framing de JSON canônico do laboratório OP-01

**14/11/2026 · NO-GO.** A leitura anterior validava apenas arrays PHP já decodificados. A F219 testa os **bytes JSON** de uma proposta sintética antes da fachada, para evitar que nomes duplicados, escapes equivalentes ou corpos enormes sejam interpretados de forma ambígua.

`MockCrmReadRequest::decodeCanonicalJson` aceita no máximo 1.024 bytes, valida oito chaves na ordem do mock, limita a profundidade e exige reencodificação idêntica em bytes; `encodeCanonicalJson` só emite valores fictícios previamente validados. A borda `handleCanonicalJson` descarta recibos anteriores antes do parse, reaproveita telemetria limitada de `SCHEMA_DENIED` sem dados e devolve negativa uniforme.

`tools/check_f219_canonical_json.php` executa **15 casos inválidos** contra a borda PHP e dois casos positivos, incluindo recuperação com nova prova sintética. Contratos Python e GitHub CI tornam os testes obrigatórios.

**No HTTP, API ou autenticação real.** A canonicalização vale exclusivamente para este mock, não estabelece o formato da futura API CRM. Baseline **879 testes PHP / 110 lint**, casos adicionais de PHP e Python. CRM #57 OPEN/DRAFT; **13 cenários reais NAO_EXECUTADO; 14 gates NO-GO**. Sem main, VPS, cliente, credenciais, produção ou custos.
