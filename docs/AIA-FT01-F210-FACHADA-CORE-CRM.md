# F210 — Fachada PHP real dos Agentes inspecionada pelo Core CRM

**14/11/2026. Somente runner CI sintético / NO-GO operacional.**

A F208/F209 verificou parser e respostas de laboratório. A F210 complementa o ensaio executando `tools/op01_php_public_response_probe.php`, que instancia a fachada PHP completa `MockCrmReadPublicFacade`, política de proprietário e confiança locais. Seu relatório determinístico, produzido inteiramente no mesmo processo PHP e sem entrada externa, é analisado por `AgentsReadResponseInspector` e `AiaReadOnlyMockBoundary`, classes reais do código-fonte do CRM pinado no **SHA `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad`**.

O novo script `tools/check_crm_aia_real_facade_core.php` executa seis casos: dois `READ_SYNTHETIC_ONLY` com recursos fictícios e quatro `DENIED_OR_NOT_FOUND` (replay, sem grant, outro tenant e campo desconhecido). Confere a projeção mínima, campos ordenados, negativa uniforme e 10 mutações hostis para o inspetor CRM; a decisão de acesso real permanece SEMPRE falsa, inclusive no sucesso fictício. Resultados externos incluem somente contagens agregadas.

O workflow GitHub de Agents verifica SHA do checkout CRM e executa primeiro todo gate offline. O gate local inclui 5 contratos Python F210 adicionais de rastreabilidade/isolamento, sem instalar dependências. Os **879 testes PHP / lint 110** continuam representando apenas a suíte original: este ensaio CI acrescenta validação inter-repositórios, não altera o baseline PHP.

**Não constitui homologação OP-01.** CRM #57 continua OPEN/DRAFT; 13 cenários bilaterais reais `NAO_EXECUTADO`, 14 gates não liberados; R01-G01..G11 e CORE/SEG/AUT/LGPD continuam NO-GO. Sem API, IAM real, ACL, token, SQL, dados de clientes, VPS, automação em produção, `main`, deploy ou custos.
