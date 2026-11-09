# AIA-R01/EVID-03 — Paridade offline da proposta CRM com contrato sintético Agents

**Estado inicial:** verificação em branch de revisão, sem homologação bilateral. **NO-GO operacional**.

O PR #57 do Imagine CRM está em DRAFT e não integrado. Seu documento de proposta CRM-AIA-RO-01 foi copiado fielmente para `contracts/reviews/crm-aia-ro-01.pr57.snapshot.md` com SHA de blob e head registrados no manifesto. A verificação de digest prova integridade da cópia local, não identidade de serviço, aprovação jurídica ou integração CRM.

`tools/check_op01_contract_parity.py` compara exclusivamente os campos e o escopo declarados pela proposta documental com `contracts/crm-aia-readonly.v1.synthetic.schema.json`. Bloqueia expansão de operação, campos de contato, referências não sintéticas, versão alterada, formato permissivo ou alegação de autorização. O resultado é sempre `pilot_decision=NO_GO`.

`tools/op01_php_parser_probe.php` recebe apenas fixtures JSON sintéticas em stdin, preserva a diferença entre objetos e listas e retorna booleanos do construtor PHP F1-12. O teste Python `tests/Contracts/test_op01_contract_parity.py` aplica casos positivos, negativos e adulterações. Não executa API real, ACL, SQL ou autenticação.

**Regressão esperada após CI:** testes documentais EVID-02 e EVID-03 e regressão PHP **228/228**, lint **74/74** (um PHP a mais pelo probe). Não declarar sucesso antes do workflow verificar o SHA exato.

**Gates:** R01-G01..G11, CRM CORE/SEG/AUT e aceite formal do PR CRM #57 ABERTOS. Nenhum deploy, credencial, servidor, mudança em `main`, despesa ou acesso a dados reais.
