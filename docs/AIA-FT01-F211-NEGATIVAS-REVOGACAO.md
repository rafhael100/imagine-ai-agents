# F211 — Revogação, suspensão e falhas da política sintética com inspeção CRM

**14/11/2026. Somente CI de laboratório. NO-GO operacional.**

A F210 verificou as seis respostas da fachada real PHP dos Agentes via Core PHP do CRM. A F211 expande o ensaio para **14 cenários** de estado local: controle autorizado fictício, replay, grant inexistente, empresa suspensa, membro suspenso, autorização do proprietário indisponível, grant revogado, envelope revogado, parecer revogado, expiração, falta de envelope, audience divergente, corpo de outra empresa e campo pessoal proibido. Em todas as negativas, o CRM continua recebendo uma única resposta `DENIED_OR_NOT_FOUND` e nenhuma autorização real.

O script `tools/check_crm_aia_owner_failclosed.php` também testa **5 transições** depois de uma leitura fictícia: revogar e reconceder grant (ABA), revogar parecer, revogar envelope, suspender empresa e falha/recuperação do proprietário. Recibos previamente aceitos devem ser negados e jamais reativados. Cada resposta passa pelo `AgentsReadResponseInspector` real do Core CRM fixado ao SHA `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad` e pela negativa uniforme do mock CRM. A saída fornece só contagens, sem identificadores.

O gate offline recebe seis novos contratos Python de cobertura; o CI usa o checkout pinado CRM já verificado antes de executar F210 e F211. **879 testes PHP/lint 110** continuam sendo a suíte original; os dois scripts são provas adicionais entre repositórios.

Este teste NÃO comprova IAM, revogação distribuída, transação de ACL real, LGPD ou homologação. **CRM #57 OPEN/DRAFT; 13 cenários operacionais NAO_EXECUTADO, R01-G01..G11 e gates CORE/SEG/AUT NO-GO.** Sem API, credenciais, banco, dados de clientes, VPS, custos, `main` ou deploy.
