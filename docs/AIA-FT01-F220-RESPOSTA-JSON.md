# F220 — Resposta JSON fechada com paridade cruzada Agents/CRM

**14/11/2026 — NO-GO.** A F219 cobre o corpo JSON da solicitação fictícia. A F220 cobre o **corpo JSON da resposta**, com validação antes de o consumidor interpretar uma projeção de leitura.

O `MockCrmPublicResponseContract` agora serializa e interpreta somente o contrato in-process previamente validado, com máximo de 512 bytes, decodificação JSON limitada e comparação canônica **byte a byte**. Respostas de negativa permanecem válidas como formato, mas **nunca são aceitas como acesso**, e uma projeção positiva exige os mesmos campos e a mesma ordem da solicitação.

`tools/check_f220_canonical_response.php` usa as **seis respostas reais do mock PHP da fachada**, preservando 2 sucessos fictícios e 4 negativas, e realiza **20 mutações negativas**, incluindo chaves duplicadas aninhadas, campo privado e tentativa de forjar autorização. No passo cross-repo da CI, `tools/check_crm_aia_real_facade_core.php` passa a inspecionar com o **PHP original do CRM, fixado em SHA**, as respostas serializadas e decodificadas da fachada. Os pareceres do inspetor continuam a negar todos os direitos reais.

**Sem HTTP, API, token, IAM, banco ou dados de clientes.** A canonicalização só vale para este mock e não é um contrato final da API. Baseline **879 testes PHP / 110 lint** + verificações F219/F220. CRM #57 OPEN/DRAFT; **13 cenários reais NAO_EXECUTADO**, **14 gates NO-GO**, `main` e VPS intactas.
