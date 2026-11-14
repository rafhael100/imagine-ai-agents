# F225 — Conformidade bilateral de bytes JSON na origem CRM

**14/11/2026 — laboratório DEV, NO-GO.**

A [PR CRM #576](https://github.com/rafhael100/imagine-crm/pull/576), squash `bbb4228a27028070a9561cb649a86224aaebde48`, introduziu `AiaReadOnlyMockBoundary::hasCanonicalFixtureShape` e `inspectCanonicalJson` no código **original do CRM**, executando validação de até 1.024 bytes antes da negação pública permanente.

A CI Agents passa a fixar o CRM nesse commit (em vez de `5eca65403a4e3707e1fd7145ec13073a762d26f6`). O mesmo `tools/check_crm_aia_cross_source.php` agora confronta, em PHP, as implementações **de ambos os repositórios**: entradas canônicas válidas, campos invertidos e **13 mutações adversariais** (nomes duplicados, espaços, aliases Unicode, versão, campo privado, admin, tamanho e raiz inválida). Todos os exemplos inválidos precisam ser negados pelos dois parsers, e o CRM nunca concede acesso mesmo quando o JSON é sintaticamente aceito. A CI continua sem HTTP, route binding, dados ou credenciais.

O verificador F224 de fontes recíprocas foi reforçado para exigir a existência real dos dois métodos novos e da comparação `hash_equals` no arquivo CRM, além dos checkouts SHA fixados e `contents: read`.

**Não constitui homologação operacional.** Baseline de **879 testes PHP e lint 110** preservado; [CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT**, **14 gates** e **13 cenários reais NAO_EXECUTADO**, **NO-GO**. `main`, VPS, custos, clientes e infraestrutura de produção não alterados.
