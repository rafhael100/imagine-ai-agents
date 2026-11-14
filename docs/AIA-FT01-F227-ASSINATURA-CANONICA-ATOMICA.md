# F227 — assinatura sintética emitida junto aos bytes canônicos

**14/11/2026 · somente laboratório, NO-GO.**

Na F226 a geração da prova HMAC ficava confinada a um script. A F227 acrescenta à classe real `MockCrmReadRequest` o método `issueSyntheticSignedFixture`, que retorna **corpo canônico e prova de teste na mesma operação**, utilizando a validação de oito campos já existente. O campo tenant e o hash referem-se aos bytes exatos, sem serialização concorrente ou duplicada.

O método aceita unicamente chaves com prefixo **público** `SYNTHETIC_PUBLIC_TEST_ONLY_` e referências de nonce sintéticas, com TTL máximo de 30 segundos, emissor e destinatário conhecidos. Uma chave pública sintética nunca comprova identidade real, e o resultado da operação não constitui autorização para ler o CRM.

O teste PHP `tools/check_f227_signed_fixture.php` executa **14 negativos** e verifica a HMAC frente a uma construção independente. O teste bilateral F226 passa a usar o emissor original Agents para duas requisições, conferindo-as com o validador PHP original do CRM fixado em SHA. Mantemos o baseline **879 testes PHP / lint 110** inalterado, com verificações extras no gate offline.

**CRM #57 OPEN/DRAFT; 14 gates e 13 cenários reais NAO_EXECUTADO; NO-GO.** Ausentes IAM real, nonce distribuído, segredos protegidos, ACL empresarial, endpoint e staging homologado. Nenhuma alteração na `main`, VPS ou produção.
