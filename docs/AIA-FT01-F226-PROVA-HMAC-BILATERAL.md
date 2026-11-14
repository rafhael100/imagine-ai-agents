# F226 — Prova criptográfica fictícia bilateral Agents → CRM

**14/11/2026 — apenas laboratório, NO-GO.**

A [CRM PR #579](https://github.com/rafhael100/imagine-crm/pull/579) introduziu verificação HMAC-SHA256 isolada com ledger de nonces local, usando **chave pública sintética** e retornando sempre negativa pública. Na HEAD CRM `f44fe65745037d39f57e894ef32a25a9efa07bc4`, o mecanismo não autentica serviços reais e não pode ser habilitado como API.

O `tools/check_f226_signed_fixture.php` agora produz **JSON canônico com a classe real de Agents**, calcula uma assinatura de teste sobre digest, emissor, destino, tenant, nonce e timestamps e a submete às classes **PHP originais do CRM por checkout SHA-fixado**. Exercita 12 mutações: corpo adulterado, chave duplicada, issuer/audience/tenant divergentes, chave trocada, antes do início, vencimento, TTL excessivo, JSON extra, claim extra e assinatura forjada. Cada negativa é testada em um ledger novo, evitando que uma falha de replay esconda um erro da assinatura. Também testa recuperação de indisponibilidade, saturação e negação pública uniforme.

A CI Agents preserva a verificação cruzada anterior, os **879 testes PHP / lint 110**, checksum da fonte CRM, `contents: read` e checkout sem credenciais. O F224 agora exige que a classe HMAC real exista no checkout e não aceite chaves produtivas.

**NÃO existe autenticação IAM real:** a chave é sintética e pública, nonce não persiste entre workers e não há política de permissão, API, banco ou cofre. [CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**, com **14 gates**, **13 cenários** reais `NAO_EXECUTADO` e **NO-GO**. Sem `main`, VPS, clientes, segredos ou deploy.
