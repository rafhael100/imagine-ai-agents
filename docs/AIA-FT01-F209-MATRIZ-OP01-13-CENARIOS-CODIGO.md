# F209 — Matriz OP-01 em código dos dois repositórios (13 cenários)

**14/11/2026 · somente laboratório de CI, sem homologação real.**

A F208 executa o parser do Imagine AI Agents e o componente sintético do CRM em uma revisão fixa do código do CRM. A F209 amplia essa prova: `tools/check_crm_aia_scenario_matrix.php` enumera **OP01-N01..N11 / OP01-P01..P02**, valida a lista contra o pacote de decisões existente e verifica a concordância sintática dos dois parsers, a resposta negativa uniforme do mock CRM e a ausência de toda permissão real no inspetor CRM.

A matriz informa **13 casos ensaiados em código fonte sintético** e **0 executados na homologação operacional**. Cada caso mantém `operational_status=NAO_EXECUTADO`; a decisão permanece `NO_GO`, sem credenciais, endpoint, ACL real, registro de auditoria durável, replay distribuído ou conteúdo de cliente.

Os casos N07 (replay distribuído), N08 (revogação na fila), N09 (indisponibilidade do CRM) e N11 (falha de telemetria) **não são provados funcionalmente** pelo ensaio: recebem apenas negativa de mock. Os casos P01/P02 têm formato sintático válido, mas não recebem leitura operacional. Nunca atualizar o pacote de homologação a partir do resultado deste teste.

Execução exclusivamente na CI, após checkout de CRM imutável, conferência de SHA e gate offline local; pipeline PHP → verificador Python rejeita JSON ambíguo ou adulterado. Dez testes Python no gate local protegem a matriz e seu vínculo na CI; os **879 testes PHP / lint 110** permanecem invariantes.

**Bloqueios:** CRM #57 OPEN/DRAFT, SEG/CORE/AUT/LGPD, R01-G01..G11 e os 13 cenários reais `NAO_EXECUTADO`. Sem `main`, VPS, banco, dados reais, deploy ou despesas.
