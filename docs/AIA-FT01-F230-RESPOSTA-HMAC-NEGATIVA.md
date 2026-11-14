# F230 — resposta CRM fictícia vinculada à requisição, com HMAC de teste

**14/11/2026 · laboratório offline · NO-GO.**

A F229 testa assinatura do pedido Agents→CRM. A F230 fecha a outra direção
**somente no laboratório**: um resultado sintético do **código-fonte original
do CRM** é selado com prova HMAC pública de teste e associado à mensagem
canônica, empresa e nonce do pedido original. O consumidor Agents verifica
os bytes exatos antes de considerar a resposta criptograficamente consistente.

### Implementação

- `src/MockBridge/MockCrmSignedDenialFixture.php`: emissor/verificador
  restrito a resposta `DENIED_OR_NOT_FOUND`, **nunca** a leitura;
  validação estrita de claims, chave explicitamente pública
  `SYNTHETIC_PUBLIC_TEST_ONLY_`, digests SHA-256 dos bytes da requisição
  e da resposta, domínio HMAC separado e prazo de até 30 segundos.
- `tools/check_f230_signed_denial.php`: usa a classe CRM original
  `AiaReadOnlyMockBoundary` na revisão fixa
  `d7c7aacc766c7ade4c65dcd211ed4569442144a9`,
  ensaia **3 tenants** fictícios e **54** mutações negativas (18 por tenant).
  Replays, indisponibilidade e saturação são avaliados separadamente.
- `tests/Contracts/test_f230_response_seal.py`: exige execução posterior
  ao checkout SHA-fixado do CRM e falha quando as proteções saem da CI.

### Restrições mantidas

Uma assinatura válida de teste **não é autenticação do CRM**, prova de
origem, autorização, permissão nem sucesso de leitura. A chave é
publicamente conhecida por desenho; o registro anti-replay é somente um
objeto local de memória, sem atomicidade distribuída. O código não aceita
respostas com projeção, não abre HTTP, banco de produção nem chaves reais.

Baseline preservado de **879 testes PHP / lint 111**, mais os contratos
Python e ensaios bilaterais; não há afirmação de que os cenários reais
foram executados.

**CRM #57 OPEN/DRAFT, 14 gates, 7 decisões independentes e 13 cenários
reais NAO_EXECUTADO: NO-GO.** Não mudar `main`, VPS, clientes, produção,
segredos, gastos ou fazer merge automático. PR F230 permanece DRAFT
até CI concluída da HEAD exata e revisão dos gates.
