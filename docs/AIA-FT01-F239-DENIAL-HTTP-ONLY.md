# F239 — contrato imutável de negativa HTTP CRM → Agents

**15/11/2026 — DRAFT / NO-GO / NO-MERGE / NO-DEPLOY**

A classe `MockSyntheticHttpDenialReceipt` recebe somente valores de laboratório (código HTTP, cabeçalhos e bytes). Ela aceita **exclusivamente** o status 404, JSON canônico de `MockCrmPublicResponseContract::denial()`, content-type JSON e dois perfis de cabeçalhos fechados: F246 (`no-store, private`) ou F248 do CRM funcional (`private, no-store, max-age=0`, `x-robots-tag: noindex, nofollow, noarchive`, `x-content-type-options: nosniff`). Cabeçalhos desconhecidos sempre negam.

A verificação rejeita sucesso 2xx, redirecionamento 3xx, erros externos diferentes do contrato, cookies, location, HTML, projeções, `real_authorization=true`, campos extras, ordenação divergente, JSON duplicado, resposta excessiva e malformação. `tools/check_f239_denial_receipt.php` cobre 3 negativas canônicas aceitáveis como prova de negativa e 46 variantes rejeitadas.

Um retorno `true` comprova apenas que a resposta é **o formato esperado de recusa pública**; não autentica o servidor, não certifica TLS/mTLS, não autoriza leitura e não concede sessão. Nenhuma conexão HTTP, URL, credencial, banco, dados pessoais ou motor de IA é acessado.

A resposta de laboratório CRM F246/F247 na [PR #602](https://github.com/rafhael100/imagine-crm/pull/602) permanece `HTTP 404 / DENIED_OR_NOT_FOUND` mesmo com prova Ed25519 válida. A prova F240 deverá verificar a combinação F238 → F239 sem simular um ALLOW inexistente. Provas em CI não liberam os 14 gates/7 decisões/13 cenários operacionais da CRM #57.
