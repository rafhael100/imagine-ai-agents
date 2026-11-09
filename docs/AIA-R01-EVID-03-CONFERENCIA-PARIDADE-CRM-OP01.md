# AIA-R01/EVID-03 — Paridade offline da proposta CRM com contrato sintético Agents

**Estado vigente (09/11/2026):** [PR #124](https://github.com/rafhael100/imagine-ai-agents/pull/124) integrado apenas à dev, squash `1a0c41875b477101ff8617da4682f8c7b44889fd`. [CI #42624040384](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624040384) PASS em snapshot/schema/parser sintéticos e 228/228 PHP, 73/73 lint oficiais + probe com lint separado. **NO-GO operacional**.

O PR #57 do Imagine CRM está em DRAFT e não integrado. Seu documento de proposta CRM-AIA-RO-01 foi copiado fielmente para `contracts/reviews/crm-aia-ro-01.pr57.snapshot.md` com SHA de blob e head registrados no manifesto. A verificação de digest prova integridade da cópia local, não identidade de serviço, aprovação jurídica ou integração CRM.

`tools/check_op01_contract_parity.py` compara exclusivamente os campos e o escopo declarados pela proposta documental com `contracts/crm-aia-readonly.v1.synthetic.schema.json`. Bloqueia expansão de operação, campos de contato, referências não sintéticas, versão alterada, formato permissivo ou alegação de autorização. O resultado é sempre `pilot_decision=NO_GO`.

`tools/op01_php_parser_probe.php` recebe apenas fixtures JSON sintéticas em stdin, preserva a diferença entre objetos e listas e retorna booleanos do construtor PHP F1-12. O teste Python `tests/Contracts/test_op01_contract_parity.py` aplica casos positivos, negativos e adulterações. Não executa API real, ACL, SQL ou autenticação.

**Regressão esperada após CI:** testes documentais EVID-02 e EVID-03 e regressão PHP **228/228**, lint **73/73** pelo validador oficial, mais **1 lint dedicado** para o probe em `tools/`. Não declarar sucesso antes do workflow verificar o SHA exato.

**Gates:** R01-G01..G11, CRM CORE/SEG/AUT e aceite formal do PR CRM #57 ABERTOS. Nenhum deploy, credencial, servidor, mudança em `main`, despesa ou acesso a dados reais.

## EVID-07 — endurecimento de procedência e rejeição de schema (09/11/2026)

O linter OP-01 agora exige **exatamente** o head documental do CRM PR #57 (`a006bd427dbf34afbbbdec3098316c47a59fc608`) e o SHA de blob da proposta (`6a1b076d2dc50cc29ffc802d6b186c623c6b49ca`). Antes, apenas o formato de head era validado; um terceiro poderia editar o snapshot e atualizar o digest no mesmo manifesto sem disparar a regra de integridade. Uma nova versão legítima da proposta exigirá revisão explícita e atualização deliberada do pino, com CI.

O schema sintético também deve ter um conjunto **fechado de palavras-chave na raiz** e metadados explícitos de laboratório. Adições como `allOf`, `not`, `if`, `$ref` ou `unevaluatedProperties`, que poderiam alterar a semântica do contrato sem serem notadas, agora são rejeitadas. Testes negativos cobrem essas variantes e falsa rotulagem de produção.

**Escopo:** melhoria do linter offline, não verificação criptográfica de serviço, assinatura de revisor, API CRM real ou autorização operacional. Todos os gates seguem abertos, com `pilot_decision=NO_GO`.

**Evidência de conclusão EVID-07:** [PR #130](https://github.com/rafhael100/imagine-ai-agents/pull/130) MERGED na dev (squash `21300c5e518166e7ac51ad5020d645c17668edb0`); [CI #42630115145](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630115145) PASS antes do merge e [CI #42630193545](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42630193545) PASS após o merge em HEAD exato. Sem homologação bilateral nem autorização real; `NO-GO`.

## EVID-08 — matriz diferencial restrita entre JSON e PHP (09/11/2026)

O arquivo `tests/Contracts/test_op01_contract_matrix.py` gera uma matriz determinística de fixtures fictícias para testar se o esquema JSON OP-01 fixado e o construtor `MockCrmReadRequest`, acessado pelo probe PHP de laboratório, concordam em formatos positivos e negativos. Casos incluem caracteres e limites nos cinco identificadores, propriedades ausentes/adicionais, tipos incompatíveis, operações, versão e projeção de campos.

A CI executa o teste sem `pip`, Internet, API ou banco, em lotes menores que o teto de 80 candidatos do probe. A aceitação esperada é calculada por um **interpretador intencionalmente restrito às propriedades do esquema sintético**, depois da verificação de pinos EVID-07; este código não implementa nem substitui um validador JSON Schema 2020-12 completo. Desvios entre o parser PHP e o contrato geram falha, mas não autorizam serviço real.

**Evidência em PR:** contagens e resultado efetivo dependem da CI no SHA revisado. O PR CRM #57 permanece DRAFT e sem aceite CORE/SEG/AUT; R01-G01..G11 ABERTOS. Sem `main`, infraestrutura, credenciais, dados pessoais ou produção.
